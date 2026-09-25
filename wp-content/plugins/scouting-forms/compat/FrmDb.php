<?php
// Stand-in for Formidable's FrmDb, loaded only when Formidable is not active.
// The theme creates one (functions.php) and reads the table names.

if (!defined('ABSPATH')) {
    exit;
}

class FrmDb {

    public $fields;
    public $forms;
    public $entries;
    public $entry_metas;

    public function __construct() {
        global $wpdb;
        $this->fields = $wpdb->prefix . 'frm_fields';
        $this->forms = $wpdb->prefix . 'frm_forms';
        $this->entries = $wpdb->prefix . 'frm_items';
        $this->entry_metas = $wpdb->prefix . 'frm_item_metas';
    }
}
