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
 * A dependent Dynamic field (Council, whose choices depend on the State picked above it) gets
 * its choices from its parent's value, like Formidable's meta_through_join: entries of the linked
 * form that point at the parent's selection through a Dynamic field of their own. A "just show
 * it" Dynamic field (data_type "data") shows a value of the parent's selected entries instead.
 * The browser asks for both through Ajax\DynamicFields when the parent changes.
 */
class DynamicOptions {

    /** @var array<string, array<int|string, string>> */
    private static array $cache = [];

    /**
     * @param array<string, mixed> $field
     * @param array<int, mixed> $values The form's current values (for dependent fields)
     * @return array<int|string, string> entry ID => label
     */
    public static function forField(array $field, array $values = []): array {
        $opts = $field['field_options'];
        $linked = FormRepository::field((int) ($opts['form_select'] ?? 0));
        if (!$linked) {
            return [];
        }

        if (self::isDependent($field)) {
            [$parentId, $parentValue] = self::drivingParent($field, $values);
            return $parentId ? self::dependent($field, $linked, $parentId, $parentValue) : [];
        }

        $key = (string) $field['id'];
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = self::sorted(self::collect($linked, !empty($opts['restrict'])), $opts);
        }
        return self::$cache[$key];
    }

    /**
     * Choices of a dependent field for a given parent value (also used by the Ajax endpoint).
     *
     * @param array<string, mixed> $field
     * @param array<string, mixed> $linked The field whose values label the choices
     * @param mixed $parentValue Entry ID(s) selected in the parent
     * @return array<int, string>
     */
    public static function dependent(array $field, array $linked, int $parentId, $parentValue): array {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $parentValue))));
        $parent = FormRepository::field($parentId);
        if (!$ids || !$parent) {
            return [];
        }
        $cacheKey = $field['id'] . ':' . $parentId . ':' . implode(',', $ids);
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        // The form holding the linked field (its parent form if the field is in a repeater)
        $linkedForm = FormRepository::find((int) $linked['form_id']);
        $formId = $linkedForm && $linkedForm['parent_form_id'] ? (int) $linkedForm['parent_form_id'] : (int) $linked['form_id'];

        // A Dynamic field of that form pointing where the parent points (last one wins, as in Formidable)
        $parentSource = (int) ($parent['field_options']['form_select'] ?? 0);
        $join = 0;
        foreach (FormRepository::fields($formId) as $candidate) {
            if ($candidate['type'] === 'data' && (int) ($candidate['field_options']['form_select'] ?? 0) === $parentSource) {
                $join = (int) $candidate['id'];
            }
        }
        if (!$join) {
            return self::$cache[$cacheKey] = [];
        }

        $match = [];
        foreach ($ids as $id) {
            $match[] = $wpdb->prepare('m.meta_value = %s OR m.meta_value LIKE %s', (string) $id, '%' . $wpdb->esc_like(':"' . $id . '"') . '%');
        }
        $userSql = '';
        if (!empty($field['field_options']['restrict'])) {
            $userSql = $wpdb->prepare(' AND e.user_id = %d', get_current_user_id());
        }
        $entryIds = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT m.item_id FROM {$wpdb->prefix}frm_item_metas m INNER JOIN {$wpdb->prefix}frm_items e ON e.id = m.item_id
             WHERE m.field_id = %d AND e.is_draft = 0{$userSql} AND (" . implode(' OR ', $match) . ')',
            $join
        ));
        if ($entryIds && $formId !== (int) $linked['form_id']) {
            // The linked field is in a repeater: its values are on the child entries
            $entryIds = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}frm_items WHERE parent_item_id IN (" . implode(',', array_map('intval', $entryIds)) . ')');
        }
        if (!$entryIds) {
            return self::$cache[$cacheKey] = [];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT item_id, meta_value FROM {$wpdb->prefix}frm_item_metas WHERE field_id = %d AND item_id IN (" . implode(',', array_map('intval', $entryIds)) . ') ORDER BY meta_value',
            (int) $linked['id']
        ));
        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row->item_id] = maybe_unserialize($row->meta_value);
        }
        return self::$cache[$cacheKey] = self::sorted(self::labels($options), $field['field_options']);
    }

    /**
     * What a "just show it" Dynamic field shows: its linked field's value on the entries picked
     * in its parent (a council's slug once the council is chosen).
     *
     * @param array<string, mixed> $field
     * @param array<int, mixed> $values
     */
    public static function displayValue(array $field, array $values): string {
        [$parentId, $parentValue] = self::drivingParent($field, $values);
        return $parentId ? self::displayValueFor($field, $parentValue) : '';
    }

    /**
     * @param array<string, mixed> $field
     * @param mixed $parentValue
     */
    public static function displayValueFor(array $field, $parentValue): string {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $parentValue))));
        $source = (int) ($field['field_options']['form_select'] ?? 0);
        if (!$ids || !$source) {
            return '';
        }
        $in = implode(',', $ids);
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}frm_item_metas WHERE field_id = %d AND item_id IN ({$in}) ORDER BY FIELD(item_id, {$in})",
            $source
        ));
        $parts = [];
        foreach ($rows as $value) {
            $value = maybe_unserialize($value);
            $parts[] = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
        }
        return implode(', ', array_filter($parts, 'strlen'));
    }

    /**
     * The parent a dependent field takes its choices from: of its Dynamic parents, the last one
     * (in rule order) that has a value.
     *
     * @param array<string, mixed> $field
     * @param array<int, mixed> $values
     * @return array{0:int, 1:mixed} parent field ID (0 if none has a value) and its value
     */
    public static function drivingParent(array $field, array $values): array {
        $found = [0, ''];
        foreach (self::dynamicParents($field) as $parentId) {
            $value = $values[$parentId] ?? '';
            if (array_filter((array) $value, static fn($v) => (string) $v !== '')) {
                $found = [$parentId, $value];
            }
        }
        return $found;
    }

    /**
     * Formidable's test: a logic rule on another Dynamic field with no option value set means
     * "take my choices from that field".
     *
     * @param array<string, mixed> $field
     * @return int[] Dynamic fields this field depends on
     */
    public static function dynamicParents(array $field): array {
        $opts = $field['field_options'];
        $values = (array) ($opts['hide_opt'] ?? []);
        $parents = [];
        foreach ((array) ($opts['hide_field'] ?? []) as $i => $parentId) {
            if (!empty($values[$i]) || !is_numeric($parentId)) {
                continue;
            }
            $parent = FormRepository::field((int) $parentId);
            if ($parent && $parent['type'] === 'data') {
                $parents[] = (int) $parentId;
            }
        }
        return $parents;
    }

    /**
     * @param array<string, mixed> $field
     */
    public static function isDependent(array $field): bool {
        return (bool) self::dynamicParents($field);
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
     * @param array<int|string, string> $options
     * @param array<string, mixed> $opts field_options
     * @return array<int|string, string>
     */
    private static function sorted(array $options, array $opts): array {
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
        return $options;
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
        return self::labels($options);
    }

    /**
     * @param array<int, mixed> $options entry ID => stored value
     * @return array<int, string> entry ID => plain-text label (blanks left out)
     */
    private static function labels(array $options): array {
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
