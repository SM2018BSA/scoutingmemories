<?php

namespace ScoutingMemories\Forms\Models;

use ScoutingMemories\Forms\Support\FormidableSettings;
use ScoutingMemories\Forms\Support\TestData;

/**
 * EntryRepository
 *
 * The single place the plugin creates Formidable entries (wp_frm_items + wp_frm_item_metas),
 * with the same columns Formidable itself writes. Later phases hook form actions (emails,
 * post creation, registration) and Formidable-compatible events in here.
 */
class EntryRepository {

    /**
     * Create an entry and its field values.
     *
     * @param int $formId
     * @param array<int|string, mixed> $metas field_id => value (arrays are serialized like Formidable does)
     * @param array<string, mixed> $args Optional: key, name, user_id, post_id, parent_item_id, is_draft, ip
     * @return int New entry ID, or 0 on failure
     */
    public static function create(int $formId, array $metas, array $args = []): int {
        global $wpdb;

        $userId = isset($args['user_id']) ? (int) $args['user_id'] : get_current_user_id();
        // Formidable stores entry times in GMT
        $now = current_time('mysql', 1);
        $baseKey = $args['key'] ?? self::uniqueKey();

        // Formidable stores a unique_id under field 0 with every entry (not with repeater rows)
        if (!array_key_exists(0, $metas) && empty($args['parent_item_id'])) {
            $metas[0] = ['unique_id' => wp_generate_password(20, false, false)];
        }

        $inserted = $wpdb->insert($wpdb->prefix . 'frm_items', [
            'item_key' => TestData::itemKey($baseKey),
            'name' => (string) ($args['name'] ?? ''),
            'description' => self::browserInfo(),
            'ip' => isset($args['ip']) ? (string) $args['ip'] : (FormidableSettings::get('no_ips') ? '' : self::clientIp()),
            'form_id' => $formId,
            'post_id' => (int) ($args['post_id'] ?? 0),
            'user_id' => $userId,
            'parent_item_id' => (int) ($args['parent_item_id'] ?? 0),
            'is_draft' => (int) ($args['is_draft'] ?? 0),
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s']);

        if (!$inserted) {
            return 0;
        }

        $entryId = (int) $wpdb->insert_id;
        foreach ($metas as $fieldId => $value) {
            $wpdb->insert($wpdb->prefix . 'frm_item_metas', [
                'meta_value' => is_array($value) ? maybe_serialize($value) : (string) $value,
                'field_id' => (int) $fieldId,
                'item_id' => $entryId,
                'created_at' => $now,
            ], ['%s', '%d', '%d', '%s']);
        }

        self::purgeCaches($formId);
        return $entryId;
    }

    /**
     * Save an edited entry the way Formidable's FrmEntryMeta::update_entry_metas does: submitted
     * values are added or updated, and any other stored value (blank now, or from a field that
     * was hidden) is removed. Fields listed in $keep are left as they are (fields this user may
     * not see). The unique_id under field 0 is never touched.
     *
     * @param array<int, mixed> $metas field_id => value
     * @param int[] $keep
     */
    public static function update(int $entryId, array $metas, array $keep = []): bool {
        global $wpdb;
        $entry = self::find($entryId);
        if (!$entry) {
            return false;
        }
        $table = $wpdb->prefix . 'frm_item_metas';
        $previous = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT field_id FROM {$table} WHERE item_id = %d AND field_id <> 0",
            $entryId
        )));

        $kept = array_map('intval', $keep);
        foreach ($metas as $fieldId => $value) {
            $fieldId = (int) $fieldId;
            if ($fieldId <= 0) {
                continue;
            }
            $blank = is_array($value) ? $value === [] : trim((string) $value) === '';
            if ($blank) {
                continue;
            }
            $kept[] = $fieldId;
            $stored = is_array($value) ? maybe_serialize($value) : (string) $value;
            if (in_array($fieldId, $previous, true)) {
                $wpdb->update($table, ['meta_value' => $stored], ['item_id' => $entryId, 'field_id' => $fieldId], ['%s'], ['%d', '%d']);
            } else {
                $wpdb->insert($table, [
                    'meta_value' => $stored,
                    'field_id' => $fieldId,
                    'item_id' => $entryId,
                    'created_at' => current_time('mysql', 1),
                ], ['%s', '%d', '%d', '%s']);
            }
        }

        $remove = array_diff($previous, $kept);
        if ($remove) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table} WHERE item_id = %d AND field_id IN (" . implode(',', array_map('intval', $remove)) . ')',
                $entryId
            ));
        }

        $wpdb->update($wpdb->prefix . 'frm_items', [
            'updated_at' => current_time('mysql', 1),
            'updated_by' => get_current_user_id(),
        ], ['id' => $entryId], ['%s', '%d'], ['%d']);

        self::purgeCaches((int) $entry['form_id']);
        return true;
    }

    /**
     * An entry's values as the form expects them: stored values, post-mapped fields read from
     * the entry's post, and repeating sections as rows keyed by child entry ID.
     *
     * @param array<int, array<string, mixed>> $fields The form's fields
     * @return array<int, mixed>
     */
    public static function formValues(int $entryId, array $fields): array {
        global $wpdb;
        $entry = self::find($entryId);
        if (!$entry) {
            return [];
        }
        $values = self::storedValues($entryId);

        foreach ($fields as $field) {
            $id = (int) $field['id'];
            $map = PostFields::mapping($field);
            if ($map && (int) $entry['post_id'] > 0) {
                $values[$id] = PostFields::value((int) $entry['post_id'], $map, $field);
            }
            if ($field['type'] === 'divider' && !empty($field['field_options']['repeat'])) {
                $childForm = (int) ($field['field_options']['form_select'] ?? 0);
                $children = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}frm_items WHERE parent_item_id = %d AND form_id = %d ORDER BY id",
                    $entryId,
                    $childForm
                ));
                $section = ['form' => $childForm, 'row_ids' => array_map('strval', $children)];
                foreach ($children as $childId) {
                    $section[(string) $childId] = self::storedValues((int) $childId);
                }
                $values[$id] = $section;
            }
        }
        return $values;
    }

    /**
     * @return array<int, mixed> field_id => value (unserialized), without the unique_id
     */
    private static function storedValues(int $entryId): array {
        global $wpdb;
        $values = [];
        foreach ($wpdb->get_results($wpdb->prepare(
            "SELECT field_id, meta_value FROM {$wpdb->prefix}frm_item_metas WHERE item_id = %d AND field_id <> 0",
            $entryId
        )) as $row) {
            $values[(int) $row->field_id] = maybe_unserialize($row->meta_value);
        }
        return $values;
    }

    /**
     * Set one field of an entry (Formidable's "update field" link), and stamp who/when.
     *
     * @param mixed $value
     */
    public static function updateField(int $entryId, int $fieldId, $value): bool {
        global $wpdb;
        $entry = self::find($entryId);
        if (!$entry || $fieldId <= 0) {
            return false;
        }

        // A field mapped to the entry's post is changed on the post (the "Publish" button sets
        // post_status), as Formidable's update_single_field does
        $field = FormRepository::field($fieldId);
        $map = $field ? PostFields::mapping($field) : null;
        if ($map && (int) $entry['post_id'] > 0) {
            $postId = (int) $entry['post_id'];
            if ($map['kind'] === 'meta') {
                PostFields::saveMeta($postId, $map['name'], $value);
            } elseif ($map['kind'] === 'taxonomy') {
                wp_set_post_terms($postId, array_map('intval', (array) $value), $map['name']);
            } else {
                $post = get_post($postId, ARRAY_A);
                if (!$post) {
                    return false;
                }
                $new = is_array($value) ? implode(', ', $value) : (string) $value;
                if ($map['name'] === 'post_status') {
                    $new = \ScoutingMemories\Forms\Actions\PostAction::allowedStatus($new, (string) $post['post_type'], $post);
                }
                $post[$map['name']] = $new;
                $result = wp_update_post(wp_slash($post), true);
                if (is_wp_error($result)) {
                    return false;
                }
            }
            $wpdb->update($wpdb->prefix . 'frm_items', [
                'updated_at' => current_time('mysql', 1),
                'updated_by' => get_current_user_id(),
            ], ['id' => $entryId], ['%s', '%d'], ['%d']);
            self::purgeCaches((int) $entry['form_id']);
            return true;
        }

        $stored = is_array($value) ? maybe_serialize($value) : (string) $value;
        $metas = $wpdb->prefix . 'frm_item_metas';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$metas} WHERE item_id = %d AND field_id = %d LIMIT 1",
            $entryId,
            $fieldId
        ));
        if ($existing) {
            $ok = $wpdb->update($metas, ['meta_value' => $stored], ['id' => (int) $existing], ['%s'], ['%d']) !== false;
        } else {
            $ok = (bool) $wpdb->insert($metas, [
                'meta_value' => $stored,
                'field_id' => $fieldId,
                'item_id' => $entryId,
                'created_at' => current_time('mysql', 1),
            ], ['%s', '%d', '%d', '%s']);
        }

        $wpdb->update($wpdb->prefix . 'frm_items', [
            'updated_at' => current_time('mysql', 1),
            'updated_by' => get_current_user_id(),
        ], ['id' => $entryId], ['%s', '%d'], ['%d']);

        self::purgeCaches((int) $entry['form_id']);
        return $ok;
    }

    /**
     * Delete an entry, its values and its child (repeater) entries. A post created from the entry
     * goes to the trash, as Formidable does, so it can still be restored.
     */
    public static function delete(int $entryId): bool {
        global $wpdb;
        $entry = self::find($entryId);
        if (!$entry) {
            return false;
        }

        $children = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}frm_items WHERE parent_item_id = %d",
            $entryId
        ));
        foreach ($children as $childId) {
            self::delete((int) $childId);
        }

        if ((int) $entry['post_id'] > 0) {
            wp_trash_post((int) $entry['post_id']);
        }

        $wpdb->delete($wpdb->prefix . 'frm_item_metas', ['item_id' => $entryId], ['%d']);
        $deleted = (bool) $wpdb->delete($wpdb->prefix . 'frm_items', ['id' => $entryId], ['%d']);

        self::purgeCaches((int) $entry['form_id']);
        return $deleted;
    }

    /**
     * Basic entry details (for shortcodes like [id], [key], [ip], [created-at]).
     *
     * @return array<string, mixed>
     */
    public static function find(int $entryId): array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, item_key, name, form_id, user_id, post_id, parent_item_id, is_draft, ip, created_at, updated_at FROM {$wpdb->prefix}frm_items WHERE id = %d",
            $entryId
        ), ARRAY_A);
        if (!$row) {
            return [];
        }
        $row['key'] = $row['item_key'];
        return $row;
    }

    /**
     * Entry name the way Formidable sets it: the first filled-in text-like field of the entry.
     *
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed> $values
     */
    public static function nameFromValues(array $fields, array $values, string $fallback = ''): string {
        foreach ($fields as $field) {
            if (!in_array($field['type'], ['text', 'email', 'textarea', 'select', 'radio', 'number', 'phone', 'url', 'hidden'], true)) {
                continue;
            }
            $value = $values[(int) $field['id']] ?? '';
            $value = is_array($value) ? implode(', ', $value) : (string) $value;
            if (trim($value) !== '') {
                return mb_substr(wp_strip_all_tags($value), 0, 255);
            }
        }
        return $fallback;
    }

    /**
     * Five lowercase letters/digits, unique among entry keys (Formidable's format).
     */
    private static function uniqueKey(): string {
        global $wpdb;
        do {
            $key = strtolower(wp_generate_password(5, false, false));
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT 1 FROM {$wpdb->prefix}frm_items WHERE item_key IN (%s, %s) LIMIT 1",
                $key,
                TestData::KEY_PREFIX . $key
            ));
        } while ($exists);
        return $key;
    }

    /**
     * Formidable keeps the browser and referring page in the entry description as JSON.
     */
    private static function browserInfo(): string {
        $info = [
            'browser' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'referrer' => '',
        ];
        if (!FormidableSettings::get('no_referrer') && isset($_SERVER['HTTP_REFERER'])) {
            $info['referrer'] = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
        }
        return (string) wp_json_encode($info);
    }

    private static function clientIp(): string {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    private static function purgeCaches(int $formId): void {
        wp_cache_delete('sm_entries_form_' . $formId);
        if (class_exists('WpeCommon') && method_exists('WpeCommon', 'purge_memcached')) {
            \WpeCommon::purge_memcached();
        }
    }
}
