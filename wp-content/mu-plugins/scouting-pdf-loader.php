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

$scouting_pdf_file = WP_PLUGIN_DIR . '/scouting-pdf-embedder/scouting-pdf-embedder.php';
if (file_exists($scouting_pdf_file)) {
    require_once $scouting_pdf_file;
}
