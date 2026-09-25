<?php

namespace ScoutingMemories\Forms\Forms\Submission;

use ScoutingMemories\Forms\Forms\Logic\FieldLogic;
use ScoutingMemories\Forms\Forms\Rendering\DynamicOptions;
use ScoutingMemories\Forms\Forms\Rendering\FieldRenderer;
use ScoutingMemories\Forms\Forms\Rendering\FormTemplate;
use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Support\FormidableSettings;

/**
 * Validator
 *
 * Reads a submission for a Formidable form, sanitizes each value by field type and checks it the
 * way Formidable does, using the field's own messages ("This field cannot be blank.",
 * "Please enter a valid email address", ...).
 */
class Validator {

    /**
     * @param array<int, array<string, mixed>> $fields From FormRepository::fields()
     * @param array<int|string, mixed> $posted Raw $_POST['item_meta'] (already unslashed)
     * @return array{values: array<int, mixed>, errors: array<int|string, string>, rows: array<int, array<string, array<int, mixed>>>}
     *         values: field ID => value to save (fields hidden by logic are left out);
     *         errors: field ID, or "FIELD-SECTION-ROW" for a repeating row, => message;
     *         rows: repeating section ID => row key => child field values (blank rows dropped)
     */
    public static function validate(array $fields, array $posted, int $entryId = 0): array {
        $values = [];
        $errors = [];
        $rows = [];
        $byId = [];
        $raw = [];

        // First read every value, so field logic can look at the whole submission
        foreach ($fields as $field) {
            $id = (int) $field['id'];
            $byId[$id] = $field;
            $type = $field['type'];
            if ($type === 'user_id') {
                $values[$id] = get_current_user_id();
                continue;
            }
            if (in_array($type, FieldRenderer::NON_INPUT_TYPES, true) || $type === 'file' || self::isDisplayOnly($field)) {
                continue;
            }
            $raw[$id] = $posted[$id] ?? ($posted[(string) $id] ?? '');
            $values[$id] = self::sanitize($field, $raw[$id]);
        }

        // "Just show it" Dynamic fields take their value from the parent's choice, not the browser
        foreach ($fields as $field) {
            if (self::isDisplayOnly($field)) {
                $values[(int) $field['id']] = DynamicOptions::displayValue($field, $values);
            }
        }

        foreach ($fields as $field) {
            $id = (int) $field['id'];
            $type = $field['type'];

            // Formidable neither checks nor saves fields its logic hides
            if (!FieldLogic::isShown($field, $values, $byId)) {
                unset($values[$id]);
                continue;
            }

            if ($type === 'divider' && !empty($field['field_options']['repeat'])) {
                [$sectionRows, $rowErrors] = self::repeater($field, $posted[$id] ?? []);
                $rows[$id] = $sectionRows;
                $values[$id] = ['form' => (int) ($field['field_options']['form_select'] ?? 0), 'row_ids' => array_keys($sectionRows)] + $sectionRows;
                $errors += $rowErrors;
                continue;
            }
            if (!array_key_exists($id, $values) || $type === 'user_id' || self::isDisplayOnly($field)) {
                continue;
            }

            // sanitize_email() turns an invalid address into '', which would read as "blank";
            // check what was typed and keep it in the field so the person can correct it
            $typed = $raw[$id] ?? '';
            if ($type === 'email' && !is_array($typed) && trim((string) $typed) !== '' && !is_email(trim((string) $typed))) {
                $values[$id] = sanitize_text_field((string) $typed);
                $errors[$id] = self::message($field, 'invalid', 'Please enter a valid email address');
                continue;
            }

            $error = self::check($field, $values[$id], $values, $entryId);
            if ($error === '' && !empty($field['field_options']['conf_field']) && in_array($type, ['password', 'email', 'text'], true)) {
                // "Confirm" box (item_meta[conf_ID]) must match
                $confirm = $posted['conf_' . $id] ?? '';
                if ((string) (is_array($confirm) ? '' : $confirm) !== (string) (is_array($raw[$id] ?? '') ? '' : ($raw[$id] ?? ''))) {
                    $message = (string) ($field['field_options']['conf_msg'] ?? '');
                    $error = $message !== '' ? $message : 'The entered values do not match';
                }
            }
            if ($error !== '') {
                $errors[$id] = $error;
            }
        }

        return ['values' => $values, 'errors' => $errors, 'rows' => $rows];
    }

    /**
     * Rows of a repeating section, each checked against the section's child form. Rows left
     * completely blank are dropped, as Formidable does.
     *
     * @param array<string, mixed> $section
     * @param mixed $posted item_meta[SECTION]
     * @return array{0: array<string, array<int, mixed>>, 1: array<string, string>}
     */
    private static function repeater(array $section, $posted): array {
        $sectionId = (int) $section['id'];
        $childFields = FormRepository::fields((int) ($section['field_options']['form_select'] ?? 0));
        $rows = [];
        $errors = [];
        foreach (FormTemplate::repeaterRows($posted) as $key => $row) {
            $result = self::validate($childFields, $row);
            if (self::rowIsBlank($result['values'], $childFields)) {
                continue;
            }
            $rows[$key] = $result['values'];
            foreach ($result['errors'] as $childId => $message) {
                $errors[$childId . '-' . $sectionId . '-' . $key] = $message;
            }
        }
        return [$rows, $errors];
    }

    /**
     * @param array<int, mixed> $values
     * @param array<int, array<string, mixed>> $fields
     */
    private static function rowIsBlank(array $values, array $fields): bool {
        foreach ($fields as $field) {
            if ($field['type'] === 'user_id' || self::isDisplayOnly($field)) {
                continue;
            }
            if (!FieldLogic::isBlank($values[(int) $field['id']] ?? '')) {
                return false;
            }
        }
        return true;
    }

    private static function isDisplayOnly(array $field): bool {
        return $field['type'] === 'data' && ($field['field_options']['data_type'] ?? '') === 'data';
    }

    /**
     * @param mixed $raw
     * @return mixed
     */
    private static function sanitize(array $field, $raw) {
        switch ($field['type']) {
            case 'checkbox':
                return array_values(array_filter(array_map('sanitize_text_field', (array) $raw), 'strlen'));
            case 'select':
            case 'data':
                if (!empty($field['field_options']['multiple'])) {
                    return array_values(array_filter(array_map('sanitize_text_field', (array) $raw), 'strlen'));
                }
                return sanitize_text_field(is_array($raw) ? (string) reset($raw) : (string) $raw);
            case 'textarea':
                return sanitize_textarea_field((string) $raw);
            case 'password':
                // Kept exactly as typed (WordPress hashes it); only surrounding line breaks removed
                return is_array($raw) ? '' : trim((string) $raw, "\r\n");
            case 'rte':
                return wp_kses_post((string) $raw);
            case 'email':
                return sanitize_email((string) $raw);
            case 'url':
                return esc_url_raw(trim((string) $raw));
            case 'number':
            case 'range':
                $raw = trim((string) $raw);
                return $raw === '' ? '' : (is_numeric($raw) ? $raw : sanitize_text_field($raw));
            default:
                return sanitize_text_field(is_array($raw) ? implode(', ', $raw) : (string) $raw);
        }
    }

    /**
     * @param mixed $value
     */
    private static function check(array $field, $value, array $values = [], int $entryId = 0): string {
        $opts = $field['field_options'];
        $isEmpty = is_array($value) ? count($value) === 0 : trim((string) $value) === '';

        if ($isEmpty) {
            return $field['required'] ? self::message($field, 'blank', '[field_name] cannot be blank.') : '';
        }

        switch ($field['type']) {
            case 'email':
                if (!is_email($value)) {
                    return self::message($field, 'invalid', 'Please enter a valid email address');
                }
                break;
            case 'url':
                if (!wp_http_validate_url($value)) {
                    return self::message($field, 'invalid', '[field_name] is invalid');
                }
                break;
            case 'number':
            case 'range':
                if (!is_numeric($value)) {
                    return self::message($field, 'invalid', '[field_name] is invalid');
                }
                if (isset($opts['minnum']) && is_numeric($opts['minnum']) && (float) $value < (float) $opts['minnum']) {
                    return self::message($field, 'invalid', '[field_name] is invalid');
                }
                if (isset($opts['maxnum']) && is_numeric($opts['maxnum']) && (float) $value > (float) $opts['maxnum']) {
                    return self::message($field, 'invalid', '[field_name] is invalid');
                }
                break;
            case 'text':
            case 'phone':
                if (isset($opts['max']) && is_numeric($opts['max']) && (int) $opts['max'] > 0 && mb_strlen((string) $value) > (int) $opts['max']) {
                    return self::message($field, 'invalid', '[field_name] is invalid');
                }
                break;
            case 'select':
            case 'radio':
            case 'checkbox':
                // Only accept the field's own choices (unless it allows "other" answers)
                if (empty($opts['other'])) {
                    $allowed = array_map('strval', array_keys(FieldRenderer::choiceList($field)));
                    if ($allowed && array_diff(array_map('strval', (array) $value), $allowed)) {
                        return self::message($field, 'invalid', '[field_name] is invalid');
                    }
                }
                break;
            case 'data':
                // A Dynamic field only accepts the entries it offers (for a dependent field, the
                // ones that match its parent's choice), so no other entry IDs can be saved
                $allowed = array_map('strval', array_keys(FieldRenderer::choiceList($field, $values)));
                if (array_diff(array_map('strval', (array) $value), $allowed)) {
                    return self::message($field, 'invalid', '[field_name] is invalid');
                }
                break;
        }

        if (!empty($opts['unique']) && self::isDuplicate($field, $value, $entryId)) {
            return self::message($field, 'unique_msg', '[field_name] must be unique');
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    /**
     * Another entry already has this value (the entry being edited does not count).
     *
     * @param mixed $value
     */
    private static function isDuplicate(array $field, $value, int $entryId = 0): bool {
        global $wpdb;
        $stored = is_array($value) ? maybe_serialize($value) : (string) $value;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->prefix}frm_item_metas WHERE field_id = %d AND meta_value = %s AND item_id <> %d LIMIT 1",
            (int) $field['id'],
            $stored,
            $entryId
        ));
    }

    /**
     * The field's own message (or Formidable's default), with "[field_name]", "This field" and
     * "This value" replaced by the field's name, as Formidable does ("Camp Name cannot be blank.").
     */
    private static function message(array $field, string $key, string $fallback): string {
        $message = (string) ($field['field_options'][$key] ?? '');
        if ($message === '' && $key === 'blank') {
            $message = (string) FormidableSettings::get('blank_msg', '');
        }
        if ($message === '') {
            $message = $fallback;
        }
        $name = (string) $field['name'];
        if ($name === '') {
            return str_replace('[field_name]', $key === 'unique_msg' ? 'This value' : 'This field', $message);
        }
        return str_replace(['[field_name]', 'This value', 'This field'], $name, $message);
    }
}
