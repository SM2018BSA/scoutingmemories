<?php

/**
 * @since 2.0
 */
class FrmRegProfileController {

	/**
	 * Show usermeta on profile page.
	 *
	 * @since 2.08 Added `$profile_user` parameter.
	 *
	 * @param WP_User|null $profile_user The current WP_User object.
	 * @return void
	 */
	public static function show_user_meta( $profile_user = null ) {
		global $profileuser;

		if ( ! $profile_user ) {
			$profile_user = $profileuser; // For backward compatibility.
		}

		/**
		 * @since 2.08
		 *
		 * @param WP_User|null $profile_user
		 */
		if ( ! apply_filters( 'frmreg_show_meta_on_profile', true, $profile_user ) ) {
			return;
		}

		$meta_keys = array();

		// Get register user actions for all forms
		$register_actions = FrmFormAction::get_action_for_form( 'all', 'register' );

		foreach ( $register_actions as $opts ) {
			if ( ! isset( $opts->post_content['reg_usermeta'] ) || empty( $opts->post_content['reg_usermeta'] ) ) {
				continue;
			}

			foreach ( $opts->post_content['reg_usermeta'] as $user_meta_vars ) {
				$meta_keys[ $user_meta_vars['meta_name'] ] = $user_meta_vars['field_id'];
			}
		}

		//TODO: prevent duplicate user meta from showing

		// Return early if $meta_keys is empty.
		if ( ! $meta_keys ) {
			return;
		}

		// Make sure at least one meta key value is not empty.
		$has_at_least_one_row = false;
		foreach ( $meta_keys as $meta_key => $field_id ) {
			if ( ! empty( $profile_user->{$meta_key} ) ) {
				$has_at_least_one_row = true;
				break;
			}
		}

		if ( ! $has_at_least_one_row ) {
			return;
		}

		$user_can_edit_entries = current_user_can( 'frm_edit_entries' );

		include FrmRegAppHelper::path() . '/views/show_usermeta.php';
	}
	
	/**
	 * @deprecated 2.01
	 */
	public static function get_avatar( $avatar, $id_or_email, $size = '96', $default = '', $alt = false, $args = array() ) {
		_deprecated_function( __FUNCTION__, '2.01', 'FrmRegAvatarController::get_avatar' );
		return FrmRegAvatarController::get_avatar( $avatar, $id_or_email, $size, $default, $alt, $args );
	}

}
