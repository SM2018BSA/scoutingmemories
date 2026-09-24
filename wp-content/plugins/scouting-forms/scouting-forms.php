<?php
/**
 * Plugin Name: Scouting Forms & Archives
 * Plugin URI: https://scoutingmemories.org/
 * Description: Roots.io-inspired form and index management for Scouting Memories. Replaces Formidable Forms with high-performance native forms, user defaults, and archive tools.
 * Version: 1.0.0
 * Author: Roger Ellis Jr / Scouting Memories Project
 * Author URI: https://scoutingmemories.org/
 * Text Domain: scouting-forms
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace ScoutingMemories\Forms;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

define('SM_FORMS_VERSION', '1.0.0');
define('SM_FORMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SM_FORMS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader: Composer or fallback PSR-4
if (file_exists(SM_FORMS_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once SM_FORMS_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'ScoutingMemories\\Forms\\';
        $base_dir = SM_FORMS_PLUGIN_DIR . 'src/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });
}

/**
 * Initialize Plugin
 */
add_action('plugins_loaded', function () {
    if (class_exists('\\ScoutingMemories\\Forms\\Plugin')) {
        \ScoutingMemories\Forms\Plugin::getInstance()->boot();
    }
});
