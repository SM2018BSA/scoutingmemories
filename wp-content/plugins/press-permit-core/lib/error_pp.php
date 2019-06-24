<?php
if ( ! class_exists('PP_Error') ) {

class PP_Error {
	private $notices = array();

	function old_pp( $ext_title, $min_pp_version ) {
		$this->error_box( sprintf( __('%1$s won&#39;t work until you upgrade Press Permit Core to version %2$s or later.', 'pp'), $ext_title, $min_pp_version ) );
		return true;
	}
	
	function old_wp( $ext_title, $min_wp_version ) {
		$this->error_box( sprintf( __('%1$s won&#39;t work until you upgrade WordPress to version %2$s or later.', 'pp'), $ext_title, $min_wp_version ) );
		return true;
	}
	
	function old_extension( $ext_title, $min_ext_version ) {		
		$this->error_box( sprintf( __('This version of %1$s cannot work with your current PP Core. Please upgrade it to %2$s or later.', 'pp'), $ext_title, $min_ext_version ) );
		return true;
	}
	
	function duplicate_extension( $ext_slug, $ext_folder ) {		
		$this->error_box( sprintf( __('Duplicate Press Permit extension activated (%1$s in folder %2$s).', 'pp'), $ext_slug, $ext_folder ) );
		return true;
	}
	
	function error_notice( $err ) {
		global $pp_plugin_page;
		$is_pp_plugin_page = ( ! empty($pp_plugin_page) ) || ( isset($_REQUEST['page']) && 0 === strpos( $_REQUEST['page'], 'pp-' ) );
		
		switch( $err ) {
			case 'multiple_pp' :
				global $pagenow;
			
				if ( is_admin() && ( 'plugins.php' == $pagenow ) && ! strpos( urldecode($_SERVER['REQUEST_URI']), 'deactivate' ) ) {
					$message = sprintf( '<strong>Error:</strong> Multiple copies of %1$s activated. Only the copy in folder "%2$s" is functional.', 'Press Permit', PPC_FOLDER );
					$this->add_notice( $message, array( 'style' => "color: black" ) );
				}
				break;
				
			case 'rs_active' :
				define( 'PP_DISABLE_QUERYFILTERS', true );
				$message = sprintf( '<strong>Note:</strong> Press Permit is running in configuration only mode. Access filtering will not be applied until Role Scoper is deactivated.' );
				$style = ( $is_pp_plugin_page ) ? 'margin-top:30px;color:black' : 'color:black';
				$this->add_notice( $message, compact( 'style' ) );
				
				define( 'PP_CONFIG_ONLY', true );
				define( 'PP_DISABLE_MENU_TWEAK', true );
				return false;
				break;
			
			case 'pp_legacy_active' :
				$this->add_notice('Press Permit Core 2 cannot operate with an older version of Press Permit active.');
				break;

			case 'old_php' :
				$this->add_notice('Sorry, Press Permit requires PHP 5.2 or higher. Please upgrade your server or deactivate Press Permit.');
				break;
			
			default :
		}
		
		return true;
	}

	function error_box( $msg ) {
		global $pagenow;
		
		if ( isset($pagenow) && ( 'update.php' != $pagenow ) ) {
			$this->add_notice( $msg );
		}
	}
	
	// deprecated
	function notice( $message, $class = 'error fade', $trigger_error = false, $force = false ) {
		$this->add_notice( $message, compact( 'class' ) );
	}

	function add_notice( $body, $args = array() ) {
		if ( ! $this->notices ) {
			add_action( 'all_admin_notices', array( &$this, 'do_notices'), 5 );
		}

		$this->notices[]= (object) array_merge( compact( 'body' ), $args );
	}

	function do_notices() {
		foreach( $this->notices as $msg ) {
			$style = ( ! empty( $msg->style ) ) ? "style='$msg->style'" : "style='color:black'";
			$class = ( ! empty( $msg->class ) ) ? "class='$msg->class'" : '';
			echo "<div id='message' class='error fade' $style $class>" . $msg->body . '</div>';
		}
	}
} // end class
} // endif exists
