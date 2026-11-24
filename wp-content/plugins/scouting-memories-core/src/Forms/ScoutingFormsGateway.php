<?php
namespace ScoutingMemories\Core\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forms and views from the Scouting Forms plugin.
 *
 * Scouting Forms answers every Formidable call the parent class makes, so anything not listed
 * here still works through the parent. Listed here is what Scouting Forms has its own
 * shortcode for: the site's forms by name, the signed-in user's posts, and the indexing
 * browser (councils, camps, lodges and its tools in one screen).
 */
class ScoutingFormsGateway extends FormidableGateway {

	private const FORM_SHORTCODES = array(
		self::FORM_ACCOUNT     => 'sm_account_profile',
		self::FORM_DEFAULTS    => 'sm_user_defaults',
		self::FORM_ADD_POST    => 'sm_add_memory_form',
		self::FORM_ADD_COUNCIL => 'sm_add_council_form',
		self::FORM_ADD_CAMP    => 'sm_add_camp_form',
		self::FORM_ADD_LODGE   => 'sm_add_lodge_form',
	);

	private const VIEW_SHORTCODES = array(
		self::VIEW_MY_POSTS => 'sm_user_posts',
		self::VIEW_INDEXING => 'sm_indexing_browser',
	);

	public function key(): string {
		return 'scouting-forms';
	}

	public function form( string $which ): string {
		$tag = self::FORM_SHORTCODES[ $which ] ?? '';
		return '' !== $tag && shortcode_exists( $tag ) ? (string) do_shortcode( "[{$tag}]" ) : parent::form( $which );
	}

	public function view( string $which ): string {
		$tag = self::VIEW_SHORTCODES[ $which ] ?? '';
		return '' !== $tag && shortcode_exists( $tag ) ? (string) do_shortcode( "[{$tag}]" ) : parent::view( $which );
	}
}
