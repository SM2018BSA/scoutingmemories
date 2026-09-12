<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * Class FrmLogAppController.
 *
 * @since 1.0
 */
class FrmLogAppController {

	/**
	 * Post type variable.
	 *
	 * @var string
	 */
	public static $post_type = 'frm_logs';

	/**
	 * Include updater
	 *
	 * @return void
	 */
	public static function include_updater() {
		if ( class_exists( 'FrmAddon' ) ) {
			FrmLogUpdate::load_hooks();
		}
	}

	/**
	 * Register post type
	 *
	 * @return void
	 */
	public static function register_post_types() {
		register_post_type(
			self::$post_type,
			array(
				'label'               => __( 'Logs', 'formidable-logs' ),
				'description'         => '',
				'public'              => false,
				'show_ui'             => true,
				'exclude_from_search' => true,
				'show_in_nav_menus'   => false,
				'show_in_menu'        => false,
				'capability_type'     => 'page',
				'capabilities'        => array(
					'edit_post'         => 'frm_edit_forms',
					'edit_posts'        => 'frm_edit_forms',
					'edit_others_posts' => 'frm_edit_forms',
					'publish_posts'     => 'frm_edit_forms',
					'delete_post'       => 'frm_edit_forms',
					'delete_posts'      => 'frm_edit_forms',
					'read_post'         => 'frm_edit_forms',
				),
				'supports'            => array(
					'title',
				),
				'has_archive'         => false,
				'labels'              => array(
					'name'          => __( 'Logs', 'formidable-logs' ),
					'singular_name' => __( 'Log', 'formidable-logs' ),
					'menu_name'     => __( 'Logs', 'formidable-logs' ),
					'edit'          => __( 'Edit' ),
					'search_items'  => __( 'Search Logs', 'formidable-logs' ),
					'not_found'     => __( 'No Logs Found.', 'formidable-logs' ),
					'add_new_item'  => __( 'Add New Log', 'formidable-logs' ),
					'edit_item'     => __( 'Edit Log', 'formidable-logs' ),
				),
			)
		);
	}

	/**
	 * Register menu
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page( 'formidable', 'Formidable | ' . __( 'Logs', 'formidable-logs' ), __( 'Logs', 'formidable-logs' ), 'frm_edit_forms', 'edit.php?post_type=' . self::$post_type );
	}

	/**
	 * Enqueue formidable admin script in frmlogs page
	 *
	 * @param string $hook Hook suffix for the current admin page.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'edit.php' !== $hook ) {
			return;
		}

		if ( FrmAppHelper::simple_get( 'post_type', 'sanitize_title' ) !== self::$post_type ) {
			return;
		}

		wp_enqueue_script( 'formidable_admin' );
		FrmAppHelper::localize_script( 'admin' );
		wp_enqueue_style( 'formidable-admin' );
		// Use to fix css caused by formidable admin css should move to separate css file.
		wp_add_inline_style( 'formidable-admin', 'select[name="m"]{display:block}' );
		FrmAppController::include_upgrade_overlay();
	}

	/**
	 * Highlight menu
	 *
	 * @return void
	 */
	public static function highlight_menu() {
		if ( class_exists( 'FrmAppHelper' ) ) {
			FrmAppHelper::maybe_highlight_menu( self::$post_type );
		}
	}

	/**
	 * Register metabox to menu
	 *
	 * @return void
	 */
	public static function add_meta_to_log() {
		add_meta_box( 'frm-show-log', __( 'Log Details', 'formidable-logs' ), array( __CLASS__, 'show_log' ), self::$post_type );
	}

	/**
	 * Show log
	 *
	 * @param WP_Post $post post object.
	 *
	 * @return void
	 */
	public static function show_log( $post ) {
		global $wpdb;
		$custom_fields = FrmDb::get_results(
			$wpdb->postmeta,
			array(
				'meta_key like' => 'frm_',
				'post_ID'       => $post->ID,
			)
		);
		include FrmLogAppHelper::plugin_path() . '/views/show.php';
	}

	/**
	 * Destroy all formlogs.
	 *
	 * @since 1.0
	 */
	public static function admin_init() {
		// More security check @destroy_all.
		if ( 'destroy_all' === FrmAppHelper::get_param( 'frm_logs_action' ) ) {
			$loglist = new FrmLog();
			$loglist->destroy_all();
			die();
		}
	}

}
