<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}
/**
 * FrmLogUpdate
 */
class FrmLogUpdate extends FrmAddon {

	/**
	 * Plugin file.
	 *
	 * @var string
	 */
	public $plugin_file;

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	public $plugin_name = 'Logs';

	/**
	 * Download id.
	 *
	 * @var integer
	 */
	public $download_id = 11927748;

	/**
	 * Version.
	 *
	 * @var string
	 */
	public $version = '1.0.1';

	/**
	 * Construct.
	 */
	public function __construct() {
		$this->plugin_file = dirname( dirname( __FILE__ ) ) . '/formidable-logs.php';
		parent::__construct();
	}

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public static function load_hooks() {
		add_filter( 'frm_include_addon_page', '__return_true' );
		new FrmLogUpdate();
	}

}
