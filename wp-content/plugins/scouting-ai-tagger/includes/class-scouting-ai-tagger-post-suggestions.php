<?php
/**
 * "Ask the AI for tags" for a post whose documents have already been read by PDF Text.
 *
 * WordPress sends the assistant the text it already holds (so the assistant reads no PDF),
 * and the answer becomes suggestions in PDF Text > Tag suggestions, marked (AI), where a
 * person adds or rejects each one. Works only while the PDF Text plugin's text and
 * suggestion classes are on the same site; without them nothing is shown.
 *
 * Nothing runs on a timer: asking is a click on a post's row, and answers are fetched when
 * someone opens the Tag suggestions screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scouting_AI_Tagger_Post_Suggestions {

	const ASK_ACTION = 'scouting_ai_tagger_ask';
	/** The job the assistant is working on for a post, until its answer has been fetched */
	const JOB_META = '_scouting_ai_tagger_job';
	/** The assistant reads the first 12,000 characters; a little more leaves room for page headings */
	const TEXT_SENT = 20000;
	const JOBS_PER_VISIT = 10;

	public static function register(): void {
		add_action( 'init', [ self::class, 'register_meta' ] );
		add_filter( 'post_row_actions', [ self::class, 'row_action' ], 10, 2 );
		add_action( 'admin_post_' . self::ASK_ACTION, [ self::class, 'handle_ask' ] );
		add_action( 'scouting_pdf_tag_suggestions_screen_top', [ self::class, 'collect_answers' ] );
	}

	public static function register_meta(): void {
		register_post_meta(
			'',
			self::JOB_META,
			[
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => '__return_false',
			]
		);
	}

	/** True when the PDF Text plugin's pieces are here and the assistant is set up. */
	public static function available(): bool {
		return Scouting_AI_Tagger::enabled()
			&& class_exists( 'Scouting_PDF_Document_Text' )
			&& class_exists( 'Scouting_PDF_Tag_Suggestions' )
			&& class_exists( 'Scouting_PDF_Transcript' );
	}

	private static function capability(): string {
		return (string) apply_filters( 'scouting_pdf_ocr_review_cap', 'edit_others_posts' );
	}

	private static function may_ask( int $post_id ): bool {
		return current_user_can( self::capability() ) && current_user_can( 'edit_post', $post_id );
	}

	private static function screen_url( array $args = [] ): string {
		return add_query_arg( $args, admin_url( 'admin.php?page=scouting-pdf-tag-suggestions' ) );
	}

	// -- asking --

	/** Posts > All Posts: an "Ask the AI for tags" link under posts that have document text. */
	public static function row_action( array $actions, WP_Post $post ): array {
		if ( ! self::available() || ! self::may_ask( $post->ID ) || ! Scouting_PDF_Document_Text::documents( $post->ID ) ) {
			return $actions;
		}
		if ( get_post_meta( $post->ID, self::JOB_META, true ) ) {
			$actions['scouting_ai_tagger'] = '<a href="' . esc_url( self::screen_url() ) . '">' . esc_html( 'The AI is reading this: see Tag suggestions' ) . '</a>';
			return $actions;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ASK_ACTION . '&post=' . $post->ID ), self::ASK_ACTION . '_' . $post->ID );
		$actions['scouting_ai_tagger'] = '<a href="' . esc_url( $url ) . '">' . esc_html( 'Ask the AI for tags' ) . '</a>';
		return $actions;
	}

	public static function handle_ask(): void {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		check_admin_referer( self::ASK_ACTION . '_' . $post_id );
		if ( ! self::available() || ! self::may_ask( $post_id ) ) {
			wp_die( esc_html( 'You are not allowed to do this.' ), 403 );
		}
		wp_safe_redirect( self::screen_url( [ 'ai' => self::ask( $post_id ) ] ) );
		exit;
	}

	/**
	 * Send one post's text to the assistant. Returns a short code for the notice:
	 * asked | no_text | daily_limit | no_api_key | queue_full | failed.
	 */
	public static function ask( int $post_id ): string {
		$post = get_post( $post_id );
		$text = $post instanceof WP_Post ? self::text_of( $post ) : '';
		if ( '' === $text ) {
			return 'no_text';
		}
		$answer = Scouting_AI_Tagger_Client::call( 'POST', '/v1/wp/suggest', [ 'post_id' => $post_id, 'text' => $text ] );
		if ( ! is_wp_error( $answer ) && 202 === $answer['status'] && ! empty( $answer['json']['id'] ) ) {
			update_post_meta( $post_id, self::JOB_META, sanitize_key( (string) $answer['json']['id'] ) );
			return 'asked';
		}
		$code = is_wp_error( $answer ) ? '' : (string) ( $answer['json']['error'] ?? '' );
		return in_array( $code, [ 'daily_limit', 'no_api_key', 'queue_full' ], true ) ? $code : 'failed';
	}

	/** The post's read pages under the same page headings the assistant writes for its own reading. */
	private static function text_of( WP_Post $post ): string {
		$pieces = [ 'Title: ' . wp_strip_all_tags( get_the_title( $post ) ) ];
		foreach ( Scouting_PDF_Transcript::documents_in_viewer_order( $post ) as $document ) {
			foreach ( $document['pages'] as $page => $text ) {
				if ( '' !== trim( $text ) ) {
					$pieces[] = sprintf( "--- page %d ---\n%s", $page, trim( $text ) );
				}
			}
		}
		return count( $pieces ) > 1 ? mb_substr( implode( "\n\n", $pieces ), 0, self::TEXT_SENT ) : '';
	}

	// -- fetching the answers --

	/**
	 * At the top of PDF Text > Tag suggestions: fetch the answers that are ready, put them
	 * in the review list, and say what happened.
	 */
	public static function collect_answers(): void {
		if ( ! self::available() || ! current_user_can( self::capability() ) ) {
			return;
		}
		$asked   = isset( $_GET['ai'] ) ? sanitize_key( wp_unslash( $_GET['ai'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks a notice.
		$notices = [
			'asked'       => [ 'info', 'The AI was asked about the post. Its suggestions will be here in a minute or so: reload this page.' ],
			'no_text'     => [ 'warning', 'That post has no document text yet, so there is nothing for the AI to read.' ],
			'daily_limit' => [ 'warning', 'The AI has reached its limit of questions for today. Try again tomorrow.' ],
			'no_api_key'  => [ 'error', 'The tagging assistant has no AI key saved. An administrator adds one in the assistant\'s settings.' ],
			'queue_full'  => [ 'warning', 'The tagging assistant is busy. Try again in a few minutes.' ],
			'failed'      => [ 'error', 'The tagging assistant could not be reached. Check its address and secret in Settings.' ],
		];
		if ( isset( $notices[ $asked ] ) ) {
			self::notice( $notices[ $asked ][0], $notices[ $asked ][1] );
		}

		$waiting = get_posts(
			[
				'post_type'      => 'any',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => self::JOBS_PER_VISIT,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => [ [ 'key' => self::JOB_META, 'compare' => 'EXISTS' ] ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a handful of rows.
			]
		);
		$new     = 0;
		$reading = 0;
		$failed  = 0;
		foreach ( $waiting as $post_id ) {
			$outcome = self::collect_one( (int) $post_id );
			if ( null === $outcome ) {
				++$reading;
			} elseif ( $outcome < 0 ) {
				++$failed;
			} else {
				$new += $outcome;
			}
		}
		if ( $new ) {
			self::notice( 'success', sprintf( _n( 'The AI added %s suggestion.', 'The AI added %s suggestions.', $new, 'scouting-ai-tagger' ), number_format_i18n( $new ) ) );
		}
		if ( $reading ) {
			self::notice( 'info', sprintf( _n( 'The AI is still reading %s post. Reload this page in a minute.', 'The AI is still reading %s posts. Reload this page in a minute.', $reading, 'scouting-ai-tagger' ), number_format_i18n( $reading ) ) );
		}
		if ( $failed ) {
			self::notice( 'error', sprintf( _n( 'The AI could not answer for %s post. Ask again from the Posts list.', 'The AI could not answer for %s posts. Ask again from the Posts list.', $failed, 'scouting-ai-tagger' ), number_format_i18n( $failed ) ) );
		}
	}

	/**
	 * One post's job: the number of new suggestions when the answer is in, -1 when the
	 * job failed or is gone, null while the assistant is still working.
	 */
	private static function collect_one( int $post_id ): ?int {
		$job = (string) get_post_meta( $post_id, self::JOB_META, true );
		if ( ! preg_match( '/^[0-9a-f]{32}$/', $job ) ) {
			delete_post_meta( $post_id, self::JOB_META );
			return -1;
		}
		$answer = Scouting_AI_Tagger_Client::call( 'GET', '/v1/wp/jobs/' . $job );
		if ( is_wp_error( $answer ) || ( 200 !== $answer['status'] && 404 !== $answer['status'] ) || ( 200 === $answer['status'] && null === $answer['json'] ) ) {
			return null; // the assistant is not answering right now: keep the job and try on the next visit
		}
		$status = 200 === $answer['status'] ? (string) ( $answer['json']['status'] ?? '' ) : 'gone';
		if ( in_array( $status, [ 'queued', 'working' ], true ) ) {
			return null;
		}
		delete_post_meta( $post_id, self::JOB_META );
		$post = get_post( $post_id );
		if ( 'done' !== $status || ! $post instanceof WP_Post || ! is_array( $answer['json']['result'] ?? null ) ) {
			return -1;
		}
		return Scouting_PDF_Tag_Suggestions::add( $post_id, Scouting_AI_Tagger_Result_Mapper::suggestions( $post, $answer['json']['result'] ) );
	}

	private static function notice( string $kind, string $message ): void {
		printf( '<div class="notice notice-%s"><p>%s</p></div>', esc_attr( $kind ), esc_html( $message ) );
	}
}
