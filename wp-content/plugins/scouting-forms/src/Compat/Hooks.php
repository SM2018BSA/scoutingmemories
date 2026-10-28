<?php

namespace ScoutingMemories\Forms\Compat;

use ScoutingMemories\Forms\Forms\Rendering\FieldRenderer;

/**
 * Hooks
 *
 * Formidable's hooks the theme listens to, fired by the plugin when Formidable is not active:
 *   - frm_pre_create_entry (values before a new entry is saved; Edit Users acts here),
 *   - frm_after_create_entry / frm_after_update_entry (council/camp/lodge slugs, Add a Post
 *     taxonomies; the theme reads the values from $_POST['item_meta']),
 *   - frm_setup_new_fields_vars / frm_setup_edit_fields_vars (defaults and choice labels when a
 *     form is shown, e.g. council numbers),
 *   - frm_get_default_value, frm_rte_options, the view pagination link filters.
 * Every save still goes through EntryService first (access rules, validation, spam check), so
 * the theme only ever sees values from people allowed to use the form.
 */
class Hooks {

    private static bool $on = false;

    public static function register(): void {
        self::$on = true;
    }

    public static function active(): bool {
        return self::$on;
    }

    /**
     * frm_pre_create_entry: the theme may act on (or change) the values of a new entry.
     *
     * @param array<string, mixed> $form
     * @param array<int, mixed> $values field ID => value
     * @return array<int, mixed>
     */
    public static function preCreate(array $form, array $values, array $fields = []): array {
        if (!self::$on) {
            return $values;
        }
        $posted = self::asPosted($values, $fields);
        $args = [
            'form_id' => (int) $form['id'],
            'form_key' => (string) $form['key'],
            'item_meta' => $posted,
            'item_key' => '',
            'parent_item_id' => 0,
            'frm_user_id' => get_current_user_id(),
        ];
        $result = self::withPostedValues($posted, static fn() => apply_filters('frm_pre_create_entry', $args));
        // The theme returns nothing in some cases; the values stay as they were then
        if (is_array($result) && isset($result['item_meta']) && is_array($result['item_meta'])) {
            return array_intersect_key($result['item_meta'], $values) + $values;
        }
        return $values;
    }

    /**
     * frm_after_create_entry / frm_after_update_entry, after the form's actions (the post exists).
     *
     * @param array<int, mixed> $values
     */
    public static function afterSave(int $entryId, int $formId, array $values, bool $created, array $fields = []): void {
        if (!self::$on || $entryId <= 0) {
            return;
        }
        // The theme adds the new council/camp/lodge to an ACF select here; see acfFieldData()
        add_filter('wp_insert_post_data', [self::class, 'acfFieldData'], 99, 3);
        try {
            self::withPostedValues(self::asPosted($values, $fields), static function () use ($entryId, $formId, $created) {
                do_action($created ? 'frm_after_create_entry' : 'frm_after_update_entry', $entryId, $formId);
                return null;
            });
        } finally {
            remove_filter('wp_insert_post_data', [self::class, 'acfFieldData'], 99);
        }
    }

    /**
     * An ACF field definition saved while the theme's hooks run (the theme adds each new
     * council/camp/lodge as a choice) is stored as ACF built it, its choices cleaned as plain
     * text. WordPress would otherwise run the member's HTML filter over the whole serialized
     * definition, which breaks it for anyone without unfiltered_html (and the next save then
     * replaces every choice with one).
     *
     * @param array<string, mixed> $data Slashed post data after sanitizing
     * @param array<string, mixed> $postarr
     * @param array<string, mixed> $unsanitized
     * @return array<string, mixed>
     */
    public static function acfFieldData($data, $postarr = [], $unsanitized = []) {
        if (!is_array($data) || ($data['post_type'] ?? '') !== 'acf-field' || !isset($unsanitized['post_content'])) {
            return $data;
        }
        $field = maybe_unserialize(wp_unslash((string) $unsanitized['post_content']));
        if (!is_array($field)) {
            return $data;
        }
        if (isset($field['choices']) && is_array($field['choices'])) {
            // Choices already stored stay exactly as they are; new or changed ones are cleaned
            $stored = [];
            $postId = (int) ($postarr['ID'] ?? 0);
            if ($postId > 0) {
                $current = maybe_unserialize((string) get_post_field('post_content', $postId, 'raw'));
                $stored = is_array($current) && isset($current['choices']) && is_array($current['choices']) ? $current['choices'] : [];
            }
            $clean = [];
            foreach ($field['choices'] as $value => $label) {
                if (array_key_exists($value, $stored) && $stored[$value] === $label) {
                    $clean[$value] = $label;
                    continue;
                }
                $clean[sanitize_text_field((string) $value)] = sanitize_text_field((string) $label);
            }
            $field['choices'] = $clean;
        }
        $data['post_content'] = wp_slash(serialize($field));
        return $data;
    }

    /**
     * frm_setup_new_fields_vars / frm_setup_edit_fields_vars for each field of a form being shown.
     * Returns the fields with the theme's choices (as 'sm_choices') and the values it set.
     *
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed> $values field ID => current value
     * @param array<int, bool> $fixed field IDs whose value was posted (not replaced)
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, mixed>}
     */
    public static function setupFields(array $fields, array $values, array $fixed, int $entryId, callable $choices): array {
        $filter = $entryId ? 'frm_setup_edit_fields_vars' : 'frm_setup_new_fields_vars';
        if (!self::$on || !has_filter($filter)) {
            return [$fields, $values];
        }
        foreach ($fields as $i => $field) {
            $id = (int) $field['id'];
            $options = in_array($field['type'], ['select', 'radio', 'checkbox', 'data'], true) ? $choices($field, $values) : [];
            $vars = array_merge((array) $field['field_options'], [
                'id' => $id,
                'field_key' => $field['key'],
                'name' => $field['name'],
                'description' => $field['description'],
                'type' => $field['type'],
                'form_id' => (int) $field['form_id'],
                'required' => $field['required'],
                'options' => $options,
                'value' => $values[$id] ?? '',
                'default_value' => $values[$id] ?? '',
            ]);
            $result = $entryId
                ? apply_filters($filter, $vars, self::fieldObject($field), $entryId, ['entry_id' => $entryId, 'action' => 'edit'])
                : apply_filters($filter, $vars, self::fieldObject($field), ['action' => 'new']);
            if (!is_array($result)) {
                continue;
            }
            if ($options !== [] && isset($result['options']) && is_array($result['options']) && $result['options'] !== $options) {
                $fields[$i]['sm_choices'] = array_map(static fn($label) => is_scalar($label) ? (string) $label : '', $result['options']);
            }
            if (empty($fixed[$id]) && array_key_exists('value', $result) && $result['value'] !== ($values[$id] ?? '')) {
                $values[$id] = $result['value'];
            }
        }
        return [$fields, $values];
    }

    /**
     * frm_get_default_value
     *
     * @param mixed $value
     * @param array<string, mixed> $field
     * @return mixed
     */
    public static function defaultValue($value, array $field) {
        if (!self::$on || !has_filter('frm_get_default_value')) {
            return $value;
        }
        $result = apply_filters('frm_get_default_value', $value, self::fieldObject($field), true, true);
        return $result === null ? $value : $result;
    }

    /**
     * The field as Formidable passes it to hooks (a database row object).
     *
     * @param array<string, mixed> $field
     */
    public static function fieldObject(array $field): \stdClass {
        return (object) [
            'id' => (int) $field['id'],
            'field_key' => (string) $field['key'],
            'name' => (string) $field['name'],
            'description' => (string) $field['description'],
            'type' => (string) $field['type'],
            'default_value' => $field['default_value'] ?? '',
            'options' => $field['options'],
            'field_order' => (int) ($field['field_order'] ?? 0),
            'form_id' => (int) $field['form_id'],
            'required' => !empty($field['required']) ? '1' : '0',
            'field_options' => (array) $field['field_options'],
        ];
    }

    /**
     * Values as a browser posts the form: every input field present, blank when empty (Formidable
     * sends a blank for an empty multiple choice too).
     *
     * @param array<int, mixed> $values
     * @param array<int, array<string, mixed>> $fields
     * @return array<int, mixed>
     */
    private static function asPosted(array $values, array $fields): array {
        foreach ($fields as $field) {
            if (!in_array($field['type'], FieldRenderer::NON_INPUT_TYPES, true) && $field['type'] !== 'password' && !array_key_exists((int) $field['id'], $values)) {
                $values[(int) $field['id']] = '';
            }
        }
        foreach ($values as $id => $value) {
            if ($value === []) {
                $values[$id] = '';
            }
        }
        return $values;
    }

    /**
     * The theme reads submitted values from $_POST['item_meta']: during the hook it holds the
     * checked values of this submission (also for saves from the admin screens).
     *
     * @param array<int, mixed> $values
     * @return mixed
     */
    private static function withPostedValues(array $values, callable $fn) {
        $had = array_key_exists('item_meta', $_POST);
        $before = $had ? $_POST['item_meta'] : null;
        $_POST['item_meta'] = wp_slash($values);
        try {
            return $fn();
        } finally {
            if ($had) {
                $_POST['item_meta'] = $before;
            } else {
                unset($_POST['item_meta']);
            }
        }
    }
}
