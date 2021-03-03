<?php


class StateEntry extends Entry {

	public $name;
	public $state_acl;
	public $region;
	public $region_slug;




	public function __construct( int $entry_id = 0 ) {
		parent::__construct( $entry_id );

		$this->name = Entry::get_field_val(AASTATE_NAME_FID, $entry_id);
		$this->state_acl = Entry::get_field_val(AASTATE_STATE_ACL_FID, $entry_id);


	}











	public static function get_state_name($state_acl) {

		if (strpos ($state_acl, ','))
			$state_acl = explode(', ', $state_acl);

		$state_name = 'none';
		if (is_array($state_acl) && count($state_acl) == 1) {
			$entry_id = Entry::get_field_id_from_key($state_acl);
			$state_name =   Entry::get_field_val(AASTATE_NAME_FID, $entry_id)  ;
		} elseif (is_array($state_acl) && count($state_acl) > 1) {
			$state_name = array();
			foreach ($state_acl as $state) {
				$entry_id = Entry::get_field_id_from_key($state);
				$state_name[] =  Entry::get_field_val(AASTATE_NAME_FID, $entry_id) ;
			}

		}
		return $state_name;
	}

	public function get_state_name_html($state_acl)
	{

		if (strpos ($state_acl, ','))
			$state_acl = explode(', ', $state_acl);

		$state_name = 'none';
		if (is_array($state_acl) && count($state_acl) == 1) {
			$entry_id = Entry::get_field_id_from_key($state_acl);
			$state_name = '<span class="small">' . Entry::get_field_val(AASTATE_NAME_FID, $entry_id) . '</span>';
		} elseif (is_array($state_acl) && count($state_acl) > 1) {
			$state_name = '<ul class="small">';
			foreach ($state_acl as $state) {
				$entry_id = Entry::get_field_id_from_key($state);
				$state_name .= '<li>' . Entry::get_field_val(AASTATE_NAME_FID, $entry_id) . '</li>';
			}
			$state_name .= '</ul>';
		}

		return $state_name;
	}




}