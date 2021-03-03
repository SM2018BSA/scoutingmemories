<?php


class PostEntry extends Entry {

	public $state_form_ids;
	public $council_form_ids;
	public $lodge_form_ids;
	public $camp_form_ids;
	public $post_id;
	public $my_entry;
	public $state_slugs;

	private $the_user;



	public function __construct( int $entry_id = 0, $the_user = null ) {
		parent::__construct( $entry_id );

		$this->entry_id = $entry_id;

		$this->the_user = &$the_user;





	}
	public function setup_hooks() {
		add_action( 'frm_after_update_entry',     array( &$this, 'frm_after_update_entry' ), 20, 2 );
		add_filter( 'frm_setup_new_fields_vars',  array( &$this, 'frm_setup_new_fields_vars' ), 20, 2 );
		add_filter( 'frm_setup_edit_fields_vars', array( &$this, 'frm_setup_edit_fields_vars' ), 30, 2 );
	}


	public function frm_setup_new_fields_vars( $values, $field ) {



		if ( $field->id == AAP_STATES_FID ) {


			// set defaults from user_meta
			foreach ( $this->the_user->all_user_meta['user_state'] as $value )
				$new_value[] = $value;
			$values['dyn_default_value'] = $values['default_value'] = $values['value'] = $new_value;
		}

		if ($field->id == AAP_COUNCIL_FID ) {
			// set defaults from user_meta
			foreach ( $this->the_user->all_user_meta['user_council'] as $value )
				$new_value[] = $value;
			$values['dyn_default_value'] = $values['default_value'] = $values['value'] = $new_value;

		}

		if ($field->id == AAP_CAMP_FID ) {
			// set defaults from user_meta
			foreach ( $this->the_user->all_user_meta['user_camp'] as $value )
				$new_value[] = $value;
			$values['dyn_default_value'] = $values['default_value'] = $values['value'] = $new_value;
		}





		return $values;
	}






	public function frm_setup_edit_fields_vars( $values, $field ) {
		$frm_action = get_request_parameter( 'frm_action' );
		if ( $this->entry_id && $frm_action === 'edit' ) {

			$new_value = array();

			if ( $field->id == AAP_STATES_FID ) {

				foreach ( $values['value'] as $value ) {
					$new_value[] = Entry::get_field_id_from_key( $value );
				}
				$values['dyn_default_value'] = $values['default_value'] = $values['value'] = $new_value;

			}


		}

		return $values;
	}




	public function frm_after_update_entry( $entry_id, $form_id ) {


		if ( ( $form_id == ADD_A_POST_FORMID ) && $entry_id ) {
			// used to get my new posts ID
			$this->my_entry = FrmEntry::getOne( $entry_id );
			$this->post_id  = $this->my_entry->post_id;


			//  Get my slugs ////////////////////////
			$args  = array();
			$slugs = array();


			$this->state_form_ids   = $_POST['item_meta'][ AAP_STATES_FID ];
			$this->council_form_ids = $_POST['item_meta'][ AAP_COUNCIL_FID ];
			$this->lodge_form_ids   = $_POST['item_meta'][ AAP_LODGE_FID ];
			$this->camp_form_ids    = $_POST['item_meta'][ AAP_CAMP_FID ];

			$this->get_update_values( 'state_ids', $this->state_form_ids, $args );
			$this->get_update_values( 'council_ids', $this->council_form_ids, $args );
			$this->get_update_values( 'camp_ids', $this->camp_form_ids, $args );
			$this->get_update_values( 'lodge_ids', $this->lodge_form_ids, $args );

			$slugs['state_slugs']   = $this->get_slugs_value( 'state_ids', $args );
			$slugs['council_slugs'] = $this->get_slugs_value( 'council_ids', $args );
			$slugs['camp_slugs']    = $this->get_slugs_value( 'camp_ids', $args );
			$slugs['lodge_slugs']   = $this->get_slugs_value( 'lodge_ids', $args );

			$this->get_state_slugs();
			update_post_meta( $this->post_id, 'state', $this->state_slugs );
			$this->the_post = new Post($this->post_id); // this will update  taxonomy


			//$this->update_entry( AAP_FRM_ENTRY_ID, $entry_id, (string) $entry_id );

			foreach ( $slugs as $key => $value ) {
				$this->update_meta_slugs( $key, $value, $this->post_id );
			}
		}

	}



	private function get_state_slugs() {

		if ( is_array( $this->state_form_ids ) ) {

			foreach ( $this->state_form_ids as $state ) {
				$state              = new Entry( $state );
				$this->state_slugs[] = $state->entry_array['state_acl'];
			}

		} else {
			$state            = new Entry( $this->state_form_ids );
			if (!is_null($state->entry_array)) { $this->state_slugs = $state->entry_array['state_acl'];}
		}
	}




	private function get_slugs_value( $which_arg, &$args ) {
		$field_ids = array(
			"state_ids"   => AASTATE_STATE_ACL_FID,
			"council_ids" => AACOUNCIL_COUNCIL_SLUG_FID,
			"camp_ids"    => AACAMP_CAMP_SLUG_FID,
			"lodge_ids"   => AALODGE_LODGE_SLUG_FID
		);
		$slugs     = array();
		foreach ( $args[ $which_arg ] as $value ) {
			if ( (int) $value > 0 ) {
				$slugs[] = Entry::get_field_val( $field_ids[ $which_arg ], $value );
			} else {
				$slugs[] = '';
			}
		}

		return $slugs;
	}


	private function get_update_values( $which_arg, $which_ids, &$args ) {
		if ( isset( $which_ids ) && is_array( $which_ids ) ) {
			foreach ( $which_ids as $which_id ) :
				$which_id             = sanitize_text_field( $which_id );
				$args[ $which_arg ][] = isset( $which_id ) ? $which_id : '';
			endforeach;
		} else {
			$args[ $which_arg ][] = sanitize_text_field( $which_ids );
		}
	}

	private function update_meta_slugs( $which_slug, $meta_value, $post_id ) {
		$meta_key = array(
			"state_slugs"   => "state",
			"council_slugs" => "council",
			"camp_slugs"    => "camp",
			"lodge_slugs"   => "lodge"
		);


		if ( metadata_exists( 'post', $post_id, $meta_key[ $which_slug ] ) )  // we have one lets updated it
		{
			update_post_meta( $post_id, $meta_key[ $which_slug ], $meta_value );
		} else //we dont have one lets add a new one
		{
			add_post_meta( $post_id, $meta_key[ $which_slug ], $meta_value );
		}
	}

}