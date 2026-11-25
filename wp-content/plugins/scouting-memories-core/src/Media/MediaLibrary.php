<?php
namespace ScoutingMemories\Core\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The front-end media library: who may use it, and what the page needs to talk to it.
 *
 * One job. The library itself is drawn by the theme (template-parts/media/) and runs in the
 * browser against WordPress's own REST API (wp/v2/media), which does the real permission
 * checks on every upload, edit and delete. This class only answers the two questions the
 * templates ask, with the rules carried over from the old "upload media" button:
 * - only a user with the upload_files capability gets the library;
 * - administrators see every file, everyone else only their own.
 */
final class MediaLibrary {

	/** May the current visitor use the library (and the editor's picture picker)? */
	public static function can_use(): bool {
		return is_user_logged_in() && current_user_can( 'upload_files' );
	}

	/** Does the current user see everyone's files (true), or only their own (false)? */
	public static function sees_all_files(): bool {
		$user = wp_get_current_user();
		return $user->exists() && in_array( 'administrator', (array) $user->roles, true );
	}

	/**
	 * What the browser side needs: where the REST API's media list lives, the nonce that
	 * proves the request comes from this logged-in user, the user's id and whether the list
	 * shows everyone's files.
	 *
	 * @return array{endpoint: string, nonce: string, user: int, all: bool}
	 */
	public static function page_data(): array {
		return array(
			'endpoint' => esc_url_raw( rest_url( 'wp/v2/media' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'user'     => get_current_user_id(),
			'all'      => self::sees_all_files(),
		);
	}
}
