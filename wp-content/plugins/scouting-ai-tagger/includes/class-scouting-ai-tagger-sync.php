<?php
/**
 * Sends the index to the tagging assistant, one step per request so PHP never times out:
 * begin -> states -> councils -> camps -> lodges -> links -> posts-1..posts-N -> commit.
 * The admin page's button drives these steps over REST; nothing runs on a timer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scouting_AI_Tagger_Sync {

	const CHUNK = 500;

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route(
			'scouting-tagger/v1',
			'/sync-step',
			[
				'methods'             => 'POST',
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'callback'            => [ self::class, 'rest_step' ],
				'args'                => [
					'step'  => [ 'type' => 'string', 'required' => true ],
					'batch' => [ 'type' => 'string', 'required' => false, 'default' => '' ],
				],
			]
		);
	}

	public static function rest_step( WP_REST_Request $request ) {
		$answer = self::step( (string) $request['step'], (string) $request['batch'] );
		if ( is_wp_error( $answer ) ) {
			return new WP_REST_Response( [ 'ok' => false, 'message' => $answer->get_error_message() ], 500 );
		}
		return new WP_REST_Response( $answer );
	}

	/**
	 * One step. Returns ['ok'=>true,'batch'=>…, 'next'=>string|null, 'message'=>…] or WP_Error.
	 */
	public static function step( string $step, string $batch = '' ) {
		if ( ! Scouting_AI_Tagger::enabled() ) {
			return new WP_Error( 'tagger_off', 'Set the app address and secret first (both at least filled in; https except local sites).' );
		}
		$reader = new Scouting_AI_Tagger_Index_Reader();

		if ( 'status' === $step ) {
			$answer = Scouting_AI_Tagger_Client::call( 'GET', '/v1/index/status' );
			if ( is_wp_error( $answer ) || 200 !== $answer['status'] || null === $answer['json'] ) {
				return new WP_Error( 'tagger_status', Scouting_AI_Tagger_Client::describe_problem( $answer ) );
			}
			return [ 'ok' => true, 'batch' => '', 'next' => null, 'message' => self::describe_status( $answer['json'] ) ];
		}

		if ( 'begin' === $step ) {
			$counts = $reader->counts();
			$answer = Scouting_AI_Tagger_Client::call( 'POST', '/v1/index/begin', [ 'source' => 'wp', 'counts' => $counts ] );
			if ( is_wp_error( $answer ) || 200 !== $answer['status'] || empty( $answer['json']['batch'] ) ) {
				return new WP_Error( 'tagger_begin', Scouting_AI_Tagger_Client::describe_problem( $answer ) );
			}
			return [
				'ok'      => true,
				'batch'   => (string) $answer['json']['batch'],
				'next'    => 'states',
				'message' => sprintf(
					'Starting: %s councils, %s camps, %s lodges, %s states and %s documents to send.',
					number_format_i18n( $counts['councils'] ),
					number_format_i18n( $counts['camps'] ),
					number_format_i18n( $counts['lodges'] ),
					number_format_i18n( $counts['states'] ),
					number_format_i18n( $counts['posts'] )
				),
			];
		}

		if ( '' === $batch ) {
			return new WP_Error( 'tagger_batch', 'This step needs the batch id from "begin".' );
		}

		switch ( true ) {
			case 'states' === $step:
				return self::push_rows( $batch, 'state', $reader->states(), 'states', 'councils' );
			case 'councils' === $step:
				return self::push_rows( $batch, 'council', $reader->councils(), 'councils', 'camps' );
			case 'camps' === $step:
				return self::push_rows( $batch, 'camp', $reader->camps(), 'camps', 'lodges' );
			case 'lodges' === $step:
				return self::push_rows( $batch, 'lodge', $reader->lodges(), 'lodges', 'links' );
			case 'links' === $step:
				return self::push_rows( $batch, 'link', $reader->links(), 'links between them', 'posts-1' );
			case (bool) preg_match( '/^posts-([0-9]{1,5})$/', $step, $found ):
				$page  = (int) $found[1];
				$pages = $reader->post_pages();
				$sent  = self::push_rows( $batch, 'post', $reader->posts_page( $page ), sprintf( 'documents (part %d of %d)', $page, $pages ), $page < $pages ? 'posts-' . ( $page + 1 ) : 'commit' );
				return $sent;
			case 'commit' === $step:
				$answer = Scouting_AI_Tagger_Client::call( 'POST', '/v1/index/commit', [ 'batch' => $batch ] );
				if ( is_wp_error( $answer ) || 200 !== $answer['status'] || empty( $answer['json']['counts'] ) ) {
					Scouting_AI_Tagger_Client::call( 'POST', '/v1/index/abort', [ 'batch' => $batch ] );
					return new WP_Error( 'tagger_commit', Scouting_AI_Tagger_Client::describe_problem( $answer ) );
				}
				$counts = $answer['json']['counts'];
				return [
					'ok'      => true,
					'batch'   => $batch,
					'next'    => null,
					'message' => sprintf(
						'The app now holds %s councils, %s camps, %s lodges, %s states, %s links and %s documents.',
						number_format_i18n( $counts['councils'] ?? 0 ),
						number_format_i18n( $counts['camps'] ?? 0 ),
						number_format_i18n( $counts['lodges'] ?? 0 ),
						number_format_i18n( $counts['states'] ?? 0 ),
						number_format_i18n( $counts['links'] ?? 0 ),
						number_format_i18n( $counts['posts'] ?? 0 )
					),
				];
		}
		return new WP_Error( 'tagger_step', 'Unknown step.' );
	}

	/** Push one kind's rows in chunks of 500; on failure, abort the batch. */
	private static function push_rows( string $batch, string $type, array $rows, string $said, string $next ) {
		$sent = 0;
		foreach ( array_chunk( $rows, self::CHUNK ) as $chunk ) {
			$answer = Scouting_AI_Tagger_Client::call( 'POST', '/v1/index/chunk', [ 'batch' => $batch, 'type' => $type, 'rows' => $chunk ] );
			if ( is_wp_error( $answer ) || 200 !== $answer['status'] ) {
				Scouting_AI_Tagger_Client::call( 'POST', '/v1/index/abort', [ 'batch' => $batch ] );
				return new WP_Error( 'tagger_chunk', Scouting_AI_Tagger_Client::describe_problem( $answer ) );
			}
			$sent += count( $chunk );
		}
		return [
			'ok'      => true,
			'batch'   => $batch,
			'next'    => $next,
			'message' => sprintf( 'Sent %s %s.', number_format_i18n( $sent ), $said ),
		];
	}

	/** Runs every step in order; for WP-CLI testing, never hooked to anything automatic. */
	public static function run_all(): array {
		$messages = [];
		$answer   = self::step( 'begin' );
		while ( true ) {
			if ( is_wp_error( $answer ) ) {
				$messages[] = 'FAILED: ' . $answer->get_error_message();
				return $messages;
			}
			$messages[] = $answer['message'];
			if ( null === $answer['next'] ) {
				return $messages;
			}
			$answer = self::step( $answer['next'], $answer['batch'] );
		}
	}

	private static function describe_status( array $status ): string {
		if ( empty( $status['received_at'] ) ) {
			return 'Connected. The app has not received the index yet.';
		}
		$counts = is_array( $status['counts'] ?? null ) ? $status['counts'] : [];
		return sprintf(
			'Connected. The app received the index on %s: %s councils, %s camps, %s lodges, %s states, %s links, %s documents.',
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $status['received_at'] ),
			number_format_i18n( $counts['councils'] ?? 0 ),
			number_format_i18n( $counts['camps'] ?? 0 ),
			number_format_i18n( $counts['lodges'] ?? 0 ),
			number_format_i18n( $counts['states'] ?? 0 ),
			number_format_i18n( $counts['links'] ?? 0 ),
			number_format_i18n( $counts['posts'] ?? 0 )
		);
	}
}
