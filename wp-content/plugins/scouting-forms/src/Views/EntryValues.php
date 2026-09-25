<?php

namespace ScoutingMemories\Forms\Views;

use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Models\PostFields;
use ScoutingMemories\Forms\Support\FormidableSettings;
use ScoutingMemories\Forms\Support\ShortcodeTrust;

/**
 * EntryValues
 *
 * Loads entries and their field values in batches, and turns stored values into what Formidable
 * displays: Dynamic ("data") fields show the linked entry's field (a state ID becomes the state
 * name), files show an image or link, lists are joined with ", ".
 */
class EntryValues {

    /** @var array<int, array<string, mixed>> entry rows by ID */
    private array $entries = [];

    /** @var array<int, array<int, mixed>> entry_id => field_id => value */
    private array $metas = [];

    /** @var array<int, array<string, mixed>> field_id => field (all forms touched so far) */
    private array $fields = [];

    /** @var array<string, int> field key => field ID */
    private array $keys = [];

    /** @var array<string, string> "entryId:fieldId" => display value for linked entries */
    private array $linked = [];

    /** @var array<int, array{kind:string, name:string}> field_id => where its value lives on the post */
    private array $postMapped = [];

    public function __construct(int $formId) {
        $this->addForm($formId);
    }

    public function addForm(int $formId): void {
        foreach (FormRepository::fields($formId) as $field) {
            $this->fields[(int) $field['id']] = $field;
            $this->keys[$field['key']] = (int) $field['id'];
            $map = PostFields::mapping($field);
            if ($map) {
                $this->postMapped[(int) $field['id']] = $map;
            }
        }
    }

    /**
     * @param int[] $ids
     */
    public function load(array $ids): void {
        global $wpdb;
        $ids = array_values(array_filter(array_map('intval', $ids)));
        $missing = array_diff($ids, array_keys($this->entries));
        if (!$missing) {
            return;
        }
        $in = implode(',', $missing);

        foreach ($wpdb->get_results("SELECT * FROM {$wpdb->prefix}frm_items WHERE id IN ({$in})", ARRAY_A) as $row) {
            $row['key'] = $row['item_key'];
            $this->entries[(int) $row['id']] = $row;
            $this->metas[(int) $row['id']] = [];
        }
        foreach ($wpdb->get_results("SELECT item_id, field_id, meta_value FROM {$wpdb->prefix}frm_item_metas WHERE item_id IN ({$in})") as $meta) {
            $this->metas[(int) $meta->item_id][(int) $meta->field_id] = maybe_unserialize($meta->meta_value);
        }

        $this->loadPostValues($missing);
        $this->preloadLinked($missing);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function entry(int $id): ?array {
        return $this->entries[$id] ?? null;
    }

    /**
     * @return array<int, mixed>
     */
    public function values(int $entryId): array {
        return $this->metas[$entryId] ?? [];
    }

    public function fieldId(string $idOrKey): int {
        if (ctype_digit($idOrKey)) {
            return isset($this->fields[(int) $idOrKey]) ? (int) $idOrKey : 0;
        }
        return $this->keys[$idOrKey] ?? 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function field(int $fieldId): ?array {
        return $this->fields[$fieldId] ?? null;
    }

    /**
     * Stored value (unserialized), as-is.
     *
     * @return mixed
     */
    public function raw(int $entryId, int $fieldId) {
        return $this->metas[$entryId][$fieldId] ?? '';
    }

    /**
     * Display value as HTML (escaped).
     *
     * @param array<string, string> $atts Tag options: show, sep, size, format
     */
    public function display(int $entryId, int $fieldId, array $atts = []): string {
        // Text people typed never runs as a shortcode when a view template is processed
        return ShortcodeTrust::inert($this->displayHtml($entryId, $fieldId, $atts));
    }

    /**
     * @param array<string, string> $atts
     */
    private function displayHtml(int $entryId, int $fieldId, array $atts): string {
        $field = $this->fields[$fieldId] ?? null;
        $value = $this->raw($entryId, $fieldId);
        $sep = isset($atts['sep']) ? (string) $atts['sep'] : ', ';

        if (isset($atts['show']) && in_array($atts['show'], ['id', 'value'], true)) {
            return esc_html(is_array($value) ? implode($sep, $value) : (string) $value);
        }
        if (!$field) {
            return esc_html(is_array($value) ? implode($sep, $value) : (string) $value);
        }

        if (($this->postMapped[$fieldId]['kind'] ?? '') === 'taxonomy' && ($this->entries[$entryId]['post_id'] ?? 0) > 0) {
            return $this->termLinks((array) $value, $this->postMapped[$fieldId]['name'], $sep);
        }

        switch ($field['type']) {
            case 'data':
                $parts = [];
                foreach ((array) $value as $linkedId) {
                    // A value that is not a linked entry shows as blank, as in Formidable
                    $parts[] = $this->linked[(int) $linkedId . ':' . (int) ($field['field_options']['form_select'] ?? 0)] ?? '';
                }
                return esc_html(implode($sep, array_filter($parts, 'strlen')));

            case 'file':
                $parts = [];
                foreach ((array) $value as $attachmentId) {
                    $attachmentId = (int) $attachmentId;
                    if (!$attachmentId) {
                        continue;
                    }
                    $image = wp_get_attachment_image_url($attachmentId, $atts['size'] ?? 'thumbnail');
                    $url = wp_get_attachment_url($attachmentId);
                    if ($image) {
                        $parts[] = '<img src="' . esc_url($image) . '" class="sm-thumb rounded shadow-sm" alt="" />';
                    } elseif ($url) {
                        $parts[] = '<a href="' . esc_url($url) . '">' . esc_html(basename($url)) . '</a>';
                    }
                }
                return implode(' ', $parts);

            case 'textarea':
            case 'rte':
                $text = is_array($value) ? implode($sep, $value) : (string) $value;
                return $field['type'] === 'rte' ? wp_kses_post($text) : nl2br(esc_html($text));

            case 'user_id':
                $user = get_userdata((int) $value);
                return esc_html($user ? $user->display_name : '');

            case 'date':
                $format = (string) ($atts['format'] ?? FormidableSettings::pro('date_format', get_option('date_format')));
                $time = is_string($value) && $value !== '' ? strtotime($value) : false;
                return $time ? esc_html(date_i18n($format, $time)) : esc_html((string) $value);

            case 'select':
            case 'radio':
            case 'checkbox':
                // With "separate values" on, the saved value is shown by its label
                if (!empty($field['field_options']['separate_value'])) {
                    $labels = [];
                    foreach ((array) $field['options'] as $option) {
                        if (is_array($option) && isset($option['value'], $option['label'])) {
                            $labels[(string) $option['value']] = (string) $option['label'];
                        }
                    }
                    $value = array_map(static function ($v) use ($labels) {
                        return $labels[(string) $v] ?? (string) $v;
                    }, (array) $value);
                }
                return esc_html(implode($sep, array_map('strval', (array) $value)));

            default:
                return esc_html(is_array($value) ? implode($sep, array_map('strval', $value)) : (string) $value);
        }
    }

    /**
     * Values of post-mapped fields come from the entry's post, as in Formidable.
     *
     * @param int[] $entryIds
     */
    private function loadPostValues(array $entryIds): void {
        if (!$this->postMapped) {
            return;
        }
        $postIds = [];
        foreach ($entryIds as $entryId) {
            $postId = (int) ($this->entries[$entryId]['post_id'] ?? 0);
            if ($postId > 0) {
                $postIds[$entryId] = $postId;
            }
        }
        if (!$postIds) {
            return;
        }
        _prime_post_caches(array_values($postIds), true, true);
        update_object_term_cache(array_values($postIds), array_unique(array_map(static function ($id) {
            return (string) get_post_type($id);
        }, array_values($postIds))));

        foreach ($postIds as $entryId => $postId) {
            foreach ($this->postMapped as $fieldId => $map) {
                if ((int) $this->fields[$fieldId]['form_id'] !== (int) $this->entries[$entryId]['form_id']) {
                    continue;
                }
                $this->metas[$entryId][$fieldId] = PostFields::value($postId, $map, $this->fields[$fieldId]);
            }
        }
    }

    /**
     * Category names linked to their archive, like Formidable's view output.
     *
     * @param int[] $termIds
     */
    private function termLinks(array $termIds, string $taxonomy, string $sep): string {
        $links = [];
        foreach ($termIds as $termId) {
            $term = get_term((int) $termId, $taxonomy);
            if (!$term || is_wp_error($term)) {
                continue;
            }
            $url = get_term_link($term, $taxonomy);
            $links[] = is_wp_error($url)
                ? esc_html($term->name)
                /* translators: %s: category name */
                : '<a href="' . esc_url($url) . '" title="' . esc_attr(sprintf(__('View all posts filed under %s', 'scouting-forms'), $term->name)) . '">' . esc_html($term->name) . '</a>';
        }
        return implode(esc_html($sep), $links);
    }

    /**
     * Resolve every Dynamic-field link of the loaded entries in one query per linked field.
     *
     * @param int[] $entryIds
     */
    private function preloadLinked(array $entryIds): void {
        global $wpdb;
        $wanted = [];
        foreach ($entryIds as $entryId) {
            foreach ($this->metas[$entryId] ?? [] as $fieldId => $value) {
                $field = $this->fields[$fieldId] ?? null;
                if (!$field || $field['type'] !== 'data') {
                    continue;
                }
                $displayField = (int) ($field['field_options']['form_select'] ?? 0);
                foreach ((array) $value as $linkedId) {
                    if ((int) $linkedId > 0 && !isset($this->linked[(int) $linkedId . ':' . $displayField])) {
                        $wanted[$displayField][] = (int) $linkedId;
                    }
                }
            }
        }

        foreach ($wanted as $displayField => $linkedIds) {
            $in = implode(',', array_unique($linkedIds));
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT item_id, meta_value FROM {$wpdb->prefix}frm_item_metas WHERE field_id = %d AND item_id IN ({$in})",
                $displayField
            ));
            foreach ($rows as $row) {
                $val = maybe_unserialize($row->meta_value);
                $this->linked[(int) $row->item_id . ':' . $displayField] = is_array($val) ? implode(', ', $val) : (string) $val;
            }
        }
    }
}
