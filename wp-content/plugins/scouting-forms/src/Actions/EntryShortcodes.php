<?php

namespace ScoutingMemories\Forms\Actions;

use ScoutingMemories\Forms\Forms\Rendering\FieldRenderer;
use ScoutingMemories\Forms\Views\TemplateTags;

/**
 * EntryShortcodes
 *
 * Replaces Formidable's entry shortcodes in email settings, success messages and redirect URLs:
 * [123] / [field_key] (field values, with show= and sep= options), [default-message], [id], [key], [form_name], [sitename],
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

        // Values from the entry have their brackets encoded, so text someone typed into the form
        // can never run as a shortcode below; only shortcodes written in the settings do
        $out = static function (string $value) use ($html): string {
            return self::inert($html ? esc_html($value) : $value);
        };

        $map = [
            '[default-message]' => self::inert(self::defaultMessage($fields, $values, $html)),
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

        $text = strtr($text, $map);

        // Field values: [123], [field_key], with options like [123 show=113] or [123 sep=" / "]
        $byTag = [];
        foreach ($fields as $field) {
            $byTag[(string) $field['id']] = $field;
            $byTag[(string) $field['key']] = $field;
        }
        $text = preg_replace_callback('/\[([A-Za-z0-9_\-]+)((?:\s+[^\]\[]*)?)\]/', static function ($m) use ($byTag, $values, $out) {
            $field = $byTag[$m[1]] ?? null;
            if (!$field) {
                return $m[0];
            }
            $atts = shortcode_parse_atts(trim($m[2]));
            return $out(self::display($field, $values[(int) $field['id']] ?? '', is_array($atts) ? $atts : []));
        }, $text);

        // Shortcodes written in the settings, e.g. [frm-field-value field_id=163 user_id=current]
        // to email the person who submitted the form (the plugin's own version is used)
        if (strpos($text, '[') !== false) {
            $text = do_shortcode(TemplateTags::ownShortcodes($text));
        }
        return $html ? $text : str_replace(['&#91;', '&#93;'], ['[', ']'], $text);
    }

    private static function inert(string $value): string {
        return str_replace(['[', ']'], ['&#91;', '&#93;'], $value);
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
     * Human-readable value: choice labels instead of stored values, a Dynamic field's linked
     * entry text instead of its ID, lists joined with commas.
     *
     * @param mixed $value
     * @param array<string, string> $atts show="id" (stored value), show=N (another field of the
     *                                    linked entry), sep=", "
     */
    public static function display(array $field, $value, array $atts = []): string {
        $sep = isset($atts['sep']) ? (string) $atts['sep'] : ', ';
        $values = array_map('strval', (array) $value);
        $show = (string) ($atts['show'] ?? '');

        if ($show === 'id' || $show === 'value') {
            return implode($sep, array_filter($values, 'strlen'));
        }
        if ($field['type'] === 'data') {
            $target = ctype_digit($show) ? (int) $show : (int) ($field['field_options']['form_select'] ?? 0);
            $values = array_map(static fn($v) => self::linkedValue((int) $v, $target), $values);
        } elseif (in_array($field['type'], ['select', 'radio', 'checkbox'], true)) {
            $choices = FieldRenderer::choiceList($field);
            $values = array_map(static fn($v) => $choices[$v] ?? $v, $values);
        }
        return implode($sep, array_filter($values, 'strlen'));
    }

    /**
     * One field of a linked entry (what a Dynamic field shows).
     */
    private static function linkedValue(int $entryId, int $fieldId): string {
        global $wpdb;
        if ($entryId <= 0 || $fieldId <= 0) {
            return '';
        }
        $value = maybe_unserialize($wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}frm_item_metas WHERE item_id = %d AND field_id = %d LIMIT 1",
            $entryId,
            $fieldId
        )));
        return is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
    }
}
