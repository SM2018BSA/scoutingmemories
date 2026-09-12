<?php
/**
 * Plugin Name: Formidable Logs
 * Description: Log events from Formidable and other add-ons
 * Version: 1.0.1
 * Plugin URI: http://formidablepro.com/
 * Author URI: http://formidablepro.com/
 * Author: Strategy11
 * Text Domain: formidable-logs
 *
 * @package formidable-logs
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * Autoload.
 *
 * @param string $class_name class name.
 * @return void
 */
function frm_log_autoloader( $class_name ) {
	// Only load FrmLog classes here.
	if ( ! preg_match( '/^FrmLog.+$/', $class_name ) && 'FrmLog' != $class_name ) {
		return;
	}

	$filepath = dirname( __FILE__ );

	if ( preg_match( '/^.+Helper$/', $class_name ) ) {
		$filepath .= '/helpers/';
	} elseif ( preg_match( '/^.+Controller$/', $class_name ) ) {
		$filepath .= '/controllers/';
	} else {
		$filepath .= '/models/';
	}

	$filepath .= $class_name . '.php';

	if ( file_exists( $filepath ) ) {
		include $filepath;
	}
}


add_action( 'plugins_loaded', 'load_formidable_logs_addon', 1 );
/**
 * Simple requirement check before starting the plugin.
 *
 * @since 1.0.1
 *
 * @return void
 */
function load_formidable_logs_addon() {
	$is_free_installed = function_exists( 'load_formidable_forms' );
	if ( $is_free_installed ) {
		// Add the autoloader.
		spl_autoload_register( 'frm_log_autoloader' );

		FrmLogHooksController::load_hooks();
	} else {
		add_action( 'admin_notices', 'frm_logs_free_not_installed_notice' );
	}
}

/**
 * Display admin notice when lite plugin is not activated.
 *
 * @since 1.0.1
 *
 * @return void
 */
function frm_logs_free_not_installed_notice() {
	?>
	<div class="error">
		<p>
			<?php esc_html_e( 'Formidable Logs requires Formidable Forms to be installed.', 'formidable-logs' ); ?>
			<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=formidable+forms&tab=search&type=term' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Install Formidable Forms', 'formidable-logs' ); ?>
			</a>
		</p>
	</div>
	<?php
}
