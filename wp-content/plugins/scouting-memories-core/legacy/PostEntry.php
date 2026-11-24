<?php


class PostEntry extends Entry {

	public $state_form_ids;
	public $council_form_ids;
	public $lodge_form_ids;
	public $camp_form_ids;
	public $post_id;
	public $my_entry;
	public $state_slugs;

	public Post $the_post;

	private $the_user;



	public function __construct( int $entry_id = 0, $the_user = null ) {
		parent::__construct( $entry_id );

		$this->entry_id = $entry_id;
		$this->the_user = &$the_user;

	}


	public function setup_hooks() {

        add_action( 'frm_after_create_entry',     array( &$this, 'frm_after_update_create_entry' ), 30, 2 );
        add_action( 'frm_after_update_entry',     array( &$this, 'frm_after_update_create_entry' ), 10, 2 );
		add_filter( 'frm_setup_new_fields_vars',  array( &$this, 'frm_setup_new_fields_vars' ), 20, 2 );
		add_filter( 'frm_rte_options',            array( &$this, 'frm_rte_options' ), 10, 2 );
	}


	// Add "Add Media" button to TinyMCE editor
	public function frm_rte_options($opts){
		$opts['media_buttons'] = true;
		return $opts;
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

        if ($field->id == AAP_LODGE_FID ) {
            // set defaults from user_meta
            foreach ( $this->the_user->all_user_meta['user_lodge'] as $value )
                $new_value[] = $value;
            $values['dyn_default_value'] = $values['default_value'] = $values['value'] = $new_value;
        }





		return $values;
	}









    public function frm_after_update_create_entry($entry_id, $form_id) {


        if ( ( $form_id == ADD_A_POST_FORMID ) && $entry_id ) {
            //echo "frm_after_create_entry";

            $entry = FrmEntry::getOne( $entry_id );
            $post_id = $entry->post_id;

            //  Get my slugs ////////////////////////
            $args  = array();
            $slugs = array();

            $state_form_ids   = $_POST['item_meta'][ AAP_STATES_FID ];
            $council_form_ids = $_POST['item_meta'][ AAP_COUNCIL_FID ];
            $lodge_form_ids   = $_POST['item_meta'][ AAP_LODGE_FID ];
            $camp_form_ids    = $_POST['item_meta'][ AAP_CAMP_FID ];
            $start_date       = $_POST['item_meta'][AAP_START_DATE_FID];
            $end_date         = $_POST['item_meta'][AAP_END_DATE_SLUG_FID];

            PostEntry::get_update_values( 'state_ids',   $state_form_ids, $args );
            PostEntry::get_update_values( 'council_ids', $council_form_ids, $args );
            PostEntry::get_update_values( 'camp_ids',    $camp_form_ids, $args );
            PostEntry::get_update_values( 'lodge_ids',   $lodge_form_ids, $args );

            //$state_slugs = PostEntry::get_state_slugs_from($args['state_ids']);
            $slugs['state_slugs']   = PostEntry::get_slugs_value( 'state_ids', $args );
            $slugs['council_slugs'] = PostEntry::get_slugs_value( 'council_ids', $args );
            $slugs['camp_slugs']    = PostEntry::get_slugs_value( 'camp_ids', $args );
            $slugs['lodge_slugs']   = PostEntry::get_slugs_value( 'lodge_ids', $args );

            foreach ( $slugs as $key => $value ) {
                PostEntry::update_meta_slugs( $key, $value, $post_id );
            }

            // this will update  taxonomy
            $state_tax = new Taxonomy( 'state', $post_id, $slugs['state_slugs']);
            $state_tax->update_tax();

            $council_tax = new Taxonomy('council', $post_id, $slugs['council_slugs']);
            $council_tax->update_tax();

            $lodge_tax = new Taxonomy('lodge', $post_id, $slugs['lodge_slugs']);
            $lodge_tax->update_tax();

            $camp_tax = new Taxonomy('camp', $post_id, $slugs['camp_slugs']);
            $camp_tax->update_tax();

            $start_date_tax = new Taxonomy('start_date', $post_id, (string)$start_date);
            $start_date_tax->update_tax();

            $end_date_tax = new Taxonomy('end_date', $post_id, (string)$end_date);
            $end_date_tax->update_tax();


        }

    }



/*
	public function frm_after_update_entry( $entry_id, $form_id ) {


		if ( ( $form_id == ADD_A_POST_FORMID ) && $entry_id ) {

            echo "frm_after_update_entry";


			// used to get my new posts ID
			$this->my_entry = FrmEntry::getOne( $entry_id );
			$post_id  = $this->my_entry->post_id;


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


		}// end of Add a Post



	}
*/


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


    static function get_state_slugs_from($state_form_ids) {

        $state_slugs = [];

        if ( is_array( $state_form_ids ) ) {
            foreach ( $state_form_ids as $state ) {
                $state              = new Entry( $state );
                $state_slugs[] = $state->entry_array['state_acl'];
            }
        } else {
            $state            = new Entry( $state_form_ids );
            if (!is_null($state->entry_array)) { $state_slugs = $state->entry_array['state_acl'];}
        }
        return $state_slugs;
    }





	private static function get_slugs_value( $which_arg, &$args ): array {
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


	private static function get_update_values( $which_arg, $which_ids, &$args ) {
		if ( isset( $which_ids ) && is_array( $which_ids ) ) {
			foreach ( $which_ids as $which_id ) :
				$which_id             = sanitize_text_field( $which_id );
				$args[ $which_arg ][] = isset( $which_id ) ? $which_id : '';
			endforeach;
		} else {
			$args[ $which_arg ][] = sanitize_text_field( $which_ids );
		}
	}

	private static function update_meta_slugs( $which_slug, $meta_value, $post_id ) {
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