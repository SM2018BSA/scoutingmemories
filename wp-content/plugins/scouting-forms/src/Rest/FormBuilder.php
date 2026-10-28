<?php

namespace ScoutingMemories\Forms\Rest;

use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Support\TestData;

/**
 * FormBuilder
 *
 * What the wp-admin builder reads and saves for a form: field settings (label, key, description,
 * placeholder, default, required/invalid/unique messages, CSS classes, choices, conditional logic),
 * the order of fields (with Formidable's in_section kept in step), field deletion, and the form's
 * email / confirmation actions. Everything is stored in Formidable's own tables and formats.
 *
 * Safety rules:
 *   - A save only writes what actually changed (a save without edits changes nothing).
 *   - Settings the builder cannot edit (array defaults, list values in logic rules, other field
 *     options, other action settings) are kept exactly as they are.
 *   - A field's type is fixed once it exists (its saved answers depend on it).
 *   - A field cannot be deleted while the theme (formidable_constants.php), another field's logic,
 *     a Dynamic field, a view or a form action uses it; sections are not deleted here.
 *   - Everything is validated before anything is written.
 */
class FormBuilder {

    public const NEW_FIELD_TYPES = ['text', 'textarea', 'email', 'url', 'number', 'phone', 'date', 'select', 'radio', 'checkbox', 'hidden', 'html'];
    public const CHOICE_TYPES = ['select', 'radio', 'checkbox'];
    public const OPERATORS = ['==', '!=', '>', '<', '>=', '<=', 'LIKE', 'not LIKE', 'LIKE%', '%LIKE'];
    public const ACTION_EVENTS = ['create', 'update', 'delete', 'draft', 'import'];
    public const EDITABLE_ACTIONS = ['email', 'on_submit'];

    /** @var array<int, string>|null */
    private static ?array $themeIds = null;

    // ------------------------------------------------------------------ reading

    /**
     * @return array<string, mixed>|null
     */
    public static function forBuilder(int $formId): ?array {
        $form = FormRepository::find($formId);
        if (!$form) {
            return null;
        }
        $theme = self::themeFieldIds();
        $fields = [];
        foreach (FormRepository::fields($formId) as $field) {
            $fields[] = self::clientField($field, $theme);
        }
        $actions = [];
        foreach (self::actionPosts($formId) as $post) {
            $actions[] = self::clientAction($post);
        }
        return [
            'form' => self::clientForm($form),
            'fields' => $fields,
            'actions' => $actions,
            'pages' => self::pages(),
            'new_field_types' => self::NEW_FIELD_TYPES,
            'operators' => self::OPERATORS,
        ];
    }

    /**
     * Field IDs the theme hard-codes (constants ending in _FID in inc/formidable_constants.php).
     *
     * @return array<int, string> field ID => constant name
     */
    public static function themeFieldIds(): array {
        if (self::$themeIds !== null) {
            return self::$themeIds;
        }
        $ids = [];
        foreach ((get_defined_constants(true)['user'] ?? []) as $name => $value) {
            if (substr((string) $name, -4) === '_FID' && is_numeric($value)) {
                $ids[(int) $value] = (string) $name;
            }
        }
        return self::$themeIds = $ids;
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    private static function clientForm(array $form): array {
        $o = $form['options'];
        return [
            'id' => $form['id'],
            'name' => $form['name'],
            'form_key' => $form['key'],
            'description' => $form['description'],
            'status' => $form['status'],
            'parent_form_id' => $form['parent_form_id'],
            'submit_value' => (string) ($o['submit_value'] ?? ''),
            'edit_value' => (string) ($o['edit_value'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $field
     * @param array<int, string> $theme
     * @return array<string, mixed>
     */
    public static function clientField(array $field, array $theme = []): array {
        $o = $field['field_options'];
        $default = $field['default_value'];
        $locked = $default !== null && !is_scalar($default);

        return [
            'id' => (int) $field['id'],
            'name' => (string) $field['name'],
            'field_key' => (string) $field['key'],
            'type' => (string) $field['type'],
            'description' => (string) $field['description'],
            'required' => (bool) $field['required'],
            'default_value' => $locked ? '' : (string) $default,
            'default_locked' => $locked,
            'placeholder' => (string) ($o['placeholder'] ?? ''),
            'classes' => (string) ($o['classes'] ?? ''),
            'blank' => (string) ($o['blank'] ?? ''),
            'invalid' => (string) ($o['invalid'] ?? ''),
            'unique' => !empty($o['unique']),
            'unique_msg' => (string) ($o['unique_msg'] ?? ''),
            'separate_value' => !empty($o['separate_value']),
            'choices' => in_array($field['type'], self::CHOICE_TYPES, true) ? self::choicesForClient($field['options']) : null,
            'logic' => self::logicForClient($o),
            'in_section' => (int) ($o['in_section'] ?? 0),
            'repeat' => !empty($o['repeat']),
            'info' => self::fieldInfo($field),
            'theme_constant' => $theme[(int) $field['id']] ?? '',
        ];
    }

    /**
     * @param mixed $options
     * @return array<int, array<string, mixed>>
     */
    private static function choicesForClient($options): array {
        $list = [];
        foreach (is_array($options) ? $options : [] as $key => $opt) {
            if (is_array($opt)) {
                $label = (string) ($opt['label'] ?? '');
                $list[] = ['key' => (string) $key, 'label' => $label, 'value' => (string) ($opt['value'] ?? $label), 'array' => true];
            } elseif (is_scalar($opt)) {
                $list[] = ['key' => (string) $key, 'label' => (string) $opt, 'value' => (string) $opt, 'array' => false];
            }
        }
        return $list;
    }

    /**
     * Formidable keeps field logic as three parallel lists plus show/hide and any/all.
     *
     * @param array<string, mixed> $o
     * @return array<string, mixed>
     */
    private static function logicForClient(array $o): array {
        $rows = [];
        $fields = (array) ($o['hide_field'] ?? []);
        $conds = (array) ($o['hide_field_cond'] ?? []);
        $values = (array) ($o['hide_opt'] ?? []);
        foreach ($fields as $i => $fieldId) {
            if ($fieldId === '' || $fieldId === null || !is_scalar($fieldId)) {
                continue;
            }
            $value = $values[$i] ?? '';
            $rows[] = [
                'index' => $i,
                'field' => (int) $fieldId,
                'cond' => is_scalar($conds[$i] ?? null) ? (string) $conds[$i] : '==',
                'value' => is_array($value) ? implode(', ', array_map('strval', array_filter($value, 'is_scalar'))) : (string) $value,
                'locked' => is_array($value),
            ];
        }
        return [
            'show_hide' => ($o['show_hide'] ?? 'show') === 'hide' ? 'hide' : 'show',
            'any_all' => ($o['any_all'] ?? 'any') === 'all' ? 'all' : 'any',
            'rows' => $rows,
        ];
    }

    /**
     * Read-only facts about a field the builder shows but does not edit.
     *
     * @param array<string, mixed> $field
     * @return string[]
     */
    private static function fieldInfo(array $field): array {
        $o = $field['field_options'];
        $info = [];
        if ($field['type'] === 'data') {
            $source = !empty($o['form_select']) && is_numeric($o['form_select']) ? FormRepository::field((int) $o['form_select']) : null;
            $sourceForm = $source ? FormRepository::find((int) $source['form_id']) : null;
            $info[] = $source
                ? sprintf('Choices come from "%s" (field %d) of the form "%s".', $source['name'], $source['id'], $sourceForm['name'] ?? '')
                : 'Dynamic field (choices from another form).';
            if (!empty($o['data_type'])) {
                $info[] = 'Shown as: ' . $o['data_type'] . '.';
            }
            if (!empty($o['hide_field']) && ($o['data_type'] ?? '') !== '') {
                $info[] = 'Its choices can depend on another Dynamic field (set in its logic rules).';
            }
        }
        if ($field['type'] === 'divider' && !empty($o['repeat'])) {
            $child = !empty($o['form_select']) ? FormRepository::find((int) $o['form_select']) : null;
            $info[] = $child
                ? sprintf('Repeating section: its fields belong to the form "%s" (#%d). Open that form to edit them.', $child['name'], $child['id'])
                : 'Repeating section.';
        }
        if (!empty($o['post_field'])) {
            $info[] = 'Saved to the post as: ' . (is_scalar($o['post_field']) ? $o['post_field'] : 'post field') . (!empty($o['custom_field']) && is_scalar($o['custom_field']) ? ' (' . $o['custom_field'] . ')' : '') . '.';
        }
        return $info;
    }

    /**
     * @return array<int, array{id:int, title:string}>
     */
    private static function pages(): array {
        $pages = [];
        foreach (get_pages(['post_status' => 'publish', 'sort_column' => 'post_title', 'number' => 1000]) as $page) {
            $pages[] = ['id' => (int) $page->ID, 'title' => $page->post_title];
        }
        return $pages;
    }

    // ------------------------------------------------------------------ saving fields

    /**
     * Save the fields of a form: {fields: [...in order...], deleted: [ids]}.
     *
     * @param array<string, mixed> $data
     * @return array{ok:bool, errors:array<string, string>, message:string}
     */
    public static function saveFields(int $formId, array $data): array {
        global $wpdb;
        $table = $wpdb->prefix . 'frm_fields';
        $existing = [];
        foreach (FormRepository::fields($formId) as $field) {
            $existing[(int) $field['id']] = $field;
        }
        $theme = self::themeFieldIds();
        $errors = [];

        $deleted = [];
        foreach ((array) ($data['deleted'] ?? []) as $id) {
            $id = (int) $id;
            if (isset($existing[$id])) {
                $deleted[$id] = true;
            }
        }

        // Incoming fields in their new order; fields of other forms are ignored
        $incoming = [];
        $seenKeys = [];
        foreach ((array) ($data['fields'] ?? []) as $i => $f) {
            if (!is_array($f)) {
                continue;
            }
            $id = (int) ($f['id'] ?? 0);
            if ($id && (!isset($existing[$id]) || isset($deleted[$id]))) {
                continue;
            }
            if ($id && isset($incoming['f' . $id])) {
                continue;
            }
            $ref = $id ? (string) $id : 'new' . (int) $i;
            if (isset($f['ref']) && is_scalar($f['ref']) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', (string) $f['ref'])) {
                $ref = (string) $f['ref'];
            }
            $incoming[$id ? 'f' . $id : 'n' . $i] = ['id' => $id, 'ref' => $ref, 'data' => $f];
        }
        $sentIds = array_map(static fn($item) => (int) $item['id'], array_values($incoming));
        // A request that does not list every field cannot say where the others go: nothing moves
        $allSent = array_diff(array_diff(array_keys($existing), array_keys($deleted)), $sentIds) === [];
        // Existing fields the browser did not send stay, after the others, in their order
        foreach ($existing as $id => $field) {
            if (!isset($deleted[$id]) && !isset($incoming['f' . $id])) {
                $incoming['f' . $id] = ['id' => $id, 'ref' => (string) $id, 'data' => self::clientField($field, $theme)];
            }
        }

        // Ids that logic rules may point at: the form's fields that stay
        $validTargets = [];
        foreach ($incoming as $item) {
            if ($item['id']) {
                $validTargets[$item['id']] = true;
            }
        }

        // ---- validate
        $plans = [];
        foreach ($incoming as $item) {
            $f = $item['data'];
            $id = $item['id'];
            $old = $id ? self::clientField($existing[$id], $theme) : null;
            $type = $id ? $existing[$id]['type'] : (string) ($f['type'] ?? '');
            $ref = $item['ref'];

            if (!$id && !in_array($type, self::NEW_FIELD_TYPES, true)) {
                $errors[$ref] = __('This kind of field cannot be added here.', 'scouting-forms');
                continue;
            }
            $name = trim(sanitize_text_field(self::str($f['name'] ?? ($old['name'] ?? ''))));
            if ($name === '' && $type !== 'end_divider' && (!$id || $name !== $old['name'])) {
                $errors[$ref] = __('Give the field a label.', 'scouting-forms');
                continue;
            }

            // Key: unchanged keys stay exactly as they are
            $key = trim(self::str($f['field_key'] ?? ($old['field_key'] ?? '')));
            if (!$id || $key !== $old['field_key']) {
                if ($key === '') {
                    $key = self::uniqueKey($name);
                } elseif (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $key)) {
                    $errors[$ref] = __('Field keys may use only letters, numbers, - and _.', 'scouting-forms');
                    continue;
                } elseif ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE field_key = %s AND id <> %d", $key, $id))) {
                    $errors[$ref] = __('Another field already uses this key.', 'scouting-forms');
                    continue;
                }
            }
            if (isset($seenKeys[$key])) {
                $errors[$ref] = __('Another field already uses this key.', 'scouting-forms');
                continue;
            }
            $seenKeys[$key] = true;

            // Choices
            $choices = null;
            if (in_array($type, self::CHOICE_TYPES, true) && isset($f['choices']) && is_array($f['choices'])) {
                $choices = [];
                $oldKeys = array_column($old['choices'] ?? [], 'key');
                foreach ($f['choices'] as $c) {
                    if (!is_array($c)) {
                        continue;
                    }
                    $label = trim(self::settingsText(self::str($c['label'] ?? '')));
                    $value = trim(sanitize_text_field(self::str($c['value'] ?? '')));
                    // A blank choice the field already had (a "choose one" first line) stays;
                    // blank rows added in the builder are dropped
                    if ($label === '' && $value === '' && !in_array(self::str($c['key'] ?? ''), $oldKeys, true)) {
                        continue;
                    }
                    $choices[] = ['key' => self::str($c['key'] ?? ''), 'label' => $label, 'value' => $value === '' ? wp_strip_all_tags($label) : $value, 'array' => !empty($c['array'])];
                }
                if (!array_filter($choices, static fn($c) => $c['label'] !== '' || $c['value'] !== '')) {
                    $errors[$ref] = __('Add at least one choice.', 'scouting-forms');
                    continue;
                }
            }

            // Conditional logic
            $logic = null;
            if (isset($f['logic']) && is_array($f['logic'])) {
                $rows = [];
                $bad = false;
                foreach ((array) ($f['logic']['rows'] ?? []) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $target = (int) ($row['field'] ?? 0);
                    $cond = self::str($row['cond'] ?? '==');
                    if (!$target) {
                        continue; // an unfinished row is dropped
                    }
                    if ($target === $id || !isset($validTargets[$target]) || !in_array($cond, self::OPERATORS, true)) {
                        $bad = true;
                        break;
                    }
                    $rows[] = ['index' => isset($row['index']) && is_numeric($row['index']) ? (int) $row['index'] : null, 'field' => $target, 'cond' => $cond, 'value' => sanitize_text_field(self::str($row['value'] ?? '')), 'locked' => !empty($row['locked'])];
                }
                if ($bad) {
                    $errors[$ref] = __('A logic rule points at a field that is not in this form (or at the field itself).', 'scouting-forms');
                    continue;
                }
                $logic = [
                    'show_hide' => ($f['logic']['show_hide'] ?? 'show') === 'hide' ? 'hide' : 'show',
                    'any_all' => ($f['logic']['any_all'] ?? 'any') === 'all' ? 'all' : 'any',
                    'rows' => $rows,
                ];
            }

            $plans[] = ['id' => $id, 'ref' => $ref, 'type' => $type, 'name' => $name, 'key' => $key, 'f' => $f, 'old' => $old, 'choices' => $choices, 'logic' => $logic];
        }

        // Sections: nothing may be moved into a repeating section (its fields live in a child
        // form), and sections that opened and closed in order must still do so
        $wasValid = self::sectionsValid(array_map(static fn($f) => $f['type'], array_values($existing)));
        $newTypes = array_map(static fn($p) => $p['type'], $plans);
        if (!$errors && $allSent && $wasValid && !self::sectionsValid($newTypes)) {
            $errors['form'] = __('Sections must open and close in order: move the section start and end back around their fields.', 'scouting-forms');
        }
        $inRepeat = false;
        foreach ($plans as $p) {
            if ($p['type'] === 'divider') {
                $inRepeat = $p['id'] && !empty($existing[$p['id']]['field_options']['repeat']);
            } elseif ($p['type'] === 'end_divider') {
                $inRepeat = false;
            } elseif ($inRepeat && $allSent) {
                $errors[$p['ref']] = __('Fields cannot be moved into a repeating section here; open the section\'s own form to add fields to it.', 'scouting-forms');
            }
        }

        // Deletions
        foreach (array_keys($deleted) as $id) {
            $reason = self::deleteBlocker($existing[$id], array_keys($deleted), $theme);
            if ($reason !== '') {
                $errors[(string) $id] = $reason;
            }
        }
        if ($deleted && !\ScoutingMemories\Forms\Support\Permissions::can('delete_forms')) {
            $errors['form'] = __('You are not allowed to delete fields.', 'scouting-forms');
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'message' => __('Nothing was saved: please fix the marked fields.', 'scouting-forms')];
        }

        // ---- write
        foreach (array_keys($deleted) as $id) {
            self::deleteFieldWithAnswers($existing[$id]);
        }

        // Order numbers are rewritten only when fields moved or were added (a save without
        // changes leaves Formidable's numbering, gaps included, as it was)
        $planIds = array_map(static fn($p) => (int) $p['id'], $plans);
        $keptIds = array_values(array_diff(array_keys($existing), array_keys($deleted)));
        $renumber = $allSent && (in_array(0, $planIds, true) || $planIds !== $keptIds);
        $nextOrder = $existing ? max(array_map(static fn($f) => (int) $f['field_order'], $existing)) : 0;

        $openSection = 0;
        $order = 0;
        foreach ($plans as $p) {
            $order++;
            $id = $p['id'];
            $f = $p['f'];
            $old = $p['old'];

            // in_section follows the position (Formidable does the same when a field is dragged)
            $inSection = null;
            // Section membership follows position only when fields moved or were added
            // (Formidable's stored values do not always match the order, and are kept then)
            if ($wasValid && $renumber) {
                if ($p['type'] === 'divider') {
                    $openSection = $id && empty($existing[$id]['field_options']['repeat']) ? $id : 0;
                } elseif ($p['type'] === 'end_divider') {
                    $openSection = 0;
                } else {
                    $inSection = $openSection;
                }
            }

            if ($id) {
                $row = [];
                // Options as stored: only the settings that changed are touched
                $opts = maybe_unserialize((string) $wpdb->get_var($wpdb->prepare("SELECT field_options FROM {$table} WHERE id = %d", $id)));
                $opts = is_array($opts) ? $opts : [];
                $optsChanged = false;

                // Compared as cleaned, so a stored label with a stray space is not rewritten
                if ($p['name'] !== trim(sanitize_text_field($old['name']))) {
                    $row['name'] = $p['name'];
                }
                if ($p['key'] !== $old['field_key']) {
                    $row['field_key'] = $p['key'];
                }
                $desc = self::str($f['description'] ?? $old['description']);
                if ($desc !== $old['description']) {
                    $row['description'] = self::settingsText($desc);
                }
                $required = !empty($f['required']);
                if ($required !== $old['required']) {
                    $row['required'] = $required ? 1 : 0;
                }
                if (!$old['default_locked'] && array_key_exists('default_value', $f) && self::str($f['default_value']) !== $old['default_value']) {
                    $row['default_value'] = self::cleanDefault($p['type'], self::str($f['default_value']));
                }
                if ($renumber && (int) $existing[$id]['field_order'] !== $order) {
                    $row['field_order'] = $order;
                }

                foreach (self::optionChanges($f, $old) as $k => $v) {
                    $opts[$k] = $v;
                    $optsChanged = true;
                }
                if ($p['choices'] !== null && self::choicesChanged($p['choices'], $old['choices'] ?? [], !empty($f['separate_value']) !== $old['separate_value'])) {
                    $row['options'] = maybe_serialize(self::buildChoices($p['choices'], $existing[$id]['options'], !empty($f['separate_value'])));
                    $opts['separate_value'] = !empty($f['separate_value']) ? 1 : 0;
                    $optsChanged = true;
                }
                if ($p['logic'] !== null && self::logicChanged($p['logic'], $old['logic'])) {
                    $opts = self::applyLogic($opts, $p['logic']);
                    $optsChanged = true;
                }
                if ($inSection !== null && (int) ($opts['in_section'] ?? 0) !== $inSection) {
                    $opts['in_section'] = $inSection;
                    $optsChanged = true;
                }
                if ($optsChanged) {
                    $row['field_options'] = maybe_serialize($opts);
                }

                if ($row) {
                    $wpdb->update($table, $row, ['id' => $id]);
                }
            } else {
                $opts = [
                    'classes' => '', 'placeholder' => '', 'blank' => '', 'invalid' => '', 'unique' => 0, 'unique_msg' => '',
                    'separate_value' => !empty($f['separate_value']) ? 1 : 0, 'required_indicator' => '*', 'custom_html' => '',
                    'in_section' => (int) $inSection,
                ];
                foreach (self::optionChanges($f, null) as $k => $v) {
                    $opts[$k] = $v;
                }
                if ($p['logic'] !== null) {
                    $opts = self::applyLogic($opts, $p['logic']);
                }
                $wpdb->insert($table, [
                    'form_id' => $formId,
                    'field_key' => $p['key'],
                    'name' => $p['name'],
                    'description' => self::settingsText(self::str($f['description'] ?? '')),
                    'type' => $p['type'],
                    'default_value' => self::cleanDefault($p['type'], self::str($f['default_value'] ?? '')),
                    'options' => $p['choices'] !== null ? maybe_serialize(self::buildChoices($p['choices'], [], !empty($f['separate_value']))) : '',
                    'field_order' => $renumber ? $order : ++$nextOrder,
                    'required' => !empty($f['required']) ? 1 : 0,
                    'field_options' => maybe_serialize($opts),
                    'created_at' => current_time('mysql', 1),
                ]);
            }
        }

        self::purgeCache();
        return ['ok' => true, 'errors' => [], 'message' => __('Fields saved.', 'scouting-forms')];
    }

    /**
     * Delete a field and, as Formidable does, its saved answers: only answers in entries of the
     * field's own form (the site has answers left from fields deleted long ago whose ID a new
     * field can get again; those are not this field's).
     *
     * @param array<string, mixed> $field
     */
    public static function deleteFieldWithAnswers(array $field): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE m FROM {$wpdb->prefix}frm_item_metas m INNER JOIN {$wpdb->prefix}frm_items i ON i.id = m.item_id WHERE m.field_id = %d AND i.form_id = %d",
            (int) $field['id'],
            (int) $field['form_id']
        ));
        $wpdb->delete($wpdb->prefix . 'frm_fields', ['id' => (int) $field['id']], ['%d']);
    }

    /**
     * Why a field may not be deleted ('' = it may).
     *
     * @param array<string, mixed> $field
     * @param int[] $alsoDeleted
     * @param array<int, string> $theme
     */
    public static function deleteBlocker(array $field, array $alsoDeleted, array $theme): string {
        global $wpdb;
        $id = (int) $field['id'];
        if (isset($theme[$id])) {
            return sprintf(__('The theme uses this field (%s), so it cannot be deleted.', 'scouting-forms'), $theme[$id]);
        }
        if (in_array($field['type'], ['divider', 'end_divider'], true)) {
            return __('Sections cannot be deleted here.', 'scouting-forms');
        }

        // Another field's logic or a Dynamic field's source
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, field_options FROM {$wpdb->prefix}frm_fields WHERE id <> %d AND (field_options LIKE %s OR field_options LIKE %s)",
            $id,
            '%hide_field%',
            '%form_select%'
        ));
        foreach ($rows ?: [] as $row) {
            if (in_array((int) $row->id, $alsoDeleted, true)) {
                continue;
            }
            $o = maybe_unserialize($row->field_options);
            if (!is_array($o)) {
                continue;
            }
            if (in_array((string) $id, array_map('strval', array_filter((array) ($o['hide_field'] ?? []), 'is_scalar')), true)) {
                return sprintf(__('The logic of the field "%s" uses this field.', 'scouting-forms'), $row->name);
            }
            if (isset($o['form_select']) && is_scalar($o['form_select']) && (string) $o['form_select'] === (string) $id) {
                return sprintf(__('The Dynamic field "%s" takes its choices from this field.', 'scouting-forms'), $row->name);
            }
        }

        $tag = '/\[(?:if\s+)?' . $id . '(?=[\s\]])/';
        // Views of any form (they can show linked entries' fields)
        $views = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_content FROM {$wpdb->posts} p WHERE p.post_type = 'frm_display' AND p.post_status <> 'trash'"
        );
        foreach ($views ?: [] as $view) {
            $options = maybe_unserialize(get_post_meta((int) $view->ID, 'frm_options', true));
            $options = is_array($options) ? $options : [];
            $used = in_array((string) $id, array_map('strval', array_filter(array_merge((array) ($options['where'] ?? []), (array) ($options['order_by'] ?? [])), 'is_scalar')), true);
            $html = $view->post_content . (string) get_post_meta((int) $view->ID, 'frm_dyncontent', true)
                . (is_string($options['before_content'] ?? null) ? $options['before_content'] : '')
                . (is_string($options['after_content'] ?? null) ? $options['after_content'] : '');
            if ($used || preg_match($tag, $html)) {
                return sprintf(__('The view "%s" uses this field.', 'scouting-forms'), $view->post_title);
            }
        }

        // Form actions of this form (email text, post mapping, account mapping)
        foreach (self::actionPosts((int) $field['form_id']) as $post) {
            if (preg_match($tag, $post->post_content) || preg_match('/"' . $id . '"/', $post->post_content)) {
                return sprintf(__('The form action "%s" uses this field.', 'scouting-forms'), $post->post_title);
            }
        }
        return '';
    }

    /**
     * Field options the builder edits, when they differ from what was loaded.
     *
     * @param array<string, mixed> $f
     * @param array<string, mixed>|null $old
     * @return array<string, mixed>
     */
    private static function optionChanges(array $f, ?array $old): array {
        $changes = [];
        foreach (['placeholder', 'blank', 'invalid', 'unique_msg'] as $k) {
            if (array_key_exists($k, $f)) {
                $clean = static fn(string $s): string => $k === 'placeholder' ? sanitize_text_field($s) : self::settingsText($s);
                $v = $clean(self::str($f[$k]));
                if ($old === null || $v !== $clean($old[$k])) {
                    $changes[$k] = $v;
                }
            }
        }
        if (array_key_exists('classes', $f)) {
            $cleanClasses = static fn(string $s): string => implode(' ', array_filter(array_map('sanitize_html_class', preg_split('/\s+/', $s) ?: [])));
            $classes = $cleanClasses(self::str($f['classes']));
            if ($old === null || $classes !== $cleanClasses($old['classes'])) {
                $changes['classes'] = $classes;
            }
        }
        if (array_key_exists('unique', $f) && ($old === null || !empty($f['unique']) !== $old['unique'])) {
            $changes['unique'] = !empty($f['unique']) ? 1 : 0;
        }
        return $changes;
    }

    /**
     * @param array<int, array<string, mixed>> $new
     * @param array<int, array<string, mixed>> $old
     */
    private static function choicesChanged(array $new, array $old, bool $separateChanged): bool {
        if ($separateChanged || count($new) !== count($old)) {
            return true;
        }
        foreach ($old as $i => $c) {
            $label = trim(self::settingsText($c['label']));
            $value = trim(sanitize_text_field($c['value']));
            $old[$i] = ['key' => $c['key'], 'label' => $label, 'value' => $value === '' ? wp_strip_all_tags($label) : $value];
        }
        foreach ($new as $i => $c) {
            if ($c['key'] !== $old[$i]['key'] || $c['label'] !== $old[$i]['label'] || $c['value'] !== $old[$i]['value']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Formidable's choice list. Existing choices keep any extra settings they carry.
     *
     * @param array<int, array<string, mixed>> $choices
     * @param mixed $original
     * @return array<int|string, mixed>
     */
    private static function buildChoices(array $choices, $original, bool $separate): array {
        $original = is_array($original) ? $original : [];
        $out = [];
        $next = 0;
        foreach ($original as $k => $unused) {
            if (is_numeric($k)) {
                $next = max($next, (int) $k + 1);
            }
        }
        foreach ($choices as $c) {
            $key = $c['key'];
            if ($key === '' || isset($out[$key]) || !preg_match('/^[A-Za-z0-9_]+$/', $key)) {
                $key = (string) $next++;
            }
            $value = $separate ? $c['value'] : wp_strip_all_tags($c['label']);
            if ($c['array'] || $separate || (isset($original[$key]) && is_array($original[$key]))) {
                $base = isset($original[$key]) && is_array($original[$key]) ? $original[$key] : [];
                $out[$key] = array_merge($base, ['label' => $c['label'], 'value' => $value]);
            } else {
                $out[$key] = $c['label'];
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $new
     * @param array<string, mixed> $old
     */
    private static function logicChanged(array $new, array $old): bool {
        if ($new['show_hide'] !== $old['show_hide'] || $new['any_all'] !== $old['any_all'] || count($new['rows']) !== count($old['rows'])) {
            return true;
        }
        foreach ($new['rows'] as $i => $row) {
            $was = $old['rows'][$i];
            if ($row['field'] !== $was['field'] || $row['cond'] !== $was['cond'] || (!$was['locked'] && $row['value'] !== sanitize_text_field($was['value']))) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $opts
     * @param array<string, mixed> $logic
     * @return array<string, mixed>
     */
    private static function applyLogic(array $opts, array $logic): array {
        $oldValues = (array) ($opts['hide_opt'] ?? []);
        $fields = $conds = $values = [];
        foreach ($logic['rows'] as $row) {
            $fields[] = (string) $row['field'];
            $conds[] = $row['cond'];
            // A list value the builder cannot edit is kept as it was
            $values[] = $row['locked'] && $row['index'] !== null && is_array($oldValues[$row['index']] ?? null)
                ? $oldValues[$row['index']]
                : $row['value'];
        }
        $opts['hide_field'] = $fields;
        $opts['hide_field_cond'] = $conds;
        $opts['hide_opt'] = $values;
        $opts['show_hide'] = $logic['show_hide'];
        $opts['any_all'] = $logic['any_all'];
        return $opts;
    }

    /**
     * Section starts and ends alternate (no end without a start, nothing left open).
     *
     * @param string[] $types
     */
    private static function sectionsValid(array $types): bool {
        $open = false;
        foreach ($types as $type) {
            if ($type === 'divider') {
                if ($open) {
                    return false;
                }
                $open = true;
            } elseif ($type === 'end_divider') {
                if (!$open) {
                    return false;
                }
                $open = false;
            }
        }
        return !$open;
    }

    private static function cleanDefault(string $type, string $value): string {
        return in_array($type, ['textarea', 'rte', 'html'], true) ? self::settingsText($value) : sanitize_text_field($value);
    }

    private static function uniqueKey(string $label): string {
        global $wpdb;
        $base = substr(trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(remove_accents($label))), '_'), 0, 40);
        $base = $base !== '' ? $base : 'field';
        $key = $base;
        while ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}frm_fields WHERE field_key = %s", $key))) {
            $key = $base . '_' . strtolower(wp_generate_password(4, false));
        }
        return $key;
    }

    // ------------------------------------------------------------------ form actions

    /**
     * The form's actions, active (publish) and inactive (draft), in Formidable's order.
     *
     * @return \WP_Post[]
     */
    public static function actionPosts(int $formId): array {
        $posts = get_posts([
            'post_type' => 'frm_form_actions',
            'post_status' => ['publish', 'draft'],
            'menu_order' => $formId,
            'numberposts' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => true,
        ]);
        return array_values(array_filter($posts, static fn($p) => (int) $p->menu_order === $formId));
    }

    /**
     * @return array<string, mixed>
     */
    private static function clientAction(\WP_Post $post): array {
        $type = (string) $post->post_excerpt;
        $s = json_decode($post->post_content, true);
        $s = is_array($s) ? $s : [];
        $out = [
            'id' => (int) $post->ID,
            'type' => $type,
            'name' => $post->post_title,
            'active' => $post->post_status === 'publish',
            'editable' => in_array($type, self::EDITABLE_ACTIONS, true),
            'event' => array_values(array_filter((array) ($s['event'] ?? ['create']), 'is_string')),
            'summary' => self::actionSummary($type, $s),
            'settings' => new \stdClass(),
        ];
        if ($type === 'email') {
            $out['settings'] = [
                'email_to' => self::str($s['email_to'] ?? ''),
                'cc' => self::str($s['cc'] ?? ''),
                'bcc' => self::str($s['bcc'] ?? ''),
                'from' => self::str($s['from'] ?? ''),
                'reply_to' => self::str($s['reply_to'] ?? ''),
                'email_subject' => self::str($s['email_subject'] ?? ''),
                'email_message' => self::str($s['email_message'] ?? ''),
                'plain_text' => !empty($s['plain_text']),
            ];
        } elseif ($type === 'on_submit') {
            $out['settings'] = [
                'success_action' => in_array($s['success_action'] ?? 'message', ['message', 'redirect', 'page'], true) ? $s['success_action'] : 'message',
                'success_msg' => self::str($s['success_msg'] ?? ''),
                'success_url' => self::str($s['success_url'] ?? ''),
                'success_page_id' => (int) ($s['success_page_id'] ?? 0),
                'show_form' => !empty($s['show_form']),
            ];
        }
        $conditions = (array) ($s['conditions'] ?? []);
        $rows = [];
        foreach ($conditions as $k => $rule) {
            if (is_numeric($k) && is_array($rule) && !empty($rule['hide_field'])) {
                $value = $rule['hide_opt'] ?? '';
                $rows[] = [
                    'index' => (int) $k,
                    'field' => (int) $rule['hide_field'],
                    'cond' => self::str($rule['hide_field_cond'] ?? '=='),
                    'value' => is_array($value) ? implode(', ', array_map('strval', array_filter($value, 'is_scalar'))) : self::str($value),
                    'locked' => is_array($value),
                ];
            }
        }
        $out['conditions'] = [
            'send_stop' => ($conditions['send_stop'] ?? 'send') === 'stop' ? 'stop' : 'send',
            'any_all' => ($conditions['any_all'] ?? 'any') === 'all' ? 'all' : 'any',
            'rows' => $rows,
        ];
        return $out;
    }

    /**
     * @param array<string, mixed> $s
     */
    private static function actionSummary(string $type, array $s): string {
        switch ($type) {
            case 'email':
                return sprintf(__('Email to %s', 'scouting-forms'), self::str($s['email_to'] ?? '') ?: '—');
            case 'on_submit':
                $what = $s['success_action'] ?? 'message';
                return $what === 'redirect' ? sprintf(__('Go to %s', 'scouting-forms'), self::str($s['success_url'] ?? ''))
                    : ($what === 'page' ? sprintf(__('Show the page "%s"', 'scouting-forms'), get_the_title((int) ($s['success_page_id'] ?? 0)))
                    : __('Show a message', 'scouting-forms'));
            case 'wppost':
                return sprintf(__('Creates or updates a post (%s) from the entry. Edit its field mapping in Formidable if it is ever needed; the builder keeps it as it is.', 'scouting-forms'), self::str($s['post_type'] ?? 'post'));
            case 'register':
                return __('Creates or updates a member account from the entry (Formidable Registration settings, kept as they are).', 'scouting-forms');
            default:
                return __('Kept as it is (not used by this plugin).', 'scouting-forms');
        }
    }

    /**
     * Save an email or confirmation action.
     *
     * @param array<string, mixed> $data
     * @return array{ok:bool, errors:array<string, string>, message:string}
     */
    public static function saveAction(\WP_Post $post, array $data): array {
        global $wpdb;
        $type = (string) $post->post_excerpt;
        $formId = (int) $post->menu_order;
        if (!in_array($type, self::EDITABLE_ACTIONS, true) || !FormRepository::find($formId)) {
            return ['ok' => false, 'errors' => [], 'message' => __('This action cannot be edited here.', 'scouting-forms')];
        }
        $settings = json_decode($post->post_content, true);
        $settings = is_array($settings) ? $settings : [];
        $original = $settings;
        $in = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $errors = [];

        // Events the builder offers come from the request; others already set (Formidable
        // Registration's user_registration, ...) are kept, and the same set keeps its stored form
        $stored = array_values(array_filter((array) ($settings['event'] ?? []), 'is_string'));
        $events = array_values(array_intersect(self::ACTION_EVENTS, array_map([self::class, 'str'], (array) ($data['event'] ?? []))));
        $events = array_values(array_unique(array_merge($events, array_diff($stored, self::ACTION_EVENTS))));
        $events = $events ?: ['create'];
        $storedSet = $stored ?: ['create']; // no events stored means "create" to Formidable
        if (!(array_diff($events, $storedSet) === [] && array_diff($storedSet, $events) === [])) {
            $settings['event'] = $events;
        }

        if ($type === 'email') {
            foreach (['email_to', 'cc', 'bcc', 'from', 'reply_to', 'email_subject'] as $k) {
                if (array_key_exists($k, $in)) {
                    $settings[$k] = self::headerText(self::str($in[$k]));
                }
            }
            if (array_key_exists('email_message', $in)) {
                $settings['email_message'] = self::settingsText(self::str($in['email_message']));
            }
            if (array_key_exists('plain_text', $in)) {
                $settings['plain_text'] = !empty($in['plain_text']) ? 1 : 0;
            }
            if (trim((string) ($settings['email_to'] ?? '')) === '') {
                $errors['email_to'] = __('Say who receives the email.', 'scouting-forms');
            }
        } else {
            $what = self::str($in['success_action'] ?? ($settings['success_action'] ?? 'message'));
            $settings['success_action'] = in_array($what, ['message', 'redirect', 'page'], true) ? $what : 'message';
            if (array_key_exists('success_msg', $in)) {
                $settings['success_msg'] = self::settingsText(self::str($in['success_msg']));
            }
            if (array_key_exists('success_url', $in)) {
                $url = trim(sanitize_text_field(self::str($in['success_url'])));
                if ($url !== '' && !preg_match('#^(https?://|/|\[)#i', $url)) {
                    $errors['success_url'] = __('Use a web address starting with https://, http:// or /, or a shortcode.', 'scouting-forms');
                }
                $settings['success_url'] = $url;
            }
            if (array_key_exists('success_page_id', $in)) {
                $pageId = absint($in['success_page_id']);
                $settings['success_page_id'] = $pageId ?: '';
            }
            if (array_key_exists('show_form', $in)) {
                $settings['show_form'] = !empty($in['show_form']) ? '1' : '';
            }
            if ($settings['success_action'] === 'redirect' && trim((string) ($settings['success_url'] ?? '')) === '') {
                $errors['success_url'] = __('Give the address to go to.', 'scouting-forms');
            }
            if ($settings['success_action'] === 'page') {
                $page = get_post((int) ($settings['success_page_id'] ?? 0));
                if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish') {
                    $errors['success_page_id'] = __('Choose a published page.', 'scouting-forms');
                }
            }
        }

        // Conditions ("send this email / do this only if ...")
        if (isset($data['conditions']) && is_array($data['conditions'])) {
            $valid = [];
            foreach (FormRepository::fields($formId) as $f) {
                $valid[(int) $f['id']] = true;
            }
            $old = (array) ($settings['conditions'] ?? []);
            $conditions = [
                'send_stop' => ($data['conditions']['send_stop'] ?? 'send') === 'stop' ? 'stop' : 'send',
                'any_all' => ($data['conditions']['any_all'] ?? 'any') === 'all' ? 'all' : 'any',
            ];
            $n = 0;
            foreach ((array) ($data['conditions']['rows'] ?? []) as $row) {
                if (!is_array($row) || empty($row['field'])) {
                    continue;
                }
                $target = (int) $row['field'];
                $cond = self::str($row['cond'] ?? '==');
                if (!isset($valid[$target]) || !in_array($cond, self::OPERATORS, true)) {
                    $errors['conditions'] = __('A condition points at a field that is not in this form.', 'scouting-forms');
                    break;
                }
                $index = isset($row['index']) && is_numeric($row['index']) ? (int) $row['index'] : null;
                $value = !empty($row['locked']) && $index !== null && is_array($old[$index]['hide_opt'] ?? null)
                    ? $old[$index]['hide_opt']
                    : sanitize_text_field(self::str($row['value'] ?? ''));
                $conditions[$n++] = ['hide_field' => (string) $target, 'hide_field_cond' => $cond, 'hide_opt' => $value];
            }
            $settings['conditions'] = $conditions;
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'message' => __('Nothing was saved: please fix the marked settings.', 'scouting-forms')];
        }

        // A setting whose meaning did not change keeps exactly what was stored (a save without
        // edits changes nothing)
        foreach ($settings as $k => $v) {
            if (self::sameSetting($original[$k] ?? null, $v)) {
                if (array_key_exists($k, $original)) {
                    $settings[$k] = $original[$k];
                } else {
                    unset($settings[$k]);
                }
            }
        }

        $title = sanitize_text_field(self::str($data['name'] ?? $post->post_title)) ?: $post->post_title;
        $status = !empty($data['active']) ? 'publish' : 'draft';
        if ($settings === $original && $title === $post->post_title && $status === $post->post_status) {
            return ['ok' => true, 'errors' => [], 'message' => __('Action saved.', 'scouting-forms')];
        }
        // Written directly: WordPress's post filters would damage the JSON (Formidable stores it raw too)
        $wpdb->update($wpdb->posts, [
            'post_content' => wp_json_encode($settings),
            'post_title' => $title,
            'post_status' => $status,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1),
        ], ['ID' => $post->ID]);
        clean_post_cache($post->ID);
        self::purgeCache();
        return ['ok' => true, 'errors' => [], 'message' => __('Action saved.', 'scouting-forms')];
    }

    /**
     * A new email or confirmation action, inactive until it is set up and switched on.
     */
    public static function createAction(int $formId, string $type): int {
        if (!in_array($type, self::EDITABLE_ACTIONS, true) || !FormRepository::find($formId)) {
            return 0;
        }
        $settings = $type === 'email'
            ? ['email_to' => '[admin_email]', 'cc' => '', 'bcc' => '', 'from' => '[sitename] <[admin_email]>', 'reply_to' => '', 'email_subject' => '', 'email_message' => '[default-message]', 'plain_text' => 0, 'event' => ['create'], 'conditions' => ['send_stop' => 'send', 'any_all' => 'any']]
            : ['event' => ['create'], 'success_action' => 'message', 'success_msg' => __('Your responses were successfully submitted. Thank you!', 'scouting-forms'), 'show_form' => ''];
        $id = wp_insert_post([
            'post_type' => 'frm_form_actions',
            'post_status' => 'draft',
            'post_title' => $type === 'email' ? __('Email Notification', 'scouting-forms') : __('Confirmation', 'scouting-forms'),
            'post_excerpt' => $type,
            'menu_order' => $formId,
            'post_name' => $formId . '_' . $type,
            'post_content' => '',
        ], true);
        if (is_wp_error($id) || !$id) {
            return 0;
        }
        global $wpdb;
        $wpdb->update($wpdb->posts, ['post_content' => wp_json_encode($settings)], ['ID' => $id]);
        clean_post_cache($id);
        TestData::markPost((int) $id);
        return (int) $id;
    }

    // ------------------------------------------------------------------ views

    /**
     * Filter and sort rows of a view, kept only when they point at the view's form fields or
     * entry columns and use operators the view engine knows.
     *
     * @param array<string, mixed> $data
     * @return array<string, array<int, string>>
     */
    public static function cleanViewRules(array $data, int $formId): array {
        $fields = [];
        foreach (FormRepository::fields($formId) as $f) {
            $fields[(string) $f['id']] = true;
        }
        $columns = ['id', 'item_key', 'created_at', 'updated_at', 'user_id', 'post_id', 'is_draft'];
        $ops = array_merge(self::OPERATORS, ['=', 'group_by', 'group_by_newest']);
        $out = ['where' => [], 'where_is' => [], 'where_val' => [], 'order_by' => [], 'order' => []];

        $where = array_values((array) ($data['where'] ?? []));
        $is = array_values((array) ($data['where_is'] ?? []));
        $val = array_values((array) ($data['where_val'] ?? []));
        foreach ($where as $i => $target) {
            $target = self::str($target);
            $op = self::str($is[$i] ?? '=');
            if ((!isset($fields[$target]) && !in_array($target, $columns, true)) || !in_array($op, $ops, true)) {
                continue;
            }
            $out['where'][] = $target;
            $out['where_is'][] = $op;
            $out['where_val'][] = sanitize_text_field(self::str($val[$i] ?? ''));
        }
        $orderBy = array_values((array) ($data['order_by'] ?? []));
        $order = array_values((array) ($data['order'] ?? []));
        foreach ($orderBy as $i => $target) {
            $target = self::str($target);
            if (!isset($fields[$target]) && !in_array($target, $columns, true) && $target !== 'rand') {
                continue;
            }
            $out['order_by'][] = $target;
            $out['order'][] = strtoupper(self::str($order[$i] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        }
        return $out;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * An email address line (To, From "Name <address>", ...): kept as typed, on one line, so no
     * extra mail headers can be slipped in.
     */
    public static function headerText(string $value): string {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value));
    }

    /**
     * @param mixed $a
     * @param mixed $b
     */
    private static function sameSetting($a, $b): bool {
        $blank = static function ($v): bool {
            if ($v === null || $v === '' || $v === 0 || $v === '0' || $v === false || $v === []) {
                return true;
            }
            // Conditions with no rules and the default choices are the same as none
            return is_array($v) && array_diff_key($v, ['send_stop' => 1, 'any_all' => 1]) === []
                && in_array($v['send_stop'] ?? '', ['', 'send'], true) && in_array($v['any_all'] ?? '', ['', 'any'], true);
        };
        if ($blank($a) && $blank($b)) {
            return true;
        }
        if (is_scalar($a) && is_scalar($b)) {
            return (string) (is_bool($a) ? (int) $a : $a) === (string) (is_bool($b) ? (int) $b : $b);
        }
        return is_array($a) && is_array($b) && $a == $b;
    }

    /**
     * @param mixed $value
     */
    public static function str($value): string {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Settings that are shown as HTML: raw for people allowed raw HTML, filtered for others.
     */
    public static function settingsText(string $value): string {
        return current_user_can('unfiltered_html') ? $value : wp_kses_post($value);
    }

    public static function purgeCache(): void {
        if (function_exists('wp_cache_flush_group')) {
            foreach (['frm_entry', 'frm_field', 'frm_form', 'frm_actions'] as $group) {
                wp_cache_flush_group($group);
            }
        }
        if (class_exists('WpeCommon') && method_exists('WpeCommon', 'purge_memcached')) {
            \WpeCommon::purge_memcached();
        }
    }
}
