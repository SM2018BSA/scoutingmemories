<?php

class FrmRegResetPWForm extends FrmRegForm{

	protected $path = '/views/reset_password_form.php';
	private $login = '';
	private $key = '';

	public function __construct( $atts ) {
		parent::__construct( $atts );

		$this->init_description();
		$this->init_login();
		$this->init_key();
		$this->init_submit_text( $atts );
	}

	/**
	 * Initialize the form description
	 *
	 * @since 2.0
	 */
	protected function init_description() {
		$global_settings = new FrmRegGlobalSettings;
		$this->description = $global_settings->get_global_message( 'reset_password' );
	}

	/**
	 * Initialize the login property
	 *
	 * @since 2.0
	 */
	private function init_login(){
		if ( isset( $_REQUEST['login'] ) ) {
			$this->login = sanitize_text_field( $_REQUEST['login'] );
		}
	}

	/**
	 * Initialize the key property
	 *
	 * @since 2.0
	 */
	private function init_key() {
		if ( isset( $_REQUEST['key'] ) ) {
			$this->key = sanitize_text_field( $_REQUEST['key'] );
		}
	}

	/**
	 * Initialize the submit button text
	 *
	 * @since 2.0
	 * @param $atts
	 */
	protected function init_submit_text( $atts ) {
		if ( isset( $atts['resetpass_button'] ) && $atts['resetpass_button'] ) {
			$this->submit_text = $atts['resetpass_button'];
		} else {
			$this->submit_text = __( 'Reset Password', 'frmreg' );
		}
	}

	/**
	 * Get the login property
	 *
	 * @since 2.0
	 * @return string
	 */
	public function get_login() {
		return $this->login;
	}

	/**
	 * Get the key property
	 *
	 * @since 2.0
	 * @return string
	 */
	public function get_key() {
		return $this->key;
	}

	/**
	 * Get the HTML ID for the first password field
	 *
	 * @since 2.0
	 * @return string
	 */
	public function get_first_field_id() {
		return 'pass1_' . $this->form_number;
	}

	/**
	 * Get the HTML ID for the second password field
	 *
	 * @since 2.0
	 * @return string
	 */
	public function get_second_field_id() {
		return 'pass2_' . $this->form_number;
	}

	/**
	 * Get the HTML ID for the hidden user field
	 *
	 * @since 2.0
	 * @return string
	 */
	public function get_user_field_id() {
		return 'user_login_' . $this->form_number;
	}

	/**
	 * Get the error message from a code
	 *
	 * @since 2.0
	 *
	 * @param string $error_code
	 *
	 * @return string
	 */
	protected function get_error_message( $error_code ) {
		if ( 0 === strpos( $error_code, 'weak_password_' ) ) {
			return $this->get_weak_password_error_message( $error_code );
		}

		switch ( $error_code ) {
			case 'expiredkey':
			case 'invalidkey':
				return __( 'The password reset link you used is not valid anymore.', 'frmreg' );

			case 'password_reset_mismatch':
				return __( 'The two passwords you entered do not match.', 'frmreg' );

			case 'password_reset_empty':
				return __( 'Sorry, we do not accept empty passwords.', 'frmreg' );

			default:
				break;
		}

		return $error_code;
	}

	/**
	 * Gets weak password error message from the error type.
	 *
	 * @since 2.05
	 *
	 * @param string $error_type Weak password type.
	 * @return string
	 */
	protected function get_weak_password_error_message( $error_type ) {
		$field_obj = FrmRegResetPasswordController::get_fake_password_field_obj();
		if ( ! is_callable( array( $field_obj, 'password_checks' ) ) ) {
			return $error_type;
		}

		$checks     = $field_obj->password_checks();
		$error_type = str_replace( 'weak_password_', '', $error_type );
		if ( isset( $checks[ $error_type ]['message'] ) ) {
			return $checks[ $error_type ]['message'];
		}

		return $error_type;
	}
}
