<?php

namespace ScoutingMemories\Forms\Forms\Submission;

use ScoutingMemories\Forms\Forms\Rendering\FieldRenderer;

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
     * @return array{values: array<int, mixed>, errors: array<int, string>}
     */
    public static function validate(array $fields, array $posted): array {
        $values = [];
        $errors = [];

        foreach ($fields as $field) {
            $id = (int) $field['id'];
            $type = $field['type'];

            if (in_array($type, FieldRenderer::NON_INPUT_TYPES, true) || $type === 'file') {
                continue;
            }
            if ($type === 'user_id') {
                $values[$id] = get_current_user_id();
                continue;
            }

            $raw = $posted[$id] ?? ($posted[(string) $id] ?? '');

            // sanitize_email() turns an invalid address into '', which would read as "blank";
            // check what was typed and keep it in the field so the person can correct it
            if ($type === 'email' && !is_array($raw) && trim((string) $raw) !== '' && !is_email(trim((string) $raw))) {
                $values[$id] = sanitize_text_field((string) $raw);
                $errors[$id] = self::message($field, 'invalid', 'Please enter a valid email address');
                continue;
            }

            $value = self::sanitize($field, $raw);
            $values[$id] = $value;

            $error = self::check($field, $value);
            if ($error !== '') {
                $errors[$id] = $error;
            }
        }

        return ['values' => $values, 'errors' => $errors];
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
    private static function check(array $field, $value): string {
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
        }

        if (!empty($opts['unique']) && self::isDuplicate($field, $value)) {
            return self::message($field, 'unique_msg', '[field_name] must be unique');
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    private static function isDuplicate(array $field, $value): bool {
        global $wpdb;
        $stored = is_array($value) ? maybe_serialize($value) : (string) $value;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->prefix}frm_item_metas WHERE field_id = %d AND meta_value = %s LIMIT 1",
            (int) $field['id'],
            $stored
        ));
    }

    private static function message(array $field, string $key, string $fallback): string {
        $message = (string) ($field['field_options'][$key] ?? '');
        if ($message === '') {
            $message = $fallback;
        }
        return str_replace('[field_name]', $field['name'], $message);
    }
}
