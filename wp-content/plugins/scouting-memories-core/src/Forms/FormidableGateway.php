<?php
namespace ScoutingMemories\Core\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forms and views by their Formidable ids, through the site's Form and View wrappers.
 *
 * This is what the theme's templates did directly before the gateway existed. It also works
 * when Scouting Forms stands in for Formidable, because Scouting Forms answers the same calls.
 */
class FormidableGateway implements FormsGateway {

	/** Form name => the constant holding its Formidable form id. */
	private const FORMS = array(
		self::FORM_ACCOUNT     => 'MY_ACCOUNT_FORMID',
		self::FORM_DEFAULTS    => 'EDIT_USER_DEFAULTS_FORMID',
		self::FORM_EDIT_USER   => 'EDIT_USERS_FORMID',
		self::FORM_ADD_POST    => 'ADD_A_POST_FORMID',
		self::FORM_ADD_COUNCIL => 'ADD_A_COUNCIL_FORMID',
		self::FORM_ADD_CAMP    => 'ADD_A_CAMP_FORMID',
		self::FORM_ADD_LODGE   => 'ADD_A_LODGE_FORMID',
	);

	/** View name => array( wrapper class, the constant holding its Formidable view id ). */
	private const VIEWS = array(
		self::VIEW_MY_POSTS => array( '\View', 'POSTS_VIEWID' ),
		self::VIEW_USERS    => array( '\View', 'EDIT_USERS_VIEWID' ),
		self::VIEW_COUNCILS => array( '\CouncilView', 'ALL_COUNCILS_VIEWID' ),
		self::VIEW_CAMPS    => array( '\CampView', 'ALL_CAMPS_VIEWID' ),
		self::VIEW_LODGES   => array( '\LodgeView', 'ALL_LODGES_VIEWID' ),
	);

	public function key(): string {
		return 'formidable';
	}

	public function form( string $which ): string {
		$constant = self::FORMS[ $which ] ?? '';
		if ( '' === $constant || ! defined( $constant ) ) {
			return '';
		}
		return (string) ( new \Form( (int) constant( $constant ) ) )->show_form();
	}

	public function view( string $which ): string {
		if ( ! isset( self::VIEWS[ $which ] ) ) {
			return ''; // Formidable has no single "indexing" view: the theme shows the three lists.
		}
		list( $class, $constant ) = self::VIEWS[ $which ];
		if ( ! defined( $constant ) ) {
			return '';
		}
		return (string) ( new $class( (int) constant( $constant ) ) )->show_view();
	}

	public function media_button(): string {
		return (string) do_shortcode( '[frontend-button]' );
	}
}
