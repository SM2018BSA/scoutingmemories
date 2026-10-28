<?php
// Stand-in for Formidable Pro's FrmProEntriesController, loaded only when Formidable is not active.

use ScoutingMemories\Forms\Compat\Api;

if (!defined('ABSPATH')) {
    exit;
}

class FrmProEntriesController {

    public static function show_entry_shortcode($atts) {
        return Api::showEntry(is_array($atts) ? $atts : []);
    }

    public static function get_field_value_shortcode($atts) {
        return Api::fieldValue(is_array($atts) ? $atts : []);
    }
}
