<?php
// Stand-in for Formidable Pro's FrmProDisplaysController, loaded only when Formidable is not active.

use ScoutingMemories\Forms\Compat\Api;

if (!defined('ABSPATH')) {
    exit;
}

class FrmProDisplaysController {

    public static function get_shortcode($atts) {
        return Api::view(is_array($atts) ? $atts : []);
    }
}
