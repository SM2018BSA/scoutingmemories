<?php
namespace ScoutingMemories\Core\Dashboard;

use ScoutingMemories\Core\Access;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which My Account tab is open, worked out on the server from the address.
 *
 * Only the tabs the current visitor can see are ever accepted (Access::visible_tabs), so
 * nothing from the address is printed back into the page and a tab that is not theirs opens
 * Home instead. Links made by the lists themselves (the indexing browser's
 * pills, search and paging; the posts list's paging) carry their own parameters but not
 * `tab`, so those parameters open the tab the list lives on.
 */
final class Tabs {

	/** Address parameter => the tab its list lives on. */
	private const OWNERS = array(
		'idx_tab'           => 'myIndexing',
		'idx_search'        => 'myIndexing',
		'idx_paged'         => 'myIndexing',
		'updated_end_dates' => 'myIndexing',
		'sm_paged'          => 'myPosts',
	);

	/** The main tab to open. $query is the request's query parameters ($_GET). */
	public static function current( array $query ): string {
		$visible = Access::visible_tabs();
		$asked   = isset( $query['tab'] ) && is_string( $query['tab'] ) ? $query['tab'] : '';
		if ( in_array( $asked, $visible, true ) ) {
			return $asked;
		}
		foreach ( self::OWNERS as $parameter => $tab ) {
			if ( isset( $query[ $parameter ] ) && in_array( $tab, $visible, true ) ) {
				return $tab;
			}
		}
		return 'myHome';
	}

	/** The Indexing sub-tab to open (the Formidable layout's Councils / Camps / Lodges). */
	public static function current_sub( array $query ): string {
		$asked = isset( $query['tab2'] ) && is_string( $query['tab2'] ) ? $query['tab2'] : '';
		return in_array( $asked, Access::allowed_sub_tabs(), true ) ? $asked : 'myCouncils';
	}
}
