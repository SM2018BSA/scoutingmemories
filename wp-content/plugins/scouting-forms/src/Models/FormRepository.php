<?php

namespace ScoutingMemories\Forms\Models;

/**
 * FormRepository
 *
 * Loads Formidable forms and fields from Formidable's own tables into plain arrays, with the
 * serialized columns decoded and Formidable's stored slashes removed from templates.
 */
class FormRepository {

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id = 0, string $key = ''): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'frm_forms';

        if ($id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        } elseif ($key !== '') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE form_key = %s", $key));
        } else {
            return null;
        }
        if (!$row) {
            return null;
        }

        $options = maybe_unserialize($row->options);
        return [
            'id' => (int) $row->id,
            'key' => (string) $row->form_key,
            'name' => (string) $row->name,
            'description' => (string) $row->description,
            'status' => (string) $row->status,
            'editable' => (bool) $row->editable,
            'parent_form_id' => (int) $row->parent_form_id,
            'options' => is_array($options) ? self::unslashDeep($options) : [],
        ];
    }

    /**
     * Fields of a form in display order.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fields(int $formId): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}frm_fields WHERE form_id = %d ORDER BY field_order ASC, id ASC",
            $formId
        ));

        return array_map([__CLASS__, 'normalizeField'], $rows ?: []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function field(int $fieldId): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}frm_fields WHERE id = %d", $fieldId));
        return $row ? self::normalizeField($row) : null;
    }

    /**
     * @param object $row
     * @return array<string, mixed>
     */
    private static function normalizeField($row): array {
        $fieldOptions = maybe_unserialize($row->field_options);
        $choices = maybe_unserialize($row->options);
        $default = maybe_unserialize($row->default_value);

        return [
            'id' => (int) $row->id,
            'key' => (string) $row->field_key,
            'form_id' => (int) $row->form_id,
            'name' => (string) $row->name,
            'description' => (string) $row->description,
            'type' => (string) $row->type,
            'required' => !empty($row->required),
            'default_value' => $default,
            'options' => $choices,
            'field_options' => is_array($fieldOptions) ? self::unslashDeep($fieldOptions) : [],
        ];
    }

    /**
     * Formidable stores templates with escaped quotes (\" ); strip them like Formidable does.
     *
     * @param mixed $value
     * @return mixed
     */
    private static function unslashDeep($value) {
        if (is_array($value)) {
            return array_map([__CLASS__, 'unslashDeep'], $value);
        }
        return is_string($value) ? stripslashes($value) : $value;
    }
}
