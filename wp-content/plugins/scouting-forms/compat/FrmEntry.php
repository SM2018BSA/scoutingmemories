<?php
// Stand-in for Formidable's FrmEntry, loaded only when Formidable is not active.

use ScoutingMemories\Forms\Compat\Data;

if (!defined('ABSPATH')) {
    exit;
}

class FrmEntry {

    public static function getAll($where, $order_by = '', $limit = '', $meta = false, $inc_form = true) {
        return Data::getAll($where, (string) $order_by, (string) $limit, (bool) $meta, (bool) $inc_form);
    }

    public static function getOne($id, $meta = false) {
        return Data::getOne($id, (bool) $meta);
    }

    public static function get_meta($entry) {
        return Data::getMeta($entry);
    }

    public static function exists($id): bool {
        return (bool) Data::getOne($id);
    }
}
