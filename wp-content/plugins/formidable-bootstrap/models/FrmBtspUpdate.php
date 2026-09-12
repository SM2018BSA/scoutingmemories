<?php

class FrmBtspUpdate extends FrmAddon {
	public $plugin_file;
	public $plugin_name = 'Bootstrap';
	public $download_id = 168463;

	public $version = '2.0';

	public function __construct() {
		$this->plugin_file = dirname( dirname( __FILE__ ) ) . '/formidable-bootstrap.php';
		parent::__construct();
	}

	public static function load_hooks() {
		add_filter( 'frm_include_addon_page', '__return_true' );
		new FrmBtspUpdate();
	}

}
