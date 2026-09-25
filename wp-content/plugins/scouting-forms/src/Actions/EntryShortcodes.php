<?php

namespace ScoutingMemories\Forms\Actions;

use ScoutingMemories\Forms\Forms\Rendering\FieldRenderer;

/**
 * EntryShortcodes
 *
 * Replaces Formidable's entry shortcodes in email settings and success messages:
 * [123] / [field_key] (field values), [default-message], [id], [key], [form_name], [sitename],
 * [siteurl], [admin_email], [date], [time], [ip], [user_id], [created-at].
 */
class EntryShortcodes {

    /**
     * @param array<string, mixed> $form
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed> $values field_id => value
     * @param array<string, mixed> $entry id, key, ip, user_id, created_at
     * @param bool $html Escape values for HTML output
     */
    public static function replace(string $text, array $form, array $fields, array $values, array $entry, bool $html = false): string {
        if ($text === '' || strpos($text, '[') === false) {
            return $text;
        }

        $out = static function (string $value) use ($html): string {
            return $html ? esc_html($value) : $value;
        };

        $map = [
            '[default-message]' => self::defaultMessage($fields, $values, $html),
            '[id]' => (string) ($entry['id'] ?? ''),
            '[key]' => $out((string) ($entry['key'] ?? '')),
            '[form_name]' => $out($form['name']),
            '[sitename]' => $out(get_bloginfo('name')),
            '[siteurl]' => home_url('/'),
            '[admin_email]' => (string) get_option('admin_email'),
            '[date]' => wp_date(get_option('date_format')),
            '[time]' => wp_date(get_option('time_format')),
            '[ip]' => $out((string) ($entry['ip'] ?? '')),
            '[user_id]' => (string) ($entry['user_id'] ?? ''),
            '[created-at]' => $out((string) ($entry['created_at'] ?? '')),
        ];

        foreach ($fields as $field) {
            $value = $out(self::display($field, $values[(int) $field['id']] ?? ''));
            $map['[' . $field['id'] . ']'] = $value;
            $map['[' . $field['key'] . ']'] = $value;
        }

        return strtr($text, $map);
    }

    /**
     * "Label: value" for every field with a value, like Formidable's [default-message].
     *
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed> $values
     */
    public static function defaultMessage(array $fields, array $values, bool $html = false): string {
        $lines = [];
        foreach ($fields as $field) {
            if (in_array($field['type'], FieldRenderer::NON_INPUT_TYPES, true) || in_array($field['type'], ['hidden', 'user_id', 'password'], true)) {
                continue;
            }
            $value = self::display($field, $values[(int) $field['id']] ?? '');
            if ($value === '') {
                continue;
            }
            $lines[] = $html
                ? '<strong>' . esc_html($field['name']) . ':</strong> ' . nl2br(esc_html($value))
                : $field['name'] . ': ' . $value;
        }
        return implode($html ? "<br>\n" : "\n", $lines);
    }

    /**
     * Human-readable value: choice labels instead of stored values, lists joined with commas.
     *
     * @param mixed $value
     */
    public static function display(array $field, $value): string {
        $values = array_map('strval', (array) $value);
        if (in_array($field['type'], ['select', 'radio', 'checkbox'], true)) {
            $choices = FieldRenderer::choiceList($field);
            $values = array_map(static fn($v) => $choices[$v] ?? $v, $values);
        }
        return implode(', ', array_filter($values, 'strlen'));
    }
}
