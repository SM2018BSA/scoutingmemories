<?php
namespace ScoutingMemories\Core\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where the front-end pages get their forms and views from.
 *
 * The theme never calls a forms plugin itself. It asks the gateway for a form or a view by
 * its name in this site's own language ("account", "councils") and prints what comes back.
 * Which plugin answers is decided once, in GatewayFactory.
 */
interface FormsGateway {

	public const FORM_ACCOUNT     = 'account';
	public const FORM_DEFAULTS    = 'defaults';
	public const FORM_EDIT_USER   = 'edit-user';
	public const FORM_ADD_POST    = 'add-post';
	public const FORM_ADD_COUNCIL = 'add-council';
	public const FORM_ADD_CAMP    = 'add-camp';
	public const FORM_ADD_LODGE   = 'add-lodge';

	public const VIEW_MY_POSTS = 'my-posts';
	public const VIEW_USERS    = 'users';
	public const VIEW_COUNCILS = 'councils';
	public const VIEW_CAMPS    = 'camps';
	public const VIEW_LODGES   = 'lodges';
	public const VIEW_INDEXING = 'indexing';

	/**
	 * A short name for the source ('formidable' or 'scouting-forms'). The theme uses it to pick
	 * the template part that lays a screen out the way this source needs.
	 */
	public function key(): string;

	/** The ready-to-print HTML of one of the FORM_* forms, or '' when this source has none. */
	public function form( string $which ): string;

	/** The ready-to-print HTML of one of the VIEW_* views, or '' when this source has none. */
	public function view( string $which ): string;

	/** The "upload media" button of the My Account page. */
	public function media_button(): string;
}
