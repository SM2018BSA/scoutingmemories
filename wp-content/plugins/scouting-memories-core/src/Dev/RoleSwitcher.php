<?php
namespace ScoutingMemories\Core\Dev;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test-site role switcher (temporary, for testing who sees what).
 *
 * Lets a real administrator look at the front end as any other kind of user, from a small
 * floating box, and always switch back from that same box.
 *
 * One job: the rules. The box itself is views/dev/role-switcher.php.
 *
 * How it stays safe:
 * - It only exists on a test site: a local DDEV site, or a site whose wp-config.php says its
 *   environment type is local, development or staging. On the live site (production) none of
 *   this is wired up.
 * - Only someone whose REAL account is an administrator gets the box.
 * - Nothing is saved to the account. The chosen kind of user lives in a cookie in that one
 *   browser, and is put on the current user in memory for each page view. The account's real
 *   roles in the database never change, so "My real roles" always brings them back.
 * - wp-admin is left alone: the dashboard always sees the real administrator.
 */
final class RoleSwitcher {

	private const COOKIE = 'sm_dev_role';
	private const PARAM  = 'sm_dev_role';
	private const NONCE  = 'sm_dev_role_switch';
	private const REAL   = 'real';

	/** The signed-in person's real roles, noted before any switch; null while nobody is signed in. */
	private static ?array $real_roles = null;

	public static function register(): void {
		if ( ! self::is_test_site() ) {
			return;
		}
		add_action( 'set_current_user', array( self::class, 'apply' ) );
		add_action( 'template_redirect', array( self::class, 'handle_switch' ), 0 );
		add_action( 'wp_footer', array( self::class, 'render' ), 100 );
	}

	/** A local DDEV site, or a site that declares itself local, development or staging. */
	public static function is_test_site(): bool {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return str_ends_with( $host, '.ddev.site' )
			|| in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true );
	}

	/** May the signed-in person use the box? Only a real administrator. */
	public static function allowed(): bool {
		return is_array( self::$real_roles ) && in_array( 'administrator', self::$real_roles, true );
	}

	/** The kind of user chosen in this browser, or '' for the real roles. */
	public static function chosen(): string {
		$role = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		return '' !== $role && wp_roles()->is_role( $role ) ? $role : '';
	}

	/**
	 * Runs each time WordPress settles who the current user is: notes the real roles, then,
	 * for a real administrator who chose another kind of user, puts that kind on the user in
	 * memory. Front end and its AJAX/REST calls only.
	 */
	public static function apply(): void {
		global $current_user;
		if ( ! $current_user instanceof \WP_User || ! $current_user->exists() ) {
			self::$real_roles = null;
			return;
		}
		self::$real_roles = array_values( (array) $current_user->roles );

		if ( ! self::allowed() || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}
		$role = self::chosen();
		if ( '' === $role ) {
			return;
		}
		$current_user->caps = array( $role => true );
		$current_user->get_role_caps(); // rebuilds ->roles and ->allcaps from ->caps; nothing is saved
	}

	/** A click in the box: remember the choice in a cookie and reload the same page without the extras. */
	public static function handle_switch(): void {
		if ( ! isset( $_GET[ self::PARAM ] ) || ! self::allowed() ) {
			return;
		}
		check_admin_referer( self::NONCE );
		$role = sanitize_key( wp_unslash( $_GET[ self::PARAM ] ) );
		$keep = self::REAL !== $role && wp_roles()->is_role( $role );

		setcookie(
			self::COOKIE,
			$keep ? $role : '',
			array(
				'expires'  => $keep ? time() + WEEK_IN_SECONDS : time() - DAY_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		wp_safe_redirect( remove_query_arg( array( self::PARAM, '_wpnonce' ) ) );
		exit;
	}

	/** The link that switches to one kind of user (or back, with no argument). */
	public static function switch_url( string $role = self::REAL ): string {
		return wp_nonce_url( add_query_arg( self::PARAM, $role ), self::NONCE );
	}

	/** Prints the floating box at the end of every front-end page, for a real administrator. */
	public static function render(): void {
		if ( ! self::allowed() ) {
			return;
		}
		$names  = wp_roles()->get_names();
		$chosen = self::chosen();
		$args   = array(
			'roles'      => array_map( 'translate_user_role', $names ),
			'chosen'     => $chosen,
			'real_names' => array_map( static fn ( $role ) => translate_user_role( $names[ $role ] ?? $role ), self::$real_roles ),
			'real_url'   => self::switch_url(),
			'urls'       => array_combine( array_keys( $names ), array_map( array( self::class, 'switch_url' ), array_keys( $names ) ) ),
		);
		require SM_CORE_DIR . '/views/dev/role-switcher.php';
	}
}
