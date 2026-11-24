<?php
namespace ScoutingMemories\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every who-sees-what decision for the dashboard and the add-* pages, in one place.
 *
 * Each method implements ONE row of the owner-approved gate mapping in docs/AUDIT.md
 * (section 3, with the 2026-10-31 amendment in section 11). Templates must call these
 * and never check capabilities directly, so the mapping stays the single source of truth.
 *
 * WHY ROLES AND NOT CAPABILITIES: on this site the historian and contributor roles
 * carry explicit capability DENIES (set with the Members plugin), and WordPress lets a
 * later role's `false` override an earlier role's `true`. Many real users hold several
 * roles (admin+historian is common), so capability checks like
 * current_user_can('add_council') return false even for administrators. Measured on
 * the live user list: capability gates would lock out 3 of the administrators and 3 of
 * the 7 indexing-team members. Role membership is what the old theme's working checks
 * used and what matches the owner's intent, so these gates read the user's roles.
 */
final class Access {

	private static function has_role( array $wanted ): bool {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->exists() ) {
			return false;
		}
		return (bool) array_intersect( $wanted, (array) $user->roles );
	}

	/** Row A: Posts tab + My Posts view. Preserves today's behaviour: any logged-in user.
	 *  (Formidable form 6 creates posts regardless of WP capabilities, so even
	 *  subscribers can have posts listed.) */
	public static function can_see_posts_tab(): bool {
		return is_user_logged_in();
	}

	/** Row A: Defaults tab content. Preserves today's behaviour: any logged-in user. */
	public static function can_see_defaults_tab(): bool {
		return is_user_logged_in();
	}

	/** Row B (approved fix, amended): Indexing tab — councils/camps/lodges views and
	 *  the Tools card. The approved intent was "the roles that hold add_council":
	 *  index_contributor, regional, manager, administrator. Checked by role because
	 *  the capability itself is deny-poisoned for multi-role users (see header). */
	public static function can_see_indexing_tab(): bool {
		return self::has_role( array( 'index_contributor', 'regional', 'manager', 'administrator' ) );
	}

	/** Row C: Admin tab, Admin 2 (Edit Users form 33) and the Process Data button.
	 *  Exactly today's semantics: the administrator role. */
	public static function can_see_admin_tabs(): bool {
		return self::has_role( array( 'administrator' ) );
	}

	/** Row D (approved fix, amended): the Add a Council / Camp / Lodge pages. Same
	 *  role set as the Indexing tab — the roles that were given add_council/add_camp/
	 *  add_lodge when the site was set up. */
	public static function can_add_council(): bool {
		return self::can_see_indexing_tab();
	}

	public static function can_add_camp(): bool {
		return self::can_see_indexing_tab();
	}

	public static function can_add_lodge(): bool {
		return self::can_see_indexing_tab();
	}

	/** Row E: the Add a Post page. Today it has no check at all; the mapping's minimum
	 *  is logged-in. (Owner actions noted in AUDIT.md: set form 6 to logged-in-only in
	 *  Formidable and reconsider its visible "Published" status choice.) */
	public static function can_add_post(): bool {
		return is_user_logged_in();
	}

	/** Row G: the Update Active End Dates tool (see Ajax::update_end_dates). */
	public static function can_run_end_dates(): bool {
		return self::has_role( array( 'administrator' ) );
	}

	/** Row H: changing user roles through the Edit Users form. Administrators (who see
	 *  the Admin 2 tab) and managers (whose role was built for user management, and
	 *  whom Formidable already lets edit others' form-33 entries). promote_users is
	 *  accepted too for any future role that genuinely holds it un-denied. */
	public static function can_edit_user_roles(): bool {
		return self::has_role( array( 'administrator', 'manager' ) ) || current_user_can( 'promote_users' );
	}

	/** Row H: the only roles the Edit Users form may grant. administrator and
	 *  system_administrator can never be granted this way. Must match the whitelist
	 *  in legacy/NewUserEntry.php::frm_edit_users(). */
	public static function grantable_roles(): array {
		return array( 'subscriber', 'author', 'historian', 'index_contributor', 'regional', 'manager' );
	}

	/** Row I: the my-account deep-link whitelists (the new theme's tab JS reads these
	 *  server-side; anything else falls back to the default tab). */
	public static function allowed_tabs(): array {
		return array( 'myHome', 'myPosts', 'myMedia', 'myDefaults', 'myIndexing', 'myAdmin', 'myAdmin2' );
	}

	/** The My Account tabs the current visitor can see, in page order. Asking for any other
	 *  tab by its address opens Home instead of an empty panel. */
	public static function visible_tabs(): array {
		$tabs = array( 'myHome' );
		if ( self::can_see_posts_tab() ) {
			$tabs[] = 'myPosts';
		}
		$tabs[] = 'myMedia';
		$tabs[] = 'myDefaults';
		if ( self::can_see_indexing_tab() ) {
			$tabs[] = 'myIndexing';
		}
		if ( self::can_see_admin_tabs() ) {
			$tabs[] = 'myAdmin';
			$tabs[] = 'myAdmin2';
		}
		return $tabs;
	}

	public static function allowed_sub_tabs(): array {
		return array( 'myCouncils', 'myCamps', 'myLodges' );
	}
}
