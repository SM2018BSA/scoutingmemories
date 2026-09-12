<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * FrmLogHooksController.
 *
 * @since 1.0
 */
class FrmLogHooksController {

	/**
	 * Load hooks.
	 *
	 * @since 1.0
	 */
	public static function load_hooks() {
		add_action( 'init', 'FrmLogAppController::register_post_types', 0 );

		// Run cron job class for purge logs.
		FrmLogCron::get_instance();

		self::load_admin_hooks();

	}

	/**
	 * Load admin side hook
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function load_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_init', 'FrmLogAppController::include_updater', 1 );
		add_action( 'admin_init', 'FrmLogAppController::admin_init' );
		add_action( 'admin_menu', 'FrmLogAppController::menu', 15 );
		add_action( 'admin_enqueue_scripts', 'FrmLogAppController::enqueue_assets' );
		add_filter( 'admin_head-post.php', 'FrmLogAppController::highlight_menu' );
		add_filter( 'admin_head-post-new.php', 'FrmLogAppController::highlight_menu' );
		add_action( 'add_meta_boxes', 'FrmLogAppController::add_meta_to_log' );

		$post_type = 'frm_logs';
		add_action( 'manage_posts_extra_tablenav', 'FrmLogList::extra_tablenav' );
		add_filter( 'manage_edit-' . $post_type . '_columns', 'FrmLogList::manage_columns' );
		add_action( 'manage_' . $post_type . '_posts_custom_column', 'FrmLogList::manage_custom_columns', 10, 2 );

		// Settings controller.
		add_filter( 'frm_add_settings_section', 'FrmLogSettingsController::add_settings_section' );
		add_action( 'frm_update_settings', 'FrmLogSettingsController::update' );
		add_action( 'frm_store_settings', 'FrmLogSettingsController::store' );

		// Ajax.
		add_action( 'wp_ajax_frm_log_generate_csv', 'FrmLogAppHelper::csv' );
	}

}
