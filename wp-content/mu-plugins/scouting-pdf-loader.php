<?php
/**
 * Plugin Name: Scouting PDF Embedder MU Autoloader
 * Description: Ensures Scouting PDF Embedder is active on both local development and WP Engine production without requiring database option manipulation.
 * Author: Scouting Memories
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Cleanly suppress legacy PDF Embedder plugins so they never collide with Scouting PDF Embedder
add_filter('option_active_plugins', function($plugins) {
    if (is_array($plugins)) {
        return array_values(array_filter($plugins, function($plugin_file) {
            return strpos($plugin_file, 'pdf-embedder/') === false && strpos($plugin_file, 'pdf-embedder-premium/') === false;
        }));
    }
    return $plugins;
}, 1);

$scouting_pdf_file = WP_PLUGIN_DIR . '/scouting-pdf-embedder/scouting-pdf-embedder.php';
if (file_exists($scouting_pdf_file)) {
    require_once $scouting_pdf_file;
}
