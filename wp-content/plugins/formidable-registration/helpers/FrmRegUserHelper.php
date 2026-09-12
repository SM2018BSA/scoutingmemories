<?php

/**
 * @since 2.0
 */
class FrmRegUserHelper {

	/**
	 * Log a user in if the "Login" option is selected and auto-generate password is not selected
	 *
	 * @since 2.0
	 *
	 * @param array $settings
	 * @param FrmRegUser $user
	 */
	public static function log_user_in( $settings, $user ) {
		if ( ! self::should_log_user_in( $settings ) || is_user_logged_in() ) {
			return;
		}

		/**
		 * Fires before logging user in.
		 *
		 * @since 2.05
		 *
		 * @param array $args {
		 *     The arguments.
		 *
		 *     @type array      $settings Registration form action settings.
		 *     @type FrmRegUser $user     User object.
		 * }
		 */
		do_action( 'frm_reg_before_log_user_in', compact( 'settings', 'user' ) );

		wp_set_current_user( $user->get_user_id(), $user->get_username() );

		$credentials = array(
			'user_login'    => $user->get_username(),
			'user_password' => $user->get_password(),
			'remember'      => false,
		);

		/*
		 * Don't use wp_signon() because some security plugins disable it.
		 * The following code is grabbed from wp_signon(), @since tags are WordPress versions, not add-on versions.
		 */

		/**
		 * Filters whether to use a secure sign-on cookie.
		 *
		 * @since 3.1.0
		 *
		 * @param bool  $secure_cookie Whether to use a secure sign-on cookie.
		 * @param array $credentials {
		 *     Array of entered sign-on data.
		 *
		 *     @type string $user_login    Username.
		 *     @type string $user_password Password entered.
		 *     @type bool   $remember      Whether to 'remember' the user. Increases the time
		 *                                 that the cookie will be kept. Default false.
		 * }
		 */
		$secure_cookie = apply_filters( 'secure_signon_cookie', is_ssl(), $credentials );

		wp_set_auth_cookie( $user->get_user_id(), $credentials['remember'], $secure_cookie );

		/**
		 * Fires after the user has successfully logged in.
		 *
		 * @since 1.5.0
		 *
		 * @param string  $user_login Username.
		 * @param WP_User $user       WP_User object of the logged-in user.
		 */
		do_action( 'wp_login', $user->get_username(), $user->get_user() );

		// wp_signon() doesn't handle this.
		wp_set_current_user( $user->get_user_id(), $user->get_username() );
	}

	/**
	 * Check settings to see if auto login option is checked and password option is mapped to field
	 *
	 * @since 2.0
	 *
	 * @param array $settings
	 *
	 * @return bool
	 */
	public static function should_log_user_in( $settings ) {
		return ( isset( $settings['login'] ) && $settings['login']
		         && isset( $settings['reg_password'] ) && is_numeric( $settings['reg_password'] ) );
	}

}