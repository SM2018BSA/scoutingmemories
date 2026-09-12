<?php

/**
 * @since 2.0
 */
class FrmRegResetPasswordController {

	/**************************************************
	 * Lost Password Functions
	 **************************************************/

	/**
	 * Redirect to custom lost password page if selected in global settings
	 *
	 * @since 2.0
	 */
	public static function redirect_to_custom_lost_password() {
		if ( 'GET' === FrmRegAppHelper::request_method() ) {
			$redirect_url = self::reset_password_page_url( 'none' );

			if ( $redirect_url ) {
				wp_redirect( esc_url_raw( $redirect_url ) );
				exit;
			}
		}
	}

	/**
	 * Initiates a password reset if either a global reset password page or
	 * a global login page is selected
	 *
	 * @since 2.0
	 */
	public static function do_lost_password() {
		if ( ! self::is_global_reset_password_page_set() && ! FrmRegLoginController::is_global_login_page_set() ) {
			return;
		}

		if ( 'POST' === FrmRegAppHelper::request_method() ) {

			// Attempt to send reset password email
			$errors = retrieve_password();

			if ( is_wp_error( $errors ) ) {
				$errors->remove( 'invalidcombo' );
				$errors->remove( 'invalid_email' );
			}

			if ( is_wp_error( $errors ) && $errors->has_errors() ) {
				// Errors found
				self::lost_password_redirect_with_errors( $errors );
			} else {
				// Email sent
				self::lost_password_redirect_no_errors();
			}
		}
	}

	/**
	 * Do a redirect from the lost password page when there are errors
	 *
	 * @since 2.0
	 * @param WP_Error $errors
	 */
	private static function lost_password_redirect_with_errors( $errors ) {
		$query_args = array( 'errors' => join( ',', $errors->get_error_codes() ) );
		self::redirect_to_selected_reset_password_page( $query_args );
	}

	/**
	 * Do a redirect from the lost password page when there are no errors
	 *
	 * @since 2.0
	 */
	private static function lost_password_redirect_no_errors() {
		$redirect_url = FrmRegLoginController::login_page_url( 'wordpress' );

		if ( strpos( $redirect_url, 'wp-login.php' ) !== false ) {
			$redirect_url = add_query_arg( 'checkemail', 'confirm', $redirect_url );
		} else {
			$redirect_url = add_query_arg( 'frm_message', 'check_email', $redirect_url );
		}

		wp_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	/*********************************************************
	 * Reset password
	 ********************************************************/

	/**
	 * Redirect to custom reset password page if it is selected in global settings
	 *
	 * @since 2.0
	 */
	public static function redirect_to_custom_reset_password() {
		if ( ! self::is_global_reset_password_page_set() ) {
			return;
		}

		if ( 'GET' === FrmRegAppHelper::request_method() ) {

			if ( ! isset( $_REQUEST[ 'key' ] ) || ! isset( $_REQUEST[ 'login' ] ) ) {
				$query_args = array();

			} else {

				// Verify key / login combo
				$key  = FrmAppHelper::get_param( 'key', '', 'get', 'sanitize_text_field' );
				$login = FrmAppHelper::get_param( 'login', '', 'get', 'sanitize_text_field' );
				$user = check_password_reset_key( $key, $login );

				if ( ! $user || is_wp_error( $user ) ) {

					$query_args = self::get_reset_password_query_args_from_user( $user );

				} else {

					$query_args = array(
						'login' => esc_attr( $login ),
						'key'   => esc_attr( $key ),
					);

				}
			}

			self::redirect_to_selected_reset_password_page( $query_args );
		}
	}

	/**
	 * Redirect to the selected reset password page
	 * No redirect occurs if global page is not selected
	 *
	 * @since 2.0
	 * @param array $query_args
	 */
	private static function redirect_to_selected_reset_password_page( $query_args = array() ) {
		$redirect_url = self::reset_password_page_url( 'none' );

		if ( $redirect_url ) {

			foreach ( $query_args as $key => $value ) {
				$redirect_url = add_query_arg( $key, $value, $redirect_url );
			}

			wp_redirect( esc_url_raw( $redirect_url ) );
			exit;
		}
	}

	/**
	 * Get the reset password query args from the error object
	 *
	 * @since 2.0
	 *
	 * @param WP_Error $error
	 *
	 * @return array
	 */
	private static function get_reset_password_query_args_from_user( $error ) {
		if ( $error && $error->get_error_code() === 'expired_key' ) {
			$query_args = array( 'errors' => 'expiredkey' );
		} else {
			$query_args = array( 'errors' => 'invalidkey' );
		}

		return $query_args;
	}

	/**
	 * Resets the user's password if the password reset form was submitted and if global reset password page is selected
	 *
	 * @since 2.0
	 */
	public static function do_reset_password() {
		if ( ! self::is_global_reset_password_page_set() ) {
			return;
		}

		if ( 'POST' === FrmRegAppHelper::request_method() && isset( $_REQUEST[ 'rp_key' ] ) && isset( $_REQUEST[ 'rp_login' ] ) ) {
			$rp_key   = sanitize_text_field( $_REQUEST['rp_key'] );
			$rp_login = sanitize_text_field( $_REQUEST['rp_login'] );

			$user = check_password_reset_key( $rp_key, $rp_login );

			if ( ! $user || is_wp_error( $user ) ) {
				$query_args = self::get_reset_password_query_args_from_user( $user );
				FrmRegLoginController::redirect_to_selected_login_page( $query_args );

			} elseif ( isset( $_POST[ 'pass1' ] ) ) {

				self::redirect_if_passwords_not_equal( $rp_key, $rp_login );
				self::redirect_if_empty_password( $rp_key, $rp_login );
				self::redirect_if_password_weak( $rp_key, $rp_login );
				self::reset_password_and_redirect( $user );

			} else {
				esc_html_e( 'Invalid request.', 'frmreg' );
			}
		}
	}

	/**
	 * Redirect from reset password page when passwords are not equal
	 *
	 * @since 2.0
	 * @param string $rp_key
	 * @param string $rp_login
	 */
	private static function redirect_if_passwords_not_equal( $rp_key, $rp_login ) {
		if ( FrmAppHelper::get_post_param( 'pass1' ) != FrmAppHelper::get_post_param( 'pass2' ) ) {

			$query_args = array(
				'key'   => $rp_key,
				'login' => $rp_login,
				'errors' => 'password_reset_mismatch',
			);

			self::redirect_to_selected_reset_password_page( $query_args );
		}
	}

	/**
	 * If password is empty, redirect to reset password page with error parameters
	 *
	 * @param $rp_key
	 * @param $rp_login
	 */
	private static function redirect_if_empty_password( $rp_key, $rp_login ) {
		if ( empty( $_POST[ 'pass1' ] ) ) {
			$query_args = array(
				'key'   => $rp_key,
				'login' => $rp_login,
				'errors' => 'password_reset_empty',
			);
			self::redirect_to_selected_reset_password_page( $query_args );
		}
	}

	/**
	 * If password is weak, redirect to reset password page with error parameters.
	 *
	 * @since 2.05
	 *
	 * @param string $rp_key   Reset password key.
	 * @param string $rp_login Reset password username.
	 */
	private static function redirect_if_password_weak( $rp_key, $rp_login ) {
		/**
		 * Allows enabling or disabling the password strength check.
		 *
		 * @since 2.05
		 *
		 * @param bool $enabled Is `true` if password strength check is enabled.
		 */
		if ( ! apply_filters( 'frm_reg_password_strength_check', true ) ) {
			return;
		}

		$field_obj  = self::get_fake_password_field_obj();
		if ( ! is_callable( array( $field_obj, 'password_checks' ) ) ) {
			return;
		}

		$error_type = self::check_password( FrmAppHelper::get_post_param( 'pass1' ) );
		if ( $error_type ) {
			$query_args = array(
				'key'   => $rp_key,
				'login' => $rp_login,
				'errors' => 'weak_password_' . $error_type,
			);

			self::redirect_to_selected_reset_password_page( $query_args );
		}
	}

	/**
	 * Checks the password format. This is modified from FrmProFieldPassword::check_format().
	 *
	 * @since 2.05
	 *
	 * @param string $password The password.
	 * @return string|false Return `false` if check is valid, return the error type if invalid.
	 */
	private static function check_password( $password ) {
		$field_obj  = self::get_fake_password_field_obj();
		$error_type = false;
		foreach ( $field_obj->password_checks() as $type => $check ) {
			if ( ! $field_obj->check_regex( $check['regex'], $password ) ) {
				$error_type = $type;
				break;
			}
		}

		return $error_type;
	}

	/**
	 * Gets a fake password field type object.
	 *
	 * @since 2.05
	 *
	 * @return FrmProFieldPassword
	 */
	public static function get_fake_password_field_obj() {
		$field        = new stdClass();
		$field->name  = __( 'Password', 'frmreg' );
		$field->type  = 'password';
		$field_object = new FrmProFieldPassword( $field, 'password' );
		$defaults     = $field_object->get_new_field_defaults();

		// Set the invalid message to pull the "Passwords must contain at least one special character" string from Pro.
		$field->field_options['invalid'] = $defaults['field_options']['invalid'];

		return $field_object;
	}

	/**
	 * Reset password and redirect from reset password page if no errors
	 *
	 * @since 2.0
	 *
	 * @param object $user
	 */
	private static function reset_password_and_redirect( $user ) {
		reset_password( $user, FrmAppHelper::get_post_param( 'pass1' ) );

		$redirect_url = FrmRegLoginController::login_page_url( 'wordpress' );

		if ( strpos( $redirect_url, 'wp-login.php' ) === false ) {
			$redirect_url = add_query_arg( 'frm_message', 'pw_changed', $redirect_url );
		}

		wp_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	/**
	 * Check of a global reset password page is set
	 *
	 * @since 2.0
	 * @return string
	 */
	private static function is_global_reset_password_page_set() {
		return ( self::reset_password_page_id() );
	}

	/**
	 * Get the global reset password page ID
	 *
	 * @since 2.0
	 * @return string
	 */
	private static function reset_password_page_id() {
		$global_settings = new FrmRegGlobalSettings;
		return $global_settings->get_global_page( 'resetpass_page' );
	}

	/**
	 * Get the reset password page URL
	 *
	 * @since 2.0
	 * @param string $fallback
	 *
	 * @return false|string
	 */
	public static function reset_password_page_url( $fallback = 'wordpress' ) {
		$page_id = self::reset_password_page_id();

		if ( $page_id ) {
			$page_url = FrmRegAppHelper::get_page_url( $page_id );
		} else if ( $fallback === 'wordpress' ) {
			$page_url = site_url( 'wp-login.php?action=resetpass' );
		} else {
			$page_url = '';
		}

		return $page_url;
	}

	/**
	 * Prevent "pending" users from resetting their password
	 *
	 * @param bool $allow
	 * @param int $user_id
	 *
	 * @return bool
	 */
	public static function prevent_password_reset( $allow, $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( in_array( 'pending', (array) $user->roles ) ) {
			return false;
		}

		return $allow;
	}

}