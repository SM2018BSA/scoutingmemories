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
     */
    public static function render(array $field, $value, string $error = ''): string {
        $type = $field['type'];

        if ($type === 'hidden' || $type === 'user_id') {
            return self::input($field, $value, $error);
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

        $replacements = [
            '[id]' => (string) $field['id'],
            '[key]' => esc_attr($field['key']),
            '[field_name]' => esc_html($field['name']),
            '[required_label]' => $required ? esc_html($indicator) : '',
            '[required_class]' => $required ? ' frm_required_field' : '',
            '[error_class]' => $error !== '' ? ' frm_blank_field' : '',
            '[label_position]' => $labelPosition,
            '[collapse_class]' => '',
            '[collapse_this]' => '',
            '[entry_key]' => '',
            '[help]' => '',
            '[description]' => wp_kses_post($field['description']),
            '[error]' => esc_html($error),
            '[input]' => self::input($field, $value, $error),
        ];

        $html = self::conditionalBlocks($template, [
            'description' => $field['description'] !== '',
            'error' => $error !== '',
            'help' => false,
        ]);
        $html = strtr($html, $replacements);

        // Formidable adds its layout classes (frm_first, frm_full, ...) and the label position
        // to the container; do the same so layouts and CSS hooks match
        $extra = trim(sprintf('frm_%s_container %s', $labelPosition, (string) ($opts['classes'] ?? '')));
        $html = preg_replace('/class="frm_form_field /', 'class="frm_form_field ' . esc_attr($extra) . ' ', $html, 1);

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
    public static function input(array $field, $value, string $error = ''): string {
        $opts = $field['field_options'];
        $id = (int) $field['id'];
        $name = "item_meta[{$id}]";
        $domId = 'field_' . $field['key'];
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
                // A Dynamic field is shown as its data_type: dropdown, checkboxes or radio buttons
                $dataType = (string) ($opts['data_type'] ?? 'select');
                if ($dataType === 'checkbox' || $dataType === 'radio') {
                    return self::choices($field, $value, $invalidClass);
                }
                return self::select($field, $value, $attrs, $invalidClass);

            case 'select':
                return self::select($field, $value, $attrs, $invalidClass);

            case 'checkbox':
            case 'radio':
                return self::choices($field, $value, $invalidClass);

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
    public static function choiceList(array $field): array {
        if ($field['type'] === 'data') {
            $list = [];
            foreach (DynamicOptions::forField($field) as $id => $label) {
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
    private static function select(array $field, $value, string $attrs, string $invalidClass): string {
        $multiple = !empty($field['field_options']['multiple']);
        $selected = array_map('strval', (array) $value);
        if ($multiple) {
            $attrs = str_replace(sprintf('name="item_meta[%d]"', $field['id']), sprintf('name="item_meta[%d][]"', $field['id']), $attrs) . ' multiple';
        }

        $html = sprintf('<select%s class="%s">', $attrs, esc_attr(ThemeClasses::select() . $invalidClass));
        if (!$multiple) {
            $html .= '<option value="">' . esc_html((string) ($field['field_options']['placeholder'] ?? '')) . '</option>';
        }
        foreach (self::choiceList($field) as $optValue => $label) {
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
    private static function choices(array $field, $value, string $invalidClass): string {
        $type = $field['type'] === 'data' ? (string) $field['field_options']['data_type'] : $field['type'];
        $id = (int) $field['id'];
        $name = $type === 'checkbox' ? "item_meta[{$id}][]" : "item_meta[{$id}]";
        $selected = array_map('strval', (array) $value);
        $inline = !empty($field['field_options']['align']) && $field['field_options']['align'] === 'inline';

        $html = '<div class="frm_opt_container" role="' . ($type === 'radio' ? 'radiogroup' : 'group') . '"'
            . ' aria-labelledby="' . esc_attr('field_' . $field['key'] . '_label') . '">';
        $i = 0;
        foreach (self::choiceList($field) as $optValue => $label) {
            $optId = 'field_' . $field['key'] . '-' . $i++;
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
     * @param mixed $value
     */
    private static function scalar($value): string {
        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }
        return (string) $value;
    }
}
