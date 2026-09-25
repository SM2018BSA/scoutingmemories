<?php

namespace ScoutingMemories\Forms\Ajax;

use ScoutingMemories\Forms\Forms\Logic\FieldLogic;
use ScoutingMemories\Forms\Forms\Rendering\DynamicOptions;
use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Support\FormAccess;

/**
 * DynamicFields
 *
 * admin-ajax.php?action=sm_forms_dynamic&field=70&parent=288&value[]=339
 *
 * When a Dynamic field's parent changes, the browser asks for the new choices (or, for a "just
 * show it" field, the value to show). Like Formidable's own endpoint this is public and read-only,
 * with no nonce: pages are cached on WP Engine, so a nonce in the page would go stale for
 * visitors. It only answers for a Dynamic field of a published form, for a parent that field
 * really depends on, for numeric parent values (at most 50), and only if the visitor may see
 * the field.
 */
class DynamicFields {

    private const MAX_VALUES = 50;

    public static function registerHooks(): void {
        add_action('wp_ajax_sm_forms_dynamic', [__CLASS__, 'handle']);
        add_action('wp_ajax_nopriv_sm_forms_dynamic', [__CLASS__, 'handle']);
    }

    public static function handle(): void {
        $fieldId = absint($_REQUEST['field'] ?? 0);
        $parentId = absint($_REQUEST['parent'] ?? 0);
        $raw = isset($_REQUEST['value']) ? (array) wp_unslash($_REQUEST['value']) : [];
        $values = array_slice(array_values(array_filter(array_map('absint', $raw))), 0, self::MAX_VALUES);

        $field = FormRepository::field($fieldId);
        if (!$field || $field['type'] !== 'data' || !in_array($parentId, DynamicOptions::dynamicParents($field), true)) {
            wp_send_json_error(['message' => 'Unknown field'], 400);
        }
        $form = FormRepository::find((int) $field['form_id']);
        if ($form && $form['parent_form_id'] > 0) {
            $form = FormRepository::find($form['parent_form_id']);
        }
        if (!$form || $form['status'] !== 'published' || !FieldLogic::visibleToUser($field) || !FormAccess::allowed($form)) {
            wp_send_json_error(['message' => 'Unknown field'], 400);
        }

        nocache_headers();
        if (($field['field_options']['data_type'] ?? 'select') === 'data') {
            $text = DynamicOptions::displayValueFor($field, $values);
            wp_send_json_success(['text' => $text, 'value' => $text]);
        }

        $linked = FormRepository::field((int) ($field['field_options']['form_select'] ?? 0));
        $options = $linked ? DynamicOptions::dependent($field, $linked, $parentId, $values) : [];
        $list = [];
        foreach ($options as $id => $label) {
            $list[] = [(string) $id, $label];
        }
        wp_send_json_success(['options' => $list]);
    }
}
