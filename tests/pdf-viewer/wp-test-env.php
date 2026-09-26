<?php
/**
 * Test environment only (copied into the throwaway WordPress by setup-wordpress.sh).
 * Loads the same Bootstrap 5 + Bootstrap Icons the Scouting Memories theme uses, so the
 * viewer looks and lays out as it does on the live site.
 */
if (!defined('ABSPATH')) {
    exit;
}

// The plugin is symlinked in from the repository; let plugin_dir_url() see through that
wp_register_plugin_realpath(WP_PLUGIN_DIR . '/scouting-pdf-embedder/scouting-pdf-embedder.php');

// The fake storage server from setup-wordpress.sh stands in for storage.scoutingmemories.org
add_filter('scouting_pdf_proxy_hosts', function($hosts) {
    $hosts[] = 'pdf-proxy-test.example';
    // Listed only to prove the endpoint still refuses a name that resolves to loopback
    $hosts[] = 'localhost';
    return $hosts;
});

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('test-bootstrap', home_url('/test-assets/bootstrap/css/bootstrap.min.css'), array(), null);
    wp_enqueue_style('test-bootstrap-icons', home_url('/test-assets/bootstrap-icons/bootstrap-icons.min.css'), array(), null);
    wp_enqueue_script('test-bootstrap', home_url('/test-assets/bootstrap/js/bootstrap.bundle.min.js'), array(), null, true);
}, 1);
