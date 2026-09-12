<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmZapAppController {

	/**
	 * @return void
	 */
	public static function load_hooks() {
		add_action( 'init', 'FrmZapAppController::load_lang' );
		add_action( 'admin_init', 'FrmZapAppController::initialize_admin', 1 );
		add_action( 'frm_add_settings_section', 'FrmZapAppController::add_settings_section' );
		add_action( 'frm_registered_form_actions', 'FrmZapAppController::register_actions' );
		add_action( 'wp_ajax_convert_zapier_posts', 'FrmZapAppController::handle_ajax_zapier_migration' );
		add_filter( 'frm_autoresponder_allowed_actions', 'FrmZapAppController::add_zapier_to_automation' );
		add_filter( 'frm_check_file_referer', 'FrmZapAppController::maybe_turnoff_file_referer_check' );
	}

	/**
	 * Add translation support.
	 *
	 * @return void
	 */
	public static function load_lang() {
		$plugin_folder_name = basename( self::path() );
		load_plugin_textdomain( 'frmzap', false, $plugin_folder_name . '/languages/' );
	}

	/**
	 * Allow Zapier to be triggered by the automation.
	 *
	 * @since 2.0
	 *
	 * @param array $actions
	 * @return array
	 */
	public static function add_zapier_to_automation( $actions ) {
		$actions[] = 'zapier';
		return $actions;
	}

	/**
	 * Check if frmzap_db_version exists in wp_options.
	 *
	 * @since 2.0
	 * @return bool False if a migration is required. True if it has already run, or if there is no need to run it.
	 */
	public static function check_db_version() {
		if ( get_option( 'frmzap_db_version' ) ) {
			// The migration has already run.
			return true;
		}

		// Check for old frm_api posts. If none exist, we don't need to show a migration message.
		$old_post_id = FrmDb::get_var( 'posts', array( 'post_type' => 'frm_api' ), 'ID' );
		return ! $old_post_id;
	}

	/**
	 * Add messages informing that the Zapier database needs an update to the message list and inbox.
	 *
	 * @since 2.0
	 *
	 * @param array $show_messages List of current messages.
	 * @return array
	 */
	public static function add_message( $show_messages ) {
		$show_messages['zap_msg'] = "Important Zapier Update Required! Your Zapier database is ready to be updated. Your Zaps will not function until updated. Click <a href='#' class='formidable_zapier_migration'>here</a> to update.";

		$new_message = array(
			'key'     => 'zap_202008',
			'force'   => true,
			'message' => 'Hey, Zapier users! Your Zapier database is ready to be updated. Your current Zaps will not function until updated.',
			'subject' => 'Important Zapier Update Required',
			'icon'    => 'frm_tooltip_icon',
			'cta'     => '<a class="formidable_zapier_migration button-secondary">Migrate</a>',
		);
		self::add_inbox_message( $new_message );
		return $show_messages;
	}

	/**
	 * Add a message to the Formidable Inbox, if it exists
	 *
	 * @since 2.0
	 *
	 * @param array $message Message to add.
	 * @return void
	 */
	private static function add_inbox_message( $message ) {
		if ( ! class_exists( 'FrmInbox' ) ) {
			return;
		}

		$inbox = new FrmInbox();
		$inbox->add_message( $message );
	}

	/**
	 * Call the method to convert zapier posts to form actions. Remove inbox message.
	 *
	 * @since 2.0
	 * @return void
	 */
	public static function handle_ajax_zapier_migration() {
		FrmAppHelper::permission_check( 'install_plugins' );
		check_ajax_referer( 'frm_ajax', 'nonce' );

		self::convert_zapier_posts();
		self::remove_inbox_message( 'zap_202008' );
		wp_die();
	}

	/**
	 * Remove a message from the Formidable inbox, if it exists.
	 *
	 * @since 2.0
	 *
	 * @param string $key Array key of the message to remove.
	 * @return void
	 */
	private static function remove_inbox_message( $key ) {
		if ( ! class_exists( 'FrmInbox' ) ) {
			return;
		}

		$inbox = new FrmInbox();
		$inbox->remove( $key );
	}

	/**
	 * Enqueue the admin JS file.
	 *
	 * @since 2.0
	 * @return void
	 */
	public static function enqueue_admin_js() {
		if ( ! is_callable( 'FrmAppHelper::is_admin_page' ) ) {
			return;
		}
		if ( FrmAppHelper::is_admin_page() || FrmAppHelper::is_admin_page( 'formidable-inbox' ) || FrmAppHelper::is_admin_page( 'formidable-settings' ) ) {
			wp_enqueue_script( 'frmzap_admin', self::plugin_url() . '/js/back_end.js', array(), 1.0 );
		}
	}

	/**
	 * Retrieve the url of the Zapier add-on
	 *
	 * @since 2.0
	 * @return string
	 */
	public static function plugin_url() {
		return plugins_url( '', self::path() . '/formidable-zapier.php' );
	}

	/**
	 * Get the path of the current file
	 *
	 * @since 2.0
	 * @return string
	 */
	public static function path() {
		return dirname( dirname( __FILE__ ) );
	}

	/**
	 * @return void
	 */
	public static function include_updater() {
		if ( class_exists( 'FrmAddon' ) ) {
			include( self::path() . '/models/FrmZapUpdate.php' );
			FrmZapUpdate::load_hooks();
		}
	}

	/**
	 * Functions to be called on admin_init
	 *
	 * @since 2.0
	 * @return void
	 */
	public static function initialize_admin() {
		self::include_updater();
		if ( ! self::check_db_version() ) {
			self::enqueue_admin_js();
			add_filter( 'frm_message_list', 'FrmZapAppController::add_message' );
		}
	}

	/**
	 * @deprecated 2.0
	 * @return void
	 */
	public static function register_post_types() {
		_deprecated_function( __FUNCTION__, '2.0' );
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
					'edit' => __( 'Edit' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
					'search_items' => __( 'Search', 'formidable' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
					'not_found' => __( 'No WebHooks Found.', 'frmzap' ),
					'add_new_item' => __( 'Add New WebHookp', 'frmzap' ),
					'edit_item' => __( 'Edit WebHook', 'frmzap' ),
				),
			)
		);
	}

	/**
	 * Check the current Formidable Forms plugin version against $min_version.
	 * Print a message and return false if $min_version is not met. Otherwise return true.
	 *
	 * @since 2.0
	 *
	 * @param string $min_version
	 * @return bool
	 */
	private static function compare_versions( $min_version ) {
		$frm_version = is_callable( 'FrmAppHelper::plugin_version' ) ? FrmAppHelper::plugin_version() : 0;

		// check if Formidable meets minimum requirements
		if ( version_compare( $frm_version, $min_version, '<' ) ) {
			esc_html_e( 'You are running an outdated version of Formidable. This plugin needs Formidable v3.0+ to work correctly.', 'frmzap' );
			return false;
		}

		return true;
	}

	/**
	 * Convert all posts of type frm_api to form actions.
	 *
	 * @since 2.0
	 * @return void
	 */
	private static function convert_zapier_posts() {
		$min_version          = '3.0';
		$passes_version_check = self::compare_versions( $min_version );

		if ( ! $passes_version_check ) {
			return;
		}

		$args = array(
			'post_type'   => 'frm_api',
			'numberposts' => -1,
		);
		$api_posts = get_posts( $args );

		foreach ( $api_posts as $post ) {
			if ( $post->migrated_to_action == 1 ) {
				continue;
			}
			$trigger_name = $post->post_title;
			$zap_url = $post->post_excerpt;
			$form_id = $post->frm_form_id;

			$new_event = explode( '_', $trigger_name )[2];

			$action = new FrmZapAction();
			$action->create_new( $form_id, $zap_url, $new_event );

			add_post_meta( $post->ID, 'migrated_to_action', 1 );
		}

		$db_version = 1;
		update_option( 'frmzap_db_version', $db_version );
	}

	/**
	 * Register the Zapier form action.
	 *
	 * @since 2.0
	 *
	 * @param array $actions Registered actions.
	 * @return array
	 */
	public static function register_actions( $actions ) {
		$actions['zapier'] = 'FrmZapAction';
		return $actions;
	}

	/**
	 * @param array $sections
	 * @return array
	 */
	public static function add_settings_section( $sections ) {
		if ( ! isset( $sections['api'] ) ) {
			$sections['api'] = array(
				'class'    => 'FrmZapAppController',
				'function' => 'show_api_key',
			);
		}
		return $sections;
	}

	/**
	 * @return void
	 */
	public static function show_api_key() {
		$api_key = get_option( 'frm_api_key' );
		if ( ! $api_key ) {
			$api_key = self::generate();
			update_option( 'frm_api_key', $api_key );
		}
		require_once self::path() . '/views/settings/api-key.php';
	}

	/**
	 * @return string
	 */
	private static function generate() {
		global $wpdb;

		$tokens        = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$segment_chars = 5;
		$num_segments  = 4;
		$key_string    = '';

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

	/**
	 * @since 2.02
	 *
	 * @param bool $check_referer
	 * @return bool
	 */
	public static function maybe_turnoff_file_referer_check( $check_referer ) {
		if ( ! $check_referer ) {
			return false;
		}

		return FrmAppHelper::get_server_value( 'HTTP_USER_AGENT' ) !== 'Zapier';
	}

}
