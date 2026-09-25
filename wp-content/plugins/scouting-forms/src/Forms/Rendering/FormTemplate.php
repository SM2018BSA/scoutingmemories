<?php

namespace ScoutingMemories\Forms\Forms\Rendering;

use ScoutingMemories\Forms\Forms\Submission\SpamGuard;
use ScoutingMemories\Forms\Ui\ThemeClasses;

/**
 * FormTemplate
 *
 * Assembles a whole form the way Formidable does: the form's before_html (title/description),
 * each field, and the form's submit_html, using the templates stored in the form's options.
 * Messages (success, form-level errors) are shown above the form.
 */
class FormTemplate {

    private const DEFAULT_BEFORE = '[if form_name]<h3 class="frm_form_title">[form_name]</h3>[/if form_name][if form_description]<div class="frm_description">[form_description]</div>[/if form_description]';
    private const DEFAULT_SUBMIT = '<div class="frm_submit"><button class="frm_button_submit" type="submit">[button_label]</button></div>';

    /**
     * @param array<string, mixed> $form From FormRepository::find()
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $state values, errors, message, show_form, form_error
     * @param array<string, bool> $display title, description
     */
    public static function render(array $form, array $fields, array $state, array $display): string {
        wp_enqueue_style('sm-forms-front');
        $opts = $form['options'];

        $html = sprintf('<div class="%s frm_forms" id="frm_form_%d_container">', ThemeClasses::SCOPE, $form['id']);

        if (!empty($state['message'])) {
            $html .= '<div class="frm_message ' . esc_attr(ThemeClasses::alert('success')) . '" role="status">' . $state['message'] . '</div>';
        }
        if (isset($state['show_form']) && !$state['show_form']) {
            return $html . '</div>';
        }

        if (!empty($state['form_error'])) {
            $html .= '<div class="frm_error_style ' . esc_attr(ThemeClasses::alert('danger')) . '" role="alert">' . esc_html($state['form_error']) . '</div>';
        } elseif (!empty($state['errors'])) {
            $invalid = !empty($opts['invalid_msg']) ? (string) $opts['invalid_msg'] : __('There was a problem with your submission. Errors are marked below.', 'scouting-forms');
            $html .= '<div class="frm_error_style ' . esc_attr(ThemeClasses::alert('danger')) . '" role="alert">' . wp_kses_post($invalid) . '</div>';
        }

        $html .= sprintf(
            '<form enctype="multipart/form-data" method="post" class="frm-show-form sm-form%s" id="form_%s" novalidate>',
            !empty($opts['form_class']) ? ' ' . esc_attr($opts['form_class']) : '',
            esc_attr($form['key'])
        );
        $html .= '<div class="frm_form_fields"><fieldset>';
        $html .= self::before($form, $display);
        $html .= '<div class="frm_fields_container">';

        foreach ($fields as $field) {
            $id = (int) $field['id'];
            $value = array_key_exists($id, $state['values'] ?? []) ? $state['values'][$id] : DefaultValues::resolve($field);
            $html .= FieldRenderer::render($field, $value, (string) ($state['errors'][$id] ?? ''));
        }

        $html .= wp_nonce_field('sm_submit_form_' . $form['id'], '_sm_form_nonce', true, false);
        $html .= sprintf('<input type="hidden" name="sm_form_id" value="%d" />', $form['id']);
        $html .= SpamGuard::fields((int) $form['id']);
        $html .= self::submit($form, $fields);
        $html .= '</div></fieldset></div></form>';

        if (!empty($opts['after_html'])) {
            $html .= wp_kses_post(do_shortcode((string) $opts['after_html']));
        }

        return $html . '</div>';
    }

    /**
     * @param array<string, bool> $display
     */
    private static function before(array $form, array $display): string {
        $template = !empty($form['options']['before_html']) ? (string) $form['options']['before_html'] : self::DEFAULT_BEFORE;
        $showName = !empty($display['title']) && $form['name'] !== '';
        $showDesc = !empty($display['description']) && trim($form['description']) !== '';

        foreach (['form_name' => $showName, 'form_description' => $showDesc] as $name => $keep) {
            $template = preg_replace('/\[if ' . $name . '\](.*?)\[\/if ' . $name . '\]/s', $keep ? '$1' : '', $template);
        }

        // [form_name] outside the [if form_name] block (the screen-reader legend) always shows
        return strtr($template, [
            '[form_name]' => esc_html($form['name']),
            '[form_description]' => $showDesc ? wp_kses_post(do_shortcode($form['description'])) : '',
            '[form_key]' => esc_attr($form['key']),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    private static function submit(array $form, array $fields): string {
        $opts = $form['options'];
        $label = !empty($opts['submit_value']) ? (string) $opts['submit_value'] : '';
        foreach ($fields as $field) {
            if ($field['type'] === 'submit' && $field['name'] !== '') {
                $label = $field['name'];
            }
        }
        if ($label === '') {
            $label = __('Submit', 'scouting-forms');
        }

        $template = !empty($opts['submit_html']) ? (string) $opts['submit_html'] : self::DEFAULT_SUBMIT;
        // Back buttons and save-draft links belong to multi-page and draft forms (later phases)
        $template = preg_replace('/\[if (back_button|save_draft)\].*?\[\/if \1\]/s', '', $template);
        $template = strtr($template, [
            '[button_label]' => esc_html($label),
            '[button_action]' => '',
            '[back_hook]' => '',
            '[back_label]' => '',
            '[draft_hook]' => '',
            '[draft_label]' => '',
            '[form_key]' => esc_attr($form['key']),
        ]);
        // Formidable's button class plus the site's Bootstrap button
        $template = preg_replace('/class="frm_button_submit/', 'class="frm_button_submit ' . esc_attr(ThemeClasses::button('scout')), $template, 1);

        return preg_replace('/\[(?:if [a-z_]+|\/if [a-z_]+)\]/', '', $template);
    }
}
