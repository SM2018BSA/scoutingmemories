<?php


class CurrentUser {

	private $first_name;
	private $last_name;
	private $avatar;

	public $current_user;
	public $id;
	private $roles;
	private $all_caps;

	public $all_user_meta;

	public function __construct() {

		$this->current_user = wp_get_current_user();
		$this->id           = $this->current_user->ID;
		$this->roles        = $this->current_user->roles;
		$this->all_caps     = $this->current_user->allcaps;

		$this->first_name = $this->get_cuf_val( CU_FIRST_NAME_FID );
		$this->last_name  = $this->get_cuf_val( CU_LAST_NAME_FID );
		$this->avatar     = $this->get_cuf_val( CU_AVATAR_IMAGE_FID );

		$this->all_user_meta = get_user_meta( $this->id );



	}



	public static function update_usermeta_data( $user_id, $meta_key, $meta_value, $unique = false ) {
		if ( metadata_exists( 'user', $user_id, $meta_key ) ) {
			update_user_meta( $user_id, $meta_key, $meta_value, $unique );
		} else {
			add_user_meta( $user_id, $meta_key, $meta_value, $unique );
		}
	}


	private function get_cuf_val( $field_id ) {
		return FrmProEntriesController::get_field_value_shortcode( array(
			'field_id' => $field_id,
			'user_id'  => 'current'
		) );
	}






	// caps:
	//
	// create_posts
	//
	public function cap_allowed( $cap ) {
		return in_array( $cap, $this->all_caps );
	}

	// roles:
	//
	// index_contributor
	// administrator
	//
	public function role_allowed( $role ) {
		return in_array( $role, $this->roles );
	}


	public function show_user_roles() {

		$user_roles = 'none';

		if ( count( $this->roles ) == 1 ) {
			$user_roles = '<span class="small">' . @implode( ", ", $this->roles ) . '</span>';
		} elseif ( count( $this->roles ) > 1 ) {
			$user_roles = '<ul class="text-capitalize small">';
			foreach ( $this->roles as $role ) {
				$user_roles .= '<li>' . str_replace( "_", " ", $role ) . '</li>';
			}
			$user_roles .= '</ul>';
		}

		return $user_roles;
	}


	public function show_avatar() {
		return $this->avatar;
	}

	public function show_first_name() {
		return $this->first_name;
	}

	public function show_last_name() {
		return $this->last_name;
	}


}