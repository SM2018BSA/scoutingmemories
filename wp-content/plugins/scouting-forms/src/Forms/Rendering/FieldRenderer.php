<?php

namespace ScoutingMemories\Forms\Forms\Rendering;

use ScoutingMemories\Forms\Support\FormidableSettings;
use ScoutingMemories\Forms\Ui\ThemeClasses;

/**
 * FieldRenderer
 *
 * Renders one Formidable field the way Formidable does: the field's own `custom_html` template
 * (with [field_name], [required_label], [input], [description], [error] ... placeholders), and the
 * input itself with the site's Bootstrap classes. Names and IDs match Formidable's
 * (`item_meta[ID]`, `field_KEY`) so existing CSS/JS hooks keep working.
 */
class FieldRenderer {

    /** Field types that are not data inputs */
    public const NON_INPUT_TYPES = ['html', 'divider', 'end_divider', 'break', 'submit', 'captcha', 'summary', 'form'];

    private const DEFAULT_TEMPLATE = '<div id="frm_field_[id]_container" class="frm_form_field form-field [required_class][error_class]">'
        . '<label for="field_[key]" id="field_[key]_label" class="frm_primary_label">[field_name] <span class="frm_required">[required_label]</span></label>'
        . '[input]'
        . '[if description]<div class="frm_description" id="frm_desc_field_[key]">[description]</div>[/if description]'
        . '[if error]<div class="frm_error" id="frm_error_field_[key]">[error]</div>[/if error]'
        . '</div>';

    /**
     * @param array<string, mixed> $field From FormRepository
     * @param mixed $value Current value (posted or default)
     * @param string $error Validation message, if any
     * @param array<string, mixed> $ctx See context(); FormTemplate passes the form's values,
     *                                  repeater names/IDs, section content and logic state
     */
    public static function render(array $field, $value, string $error = '', array $ctx = []): string {
        $type = $field['type'];
        $ctx = self::context($field, $ctx);

        if ($type === 'hidden' || $type === 'user_id') {
            return self::input($field, $value, $error, $ctx);
        }
        if ($type === 'end_divider') {
            return '';
        }
        if ($type === 'submit' || $type === 'break') {
            // The submit button comes from the form's submit_html; page breaks are Phase 3
            return '';
        }

        $opts = $field['field_options'];
        $template = !empty($opts['custom_html']) ? (string) $opts['custom_html'] : self::DEFAULT_TEMPLATE;
        $labelPosition = !empty($opts['label']) ? sanitize_html_class((string) $opts['label']) : 'top';

        $required = $field['required'] && !in_array($type, self::NON_INPUT_TYPES, true);
        $indicator = array_key_exists('required_indicator', $opts) ? (string) $opts['required_indicator'] : '*';

        $collapsible = $type === 'divider' && !empty($opts['slide']);
        $replacements = [
            '[id]' => esc_attr($ctx['id']),
            '[key]' => esc_attr($ctx['key']),
            '[field_name]' => esc_html($field['name']),
            '[required_label]' => $required ? esc_html($indicator) : '',
            '[required_class]' => $required ? ' frm_required_field' : '',
            '[error_class]' => $error !== '' ? ' frm_blank_field' : '',
            '[label_position]' => $labelPosition,
            '[collapse_class]' => $type === 'divider' ? ($collapsible ? ' sm-trigger' : ' frm_section_spacing') : '',
            '[collapse_this]' => (string) ($ctx['inner'] ?? ''),
            '[entry_key]' => '',
            '[help]' => '',
            '[description]' => wp_kses_post($field['description']),
            '[error]' => esc_html($error),
            '[input]' => $type === 'divider' ? '' : self::input($field, $value, $error, $ctx),
        ];

        $html = self::conditionalBlocks($template, [
            'description' => $field['description'] !== '',
            'error' => $error !== '',
            'help' => false,
        ]);
        $html = strtr($html, $replacements);

        // Formidable adds its layout classes (frm_first, frm_full, ...) and the label position
        // to the container; do the same so layouts and CSS hooks match
        $extra = trim(sprintf('frm_%s_container %s %s', $labelPosition, (string) ($opts['classes'] ?? ''), (string) ($ctx['class'] ?? '')));
        $html = preg_replace('/class="frm_form_field /', 'class="frm_form_field ' . esc_attr($extra) . ' ', $html, 1);

        // The browser finds fields by data-sm-field; fields hidden by logic start hidden
        $marker = ' data-sm-field="' . (int) $field['id'] . '"' . (!empty($ctx['hidden']) ? ' hidden' : '');
        $html = preg_replace('/<div id="frm_field_' . preg_quote(esc_attr($ctx['id']), '/') . '_container"/', '$0' . $marker, $html, 1);

        if ($collapsible) {
            // A collapsible section heading opens and closes its fields (forms-front.js)
            $icon = '<svg viewBox="0 0 20 20" width="1em" height="1em" aria-hidden="true" class="frmsvg frm-svg-icon sm-toggle-icon"><path d="M5 6l5 5 5-5 2 1-7 7-7-7 2-1z"></path></svg>';
            $html = preg_replace('/<h3 class="([^"]*sm-trigger[^"]*)">(.*?)<\/h3>/s', '<h3 class="$1" tabindex="0" role="button" aria-expanded="false">$2 ' . $icon . '</h3>', $html, 1);
        }

        // Formidable template placeholders we don't use render as nothing rather than raw text.
        // Only known placeholder names are removed, so bracketed text in labels stays intact.
        $unused = '(?:collapse_icon|input_id|field_label|shortcodes|label_id|default_value|placeholder|clear_on_focus|hide_opt)';
        $html = preg_replace('/\[(?:if [a-z_]+|\/if [a-z_]+)\]/', '', $html);
        return preg_replace('/\[' . $unused . '\]/', '', $html);
    }

    /**
     * Keep or drop [if name]...[/if name] blocks.
     *
     * @param array<string, bool> $conditions
     */
    private static function conditionalBlocks(string $template, array $conditions): string {
        foreach ($conditions as $name => $keep) {
            $pattern = '/\[if ' . preg_quote($name, '/') . '\](.*?)\[\/if ' . preg_quote($name, '/') . '\]/s';
            $template = preg_replace($pattern, $keep ? '$1' : '', $template);
        }
        return $template;
    }

    /**
     * The input element(s) for a field.
     *
     * @param mixed $value
     */
    public static function input(array $field, $value, string $error = '', array $ctx = []): string {
        $ctx = self::context($field, $ctx);
        $opts = $field['field_options'];
        $id = (int) $field['id'];
        $name = (string) $ctx['name'];
        $domId = 'field_' . $ctx['key'];
        $type = $field['type'];
        $required = $field['required'];
        $invalidClass = $error !== '' ? ' is-invalid' : '';

        $attrs = sprintf(' id="%s" name="%s"', esc_attr($domId), esc_attr($name));
        if ($required) {
            $attrs .= ' aria-required="true"';
        }
        if ($error !== '') {
            $attrs .= sprintf(' aria-invalid="true" aria-describedby="%s"', esc_attr('frm_error_' . $domId));
        } elseif ($field['description'] !== '') {
            $attrs .= sprintf(' aria-describedby="%s"', esc_attr('frm_desc_' . $domId));
        }
        if (!empty($opts['placeholder'])) {
            $attrs .= sprintf(' placeholder="%s"', esc_attr($opts['placeholder']));
        }
        if (!empty($opts['read_only'])) {
            $attrs .= ' readonly';
        }
        if (!empty($opts['autocom'])) {
            // Searchable dropdown (forms-front.js), like Formidable's autocomplete setting
            $attrs .= ' data-sm-autocomplete="1"';
        }

        switch ($type) {
            case 'hidden':
                return sprintf('<input type="hidden"%s value="%s" />', $attrs, esc_attr(self::scalar($value)));

            case 'user_id':
                return sprintf('<input type="hidden"%s value="%d" />', $attrs, get_current_user_id());

            case 'textarea':
            case 'rte':
                // For textareas Formidable's "max" setting is the number of rows
                $rows = isset($opts['max']) && is_numeric($opts['max']) && (int) $opts['max'] > 0 ? (int) $opts['max'] : 5;
                return sprintf(
                    '<textarea%s rows="%d" class="%s">%s</textarea>',
                    $attrs,
                    $rows,
                    esc_attr(ThemeClasses::textarea() . $invalidClass),
                    esc_textarea(self::scalar($value))
                );

            case 'data':
                // A Dynamic field is shown as its data_type: dropdown, checkboxes, radio buttons, or
                // "just show it" (the linked value as text, sent in a hidden input)
                $dataType = (string) ($opts['data_type'] ?? 'select');
                if ($dataType === 'data') {
                    $shown = isset($ctx['values']) ? DynamicOptions::displayValue($field, (array) $ctx['values']) : self::scalar($value);
                    return sprintf(
                        '<p class="frm_show_it">%s</p><input type="hidden" id="%s" name="%s" value="%s" />',
                        esc_html($shown),
                        esc_attr($domId),
                        esc_attr($name),
                        esc_attr($shown)
                    );
                }
                if ($dataType === 'checkbox' || $dataType === 'radio') {
                    return self::choices($field, $value, $invalidClass, $ctx);
                }
                return self::select($field, $value, $attrs, $invalidClass, $ctx);

            case 'select':
                return self::select($field, $value, $attrs, $invalidClass, $ctx);

            case 'checkbox':
            case 'radio':
                return self::choices($field, $value, $invalidClass, $ctx);

            case 'toggle':
                $checkedValue = (string) ($opts['toggle_on'] ?? '1');
                return sprintf(
                    '<div class="form-check form-switch"><input type="hidden" name="%1$s" value="" /><input type="checkbox" role="switch"%2$s value="%3$s" class="%4$s"%5$s /></div>',
                    esc_attr($name),
                    $attrs,
                    esc_attr($checkedValue),
                    esc_attr(ThemeClasses::checkbox() . $invalidClass),
                    checked(self::scalar($value), $checkedValue, false)
                );

            case 'file':
                return sprintf(
                    '<input type="file" id="%s" name="file_%d" class="%s"%s />',
                    esc_attr($domId),
                    $id,
                    esc_attr(ThemeClasses::input() . $invalidClass),
                    $required ? ' aria-required="true"' : ''
                );

            case 'captcha':
                return self::captcha($field);

            case 'html':
                $content = $field['description'] !== '' ? $field['description'] : self::scalar($field['default_value']);
                return '<div class="frm_html_content">' . wp_kses_post(do_shortcode($content)) . '</div>';

            case 'divider':
                return '';

            default:
                $inputType = [
                    'email' => 'email',
                    'url' => 'url',
                    'number' => 'number',
                    'range' => 'range',
                    'phone' => 'tel',
                    'date' => 'date',
                    'time' => 'time',
                    'password' => 'password',
                ][$type] ?? 'text';

                if ($inputType === 'text' && isset($opts['max']) && is_numeric($opts['max']) && (int) $opts['max'] > 0) {
                    $attrs .= sprintf(' maxlength="%d"', (int) $opts['max']);
                }
                if ($inputType === 'email') {
                    $attrs .= ' autocomplete="email"';
                }

                return sprintf(
                    '<input type="%s"%s value="%s" class="%s" />',
                    $inputType,
                    $attrs,
                    $inputType === 'password' ? '' : esc_attr(self::scalar($value)),
                    esc_attr(ThemeClasses::input() . $invalidClass)
                );
        }
    }

    /**
     * Choices of a select/radio/checkbox field as value => label.
     *
     * @return array<string, string>
     */
    public static function choiceList(array $field, array $values = []): array {
        if ($field['type'] === 'data') {
            $list = [];
            foreach (DynamicOptions::forField($field, $values) as $id => $label) {
                $list[(string) $id] = $label;
            }
            return $list;
        }

        $list = [];
        $separateValues = !empty($field['field_options']['separate_value']);
        $choices = is_array($field['options']) ? $field['options'] : [];

        foreach ($choices as $key => $choice) {
            if (is_array($choice)) {
                $label = (string) ($choice['label'] ?? ($choice['value'] ?? ''));
                $value = $separateValues ? (string) ($choice['value'] ?? $label) : $label;
            } else {
                $label = (string) $choice;
                $value = is_int($key) ? $label : (string) $key;
            }
            if ($label === '' && $value === '') {
                continue;
            }
            $list[$value] = $label;
        }
        return $list;
    }

    /**
     * @param mixed $value
     */
    private static function select(array $field, $value, string $attrs, string $invalidClass, array $ctx = []): string {
        $ctx = self::context($field, $ctx);
        $multiple = !empty($field['field_options']['multiple']);
        $selected = array_map('strval', (array) $value);
        if ($multiple) {
            $attrs = str_replace('name="' . esc_attr($ctx['name']) . '"', 'name="' . esc_attr($ctx['name'] . '[]') . '"', $attrs) . ' multiple';
        }

        $html = sprintf('<select%s class="%s">', $attrs, esc_attr(ThemeClasses::select() . $invalidClass));
        if (!$multiple) {
            $html .= '<option value="">' . esc_html((string) ($field['field_options']['placeholder'] ?? '')) . '</option>';
        }
        foreach (self::choiceList($field, (array) ($ctx['values'] ?? [])) as $optValue => $label) {
            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($optValue),
                in_array((string) $optValue, $selected, true) ? ' selected' : '',
                esc_html($label)
            );
        }
        return $html . '</select>';
    }

    /**
     * @param mixed $value
     */
    private static function choices(array $field, $value, string $invalidClass, array $ctx = []): string {
        $ctx = self::context($field, $ctx);
        $type = $field['type'] === 'data' ? (string) $field['field_options']['data_type'] : $field['type'];
        $name = $type === 'checkbox' ? $ctx['name'] . '[]' : $ctx['name'];
        $selected = array_map('strval', (array) $value);
        $inline = !empty($field['field_options']['align']) && $field['field_options']['align'] === 'inline';

        $html = '<div class="frm_opt_container" role="' . ($type === 'radio' ? 'radiogroup' : 'group') . '"'
            . ' aria-labelledby="' . esc_attr('field_' . $ctx['key'] . '_label') . '">';
        $i = 0;
        foreach (self::choiceList($field, (array) ($ctx['values'] ?? [])) as $optValue => $label) {
            $optId = 'field_' . $ctx['key'] . '-' . $i++;
            $html .= sprintf(
                '<div class="form-check%s frm_%s"><input type="%s" id="%s" name="%s" value="%s" class="%s"%s /><label class="form-check-label" for="%s">%s</label></div>',
                $inline ? ' form-check-inline' : '',
                $type,
                $type,
                esc_attr($optId),
                esc_attr($name),
                esc_attr($optValue),
                esc_attr(ThemeClasses::checkbox() . $invalidClass),
                in_array((string) $optValue, $selected, true) ? ' checked' : '',
                esc_attr($optId),
                esc_html($label)
            );
        }
        return $html . '</div>';
    }

    private static function captcha(array $field): string {
        $keys = FormidableSettings::recaptcha();
        if ($keys['site_key'] === '') {
            return '';
        }
        wp_enqueue_script('sm-recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, true);
        $opts = $field['field_options'];
        return sprintf(
            '<div id="field_%s" class="g-recaptcha" data-sitekey="%s" data-size="%s" data-theme="%s"></div>',
            esc_attr($field['key']),
            esc_attr($keys['site_key']),
            esc_attr((string) ($opts['captcha_size'] ?? 'normal')),
            esc_attr((string) ($opts['captcha_theme'] ?? 'light'))
        );
    }

    /**
     * Where a field is being drawn: its input name, the key used in HTML IDs, the container ID,
     * and optionally the form's values (dependent choices), extra classes, section content and
     * whether logic hides it. Defaults are the plain top-level field (item_meta[ID], field_KEY).
     *
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    private static function context(array $field, array $ctx): array {
        return $ctx + [
            'name' => 'item_meta[' . (int) $field['id'] . ']',
            'key' => (string) $field['key'],
            'id' => (string) $field['id'],
        ];
    }

    /**
     * @param mixed $value
     */
    private static function scalar($value): string {
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }
        return (string) $value;
    }
}
