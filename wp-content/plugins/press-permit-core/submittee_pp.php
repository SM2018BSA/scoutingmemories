<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action( 'update_option_pp_beta_updates', '_pp_refresh_version_info' );

function _pp_refresh_version_info() {
	delete_site_transient( 'update_plugins' );
	pp_get_version_info( true, false, true );
	wp_update_plugins();
}

do_action( 'pp_submittee' );

class PP_Submittee {
	function process_submission() {
		if ( ! current_user_can('pp_manage_settings') )
			wp_die(_pp_('Cheatin&#8217; uh?'));
		
		if ( ! empty( $_REQUEST['pp_refresh_updates'] ) ) {
			delete_site_transient( 'update_plugins' );
			pp_get_version_info( true, false, true );
			wp_update_plugins();
			wp_redirect( admin_url('admin.php?page=pp-settings&pp_refresh_done=1') );
			exit;
		}
		
		if ( ! empty( $_REQUEST['pp_renewal'] ) ) {
			$opt_val = get_option( 'pp_support_key' );
			$renewal_token = ( ! is_array($opt_val) || count($opt_val) < 2 ) ? '' : substr( $opt_val[1], 0, 16 );
			
			$url = site_url('');
			$arr_url = parse_url( $url );
			$site = urlencode( str_replace( $arr_url['scheme'] . '://', '', $url ) );
			
			wp_redirect( 'https://presspermit.com/renewal/?pkg=press-permit-pro&site=' . $site . '&rt=' . $renewal_token );
			exit;
		}
	
		if ( ! empty( $_REQUEST['pp_upload_config'] ) || ! empty( $_REQUEST['pp_help_ticket'] ) ) {
			require_once( dirname(__FILE__).'/admin/support_pp.php' );
			$args = array();
			if ( isset( $_REQUEST['post_id'] ) )
				$args['post_id'] = (int) $_REQUEST['post_id'];
				
			if ( isset( $_REQUEST['term_taxonomy_id'] ) )
				$args['term_taxonomy_id'] = (int) $_REQUEST['term_taxonomy_id'];
			
			$key = pp_get_option( 'support_key' );
			
			if ( ! empty( $_REQUEST['pp_help_ticket'] ) ) {
				$url = "https://presspermit.com/contact/?pp_topic=press-permit&via=wp-admin";
				
				if ( $key && is_array( $key ) && ( 1 == $key[0] ) ) {  // note: this is only a hash
					$url = add_query_arg( 'ppsh', $key[1], $url );
				}
				
				wp_redirect( $url );
			}
			
			if ( $key && is_array( $key ) && ! empty( $key[1] ) ) {
				$success = _pp_support_upload( $args );
			}
			
			if ( empty( $_REQUEST['pp_help_ticket'] ) ) {
				if ( -1 === $success )
					$flag = 'pp_config_no_change';
				elseif( $success )
					$flag = 'pp_config_uploaded';
				else
					$flag = 'pp_config_failed';

				wp_redirect( admin_url("admin.php?page=pp-settings&{$flag}=1") );
			}
			
			exit;
		}
	
		if ( isset($_POST['pp_submit']) ) {
			$this->handle_submission( 'update' );
			
		} elseif ( isset($_POST['pp_defaults']) ) {
			$this->handle_submission( 'default' );
			
		} elseif ( isset($_POST['pp_role_usage_defaults']) ) {
			delete_option( 'pp_role_usage' );
			pp_refresh_options();
		}
	}

	function handle_submission( $action ) {
		$args = apply_filters( 'pp_handle_submission_args', array() );

		if ( empty($_POST['pp_submission_topic']) )
			return;
		
		if ( 'options' == $_POST['pp_submission_topic'] ) {
			$method = "{$action}_options";
			if ( method_exists( $this, $method ) )
				call_user_func( array($this, $method), $args );

			do_action( 'pp_handle_submission', $action, $args );
			
			pp_refresh_options();
		}
	}
	
	function update_options( $args ) {
		check_admin_referer( 'pp-update-options' );
	
		$this->update_page_options( $args );
		
		global $wpdb;
		$wpdb->query( "UPDATE $wpdb->options SET autoload = 'no' WHERE option_name LIKE 'pp_%' AND option_name NOT LIKE '%_version' AND option_name NOT IN ('pp_custom_conditions_post_status')" );
	}
	
	function default_options( $args ) {
		check_admin_referer( 'pp-update-options' );
	
		$default_prefix = apply_filters( 'pp_options_apply_default_prefix', '', $args );

		$reviewed_options = array_merge( explode(',', $_POST['all_options']), explode(',', $_POST['all_otype_options']) );
		foreach ( $reviewed_options as $option_name )
			pp_delete_option($default_prefix . $option_name, $args );
	}

	function update_page_options( $args ) {
		global $pp_default_options;
		
		do_action( 'pp_update_options', $args );
		
		$default_prefix = apply_filters( 'pp_options_apply_default_prefix', '', $args );
		
		foreach ( explode(',', $_POST['all_options']) as $option_basename ) {
			$value = isset($_POST[$option_basename]) ? $_POST[$option_basename] : '';

			if ( ! is_array($value) )
				$value = trim($value);

			if ( 'beta_updates' == $option_basename ) {
				if ( stripslashes_deep($value) != pp_get_option( 'beta_updates' ) ) {
					// force regeneration and buffering of file update urls
					delete_site_transient( 'update_plugins' );
					pp_get_version_info( true, false, true );
					wp_update_plugins();
				}
			}
				
			pp_update_option( $default_prefix . $option_basename, stripslashes_deep($value), $args );
		}
		
		foreach ( explode(',', $_POST['all_otype_options']) as $option_basename ) {
			// support stored default values (to apply to any post type which does not have an explicit setting)
			if ( isset($_POST[$option_basename][0]) ) {
				$_POST[$option_basename][''] = $_POST[$option_basename][0];
				unset( $_POST[$option_basename][0] );
			}

			$value = ( isset( $pp_default_options[$option_basename] ) ) ? $pp_default_options[$option_basename] : array();
			
			if ( $current = pp_get_option( $option_basename ) ) // retain setting for any types which were previously enabled for filtering but are currently not registered
				$value = array_merge( $value, $current );

			if ( isset( $_POST[$option_basename] ) )
				$value = array_merge( $value, $_POST[$option_basename] );

			foreach( array_keys($value) as $key )
				$value[$key] = stripslashes_deep( $value[$key] );
			
			pp_update_option( $default_prefix . $option_basename, $value, $args );
		}
		
		if ( ! empty( $_POST['post_blockage_priority'] ) ) {	 // once this is switched on manually, don't ever default-disable it again
			if ( get_option( 'ppperm_legacy_exception_handling' ) ) {
				delete_option( 'ppperm_legacy_exception_handling' );
				
				require_once( dirname(__FILE__).'/admin/admin-load_pp.php' );
				_pp_dashboard_dismiss_msg();
			}
		}
		
		if ( ! empty( $_POST['do_group_index_drop'] ) ) {
			if ( get_option('pp_need_group_index_drop') ) {
				require_once( dirname(__FILE__).'/admin/update_pp.php');
				PP_Updated::do_index_drop( PP_Updated::drop_group_indexes_sql(), 'pp_did_group_index_drop' );
				delete_option( 'pp_need_group_index_drop' );
			}
		}
	}
}
