<?php


class LodgeEntry extends Entry {

	public $name;
	public $number;
	public $slug;
	public $active;
	public $start;
	public $end;

	private $lodge_state;
	private $lodge_state_val;
	public $lodge_state_slugs;

	private $council_names;
	private $council_names_val;
	public $council_slugs;


	private $council_state;
	private $council_state_val;

	private $author_uid;
	private $author_uid_val;


	public function __construct( int $entry_id = 0 ) {
		parent::__construct( $entry_id );
		if ( $entry_id ) {


			$this->entry_array = $this->get_entry_array();


			( array_key_exists( 'al_lodge_name',         $this->entry_array ) ? $this->name             = $this->entry_array['al_lodge_name']         : $this->name             = '' );
			( array_key_exists( 'al_lodge_number',       $this->entry_array ) ? $this->number           = $this->entry_array['al_lodge_number']       : $this->number           = '' );
			( array_key_exists( 'al_lodge_slug',         $this->entry_array ) ? $this->slug             = $this->entry_array['al_lodge_slug']         : $this->slug             = '' );
			( array_key_exists( 'al_lodge_active',       $this->entry_array ) ? $this->active           = $this->entry_array['al_lodge_active']       : $this->active           = '' );
			( array_key_exists( 'al_lodge_start',        $this->entry_array ) ? $this->start            = $this->entry_array['al_lodge_start']        : $this->start            = '' );
			( array_key_exists( 'al_lodge_end',          $this->entry_array ) ? $this->end              = $this->entry_array['al_lodge_end']          : $this->end              = '' );
			( array_key_exists( 'al_lodge_state',        $this->entry_array ) ? $this->lodge_state      = $this->entry_array['al_lodge_state']        : $this->lodge_state      = '' );
			( array_key_exists( 'al_lodge_state-value',  $this->entry_array ) ? $this->lodge_state_val  = $this->entry_array['al_lodge_state-value']  : $this->lodge_state_val  = '' );




			$this->get_state_slugs();


			//$state = new Entry( $this->lodge_state_val );

			if ( ! is_array( $this->lodge_state_slugs ) ) {				$this->lodge_state_slugs = array( $this->lodge_state_slugs );
			}


			( array_key_exists( 'al_council_state',       $this->entry_array ) ? $this->council_state      = $this->entry_array['al_council_state']          : $this->council_state      = '' );
			( array_key_exists( 'al_council_state-value', $this->entry_array ) ? $this->council_state_val  = $this->entry_array['al_council_state-value']    : $this->council_state_val  = '' );
			( array_key_exists( 'al_council_name',        $this->entry_array ) ? $this->council_names      = $this->entry_array['al_council_name']           : $this->council_names      = '' );
			( array_key_exists( 'al_council_name-value',  $this->entry_array ) ? $this->council_names_val  = $this->entry_array['al_council_name-value']     : $this->council_names_val  = '' );
			( array_key_exists( 'al_user_id',             $this->entry_array ) ? $this->author_uid         = $this->entry_array['al_user_id']                : $this->author_uid         = '' );
			( array_key_exists( 'al_user_id-value',       $this->entry_array ) ? $this->author_uid_val     = $this->entry_array['al_user_id-value']          : $this->author_uid_val     = '' );

			if ( ! array_key_exists( 'al_council_slug', $this->entry_array ) ) {


				$this->council_slugs = $this->get_council_slugs();

			} else {

				$this->council_slugs = $this->entry_array['al_council_slug-value'];

			}

			if ( ! is_array( $this->council_slugs ) ) {
				$this->council_slugs = array( $this->council_slugs );
			}


		}

	}

	public function setup_hooks() {
		add_action( 'frm_after_create_entry', array( &$this, 'frm_after_create_entry' ), 30, 2 );
		add_action( 'frm_where_filter', array( &$this, 'frm_where_filter' ), 10, 2 );
	}

	public static function frm_after_create_entry( $entry_id, $form_id ) {

		if ( $form_id === ADD_A_LODGE_FORMID ) {

			$args               = array();
			$lodge_name         = sanitize_text_field( $_POST['item_meta'][ AALODGE_NAME_FID ] );
			$args['lodge_name'] = isset( $lodge_name ) ? $lodge_name : 'none'; // field IDs from the form

			$lodge_num         = sanitize_text_field( $_POST['item_meta'][ AALODGE_NUMBER_FID ] );
			$args['lodge_num'] = isset( $lodge_num ) ? $lodge_num : '';

			$lodge_name = clean( $lodge_name );
			$lodge_name = $lodge_name . '_' . $args['lodge_num'];

			// add the lodge_name to the hidden field for the cat slug
			Entry::update_an_entry( AALODGE_LODGE_SLUG_FID, 'al_lodge_slug', $lodge_name, $entry_id );

			AdvancedCustomField::update_select( $lodge_name, wp_unslash( $args['lodge_name'] ), LODGE_ACF );

			// This will make sure council_slug is setup !
			$lodge = new LodgeEntry( $entry_id );

		}

		return;
	}

	public function frm_where_filter( $where, $args ) {
		if ( $args['display']->ID == ALL_LODGES_SEARCH_VIEWID ) {

			$search_val = array();

			foreach ( $args['where_val'] as $entry_id ) {
				$council      = new Entry( $entry_id );
				$search_val[] = $council->entry_array['council_slug'];
			}

			if ( $search_val ) {
				$where = "(meta_value like '%" . implode( $search_val ) . "%' and fi.id = " . AALODGE_COUNCIL_SLUG_FID . ")";
			}

		}

		return $where;
	}


	private function get_state_slugs() {


		if ( is_array( $this->lodge_state_val ) ) {

			foreach ( $this->lodge_state_val as $state ) {
				$state                     = new Entry( $state );
				$this->lodge_state_slugs[] = $state->entry_array['state_acl'];
			}

		} else {


			$state                   = new Entry( $this->lodge_state_val );
			$this->lodge_state_slugs = $state->entry_array['state_acl'];
		}


	}

	public function get_council_slugs() {
		if ( ! is_array( $this->council_names_val ) ) {

			$lodge_council = new Entry( $this->council_names_val );
			$council_slug  = $lodge_council->entry_array['council_slug'];

			$added = $this->update_meta_entry( AALODGE_COUNCIL_SLUG_FID, 'al_council_slug', $council_slug );
			if ( ! $added ) {
				$this->update_entry( AALODGE_COUNCIL_SLUG_FID, 'al_council_slug', $council_slug );
			}

			return $council_slug;


		} else {

			$lodge_councils = array();

			foreach ( $this->council_names_val as $council_id ) {
				$lodge_council    = new Entry( $council_id );
				$lodge_councils[] = $lodge_council->entry_array['council_slug'];
			}

			$added = $this->update_meta_entry( AALODGE_COUNCIL_SLUG_FID, 'al_council_slug', $lodge_councils );
			if ( ! $added ) {
				$this->update_entry( AALODGE_COUNCIL_SLUG_FID, 'al_council_slug', $lodge_councils );
			}

			return $lodge_councils;

		}

	}


}