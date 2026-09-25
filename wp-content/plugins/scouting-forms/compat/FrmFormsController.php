<?php
// Stand-in for Formidable's FrmFormsController, loaded only when Formidable is not active.

use ScoutingMemories\Forms\Compat\Api;

if (!defined('ABSPATH')) {
    exit;
}

class FrmFormsController {

    public static function show_form($id = '', $key = '', $title = false, $description = false, $atts = []) {
        return Api::form(array_merge((array) $atts, [
            'id' => $id,
            'key' => $key,
            'title' => $title ? '1' : '0',
            'description' => $description ? '1' : '0',
        ]));
    }

    public static function get_form_shortcode($atts) {
        return Api::form(is_array($atts) ? $atts : []);
    }
}
