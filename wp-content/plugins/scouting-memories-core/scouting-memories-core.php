<?php
/**
 * Plugin Name: Scouting Memories Core
 * Description: The site's domain layer: Formidable entry and view wrappers, access checks, search AJAX and taxonomy writers. Presentation stays in the theme. The active theme opts in by defining SM_THEME_DELEGATES_TO_CORE; a theme that carries its own copies keeps working untouched.
 * Version: 0.1.0
 * Requires PHP: 8.0
 * Author: Scouting Memories
 * Text Domain: scouting-memories-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SM_CORE_VERSION', '0.1.0' );
define( 'SM_CORE_DIR', __DIR__ );

require_once __DIR__ . '/src/Autoloader.php';
require_once __DIR__ . '/src/Plugin.php';

ScoutingMemories\Core\Plugin::boot();
