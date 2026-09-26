<?php
/**
 * Formidable entries.
 *
 * Base class for the theme's Formidable entry wrappers (councils, camps,
 * lodges, posts and new users), plus static helpers for reading and writing
 * entry values and looking entries up.
 *
 * @package ScoutingMemories
 */

require_once( get_template_directory() . '/Classes/Helpers.php' );

Helpers::check_file_access();

/**
 * One Formidable entry and helpers for working with entries.
 *
 * @see NewUserEntry
 * @see CouncilEntry
 * @see CampEntry
 * @see LodgeEntry
 * @see PostEntry
 */
class Entry {

	/**
	 * Formidable entry ID.
	 *
	 * @var int|string|null Null when the entry was built without an ID.
	 */
	public $entry_id;

	/**
	 * The entry's values keyed by field key, as returned by get_entry_array().
	 *
	 * Each field appears twice when it has a stored value that differs from
	 * what is displayed: "key" holds the displayed value and "key-value" the
	 * stored one (for example, the ID of a linked entry).
	 *
	 * @var array|null Null when the entry was built without an ID.
	 */
	public $entry_array;

	/**
	 * Loads an entry and its values.
	 *
	 * The return statement has no effect, since PHP discards a constructor's
	 * return value; it only stops loading when no ID is given.
	 *
	 * @param int|string|null $entry_id Optional. Formidable entry ID. Default null.
	 */
	public function __construct( $entry_id = null ) {
		if ( $entry_id == null ) {
			return null;
		}
		$this->entry_id    = $entry_id;
		$this->entry_array = $this->get_entry_array();
	}

	// public function set_entry() {
	// 	$this->entry = FrmProEntriesController::show_entry_shortcode( array( 'id' => $this->entry_id,  'plain_text' => 0, 'format' => 'text' ) );
	// }

	/**
	 * Gets every value of this entry.
	 *
	 * @return array Values keyed by field key (see $entry_array).
	 */
	public function get_entry_array() {
		return FrmProEntriesController::show_entry_shortcode( array( 'id' => $this->entry_id, 'format' => 'array' ) );
	}

	/**
	 * Replaces this entry's stored value for one field.
	 *
	 * @param int    $field_id Formidable field ID.
	 * @param string $meta_key Not used by Formidable, which finds the value by
	 *                         entry and field ID; kept for readability.
	 * @param mixed  $value    New value. Arrays are serialized.
	 * @return int|false Number of rows updated, or false on error or when
	 *                   $field_id is empty.
	 */
	public function update_entry( $field_id, $meta_key, $value ) {
		return FrmEntryMeta::update_entry_meta( $this->entry_id, $field_id, $meta_key, $value );
	}

	/**
	 * Adds a value for one field to this entry.
	 *
	 * Use update_entry() when the field may already have a value; this adds a
	 * new row and does not replace the old one.
	 *
	 * @param int    $field_id   Formidable field ID.
	 * @param string $meta_key   Not used by Formidable; kept for readability.
	 * @param mixed  $meta_value Value to add. Arrays are serialized.
	 * @return int ID of the new value row, or 0 if nothing was added.
	 */
	public function update_meta_entry( $field_id, $meta_key, $meta_value ) {
		return FrmEntryMeta::add_entry_meta( $this->entry_id, $field_id, $meta_key, $meta_value );
	}

	/**
	 * Finds the entry whose value in a given field matches a key.
	 *
	 * Compares with SQL LIKE, so "_" and "%" in $field_key act as wildcards
	 * (slugs such as "Pike_s_Peak_1960" contain underscores). Prints the
	 * database error, if any.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string     $field_key Value to look for, usually a slug.
	 * @param int|string $field_id  Formidable field ID to search. Added to the
	 *                              SQL unescaped, so it must be a trusted
	 *                              integer (such as one of the *_FID constants).
	 * @return string|null ID of the first matching entry, or null if none.
	 */
	public static function get_repeater_ids_from_key( $field_key, $field_id ) {
		global $wpdb;
		$entry_id = $wpdb->get_var( $wpdb->prepare( "SELECT item_id FROM $wpdb->prefix" . "frm_item_metas WHERE meta_value LIKE %s AND field_id=" . $field_id, $field_key ) );
		if ( $wpdb->last_error !== '' ) {
			$wpdb->print_error();
		}

		return $entry_id;
	}

	/**
	 * Finds the entry that has a given value in any field.
	 *
	 * Despite the name, this returns an entry ID, not a field ID. Used to turn
	 * a slug into the ID of the council, camp, lodge or state entry it names.
	 * An array of keys is joined with commas and matched as one value.
	 *
	 * Compares with SQL LIKE, so "_" and "%" act as wildcards. Prints the
	 * database error, if any.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string|string[] $field_key Value to look for, usually a slug.
	 * @return string|null ID of the first matching entry, or null if none.
	 */
	public static function get_field_id_from_key( $field_key ) {
		if ( is_array( $field_key ) ) {
			$field_key = implode( ",", $field_key );
		}
		global $wpdb;
		$entry_id = $wpdb->get_var( $wpdb->prepare( "SELECT item_id FROM $wpdb->prefix" . "frm_item_metas WHERE meta_value LIKE %s", $field_key ) );
		if ( $wpdb->last_error !== '' ) {
			$wpdb->print_error();
		}

		return $entry_id;
	}

	/**
	 * Replaces the stored value for one field of any entry.
	 *
	 * Static form of update_entry(). Note the argument order: the entry ID
	 * comes last.
	 *
	 * @param int        $field_id Formidable field ID.
	 * @param string     $meta_key Not used by Formidable; kept for readability.
	 * @param mixed      $value    New value. Arrays are serialized.
	 * @param int|string $entry_id Formidable entry ID.
	 * @return int|false Number of rows updated, or false on error or when
	 *                   $field_id is empty.
	 */
	public static function update_an_entry( $field_id, $meta_key, $value, $entry_id ) {
		return FrmEntryMeta::update_entry_meta( $entry_id, $field_id, $meta_key, $value );
	}

	/**
	 * Gets one field's displayed value, from an entry or from a user's entry.
	 *
	 * @param int             $field_id Formidable field ID.
	 * @param int|string      $entry_id Entry ID. Ignored when $user_id is given.
	 * @param int|string|null $user_id  Optional. Read the value from this
	 *                                  user's entry instead ("current" for the
	 *                                  logged-in user). Default null.
	 * @return string The field's value, or an empty string if there is none.
	 */
	public static function get_field_val( $field_id, $entry_id, $user_id = null ) {
		if ( is_null( $user_id ) ) {
			return FrmProEntriesController::get_field_value_shortcode( array(
				'field_id' => $field_id,
				'entry' => $entry_id
			) );
		} else {
			return FrmProEntriesController::get_field_value_shortcode( array(
				'field_id' => $field_id,
				'user_id' => $user_id
			) );
		}
	}

	/**
	 * Finds every array, at any depth, whose $key holds exactly $value.
	 *
	 * @param array  $array Array to search, of any depth.
	 * @param string $key   Key to check in each nested array.
	 * @param mixed  $value Value to match (strict comparison).
	 * @return array[] The matching arrays, empty if none match.
	 */
	public static function search( $array, $key, $value ) {

		$result = array();

		// RecursiveArrayIterator to traverse an
		// unknown amount of sub arrays within
		// the outer array.
		$arrIt = new RecursiveArrayIterator( $array );

		// RecursiveIteratorIterator used to iterate
		// through recursive iterators
		$it = new RecursiveIteratorIterator( $arrIt );

		foreach ( $it as $sub ) {

			// Current active sub iterator
			$subArray = $it->getSubIterator();

			if ( $subArray[ $key ] === $value ) {
				$result[] = iterator_to_array( $subArray );
			}
		}

		return $result;
	}

	/**
	 * Builds the query used by getEntryIds(), one piece at a time.
	 *
	 * Adapted from Formidable's own entry ID query. Drafts and the owning user
	 * are filtered here from $args, so callers don't need to add them to $where.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array|string $where    Conditions: an array of column => value
	 *                               pairs (prepared here), or a string that is
	 *                               already prepared and may end in GROUP BY.
	 * @param string       $order_by ORDER BY clause, or empty.
	 * @param string       $limit    LIMIT clause, or empty.
	 * @param bool         $unique   Whether to select DISTINCT IDs.
	 * @param array        $args {
	 *     Query options. is_draft, user_id and group_by are required.
	 *
	 *     @type bool|int $is_draft                        False for published
	 *                                                     entries only, 1 for
	 *                                                     drafts only, anything
	 *                                                     else for both.
	 *     @type int      $user_id                         Only this user's
	 *                                                     entries, if set.
	 *     @type string   $group_by                        GROUP BY column (array
	 *                                                     $where only).
	 *     @type bool     $return_parent_id                Select the parent entry
	 *                                                     ID instead. Default false.
	 *     @type bool     $return_parent_id_if_0_return_id Select the parent entry
	 *                                                     ID, or the entry's own ID
	 *                                                     when it has no parent.
	 *                                                     Default false.
	 * }
	 * @param string[]     $query    Query pieces, added to in place.
	 * @return void
	 */
	private static function get_ids_query( $where, $order_by, $limit, $unique, $args, array &$query ) {
		global $wpdb;
		$query[]  = 'SELECT';
		$defaults = array(
			'return_parent_id' => false,
			'return_parent_id_if_0_return_id' => false,
		);
		$args     = array_merge( $defaults, $args );

		if ( $unique ) {
			$query[] = 'DISTINCT';
		}

		if ( $args['return_parent_id_if_0_return_id'] ) {
			$query[] = 'IF ( e.parent_item_id = 0, it.item_id, e.parent_item_id )';
		} elseif ( $args['return_parent_id'] ) {
			$query[] = 'e.parent_item_id';
		} else {
			$query[] = 'it.item_id';
		}

		$query[] = 'FROM ' . $wpdb->prefix . 'frm_item_metas it LEFT OUTER JOIN ' . $wpdb->prefix . 'frm_fields fi ON it.field_id=fi.id';

		$query[] = 'INNER JOIN ' . $wpdb->prefix . 'frm_items e ON (e.id=it.item_id)';
		if ( is_array( $where ) ) {
			if ( ! $args['is_draft'] ) {
				$where['e.is_draft'] = 0;
			} elseif ( $args['is_draft'] == 1 ) {
				$where['e.is_draft'] = 1;
			}

			if ( ! empty( $args['user_id'] ) ) {
				$where['e.user_id'] = $args['user_id'];
			}
			$query[] = Database::prepend_and_or_where( ' WHERE ', $where ) . $order_by . $limit;

			if ( $args['group_by'] ) {
				$query[] = ' GROUP BY ' . sanitize_text_field( $args['group_by'] );
			}

			return;
		}

		$draft_where = '';
		$user_where  = '';
		if ( ! $args['is_draft'] ) {
			$draft_where = $wpdb->prepare( ' AND e.is_draft=%d', 0 );
		} elseif ( $args['is_draft'] == 1 ) {
			$draft_where = $wpdb->prepare( ' AND e.is_draft=%d', 1 );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$user_where = $wpdb->prepare( ' AND e.user_id=%d', $args['user_id'] );
		}

		if ( strpos( $where, ' GROUP BY ' ) ) {
			// don't inject WHERE filtering after GROUP BY
			$parts  = explode( ' GROUP BY ', $where );
			$where  = $parts[0];
			$where .= $draft_where . $user_where;
			$where .= ' GROUP BY ' . $parts[1];
		} else {
			$where .= $draft_where . $user_where;
		}

		// The query has already been prepared
		$query[] = Database::prepend_and_or_where( ' WHERE ', $where ) . $order_by . $limit;
	}

	/**
	 * Gets the IDs of the entries that match the given conditions.
	 *
	 * Results are cached (group "frm_entry") under a key built from all the
	 * arguments.
	 *
	 * @param array|string $where    Optional. Conditions; see get_ids_query().
	 *                               Default empty array.
	 * @param string       $order_by Optional. ORDER BY clause. Default empty.
	 * @param string       $limit    Optional. LIMIT clause. Default empty.
	 * @param bool         $unique   Optional. Whether to return each ID only once.
	 *                               Default true.
	 * @param array        $args {
	 *     Optional. Query options.
	 *
	 *     @type bool|int $is_draft Which entries to include; see get_ids_query().
	 *                              Default false (published only).
	 *     @type int      $user_id  Only this user's entries. Default empty (all).
	 *     @type string   $group_by GROUP BY column. Default empty.
	 * }
	 * @return string[]|string|null The matching entry IDs. With a $limit of
	 *                              exactly ' LIMIT 1', a single ID, or null if
	 *                              none match.
	 */
	public static function getEntryIds( $where = array(), $order_by = '', $limit = '', $unique = true, $args = array() ) {
		$defaults = array(
			'is_draft' => false,
			'user_id' => '',
			'group_by' => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$query = array();
		self::get_ids_query( $where, $order_by, $limit, $unique, $args, $query );

		$query = implode( ' ', $query );

		$cache_key = 'ids_' . Helpers::maybe_json_encode( $where ) . $order_by . 'l' . $limit . 'u' . $unique . Helpers::maybe_json_encode( $args );
		// One ID for a single-row limit, otherwise a column of IDs.
		$type = 'get_' . ( ' LIMIT 1' === $limit ? 'var' : 'col' );

		return Database::check_cache( $cache_key, 'frm_entry', $query, $type );
	}
}
