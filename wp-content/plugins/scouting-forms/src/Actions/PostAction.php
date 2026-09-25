<?php

namespace ScoutingMemories\Forms\Actions;

use ScoutingMemories\Forms\Support\TestData;

/**
 * PostAction
 *
 * Formidable's "Create Post" form action (wppost), following FrmProPost:
 *   - post_title, post_content, post_excerpt, post_name, post_date, post_status, post_password,
 *     post_parent and menu_order come from the mapped fields (a setting holding a field ID);
 *   - custom fields (post_custom_fields: meta name => field) become post meta (blank = removed);
 *   - taxonomies (post_category: taxonomy => field) become the post's terms;
 *   - on create the post is linked to the entry (frm_items.post_id); on update the same post is
 *     updated; afterwards the mapped values are removed from the entry, since the post holds them.
 *
 * One difference, on purpose: only people allowed to publish posts (publish_posts) can set a
 * post to published, private or scheduled; anyone else's post is saved as "pending" for review.
 */
class PostAction {

    private const POST_FIELDS = ['post_content', 'post_excerpt', 'post_title', 'post_name', 'post_date', 'post_status', 'post_password', 'post_parent', 'menu_order'];

    /**
     * @param array<string, mixed> $settings Action settings
     * @param array<string, mixed> $context form, fields, values, entry
     * @return int The post ID, or 0
     */
    public static function save(array $settings, array $context): int {
        $entry = $context['entry'];
        $entryId = (int) ($entry['id'] ?? 0);
        if (!$entryId) {
            return 0;
        }
        $values = self::combinedValues($entryId, (array) $context['values']);
        $fields = [];
        foreach ((array) $context['fields'] as $field) {
            $fields[(int) $field['id']] = $field;
        }

        $new = self::setupPost($settings, $values, $fields, $entry);
        $existing = (int) ($entry['post_id'] ?? 0) > 0 ? get_post((int) $entry['post_id'], ARRAY_A) : null;

        if ($existing) {
            unset($existing['post_content']);
            $post = $existing;
        } else {
            $post = ['post_type' => (string) ($settings['post_type'] ?? 'post')];
            if (empty($new['post_status']) && in_array($settings['post_status'] ?? '', ['pending', 'publish'], true)) {
                $new['post_status'] = $settings['post_status'];
            }
            $post['post_author'] = (int) ($entry['user_id'] ?? 0) ?: get_current_user_id();
        }
        foreach (array_merge(self::POST_FIELDS, ['post_category']) as $key) {
            if (array_key_exists($key, $new)) {
                $post[$key] = $new[$key];
            }
        }
        $post['post_status'] = self::allowedStatus((string) ($post['post_status'] ?? 'draft'), (string) ($post['post_type'] ?? 'post'), $existing);
        if (!empty($post['post_date'])) {
            $post['post_date_gmt'] = get_gmt_from_date($post['post_date']);
        }

        $postId = wp_insert_post(wp_slash($post), true);
        if (is_wp_error($postId) || !$postId) {
            return 0;
        }
        $postId = (int) $postId;
        if (!$existing) {
            TestData::markPost($postId);
        }

        foreach ($new['taxonomies'] as $taxonomy => $terms) {
            wp_set_post_terms($postId, is_taxonomy_hierarchical($taxonomy) ? array_keys($terms) : array_values($terms), $taxonomy);
        }
        self::linkAttachments($postId, $fields, $values);
        foreach ($new['post_custom'] as $key => $value) {
            if ($value === '' || $value === [] || $value === null) {
                delete_post_meta($postId, $key);
            } else {
                update_post_meta($postId, $key, wp_slash($value));
            }
        }
        update_post_meta($postId, '_edit_last', get_current_user_id());

        global $wpdb;
        if (!$existing) {
            $wpdb->update($wpdb->prefix . 'frm_items', ['post_id' => $postId], ['id' => $entryId], ['%d'], ['%d']);
        }

        // The post now holds these values; the entry keeps the rest
        $mapped = self::mappedFieldIds($settings);
        if ($mapped) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}frm_item_metas WHERE item_id = %d AND field_id IN (" . implode(',', $mapped) . ')',
                $entryId
            ));
        }
        return $postId;
    }

    /**
     * Published, private and scheduled posts need publish rights; others are saved for review.
     *
     * @param array<string, mixed>|null $existing
     */
    public static function allowedStatus(string $status, string $postType, ?array $existing = null): string {
        $status = $status !== '' ? $status : 'draft';
        if (!in_array($status, ['publish', 'private', 'future'], true)) {
            return in_array($status, ['draft', 'pending'], true) ? $status : 'draft';
        }
        // Keeping a post's current status is always fine
        if ($existing && ($existing['post_status'] ?? '') === $status) {
            return $status;
        }
        $type = get_post_type_object($postType);
        $cap = $type && isset($type->cap->publish_posts) ? $type->cap->publish_posts : 'publish_posts';
        return current_user_can($cap) ? $status : 'pending';
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<int, mixed> $values
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function setupPost(array $settings, array $values, array $fields, array $entry): array {
        $new = ['post_custom' => [], 'taxonomies' => [], 'post_category' => []];

        foreach (self::POST_FIELDS as $name) {
            $setting = $settings[$name] ?? '';
            if (!is_numeric($setting)) {
                continue;
            }
            if ($name === 'post_parent') {
                $new[$name] = (int) $setting;
                continue;
            }
            $value = $values[(int) $setting] ?? '';
            $value = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
            if ($name === 'post_date') {
                $value = self::dbDate($value, 'Y-m-d H:i:s');
            }
            if ($name === 'post_content') {
                // A visitor's text must not run shortcodes on the published post
                $value = str_replace(['[', ']'], ['&#91;', '&#93;'], $value);
            }
            $new[$name] = $value;
        }

        foreach ((array) ($settings['post_custom_fields'] ?? []) as $custom) {
            $fieldId = (int) ($custom['field_id'] ?? 0);
            $key = (string) ($custom['meta_name'] ?? '');
            if (!$fieldId || $key === '' || !isset($fields[$fieldId])) {
                continue;
            }
            $value = $values[$fieldId] ?? '';
            if ($fields[$fieldId]['type'] === 'date') {
                $value = self::dbDate(is_array($value) ? '' : (string) $value, 'Y-m-d');
            }
            if (array_key_exists($key, $new['post_custom'])) {
                $new['post_custom'][$key] = (array) $new['post_custom'][$key];
                $new['post_custom'][$key][] = $value;
            } else {
                $new['post_custom'][$key] = $value;
            }
        }

        foreach ((array) ($settings['post_category'] ?? []) as $tax) {
            $fieldId = (int) ($tax['field_id'] ?? 0);
            $taxonomy = (string) ($tax['meta_name'] ?? '');
            if (!$fieldId || $taxonomy === '') {
                continue;
            }
            $terms = array_filter(array_map('intval', (array) ($values[$fieldId] ?? [])));
            if ($taxonomy === 'category') {
                $new['post_category'] = array_values(array_unique(array_merge($new['post_category'], $terms)));
                continue;
            }
            foreach ($terms as $termId) {
                $term = get_term($termId, $taxonomy);
                $new['taxonomies'][$taxonomy][$termId] = $term && !is_wp_error($term) ? $term->name : (string) $termId;
            }
        }
        return $new;
    }

    /**
     * The entry's values plus its child entries' values (repeating sections), like Formidable.
     *
     * @param array<int, mixed> $values
     * @return array<int, mixed>
     */
    private static function combinedValues(int $entryId, array $values): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.field_id, m.meta_value FROM {$wpdb->prefix}frm_item_metas m INNER JOIN {$wpdb->prefix}frm_items i ON i.id = m.item_id WHERE i.parent_item_id = %d",
            $entryId
        ));
        foreach ($rows as $row) {
            if (!array_key_exists((int) $row->field_id, $values)) {
                $values[(int) $row->field_id] = maybe_unserialize($row->meta_value);
            }
        }
        return $values;
    }

    /**
     * Field IDs the action writes to the post (Formidable deletes these from the entry).
     *
     * @return int[]
     */
    public static function mappedFieldIds(array $settings): array {
        $ids = [];
        foreach ($settings as $name => $value) {
            if (strpos((string) $name, 'post') !== 0) {
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $row) {
                    if (is_array($row) && !empty($row['field_id']) && is_numeric($row['field_id'])) {
                        $ids[] = (int) $row['field_id'];
                    }
                }
            } elseif (is_numeric($value) && $name !== 'post_parent') {
                $ids[] = (int) $value;
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Uploaded files of the entry become attachments of the post.
     *
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed> $values
     */
    private static function linkAttachments(int $postId, array $fields, array $values): void {
        global $wpdb;
        foreach ($fields as $id => $field) {
            if ($field['type'] !== 'file') {
                continue;
            }
            foreach (array_filter(array_map('intval', (array) ($values[$id] ?? []))) as $attachmentId) {
                $wpdb->update($wpdb->posts, ['post_parent' => $postId], ['ID' => $attachmentId, 'post_type' => 'attachment'], ['%d'], ['%d', '%s']);
                clean_attachment_cache($attachmentId);
            }
        }
    }

    /**
     * A date as typed on the site (Formidable's m/d/Y setting) in database format.
     */
    private static function dbDate(string $value, string $format): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $siteFormat = (string) \ScoutingMemories\Forms\Support\FormidableSettings::pro('date_format', 'm/d/Y');
        $date = \DateTime::createFromFormat('!' . $siteFormat, $value) ?: \DateTime::createFromFormat('!Y-m-d', $value);
        if (!$date) {
            $time = strtotime($value);
            return $time ? gmdate($format, $time) : $value;
        }
        return $date->format($format);
    }
}
