<?php

class FrmZapUpdate extends FrmAddon {

	public $plugin_file;
	public $plugin_name = 'Zapier';
	public $download_id = 170645;
	public $version = '2.03';

	public function __construct() {
		$this->plugin_file = dirname( dirname( __FILE__ ) ) . '/formidable-zapier.php';
		parent::__construct();
	}

	/**
	 * @return void
	 */
	public static function load_hooks() {
		add_filter( 'frm_include_addon_page', '__return_true' );
		new FrmZapUpdate();
	}

}
