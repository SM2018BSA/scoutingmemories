<?php
namespace ScoutingMemories\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap.
 *
 * Nothing wires up unless the ACTIVE theme declares `define('SM_THEME_DELEGATES_TO_CORE', true)`
 * in its functions.php. Theme functions.php runs before the after_setup_theme action, so the
 * check below is deterministic. A theme that never declares it (the live Bootstrap theme)
 * coexists with this plugin without anything running twice.
 *
 * Formidable guard: the legacy domain classes call Formidable and Formidable Pro directly.
 * If either is missing we show an admin notice instead of fataling the whole site (the old
 * theme used to fatal).
 */
final class Plugin {

	public static function boot(): void {
		Autoloader::register();
		add_action( 'after_setup_theme', [ self::class, 'wire' ] );
		// Test sites only (never the live site): the floating box that shows the pages as another kind of user.
		Dev\RoleSwitcher::register();
	}

	public static function wire(): void {
		if ( ! defined( 'SM_THEME_DELEGATES_TO_CORE' ) || ! SM_THEME_DELEGATES_TO_CORE ) {
			return; // the active theme carries its own copies of the domain code
		}
		if ( ! self::formidable_present() ) {
			add_action( 'admin_notices', [ self::class, 'formidable_notice' ] );
			return;
		}
		self::register_domain_hooks();
	}

	private static function formidable_present(): bool {
		return class_exists( 'FrmEntryMeta' ) && class_exists( 'FrmProEntriesController' );
	}

	public static function formidable_notice(): void {
		echo '<div class="notice notice-error"><p>Scouting Memories Core: the Formidable and Formidable Pro plugins must be active. The site&#8217;s forms, views and dashboard stay switched off until they are.</p></div>';
	}

	/**
	 * Moved verbatim from the old theme's functions.php ("load formidable field hooks"),
	 * with two deliberate changes: the unused `new FrmEntryMeta / FrmAppHelper / FrmDb`
	 * instances are dropped (every call site is static), and $the_user is exported as the
	 * global the dashboard and add-* templates read.
	 */
	private static function register_domain_hooks(): void {
		require_once SM_CORE_DIR . '/legacy/compat-functions.php';
		require_once SM_CORE_DIR . '/includes/formidable-constants.php';

		$the_user = new \CurrentUser();
		$GLOBALS['the_user'] = $the_user;

		$new_user = new \NewUserEntry();
		$new_user->setup_hooks();

		$council = new \CouncilEntry();
		$council->setup_hooks();

		$lodge = new \LodgeEntry();
		$lodge->setup_hooks();

		$camp = new \CampEntry();
		$camp->setup_hooks();

		$addPost = new \PostEntry( 0, $the_user );
		$addPost->setup_hooks();

		$councilsView = new \CouncilView( ALL_COUNCILS_VIEWID );
		$councilsView->setup_hooks();

		$lodgesView = new \LodgeView( ALL_LODGES_VIEWID );
		$lodgesView->setup_hooks();

		$campsView = new \CampView( ALL_CAMPS_VIEWID );
		$campsView->setup_hooks();

		$search_form = new \SearchForm();
		$search_form->setup_hooks();

		Ajax::register();
	}
}
