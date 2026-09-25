<?php
// Stand-in for Formidable's FrmEntryMeta, loaded only when Formidable is not active.

use ScoutingMemories\Forms\Compat\Data;

if (!defined('ABSPATH')) {
    exit;
}

class FrmEntryMeta {

    public static function getEntryIds($where = [], $order_by = '', $limit = '', $unique = true, $args = []) {
        return Data::getEntryIds($where, (string) $order_by, (string) $limit, (bool) $unique, (array) $args);
    }

    public static function add_entry_meta($entry_id, $field_id, $meta_key = null, $meta_value = '', $field = null) {
        return Data::addMeta((int) $entry_id, (int) $field_id, $meta_value);
    }

    public static function update_entry_meta($entry_id, $field_id, $meta_key = null, $meta_value = '', $field = null) {
        return Data::updateMeta((int) $entry_id, (int) $field_id, $meta_value);
    }

    public static function get_meta_value($entry, $field_id) {
        $entry = is_object($entry) && isset($entry->metas) ? $entry : Data::getOne(is_object($entry) ? $entry->id : $entry, true);
        return $entry->metas[$field_id] ?? '';
    }
}
