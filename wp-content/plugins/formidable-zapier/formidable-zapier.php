<?php
/*
Plugin Name: Formidable Zapier
Description: Integrate with everything through Zapier
Version: 2.03
Plugin URI: https://formidablepro.com/knowledgebase/formidable-zapier/
Author URI: http://strategy11.com
Author: Strategy11
Text Domain: frmzap
*/

/**
 * Register autoload for Formidable Zapier.
 *
 * @param string $class_name
 * @return void
 */
function frm_forms_zap_autoloader( $class_name ) {
	// Only load Frm classes here
	if ( ! preg_match( '/^FrmZap.+$/', $class_name ) ) {
		return;
	}

	$filepath = __DIR__ . '/';
	if ( preg_match( '/^.+Controller$/', $class_name ) ) {
		$filepath .= 'controllers/';
	} else {
		$filepath .= 'models/';
	}

	$filepath .= $class_name . '.php';

	if ( file_exists( $filepath ) ) {
		include $filepath;
	}
}

/**
 * @return void
 */
function load_formidable_zap() {
	$is_free_installed = function_exists( 'load_formidable_forms' );

	if ( ! $is_free_installed ) {
		add_action( 'admin_notices', 'frm_zap_free_not_installed_notice' );
	} else {
		// Add the autoloader.
		spl_autoload_register( 'frm_forms_zap_autoloader' );
		FrmZapAppController::load_hooks();
		FrmZapApiController::load_hooks();
	}
}

/**
 * @return void
 */
function frm_zap_free_not_installed_notice() {
	?>
	<div class="error">
		<p>
			<?php esc_html_e( 'Formidable Zapier requires Formidable Forms to be installed.', 'frmzap' ); ?>
			<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=formidable+forms&tab=search&type=term' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Install Formidable Forms', 'frmzap' ); ?>
			</a>
		</p>
	</div>
	<?php
}

add_action( 'plugins_loaded', 'load_formidable_zap', 1 );
