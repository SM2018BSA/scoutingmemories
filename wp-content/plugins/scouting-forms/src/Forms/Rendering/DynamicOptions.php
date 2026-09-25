<?php

namespace ScoutingMemories\Forms\Forms\Rendering;

use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Models\PostFields;

/**
 * DynamicOptions
 *
 * Choices of a Dynamic ("data") field, built like Formidable: one choice per entry of the linked
 * form, labelled with the linked field's value (form_select) and valued with the entry ID.
 * Blank values and draft entries are left out; "restrict" limits the choices to the current
 * user's entries; option_order sorts them (ascending unless set otherwise).
 *
 * A dependent Dynamic field (Council, whose choices depend on the State picked above it) has
 * no choices until its parent is chosen; Formidable then loads them in the browser. Phase 3 adds
 * that loading; until then such a field starts empty, as Formidable's does.
 */
class DynamicOptions {

    /** @var array<int, array<int|string, string>> */
    private static array $cache = [];

    /**
     * @param array<string, mixed> $field
     * @return array<int|string, string> entry ID => label
     */
    public static function forField(array $field): array {
        $fieldId = (int) $field['id'];
        if (isset(self::$cache[$fieldId])) {
            return self::$cache[$fieldId];
        }

        $opts = $field['field_options'];
        $linked = FormRepository::field((int) ($opts['form_select'] ?? 0));
        if (!$linked || self::isDependent($field)) {
            return self::$cache[$fieldId] = [];
        }

        $options = self::collect($linked, !empty($opts['restrict']));

        // Formidable's default for Dynamic fields is alphabetical
        $order = (string) ($opts['option_order'] ?? 'ascending');
        if ($order === 'ascending' || $order === 'descending') {
            if (class_exists('Collator')) {
                (new \Collator(get_locale()))->asort($options);
            } else {
                natcasesort($options);
            }
            if ($order === 'descending') {
                $options = array_reverse($options, true);
            }
        }

        return self::$cache[$fieldId] = $options;
    }

    /**
     * Formidable's test: a logic rule on another Dynamic field with no option value set means
     * "take my choices from that field".
     *
     * @param array<string, mixed> $field
     */
    public static function isDependent(array $field): bool {
        $opts = $field['field_options'];
        $parents = (array) ($opts['hide_field'] ?? []);
        $values = (array) ($opts['hide_opt'] ?? []);
        foreach ($parents as $i => $parentId) {
            if (!empty($values[$i]) || !is_numeric($parentId)) {
                continue;
            }
            $parent = FormRepository::field((int) $parentId);
            if ($parent && $parent['type'] === 'data') {
                return true;
            }
        }
        return false;
    }

    /**
     * The entry ID whose linked value is $text (a default like [get param=state] gives a name).
     */
    public static function idForText(array $field, string $text): string {
        if ($text === '' || ctype_digit($text)) {
            return $text;
        }
        foreach (self::forField($field) as $id => $label) {
            if (strcasecmp(trim($label), trim($text)) === 0) {
                return (string) $id;
            }
        }
        return '';
    }

    /**
     * @param array<string, mixed> $linked The linked (displayed) field
     * @return array<int, string>
     */
    private static function collect(array $linked, bool $restrict): array {
        global $wpdb;
        $items = $wpdb->prefix . 'frm_items';
        $userSql = '';
        if ($restrict) {
            $userId = get_current_user_id();
            if (!$userId) {
                return [];
            }
            $userSql = $wpdb->prepare(' AND i.user_id = %d', $userId);
        }

        $options = [];
        $map = PostFields::mapping($linked);
        if ($map) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT i.id, i.post_id FROM {$items} i WHERE i.form_id = %d AND i.is_draft = 0 AND i.post_id > 0{$userSql} ORDER BY i.id",
                (int) $linked['form_id']
            ));
            _prime_post_caches(array_map(static fn($r) => (int) $r->post_id, $rows), false, true);
            foreach ($rows as $row) {
                $options[(int) $row->id] = PostFields::value((int) $row->post_id, $map, $linked);
            }
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT m.item_id, m.meta_value FROM {$wpdb->prefix}frm_item_metas m
                 INNER JOIN {$items} i ON i.id = m.item_id
                 WHERE m.field_id = %d AND i.is_draft = 0{$userSql} ORDER BY m.id",
                (int) $linked['id']
            ));
            foreach ($rows as $row) {
                $options[(int) $row->item_id] = maybe_unserialize($row->meta_value);
            }
        }

        $labels = [];
        foreach ($options as $id => $value) {
            $value = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
            if ($value === '') {
                continue;
            }
            $labels[$id] = wp_strip_all_tags($value);
        }
        return $labels;
    }
}
