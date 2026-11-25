<?php
/**
 * Plugin Name: Scouting AI Tagger
 * Description: Connects the AI Tagging Assistant: sends it the council, camp and lodge index, asks it for tags for posts whose documents PDF Text has read, and (in a later version) embeds it for historians.
 * Version: 0.2.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SCOUTING_AI_TAGGER_VERSION', '0.2.0' );
define( 'SCOUTING_AI_TAGGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCOUTING_AI_TAGGER_URL_BASE', plugin_dir_url( __FILE__ ) );

require_once SCOUTING_AI_TAGGER_DIR . 'includes/class-scouting-ai-tagger.php';
require_once SCOUTING_AI_TAGGER_DIR . 'includes/class-scouting-ai-tagger-client.php';
require_once SCOUTING_AI_TAGGER_DIR . 'includes/class-scouting-ai-tagger-index-reader.php';
require_once SCOUTING_AI_TAGGER_DIR . 'includes/class-scouting-ai-tagger-sync.php';
require_once SCOUTING_AI_TAGGER_DIR . 'includes/class-scouting-ai-tagger-settings.php';
require_once SCOUTING_AI_TAGGER_DIR . 'includes/class-scouting-ai-tagger-result-mapper.php';
require_once SCOUTING_AI_TAGGER_DIR . 'includes/class-scouting-ai-tagger-post-suggestions.php';

Scouting_AI_Tagger::get_instance();
