<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

if ( class_exists( 'FrmZapAppController' ) ) {
	return;
}

class FrmZapAppController {

	public static function load_hooks() {
		add_action( 'admin_init', 'FrmZapAppController::include_updater', 1 );
		add_action( 'init', 'FrmZapAppController::register_post_types', 1 );
		add_action( 'frm_add_settings_section', 'FrmZapAppController::add_settings_section' );
	}

	public static function path() {
		return dirname( dirname( __FILE__ ) );
	}

	public static function include_updater() {
		if ( class_exists( 'FrmAddon' ) ) {
			include( self::path() . '/models/FrmZapUpdate.php' );
			FrmZapUpdate::load_hooks();
		}
	}

	public static function register_post_types() {
		if ( get_post_type_object( 'frm_api' ) ) {
			// only register if not registered from somewhere else
			return;
		}

		register_post_type(
			'frm_api',
			array(
				'label' => __( 'Formidable WebHooks', 'frmzap' ),
				'description' => '',
				'public' => false,
				'show_ui' => false,
				'capability_type' => 'page',
				'supports' => array( 'revisions', 'excerpt' ),
				'labels' => array(
					'name' => __( 'WebHooks', 'frmzap' ),
					'singular_name' => __( 'WebHook', 'frmzap' ),
					'menu_name' => 'WebHooks',
					'edit' => __( 'Edit' ),
					'search_items' => __( 'Search', 'formidable' ),
					'not_found' => __( 'No WebHooks Found.', 'frmzap' ),
					'add_new_item' => __( 'Add New WebHookp', 'frmzap' ),
					'edit_item' => __( 'Edit WebHook', 'frmzap' ),
				),
			)
		);
	}

	public static function add_settings_section( $sections ) {
		if ( ! isset( $sections['api'] ) ) {
			$sections['api'] = array(
				'class'    => 'FrmZapAppController',
				'function' => 'show_api_key',
			);
		}
		return $sections;
	}

	public static function show_api_key() {
		$api_key = get_option( 'frm_api_key' );
		if ( ! $api_key ) {
			$api_key = self::generate();
			update_option( 'frm_api_key', $api_key );
		}
		require_once( self::path() . '/views/settings/api-key.php' );
	}

	private static function generate() {
		global $wpdb;

		$tokens = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$segment_chars = 5;
		$num_segments = 4;
		$key_string = '';

		for ( $i = 0; $i < $num_segments; $i++ ) {
			$segment = '';

			for ( $j = 0; $j < $segment_chars; $j++ ) {
				$segment .= $tokens[ rand( 0, 35 ) ];
			}

			$key_string .= $segment;

			if ( $i < ( $num_segments - 1 ) ) {
				$key_string .= '-';
			}
		}

		return $key_string;
	}

}
