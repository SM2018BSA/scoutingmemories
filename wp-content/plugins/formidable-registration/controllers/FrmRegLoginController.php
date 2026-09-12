<?php

/**
 * @since 2.0
 */
class FrmRegLoginController {

	/**
	 * Login limitation error code.
	 *
	 * @since 2.05
	 *
	 * @var string
	 */
	public static $login_limit_error = 'login_limit_exceeded';

	/**
	 * Redirect the user to the custom login page instead of wp-login.php
	 *
	 * @since 2.0
	 */
	public static function redirect_to_custom_login() {
		if ( 'GET' === FrmRegAppHelper::request_method() && ! isset( $_GET['interim-login'] ) ) {

			$args = array();
			foreach ( array( 'redirect_to', 'checkemail' ) as $param ) {
				$request_param_string = self::get_request_param_string( $param );

				if ( '' === $request_param_string ) {
					continue;
				}

				$args[ $param ] = $request_param_string;
			}

			self::redirect_to_selected_login_page( $args );
		}
	}

	/**
	 * @since 2.10
	 * 
	 * @param mixed $param
	 *
	 * @return string
	 */
	private static function get_request_param_string( $param ) {
		if ( empty( $_REQUEST[ $param ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$param_value = $_REQUEST[ $param ];
		if ( is_string( $param_value ) ) {
			return rawurlencode( $param_value );
		}

		if ( is_array( $param_value ) && count( $param_value ) === 1 ) {
			$first_element = reset( $param_value );
			if ( is_string( $first_element ) ) {
				return rawurlencode( $first_element );
			}
		}

		return '';
	}

	/**
	 * Redirect the user after authentication if there were any errors.
	 *
	 * @since 2.0
	 * @param WP_User|WP_Error  $user       The signed in user, or the errors that have occurred during login.
	 *
	 * @return WP_User|WP_Error The logged in user, or error information if there were errors.
	 */
	public static function redirect_at_authenticate_when_error( $user ) {
		if ( 'POST' === FrmRegAppHelper::request_method() && ! isset( $_POST['interim-login'] ) ) {

			// If it's a REST API request, don't interfere with login page.
			if ( 'application/json' === FrmAppHelper::get_server_value( 'CONTENT_TYPE' ) ) {
				return $user;
			}

			$login_url = self::login_page_url( 'none' );
			self::maybe_set_login_limit_exceeded_error( $user );

			if ( $login_url && is_wp_error( $user ) ) {

				self::run_login_failed_hooks( FrmAppHelper::get_post_param( 'log' ), $user );

				self::add_error_code_to_query_string( $user, $login_url );
				self::add_error_message_text_to_query_string( $user, $login_url );
				self::add_posted_redirect_to_query_string( $login_url );

				wp_redirect( esc_url_raw( $login_url ) );
				exit;
			}
		}

		return $user;
	}

	/**
	 * Checks if login limit exceeded, change $user to WP_Error object.
	 *
	 * @since 2.05
	 *
	 * @param WP_User|WP_Error $user User object or error.
	 */
	private static function maybe_set_login_limit_exceeded_error( &$user ) {
		$error_message = self::get_login_limit_exceeded_error_message();
		if ( ! $error_message ) {
			return;
		}

		$user = new WP_Error();
		$user->add( self::$login_limit_error, $error_message );
	}

	/**
	 * Gets login limit exceeded error message.
	 *
	 * @since 2.05
	 *
	 * @return string Return empty string if no errors.
	 */
	private static function get_login_limit_exceeded_error_message() {
		$message = FrmRegSessionErrorController::get_error_from_session( self::$login_limit_error );

		if ( $message ) {
			// This message shouldn't be filtered on the redirected page.
			return $message;
		}

		if ( function_exists( 'loginizer_can_login' ) && ! loginizer_can_login() ) {
			$message = $GLOBALS['lz_error']['ip_blocked'];
		} elseif ( class_exists( 'Limit_Login_Attempts' ) && ! empty( $GLOBALS['limit_login_attempts_obj'] ) ) {
			$message = $GLOBALS['limit_login_attempts_obj']->get_message();
		}

		/**
		 * Allows 3rd-party plugins to add custom login limitation checks.
		 *
		 * @since 2.05
		 *
		 * @param string $message Login limit exceeded message. Leave empty if no errors.
		 */
		$message = apply_filters( 'frm_reg_login_limit_exceeded_error_message', $message );

		if ( $message ) {
			FrmRegSessionErrorController::start_session();
			FrmRegSessionErrorController::add_error_to_session( self::$login_limit_error, $message );
		}

		return $message;
	}

	/**
	 * Checks if login limitation feature is available or not.
	 *
	 * @since 2.05
	 *
	 * @return bool
	 */
	public static function is_login_limit_feature_activated() {
		$activated = false;

		if ( function_exists( 'loginizer_can_login' ) || class_exists( 'Limit_Login_Attempts' ) ) {
			$activated = true;
		}

		/**
		 * Allows 3rd-party plugin to inform Formidable Registration that login limitation feature is available.
		 *
		 * @since 2.05
		 *
		 * @param bool $activated  Login limitation is activated or not.
		 */
		return apply_filters( 'frm_reg_login_limit_feature_activated', $activated );
	}

	/**
	 * Runs hooks when login failed.
	 *
	 * @since 2.05
	 *
	 * @param string   $username Username.
	 * @param WP_Error $error    Error object.
	 */
	private static function run_login_failed_hooks( $username, $error ) {
		if ( self::$login_limit_error !== $error->get_error_code() ) {
			do_action( 'wp_login_failed', $username, $error );
		}
	}

	/**
	 * Add the error code to a URL
	 *
	 * @since 2.0
	 *
	 * @param WP_Error $error
	 * @param string $login_url
	 */
	private static function add_error_code_to_query_string( $error, &$login_url ) {
		$error_code = self::get_error_code( $error );
		$login_url = add_query_arg( 'frmreg_error', $error_code, $login_url );
	}

	/**
	 * @param WP_Error $error
	 *
	 * @return mixed
	 */
	private static function get_error_code( $error ) {
		$error_codes = $error->get_error_codes();
		$error_code  = reset( $error_codes );

		// Do not show invalid username error to prevent user enumeration attack.
		if ( 'invalid_username' === $error_code ) {
			$error_code = 'incorrect_password';
		}

		return $error_code;
	}

	/**
	 * Add an error's message text to query string if it is an unknown error code
	 *
	 * @since 2.0
	 *
	 * @param WP_Error $error
	 * @param string $login_url
	 */
	private static function add_error_message_text_to_query_string( $error, &$login_url ) {
		$error_code = self::get_error_code( $error );

		if ( ! FrmRegMessagesHelper::is_known_error_code( $error_code ) ) {
			$error_message = urlencode( $error->get_error_message() );
			$login_url = add_query_arg( 'frm_message_text', $error_message, $login_url );
		}
	}

	/**
	 * Add posted redirect_to parameter to query args
	 *
	 * @since 2.0
	 * @param $login_url
	 */
	private static function add_posted_redirect_to_query_string( &$login_url ) {
		if ( ! empty( $_POST['redirect_to'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$login_url = add_query_arg( 'redirect_to', urlencode( $_POST['redirect_to'] ), $login_url );
		}
	}

	/**
	 * Returns the URL to which the user should be redirected after the (successful) login.
	 *
	 * @since 2.0
	 *
	 * @param string $redirect_to URL to redirect to
	 * @param string $request URL the user is coming from
	 * @param object $user
	 *
	 * @return string
	 */
	public static function redirect_after_login( $redirect_to, $request, $user ) {
		// TODO: maybe add global setting for this

		return $redirect_to;
	}

	/**
	 * Check if a global login page is set
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public static function is_global_login_page_set() {
		return ( self::login_page_id() );
	}

	/**
	 * Get global login page ID
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public static function login_page_id() {
		$global_settings = new FrmRegGlobalSettings();
		return $global_settings->get_global_page( 'login_page' );
	}

	/**
	 * Get the login page URL. If none is selected in global settings, return default login URL or blank.
	 *
	 * @since 2.0
	 *
	 * @param string $fallback
	 *
	 * @return string
	 */
	public static function login_page_url( $fallback ) {
		$page_id = self::login_page_id();

		if ( $page_id ) {
			$login_url = FrmRegAppHelper::get_page_url( $page_id );
		} elseif ( $fallback === 'wordpress' ) {
			$login_url = wp_login_url();
		} else {
			$login_url = '';
		}

		return $login_url;
	}

	/**
	 * Redirect to the selected login page
	 * No redirect occurs if global page is not selected
	 *
	 * @param array $query_args
	 */
	public static function redirect_to_selected_login_page( $query_args = array() ) {
		$redirect_url = self::login_page_url( 'none' );

		if ( $redirect_url ) {

			foreach ( $query_args as $key => $value ) {
				$redirect_url = add_query_arg( $key, $value, $redirect_url );
			}

			wp_redirect( esc_url_raw( $redirect_url ) );
			exit;
		}
	}

	/**
	 * Print success message on standard wp-login page when activation link is resent
	 * This function will only apply when users do not select a global login/logout page in their global settings
	 *
	 * @since 1.11
	 *
	 * @param string $message
	 * @return string $message
	 */
	public static function print_login_messages( $message ) {
		if ( isset( $_GET['frm_message'] ) && $_GET['frm_message'] === 'activation_sent' ) {
			$message = '<p class="message">' . FrmRegMessagesHelper::activation_sent_message() . '</p>';
		} elseif ( isset( $_GET['frmreg_error'] ) && $_GET['frmreg_error'] === 'invalid_key' ) {
			$message = '<div id="login_error">' . FrmRegMessagesHelper::activation_invalid_key_message() . '</div>';
		}

		return $message;
	}

	/**
	 * Prevent "pending" users from logging in
	 *
	 * @param WP_User|WP_Error $user
	 *
	 * @return WP_User|WP_Error
	 */
	public static function prevent_pending_login( $user ) {
		//If user has "Pending" role, don't let them in
		if ( $user instanceof WP_User && in_array( 'pending', (array) $user->roles, true ) ) {
			$moderate_type = (array) get_user_meta( $user->ID, 'frmreg_moderate', 1 );

			if ( in_array( 'email', $moderate_type, true ) ) {
				return new WP_Error( 'resend_activation_' . $user->ID, FrmRegMessagesHelper::resend_activation_message( $user->ID ) );
			}
		}

		return $user;
	}

	/**
	 * Checks if the current page contains the login form.
	 *
	 * @since 2.05
	 *
	 * @return bool
	 */
	public static function page_contains_login_form() {
		if ( ! is_singular() || is_user_logged_in() ) {
			return false;
		}

		$errors   = array();
		$page_key = 'login_page';
		$settings = new FrmRegGlobalSettings();

		$settings->check_page_content( get_queried_object_id(), $page_key, $errors );

		return empty( $errors );
	}
}
