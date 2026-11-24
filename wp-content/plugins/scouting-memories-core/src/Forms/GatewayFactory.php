<?php
namespace ScoutingMemories\Core\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Picks the forms source once per request: Scouting Forms when that plugin is active,
 * Formidable otherwise.
 */
final class GatewayFactory {

	private static ?FormsGateway $current = null;

	public static function current(): FormsGateway {
		if ( null === self::$current ) {
			self::$current = self::scouting_forms_active() ? new ScoutingFormsGateway() : new FormidableGateway();
		}
		return self::$current;
	}

	/** Scouting Forms defines this constant in its main file and registers this shortcode. */
	private static function scouting_forms_active(): bool {
		return defined( 'SM_FORMS_VERSION' ) && shortcode_exists( 'sm_user_posts' );
	}
}
