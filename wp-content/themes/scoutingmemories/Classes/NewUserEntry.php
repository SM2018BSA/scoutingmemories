<?php
/**
 * New user entries and the account forms that edit them.
 *
 * Wraps a Formidable "New User Registration" entry together with the WordPress
 * user it created, and hooks into Formidable so the admin "Edit Users" form
 * (EDIT_USERS_FORMID) and the "Defaults" form on the My Account page
 * (EDIT_USER_DEFAULTS_FORMID) read from and write back to that user.
 *
 * @package ScoutingMemories
 */

/**
 * A New User Registration entry and the WordPress user behind it.
 *
 * Built with an entry ID, it loads the entry, the matching WordPress user, that
 * user's roles and the regions, states and councils they are assigned to. Built
 * without one (as functions.php does), it only serves as the holder for the
 * Formidable hooks registered by setup_hooks().
 *
 * @see Entry
 */
class NewUserEntry extends Entry {

	/**
	 * Display value of the entry's user field (the WordPress username).
	 *
	 * @var string|null
	 */
	public $user_id;

	/**
	 * Stored value of the entry's user field (the WordPress user ID).
	 *
	 * @var string|null
	 */
	public $user_id_value;

	/**
	 * Every stored value of the user's "assigned_region_slug" meta.
	 *
	 * @var string[]|null Null when the user has no such meta.
	 */
	public $assigned_region_slug;

	/**
	 * Every stored value of the user's "assigned_state" meta (state entry IDs).
	 *
	 * @var string[]|null Null when the user has no such meta.
	 */
	public $assigned_state;

	/**
	 * Access-control slugs ("state_acl") of the user's assigned states.
	 *
	 * Uninitialized until get_state_slugs() finds at least one state.
	 *
	 * @var array
	 */
	public array $assigned_state_slugs;

	/**
	 * Every stored value of the user's "assigned_council" meta.
	 *
	 * @var string[]|null Null when the user has no such meta.
	 */
	public $assigned_council;

	/**
	 * The WordPress user the entry belongs to.
	 *
	 * @var WP_User|false|null False when no user has the stored ID.
	 */
	private $wp_user;

	/**
	 * All of the user's meta, as returned by get_user_meta() without a key.
	 *
	 * @var array<string, string[]>|false|null
	 */
	public $wp_user_meta;

	/**
	 * The user's role slugs.
	 *
	 * @var string[]|null
	 */
	public $roles;

	// Example of $this->entry_array for a New User Registration entry:
	// public 'entry_array' =>
	// array (size=20)
	// 'nur_avatar_image' => string 'http://storage.scoutingmemories.org/formidable/17/dd18c206-scouting-memories.png' (length=80)
	// 'nur_avatar_image-value' => string '884' (length=3)
	// 'nur_first_name' => string 'Roger' (length=5)
	// 'nur_last_name' => string 'Ellis' (length=5)
	// 'nur_email_address' => string 'sm2018bsa@gmail.com' (length=19)
	// 'nur_dt_state' => string 'New York' (length=8)
	// 'nur_dt_state-value' => string '336' (length=3)
	// 'nur_dt_council' => string 'General Herkimer Council' (length=24)
	// 'nur_dt_council-value' => string '378' (length=3)
	// 'nur_f_why' => string 'Historian' (length=9)
	// 'nur_f_active_assigned_council' => string 'No' (length=2)
	// 'nur_f_assigned_state' => string 'Colorado' (length=8)
	// 'nur_f_assigned_state-value' => string '310' (length=3)
	// 'nur_f_assigned_council' => string 'Pike's Peak Council' (length=19)
	// 'nur_f_assigned_council-value' => string '1217' (length=4)
	// 'nur_f_assigned_council_slug' => string 'Pike_s_Peak_1960' (length=16)
	// 'nur_user_id' => string 'sm2018bsa' (length=9)
	// 'nur_user_id-value' => string '16' (length=2)
	// 'nur_s_assigned_council_slug' => string 'Pike_s_Peak_1960' (length=16)
	// 'nur_s_council_slug' => string 'general_herkimer_400' (length=20)
	//

	/**
	 * Loads a New User Registration entry and the WordPress user it belongs to.
	 *
	 * Only numeric IDs load anything: an entry key (a string) or no ID at all
	 * leaves every property empty. The return statements have no effect, since
	 * PHP discards a constructor's return value.
	 *
	 * @param int|string|null $entry_id Optional. Formidable entry ID. Default null.
	 */
	public function __construct( $entry_id = null ) {
		if ( ! is_null( $entry_id ) && ! is_string( $entry_id ) ) {
			parent::__construct( (int) $entry_id );
		}

		if ( is_null( $entry_id ) ) {
			return false;
		}

		if ( $entry_id && ! is_string( $entry_id ) ) {
			//$entry = new Entry( $entry_id );

			$this->user_id       = $this->entry_array['nur_user_id'];
			$this->user_id_value = $this->entry_array['nur_user_id-value'];

			$this->wp_user = get_userdata( $this->user_id_value );
			$this->roles   = $this->wp_user->roles;

			$this->wp_user_meta = get_user_meta( $this->wp_user->ID );

			// get_user_meta() without a key returns every value of each key, so these are arrays.
			$this->assigned_region_slug = $this->wp_user_meta['assigned_region_slug'];
			$this->assigned_state       = $this->wp_user_meta['assigned_state'];
			$this->assigned_council     = $this->wp_user_meta['assigned_council'];

			$this->get_state_slugs();
		}

		return true;
	}

	/**
	 * Registers the Formidable hooks for the Edit Users and Defaults forms.
	 *
	 * Called once from functions.php on an instance built without an entry ID.
	 *
	 * @return void
	 */
	public function setup_hooks() {
		add_filter( 'frm_pre_create_entry', array( &$this, 'frm_edit_users' ), 30, 1 );
		add_filter( 'frm_get_default_value', array( &$this, 'set_user_default_values' ), 10, 3 );
		add_filter( 'frm_setup_new_fields_vars', array( &$this, 'frm_setup_new_fields_vars' ), 20, 2 );
	}

	/**
	 * Fills $assigned_state_slugs from the state entries in $assigned_state.
	 *
	 * Each assigned state is a Formidable entry ID; its "state_acl" field holds
	 * the state's access-control slug.
	 *
	 * @throws TypeError If $assigned_state is a single ID whose "state_acl" is a
	 *                   string, since $assigned_state_slugs only accepts arrays.
	 *                   Not reached in practice: user meta read in the
	 *                   constructor is always an array.
	 *
	 * @return void
	 */
	private function get_state_slugs() {
		if ( is_array( $this->assigned_state ) ) {
			foreach ( $this->assigned_state as $state ) {
				$state                        = new Entry( $state );
				$this->assigned_state_slugs[] = $state->entry_array['state_acl'];
			}
		} else {
			$state = new Entry( $this->assigned_state );
			if ( ! is_null( $state->entry_array ) ) {
				$this->assigned_state_slugs = $state->entry_array['state_acl'];
			}
		}
	}

	/**
	 * Pre-fills the Edit Users form with the chosen user's region and states.
	 *
	 * Hooked to Formidable's `frm_setup_new_fields_vars` filter, which runs for
	 * every field of every form being displayed. The user comes from the
	 * `?entry=` request parameter (a New User Registration entry ID). Without
	 * it, the field is left exactly as Formidable built it.
	 *
	 * The filter's return value must always be the field array: returning
	 * anything else makes Formidable stop the whole page with a fatal error.
	 *
	 * @see 'frm_setup_new_fields_vars'
	 *
	 * @param array  $values Field settings and value Formidable will display.
	 * @param object $field  The field row being set up.
	 * @return array The field settings, with the default value set for the
	 *               Assigned Region and Assigned State fields.
	 */
	public function frm_setup_new_fields_vars( $values, $field ) {
		if ( $field->id == EU_ASSIGNED_REGION_FID ) {
			$entry_id = get_request_parameter( 'entry' );
			if ( $entry_id == null ) {
				return $values;
			}

			$new_user  = new NewUserEntry( (int) $entry_id );
			$new_value = $new_user->assigned_region_slug;

			// Data fields store the ID of the entry holding the value, not the value itself.
			$values['dyn_default_value'] = $values['default_value'] = $values['value'] = Entry::get_field_id_from_key( $new_value );
		}

		if ( $field->id == EU_ASSIGNED_STATE_FID ) {
			$entry_id = get_request_parameter( 'entry' );
			if ( $entry_id == null ) {
				return $values;
			}

			$new_user  = new NewUserEntry( (int) $entry_id );
			$new_value = $new_user->assigned_state_slugs;

			$values['dyn_default_value'] = $values['default_value'] = $values['value'] = Entry::get_field_id_from_key( $new_value );
		}

		return $values;
	}

	/**
	 * Pre-selects the chosen user's current roles in the Edit Users form.
	 *
	 * Hooked to Formidable's `frm_get_default_value` filter. The user comes from
	 * the `?entry=` request parameter; without it, the default is left as is.
	 * The filter's third argument is registered but not needed here.
	 *
	 * @see 'frm_get_default_value'
	 *
	 * @param mixed  $new_value The field's default value.
	 * @param object $field     The field row.
	 * @return mixed The user's role slugs (string[]) for the Set Role field;
	 *               otherwise $new_value unchanged.
	 */
	public function set_user_default_values( $new_value, $field ) {
		if ( $field->id == EU_SET_ROLE_FID ) {
			$entry_id = get_request_parameter( 'entry' );
			if ( $entry_id == null ) {
				return $new_value;
			}

			$new_user   = new NewUserEntry( (int) $entry_id );
			$new_values = array();
			foreach ( $new_user->roles as $role ) {
				$new_values[] = $role;
			}

			$new_value = $new_values;
		}

		return $new_value;
	}

	/**
	 * Saves the Edit Users and Defaults forms to the matching WordPress user.
	 *
	 * Hooked to Formidable's `frm_pre_create_entry` filter, so it runs before
	 * any form's entry is saved and must always return the entry values.
	 *
	 * - Edit Users form: copies the assigned state, council, region and their
	 *   active flags to both the New User Registration entry (from `?entry=`)
	 *   and the user's meta, and replaces the user's roles with those picked.
	 * - Defaults form: stores the picked defaults (state, council, credits and
	 *   so on) in the current user's meta.
	 *
	 * @see 'frm_pre_create_entry'
	 *
	 * @param array $values The entry about to be created. Keys used: 'form_id'
	 *                      and 'item_meta' (submitted values keyed by field ID).
	 * @return array The entry values, unchanged.
	 *
	 * @throws Error     If no WordPress user has the email stored on the entry.
	 * @throws TypeError If Set Role submits a single role as a string, which
	 *                   count() does not accept.
	 */
	public function frm_edit_users( $values ) {
		if ( $values['form_id'] == EDIT_USERS_FORMID ) {
			$entry_id = get_request_parameter( 'entry' );
			if ( $entry_id == null ) {
				return $values;
			}

			$user_email = Entry::get_field_val( NUR_EMAIL_FID, (int) $entry_id );
			$user       = get_user_by( 'email', $user_email );
			$roles      = $user->roles;

			$set_roles = $values['item_meta'][ EU_SET_ROLE_FID ];

			//update_entry($entry_id, NUR_ASSIGNED_STATE_FID,              $values['item_meta'][EU_ASSIGNED_STATE_FID]);

			// Formidable finds the meta by entry and field ID; the meta key argument is not used.
			Entry::update_an_entry( NUR_ASSIGNED_STATE_FID, 'nur_assigned_state_fk', $values['item_meta'][ EU_ASSIGNED_STATE_FID ], $entry_id );
			Entry::update_an_entry( NUR_ASSIGNED_COUNCIL_FID, 'nur_assigned_council_fk', $values['item_meta'][ EU_ASSIGNED_COUNCIL_FID ], $entry_id );
			Entry::update_an_entry( NUR_ASSIGNED_COUNCIL_ACTIVE_FID, 'nur_active_assigned_council_fk', $values['item_meta'][ EU_ASSIGNED_ACTIVE_COUNCIL_FID ], $entry_id );
			Entry::update_an_entry( NUR_ASSIGNED_REGION_SLUG_FID, 'nur_active_assigned_council_fk', $values['item_meta'][ EU_ASSIGNED_REGION_SLUG_FID ], $entry_id );
			Entry::update_an_entry( NUR_ASSIGNED_REGION_ACTIVE_FID, 'nur_active_assigned_council_fk', $values['item_meta'][ EU_ASSIGNED_ACTIVE_REGION_FID ], $entry_id );

			CurrentUser::update_usermeta_data( $user->ID, 'assigned_state', $values['item_meta'][ EU_ASSIGNED_STATE_FID ] );
			CurrentUser::update_usermeta_data( $user->ID, 'assigned_council', $values['item_meta'][ EU_ASSIGNED_COUNCIL_FID ] );
			CurrentUser::update_usermeta_data( $user->ID, 'active_assigned_council', $values['item_meta'][ EU_ASSIGNED_ACTIVE_COUNCIL_FID ] );
			CurrentUser::update_usermeta_data( $user->ID, 'assigned_region_slug', $values['item_meta'][ EU_ASSIGNED_REGION_SLUG_FID ] );
			CurrentUser::update_usermeta_data( $user->ID, 'active_assigned_region', $values['item_meta'][ EU_ASSIGNED_ACTIVE_REGION_FID ] );

			// Replace every current role with the picked one(s); nothing changes if none was picked.
			if ( $set_roles != '' ) {
				foreach ( $roles as $role ) {
					$user->remove_role( $role );
				}
				if ( count( $set_roles ) > 1 ) :
					foreach ( $set_roles as $set_role ) {
						$user->add_role( $set_role );
					}
				else :
					$user->set_role( $set_roles );
				endif;
			}
		}

		// map user meta info
		if ( $values['form_id'] == EDIT_USER_DEFAULTS_FORMID ) {
			$current_user_id = get_current_user_id();

			update_user_meta( $current_user_id, 'user_state', $values['item_meta'][ EAD_STATE_FID ] );
			update_user_meta( $current_user_id, 'user_council', $values['item_meta'][ EAD_COUNCIL_FID ] );
			update_user_meta( $current_user_id, 'user_camp', $values['item_meta'][ EAD_CAMP_FID ] );
			update_user_meta( $current_user_id, 'user_lodge', $values['item_meta'][ EAD_LODGE_FID ] );
			update_user_meta( $current_user_id, 'user_category', $values['item_meta'][ EAD_CATEGORY_FID ] );
			update_user_meta( $current_user_id, 'user_credit_author', $values['item_meta'][ EAD_AUTHOR_FID ] );
			update_user_meta( $current_user_id, 'user_photographer', $values['item_meta'][ EAD_PHOTOGRAPHER_FID ] );
			update_user_meta( $current_user_id, 'user_contributors', $values['item_meta'][ EAD_CONTRIBUTORS_FID ] );
			update_user_meta( $current_user_id, 'date_original', $values['item_meta'][ EAD_DATE_ORIGINAL_FID ] );
			update_user_meta( $current_user_id, 'user_date_digital', $values['item_meta'][ EAD_DATE_DIGITAL_FID ] );
			update_user_meta( $current_user_id, 'user_pub_digital', $values['item_meta'][ EAD_PUB_DIGITAL_FID ] );
			update_user_meta( $current_user_id, 'user_subject', $values['item_meta'][ EAD_SUBJECT_FID ] );
			update_user_meta( $current_user_id, 'user_location', $values['item_meta'][ EAD_LOCATION_FID ] );
			update_user_meta( $current_user_id, 'user_identifier', $values['item_meta'][ EAD_IDENTIFIER_FID ] );
			update_user_meta( $current_user_id, 'user_physical_description', $values['item_meta'][ EAD_PHY_DSC_FID ] );
			update_user_meta( $current_user_id, 'user_state_slug', $values['item_meta'][ EAD_STATE_SLUG_FID ] );
			update_user_meta( $current_user_id, 'user_council_slug', $values['item_meta'][ EAD_COUNCIL_SLUG_FID ] );
			update_user_meta( $current_user_id, 'user_camp_slug', $values['item_meta'][ EAD_CAMP_SLUG_FID ] );
			update_user_meta( $current_user_id, 'user_lodge_slug', $values['item_meta'][ EAD_LODGE_SLUG_FID ] );
		}

		return $values;
	}
}
