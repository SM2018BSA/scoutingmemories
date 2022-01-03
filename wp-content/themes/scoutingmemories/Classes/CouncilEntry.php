<?php


class CouncilEntry extends Entry {

	public $active;
	public $name;
	public $number;
	public $council_slug;
	public $state;
	public $state_val;
	public $state_slug;
	public $start_date;
	public $end_date;
	public $older_council_link;
	public $has_merged;
	public $merged_councils;


	public function __construct( int $entry_id = 0, $merged_councils = array(), $get_merged = TRUE ) {
		if (!is_null($entry_id) && !is_string($entry_id)) { parent::__construct( (int)$entry_id ); }

		if ( is_null($entry_id)) { return false;}

		if ( $entry_id && !is_string($entry_id) ) {
			$this->entry_id    = $entry_id;
			$this->entry_array = $this->get_entry_array();

			if (!is_array($this->entry_array)) {
                $this->entry_array = array();
            }

			( array_key_exists( 'council_num',         $this->entry_array ) ? $this->number        = $this->entry_array['council_num']          : $this->number        = '' );
			( array_key_exists( 'council_name',        $this->entry_array ) ? $this->name          = $this->entry_array['council_name']         : $this->name          = '' );
			( array_key_exists( 'council_state',       $this->entry_array ) ? $this->state         = $this->entry_array['council_state']        : $this->state         = '' );
			( array_key_exists( 'council_state-value', $this->entry_array ) ? $this->state_val     = $this->entry_array['council_state-value']  : $this->state_val     = '' );

			( array_key_exists( 'council_slug',        $this->entry_array ) ? $this->council_slug  = $this->entry_array['council_slug']         : $this->council_slug  = '' );
			( array_key_exists( 'council_slug',        $this->entry_array ) ? $this->council_slug  = $this->entry_array['council_slug']         : $this->council_slug  = '' );
			( array_key_exists( 'council_start',       $this->entry_array ) ? $this->start_date    = $this->entry_array['council_start']        : $this->start_date    = '' );
			( array_key_exists( 'council_end',         $this->entry_array ) ? $this->end_date      = $this->entry_array['council_end']          : $this->end_date      = '' );
			( array_key_exists( 'council_active_1',    $this->entry_array ) ? $this->active        = $this->entry_array['council_active_1']     : $this->active        = '' );



			$this->get_state_slugs();

			if ( ! is_array( $this->state_slug ) ) {
				$this->state_slug = array( $this->state_slug );
			}




			if ($get_merged) {
				$this->find_merged();

				if ( $this->has_merged ) {

					$this->get_merged_councils( $merged_councils );
				}

			}

		}

		return true;

	}


	public function setup_hooks() {
		add_action( 'frm_after_create_entry', array( &$this, 'frm_after_create_entry' ), 30, 2 );
		add_filter( 'frm_setup_new_fields_vars', array( &$this, 'frm_add_council_number' ), 30, 2 );
		add_filter( 'frm_setup_edit_fields_vars', array( &$this, 'frm_add_council_number' ), 30, 2 );
	}


	public static function frm_add_council_number( $values, $field ) {

		if ( $field->id == NUR_ASSIGNED_COUNCIL_FID || $field->id == EU_ASSIGNED_COUNCIL_FID ) {
			if ( ! is_array( $values['options'] ) ) {
				return $values;
			}
			asort( $values['options'] ); // sort the values here

			CouncilEntry::add_council_number( $values );

			foreach ( $values['options'] as $key => $value ) {
				$val = Entry::get_field_val( AACOUNCIL_COUNCIL_ACTIVE_FID, $key );
				if ( $key == '' ) {
					unset( $values['options'][ $key ] );
				}
				if ( $val == 'No' ) {
					unset( $values['options'][ $key ] );
				}
			}
		}


		if ( $field->id == SM1_COUNCIL_FID
		     || $field->id == SM2_COUNCIL_FID
		     || $field->id == SM3_COUNCIL_FID
		     || $field->id == EAD_COUNCIL_FID
		     || $field->id == AAP_COUNCIL_FID
		     || $field->id == AACAMP_COUNCIL_FID
		     || $field->id == AALODGE_COUNCIL_FID
		     || $field->id == AACOUNCIL_OLDER_NAME_FID
		     || $field->id == AACAMP_LINKED_COUNCIL_FID
		     || $field->id == AALODGE_LINKED_COUNCIL_FID
		     || $field->id == NUR_DEFAULT_COUNCIL_FID
		) {

			if ( isset( $values['options'] ) && $values['options'] != '' ) {
				foreach ( $values['options'] as $key => $value ) {
					if ( $key == '' ) {
						unset( $values['options'][ $key ] );
					}
				}
			}

			CouncilEntry::add_council_number( $values );
		}


		return $values;
	}


	private static function add_council_number( &$values ) {
		if ( isset( $values['options'] ) && $values['options'] != '' ) {
			foreach ( $values['options'] as $key => $value ) {
				$val                       = Entry::get_field_val( AACOUNCIL_NUMBER_FID, $key );
				$values['options'][ $key ] .= ' (#' . $val . ')';
			}
		}

	}

	private function get_merged_councils( $merged_councils ) {

		foreach ( $this->older_council_link as $item ) {

			$entry_id = null;

			if ( isset( $item['cm_old_council_name-value'] ) ) {
				$entry_id = $item['cm_old_council_name-value'];
			}

			if ( Entry::search( $merged_councils, "cm_old_council_name-value", $entry_id ) || $entry_id == null ) {
				$skip_it = true;
			} else {
				$skip_it = false;
			}

			if ( ! $skip_it ) {
				$this->merged_councils[] = $item;
				$entry                   = new CouncilEntry( $entry_id, $this->merged_councils );
				if ( $entry->has_merged && $entry->merged_councils != null ) {
					foreach ( $entry->merged_councils as $merged_council ) {
						$this->merged_councils[] = $merged_council;
					}

				}
			}


		}

	}


	private function get_state_slugs() {

		if ( is_array( $this->state_val ) ) {

			foreach ( $this->state_val as $state ) {
				$state              = new Entry( $state );
				$this->state_slug[] = $state->entry_array['state_acl'];
			}

		} else {
			$state            = new Entry( $this->state_val );
			if (!is_null($state->entry_array)) { $this->state_slug = $state->entry_array['state_acl'];}
		}
	}

	// returns true or false if the entry has merged councils or not
	private function find_merged() {

		if ( array_key_exists( 'older_council_link', $this->entry_array ) ) {

			$this->older_council_link = array_values( $this->entry_array['older_council_link'] );
			array_shift( $this->older_council_link );

			if ( count( $this->older_council_link[0] ) == 0 ) {
				$this->has_merged = false;
			}
			$this->has_merged = true;


		} else {
			//echo 'no older council key ';
			$this->has_merged = false;
		}


	}


	public static function frm_after_create_entry( $entry_id, $form_id ) {

		if ( $form_id === ADD_A_COUNCIL_FORMID ) {

			$args                 = array();
			$council_name         = sanitize_text_field( $_POST['item_meta'][ AACOUNCIL_NAME_FID ] );
			$args['council_name'] = isset( $council_name ) ? $council_name : '';

			$council_num         = sanitize_text_field( $_POST['item_meta'][ AACOUNCIL_NUMBER_FID ] );
			$args['council_num'] = isset( $council_num ) ? $council_num : '';

			$council_name = clean( $council_name );
			$council_name = $council_name . '_' . $args['council_num'];

			// add the council_name to the hidden field for the cat slug
			Entry::update_an_entry( AACOUNCIL_COUNCIL_SLUG_FID, 'council_slug', $council_name, $entry_id );

			AdvancedCustomField::update_select( $council_name, wp_unslash( $args['council_name'] ), COUNCIL_ACF );

		}

		return;
	}



	//// // //
    ///
    /// This funciton updates Council indexing. Anything marked as active will have it's end date updated to the current year
    /// Echo the result to show how many indexes were updated.
    //
	public static function update_active() {
        $council_entries  = FrmEntry::getAll(['form_id' => ADD_A_COUNCIL_FORMID ], '', '', true   );
        $active_councils = array();
        $current_year = date("Y");
        foreach ($council_entries as $key => $value) {
            if ($value->metas[AACOUNCIL_COUNCIL_ACTIVE_FID] == 'Yes') {
                $active_council = new Entry($value->id);
                $active_council->entry_array['council_end'] = (string)$current_year;
                Entry::update_an_entry( AACOUNCIL_END_DATE_FID, 'council_end', (string)$current_year, $active_council->entry_id);
                $active_councils[] = $active_council;
            }
        }
        return count($active_councils);
    }









}