<?php
namespace ScoutingMemories\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads this plugin's classes on demand.
 *
 * Two kinds of classes live here:
 * - New code, namespaced ScoutingMemories\Core\..., resolved from src/.
 * - The legacy domain classes moved verbatim from the old theme (global names such as
 *   Entry, View, CurrentUser), resolved from legacy/ through the LEGACY map below.
 *
 * spl_autoload only fires for classes that are not defined yet, so an old theme that
 * require_once's its own copies first always wins — no redeclare fatals, by design.
 * The LEGACY map is filled in when the classes move (plan step: move domain classes).
 */
final class Autoloader {

	/** Legacy global class name => file name inside legacy/. */
	private const LEGACY = [
		'AdvancedCustomField' => 'AdvancedCustomField.php',
		'CampEntry'           => 'CampEntry.php',
		'CampView'            => 'CampView.php',
		'CouncilEntry'        => 'CouncilEntry.php',
		'CouncilView'         => 'CouncilView.php',
		'CurrentUser'         => 'CurrentUser.php',
		'Entry'               => 'Entry.php',
		'Form'                => 'Form.php',
		'Helpers'             => 'Helpers.php',
		'Indexing'            => 'Indexing.php',
		'LodgeEntry'          => 'LodgeEntry.php',
		'LodgeView'           => 'LodgeView.php',
		'NewUserEntry'        => 'NewUserEntry.php',
		'Post'                => 'Post.php',
		'PostEntry'           => 'PostEntry.php',
		'Query'               => 'Query.php',
		'SearchForm'          => 'SearchForm.php',
		'StateEntry'          => 'StateEntry.php',
		'View'                => 'View.php',
	];

	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	public static function load( string $class ): void {
		if ( isset( self::LEGACY[ $class ] ) ) {
			// Helpers only: the old theme's memory-box.php template require_once's the
			// THEME's Classes/Helpers.php by path. If we loaded our copy first, that
			// require would redeclare the class and fatal. So while the active theme
			// ships its own Helpers.php we load THAT file (same path = require_once
			// dedupes); our copy serves themes without one (the new Tailwind theme).
			if ( 'Helpers' === $class ) {
				$theme_copy = get_template_directory() . '/Classes/Helpers.php';
				if ( is_file( $theme_copy ) ) {
					require_once $theme_copy;
					return;
				}
			}
			require_once SM_CORE_DIR . '/legacy/' . self::LEGACY[ $class ];
			return;
		}
		if ( str_starts_with( $class, __NAMESPACE__ . '\\' ) ) {
			$file = SM_CORE_DIR . '/src/' . str_replace( '\\', '/', substr( $class, strlen( __NAMESPACE__ ) + 1 ) ) . '.php';
			if ( is_file( $file ) ) {
				require_once $file;
			}
		}
	}
}
