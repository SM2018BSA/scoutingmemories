<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class FrmRegUpdate extends FrmAddon {

	public $plugin_file;
	public $plugin_name = 'User Registration';
	public $download_id = 173984;
	public $version;

	public function __construct() {
		$this->plugin_file = dirname( dirname( __FILE__ ) ) . '/formidable-registration.php';
		$this->version     = FrmRegAppHelper::plugin_version();
		parent::__construct();
	}

	public static function load_hooks() {
		add_filter( 'frm_include_addon_page', '__return_true' );
		new FrmRegUpdate();
	}
}
