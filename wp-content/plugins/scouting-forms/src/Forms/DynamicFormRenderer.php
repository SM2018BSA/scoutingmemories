<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Models\EntryRepository;
use ScoutingMemories\Forms\Support\TestData;
use ScoutingMemories\Forms\Ui\ThemeClasses;

/**
 * DynamicFormRenderer
 *
 * Dynamically renders and processes any Formidable/Scouting form from database tables.
 * Fully encapsulated with Tailwind CSS v4 via ThemeClasses.
 * Compliant with WP Engine hosting (media_handle_upload, object cache invalidation, no sessions).
 */
class DynamicFormRenderer extends FormHandler {

    public static function registerHooks(): void {
        add_shortcode('sm_form', [__CLASS__, 'renderShortcode']);

        // Fallback for existing [formidable] content, only when Formidable itself is not loaded.
        // Checked late on init so plugin load order can't let us shadow Formidable's shortcode.
        add_action('init', function () {
            if (!class_exists('FrmFormsController') && !shortcode_exists('formidable')) {
                add_shortcode('formidable', [__CLASS__, 'renderFormidableFallback']);
            }
        }, 999);
    }

    /**
     * Fallback for [formidable id=X] or [formidable key=X]
     */
    public static function renderFormidableFallback(array $atts = []): string {
        return self::renderShortcode($atts);
    }

    /**
     * [sm_form id="X" title="true" description="true"]
     */
    public static function renderShortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'id'          => 0,
            'key'         => '',
            'title'       => 'false',
            'description' => 'false',
            'minimize'    => 'false',
        ], $atts, 'sm_form');

        global $wpdb;
        $formId = (int) $atts['id'];
        $formKey = sanitize_title($atts['key']);

        $form = null;
        if ($formId > 0) {
            $form = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}frm_forms WHERE id = %d",
                $formId
            ));
        } elseif (!empty($formKey)) {
            $form = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}frm_forms WHERE form_key = %s",
                $formKey
            ));
        }

        if (!$form) {
            return '<!-- Scouting Forms: form not found (ID: ' . (int) $formId . ', key: ' . esc_html($formKey) . ') -->';
        }

        $formId = (int) $form->id;

        // Process POST submission if submitted for this form
        $submitNotice = '';
        if (
            (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') &&
            isset($_POST['sm_form_id']) &&
            (int)$_POST['sm_form_id'] === $formId
        ) {
            $submitNotice = self::handleFormSubmission($form);
        }

        // Fetch fields
        $fields = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}frm_fields WHERE form_id = %d ORDER BY field_order ASC, id ASC",
            $formId
        ));

        return self::renderFormHtml($form, $fields, $atts, $submitNotice);
    }

    /**
     * Render the complete form HTML
     */
    private static function renderFormHtml(object $form, array $fields, array $atts, string $submitNotice = ''): string {
        $showTitle = in_array(strtolower($atts['title']), ['1', 'true', 'yes'], true);
        $showDesc = in_array(strtolower($atts['description']), ['1', 'true', 'yes'], true);

        wp_enqueue_style('sm-forms-front');
        $html = '<div class="' . ThemeClasses::SCOPE . ' sm-form-container ' . ThemeClasses::card() . '">';

        if ($showTitle && !empty($form->name)) {
            $html .= '<div class="' . ThemeClasses::cardHeader() . '">';
            $html .= '<h3 class="' . ThemeClasses::cardTitle() . '">' . esc_html($form->name) . '</h3>';
            $html .= '</div>';
        }

        if ($showDesc && !empty($form->description)) {
            $html .= '<p class="text-muted mb-4">' . esc_html($form->description) . '</p>';
        }

        if (!empty($submitNotice)) {
            $html .= $submitNotice;
        }

        $html .= '<form method="POST" action="" enctype="multipart/form-data" class="sm-form">';
        $html .= wp_nonce_field('sm_submit_form_' . $form->id, '_sm_form_nonce', true, false);
        $html .= '<input type="hidden" name="sm_form_id" value="' . esc_attr($form->id) . '" />';

        foreach ($fields as $field) {
            $html .= self::renderFieldHtml($field);
        }

        // Check if there is already a submit button in fields, otherwise add default submit
        $hasSubmit = false;
        foreach ($fields as $f) {
            if ($f->type === 'submit') {
                $hasSubmit = true;
                break;
            }
        }

        if (!$hasSubmit) {
            $html .= '<div class="' . ThemeClasses::cardFooter() . '">';
            $html .= '<button type="submit" class="' . ThemeClasses::button('scout', 'md') . '">';
            $html .= esc_html__('Submit', 'scouting-forms');
            $html .= '</button>';
            $html .= '</div>';
        }

        $html .= '</form>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render an individual field based on type
     */
    private static function renderFieldHtml(object $field): string {
        $options = maybe_unserialize($field->field_options);
        if (!is_array($options)) {
            $options = [];
        }

        $fieldId = (int) $field->id;
        $fieldName = "item_meta[{$fieldId}]";
        $isRequired = !empty($field->required);
        $defaultValue = $field->default_value ?? '';

        // Hidden or User ID
        if ($field->type === 'hidden') {
            return '<input type="hidden" name="' . esc_attr($fieldName) . '" value="' . esc_attr($defaultValue) . '" />';
        }

        if ($field->type === 'user_id') {
            $userId = get_current_user_id();
            return '<input type="hidden" name="' . esc_attr($fieldName) . '" value="' . esc_attr($userId) . '" />';
        }

        // Section Dividers
        if ($field->type === 'divider') {
            return '<div class="pt-3 pb-2 border-bottom mb-3"><h4 class="h6 mb-0">' . esc_html($field->name) . '</h4></div>';
        }

        if ($field->type === 'end_divider' || $field->type === 'break') {
            return '<div class="my-3"></div>';
        }

        if ($field->type === 'html') {
            return '<div class="small text-muted mb-3">' . wp_kses_post($field->description ?: $defaultValue) . '</div>';
        }

        if ($field->type === 'submit') {
            $btnText = !empty($field->name) ? $field->name : __('Submit', 'scouting-forms');
            return '<div class="' . ThemeClasses::cardFooter() . '"><button type="submit" class="' . ThemeClasses::button('scout', 'md') . '">' . esc_html($btnText) . '</button></div>';
        }

        // Standard Field Wrapper
        $html = '<div class="mb-3">';
        $html .= '<label class="' . ThemeClasses::label($isRequired) . '" for="field_' . esc_attr($fieldId) . '">';
        $html .= esc_html($field->name);
        $html .= '</label>';

        switch ($field->type) {
            case 'textarea':
            case 'rte':
                $html .= '<textarea id="field_' . esc_attr($fieldId) . '" name="' . esc_attr($fieldName) . '" rows="4" class="' . ThemeClasses::textarea() . '"' . ($isRequired ? ' required' : '') . '>' . esc_textarea($defaultValue) . '</textarea>';
                break;

            case 'select':
            case 'data':
                $choices = maybe_unserialize($field->options);
                $html .= '<select id="field_' . esc_attr($fieldId) . '" name="' . esc_attr($fieldName) . '" class="' . ThemeClasses::select() . '"' . ($isRequired ? ' required' : '') . '>';
                $html .= '<option value="">' . esc_html__('— Select —', 'scouting-forms') . '</option>';
                if (is_array($choices)) {
                    foreach ($choices as $choiceVal => $choiceLabel) {
                        if (is_array($choiceLabel)) {
                            $cVal = (string)($choiceLabel['value'] ?? ($choiceLabel['label'] ?? ''));
                            $cText = (string)($choiceLabel['label'] ?? $cVal);
                        } else {
                            $cVal = is_int($choiceVal) ? (string)$choiceLabel : (string)$choiceVal;
                            $cText = (string)$choiceLabel;
                        }
                        $selected = ($cVal === (string)$defaultValue) ? ' selected' : '';
                        $html .= '<option value="' . esc_attr($cVal) . '"' . $selected . '>' . esc_html($cText) . '</option>';
                    }
                }
                $html .= '</select>';
                break;

            case 'checkbox':
                $choices = maybe_unserialize($field->options);
                $html .= '<div class="mt-1">';
                if (is_array($choices)) {
                    foreach ($choices as $choiceVal => $choiceLabel) {
                        if (is_array($choiceLabel)) {
                            $cVal = (string)($choiceLabel['value'] ?? ($choiceLabel['label'] ?? ''));
                            $cText = (string)($choiceLabel['label'] ?? $cVal);
                        } else {
                            $cVal = is_int($choiceVal) ? (string)$choiceLabel : (string)$choiceVal;
                            $cText = (string)$choiceLabel;
                        }
                        $html .= '<label class="form-check form-check-inline">';
                        $html .= '<input type="checkbox" name="' . esc_attr($fieldName) . '[]" value="' . esc_attr($cVal) . '" class="' . ThemeClasses::checkbox() . '" />';
                        $html .= '<span class="form-check-label">' . esc_html($cText) . '</span>';
                        $html .= '</label>';
                    }
                }
                $html .= '</div>';
                break;

            case 'radio':
                $choices = maybe_unserialize($field->options);
                $html .= '<div class="mt-1">';
                if (is_array($choices)) {
                    foreach ($choices as $choiceVal => $choiceLabel) {
                        if (is_array($choiceLabel)) {
                            $cVal = (string)($choiceLabel['value'] ?? ($choiceLabel['label'] ?? ''));
                            $cText = (string)($choiceLabel['label'] ?? $cVal);
                        } else {
                            $cVal = is_int($choiceVal) ? (string)$choiceLabel : (string)$choiceVal;
                            $cText = (string)$choiceLabel;
                        }
                        $html .= '<label class="form-check form-check-inline">';
                        $html .= '<input type="radio" name="' . esc_attr($fieldName) . '" value="' . esc_attr($cVal) . '" class="' . ThemeClasses::radio() . '"' . ($isRequired ? ' required' : '') . ' />';
                        $html .= '<span class="form-check-label">' . esc_html($cText) . '</span>';
                        $html .= '</label>';
                    }
                }
                $html .= '</div>';
                break;

            case 'file':
                $html .= '<input type="file" id="field_' . esc_attr($fieldId) . '" name="file_' . esc_attr($fieldId) . '" class="' . ThemeClasses::input() . '"' . ($isRequired ? ' required' : '') . ' />';
                break;

            case 'email':
                $html .= '<input type="email" id="field_' . esc_attr($fieldId) . '" name="' . esc_attr($fieldName) . '" value="' . esc_attr($defaultValue) . '" class="' . ThemeClasses::input() . '"' . ($isRequired ? ' required' : '') . ' />';
                break;

            case 'number':
                $html .= '<input type="number" id="field_' . esc_attr($fieldId) . '" name="' . esc_attr($fieldName) . '" value="' . esc_attr($defaultValue) . '" class="' . ThemeClasses::input() . '"' . ($isRequired ? ' required' : '') . ' />';
                break;

            case 'date':
                $html .= '<input type="date" id="field_' . esc_attr($fieldId) . '" name="' . esc_attr($fieldName) . '" value="' . esc_attr($defaultValue) . '" class="' . ThemeClasses::input() . '"' . ($isRequired ? ' required' : '') . ' />';
                break;

            default:
                $html .= '<input type="text" id="field_' . esc_attr($fieldId) . '" name="' . esc_attr($fieldName) . '" value="' . esc_attr($defaultValue) . '" class="' . ThemeClasses::input() . '"' . ($isRequired ? ' required' : '') . ' />';
                break;
        }

        if (!empty($field->description)) {
            $html .= '<p class="' . ThemeClasses::helperText() . '">' . esc_html($field->description) . '</p>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Process form submission and save it as a Formidable entry (via EntryRepository)
     */
    private static function handleFormSubmission(object $form): string {
        if (!isset($_POST['_sm_form_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_sm_form_nonce'])), 'sm_submit_form_' . $form->id)) {
            return '<div class="' . ThemeClasses::alert('danger') . '">' . esc_html__('Security check failed. Please refresh and try again.', 'scouting-forms') . '</div>';
        }

        $userId = get_current_user_id();
        $itemMetas = isset($_POST['item_meta']) && is_array($_POST['item_meta']) ? $_POST['item_meta'] : [];

        // Handle file uploads using media_handle_upload() (WP Engine / WP Stateless compliant)
        if (!empty($_FILES)) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            foreach ($_FILES as $inputKey => $fileData) {
                if (strpos($inputKey, 'file_') === 0 && !empty($fileData['name'])) {
                    $targetFieldId = (int) str_replace('file_', '', $inputKey);
                    $attachId = media_handle_upload($inputKey, 0);
                    if (!is_wp_error($attachId)) {
                        TestData::markPost((int) $attachId);
                        $itemMetas[$targetFieldId] = $attachId;
                    }
                }
            }
        }

        $cleanMetas = [];
        foreach ($itemMetas as $fieldId => $fieldValue) {
            $cleanMetas[(int) $fieldId] = is_array($fieldValue)
                ? map_deep(wp_unslash($fieldValue), 'sanitize_textarea_field')
                : sanitize_textarea_field(wp_unslash((string) $fieldValue));
        }

        $itemId = EntryRepository::create((int) $form->id, $cleanMetas, [
            'key' => $form->form_key . '-' . wp_generate_password(8, false, false),
            'name' => $form->name . ' Entry',
            'user_id' => $userId,
        ]);

        if (!$itemId) {
            return '<div class="' . ThemeClasses::alert('danger') . '">' . esc_html__('Failed to save entry. Please try again.', 'scouting-forms') . '</div>';
        }

        return '<div class="' . ThemeClasses::alert('success', 'd-flex align-items-center gap-2') . '">' .
               '<i class="bi bi-check-circle-fill" aria-hidden="true"></i>' .
               esc_html__('Your entry was saved successfully!', 'scouting-forms') .
               '</div>';
    }
}
