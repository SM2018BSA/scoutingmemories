<?php
/**
 * End-to-end check of "Ask the AI for tags": WordPress sends a post's stored document text to
 * the assistant, fetches the answer and puts it in PDF Text's review list, marked as from the AI.
 *
 *   python tests/tagger/wp_suggest_check.py <state folder> --serve      (in another window)
 *   php tests/tagger/post-suggestions-check.php /path/to/a/test/wordpress
 *
 * Test environment only. The WordPress must have the PDF Text plugin (scouting-pdf-embedder,
 * with its document text and tag suggestion classes) and the archive taxonomies; the test
 * WordPress of the pdf-ocr branch's tests/pdf-viewer has both. This plugin is loaded from this
 * folder, so it need not be installed there. The assistant is the stand-in from
 * wp_suggest_check.py: no AI provider is called. Prints ALL POST SUGGESTION CHECKS PASSED.
 */
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

$wp_dir                 = rtrim( $argv[1] ?? '', '/' );
$_SERVER['HTTP_HOST']   = 'localhost:8888';
$_SERVER['REQUEST_URI'] = '/';
define( 'WP_USE_THEMES', false );
require $wp_dir . '/wp-load.php';
if ( ! class_exists( 'Scouting_AI_Tagger' ) ) {
	require dirname( __DIR__, 2 ) . '/wp-content/plugins/scouting-ai-tagger/scouting-ai-tagger.php';
}
foreach ( [ 'Scouting_PDF_Document_Text', 'Scouting_PDF_Tag_Suggestions', 'Scouting_PDF_Transcript', 'Scouting_PDF_OCR' ] as $needed ) {
	if ( ! class_exists( $needed ) ) {
		fwrite( STDERR, "This WordPress does not have the PDF Text plugin's $needed class.\n" );
		exit( 1 );
	}
}

$failures = 0;
function check( $label, $actual, $expected ) {
	global $failures;
	if ( $actual !== $expected ) {
		$failures++;
		echo "FAIL: $label\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
	}
}

/** What the Tag suggestions screen prints at its top, as text lines */
function screen_top(): array {
	ob_start();
	do_action( 'scouting_pdf_tag_suggestions_screen_top' );
	return array_values( array_filter( array_map( 'trim', explode( "\n", wp_strip_all_tags( str_replace( '</div>', "\n", ob_get_clean() ) ) ) ) ) );
}

$S = 'Scouting_AI_Tagger_Post_Suggestions';
$G = 'Scouting_PDF_Tag_Suggestions';
foreach ( [ 'state', 'council', 'camp', 'lodge', 'start_date', 'end_date' ] as $taxonomy ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		register_taxonomy( $taxonomy, 'post', [ 'public' => true ] );
	}
}

$saved_option = get_option( Scouting_AI_Tagger::OPTION );
$admins       = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
$reader       = wp_insert_user( [ 'user_login' => 'sm_ai_check_reader', 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'sm-ai-check@example.com', 'role' => 'subscriber' ] );
$pdf          = 'https://storage.scoutingmemories.org/sm-tests/ai-check.pdf';
$doc_key      = Scouting_PDF_OCR::doc_key( Scouting_PDF_OCR::canonical_url( $pdf ) );
$text         = "Troop 12 hiked to Zephyrcrest Lookout in 1938.\nCamp Tahquitz, California.";
$post_id      = wp_insert_post( [ 'post_title' => 'AI check document', 'post_status' => 'publish', 'post_content' => '[pdf-embedder url="' . $pdf . '"]' ] );
$empty_id     = wp_insert_post( [ 'post_title' => 'AI check: no text', 'post_status' => 'publish', 'post_content' => '<p>No document.</p>' ] );
add_post_meta( $post_id, Scouting_PDF_Document_Text::META_KEY, wp_slash( Scouting_PDF_Document_Text::encode( $doc_key, [ 2 => $text ] ) ) );
$terms = [];
foreach ( [ [ 'camp', 'AI_Check_Camp_9100' ], [ 'council', 'AI_Check_Council_91' ], [ 'state', 'ZQ' ], [ 'lodge', 'AI_Check_Lodge_' ] ] as $pair ) {
	$made                = wp_insert_term( $pair[1], $pair[0] );
	$terms[ $pair[0] ] = is_wp_error( $made ) ? (int) $made->get_error_data() : (int) $made['term_id'];
}
$post = get_post( $post_id );

try {
	// ---- The answer's fields as review-list rows ----
	wp_set_object_terms( $post_id, $terms['lodge'], 'lodge' );
	$result = [
		'fields' => [
			'camp'       => [ 'value' => get_term( $terms['camp'] )->slug, 'validated' => true, 'evidence' => 'at the camp' ],
			'council'    => [ 'value' => get_term( $terms['council'] )->slug, 'validated' => false, 'model_value' => 'x', 'evidence' => '' ],
			'lodge'      => [ 'value' => get_term( $terms['lodge'] )->slug, 'validated' => true, 'evidence' => 'the lodge' ],
			'state'      => [ 'value' => [ 'ZQ', 'XX' ], 'evidence' => '' ],
			'start_date' => [ 'value' => '1938', 'evidence' => 'in 1938' ],
			'end_date'   => [ 'value' => null, 'evidence' => '' ],
			'category'   => [ 'value' => 'Newsletter', 'evidence' => '' ],
			'subject'    => [ 'value' => '<script>alert(1)</script>', 'evidence' => '' ],
		],
	];
	$rows   = Scouting_AI_Tagger_Result_Mapper::suggestions( $post, $result );
	$short  = array_map( static fn( $row ) => $row['field'] . ': ' . $row['label'] . ' | ' . $row['evidence'], $rows );
	check( 'only real terms the post does not have, in the fields the review list knows', $short, [
		'state: ZQ | ' . Scouting_AI_Tagger_Result_Mapper::NO_EVIDENCE,
		'camp: AI Check Camp | at the camp',
		'start_date: 1938 | in 1938',
	] );
	check( 'a term is stored by its ID', $rows[1]['value'], $terms['camp'] );
	check( 'every row says it is from the AI', array_unique( array_column( $rows, 'source' ) ), [ 'ai' ] );
	check( 'the Page link goes to the first read page', [ $rows[0]['viewer'], $rows[0]['page'] ], [ 1, 2 ] );
	check( 'an answer with no fields gives nothing', Scouting_AI_Tagger_Result_Mapper::suggestions( $post, [ 'fields' => 'x' ] ), [] );
	wp_set_object_terms( $post_id, [], 'lodge' );

	// ---- Asking, with the stand-in assistant ----
	update_option( Scouting_AI_Tagger::OPTION, [ 'url' => 'http://127.0.0.1:8017', 'secret' => 'wp-suggest-check-secret-0123456789abcdef' ] );
	check( 'the pieces are all here', $S::available(), true );
	check( 'a post without document text is not sent', $S::ask( $empty_id ), 'no_text' );
	check( 'asked', $S::ask( $post_id ), 'asked' );
	check( 'the job is remembered on the post', (bool) preg_match( '/^[0-9a-f]{32}$/', (string) get_post_meta( $post_id, $S::JOB_META, true ) ), true );
	check( 'asking tags nothing', wp_get_object_terms( $post_id, 'start_date', [ 'fields' => 'names' ] ), [] );

	// ---- Fetching the answer happens on the Tag suggestions screen, for reviewers only ----
	wp_set_current_user( (int) $reader );
	check( 'a reader opening the hook fetches nothing', screen_top(), [] );
	check( 'the job is still waiting', (bool) get_post_meta( $post_id, $S::JOB_META, true ), true );
	wp_set_current_user( (int) $admins[0] );
	$lines = [];
	for ( $try = 0; $try < 30 && get_post_meta( $post_id, $S::JOB_META, true ); $try++ ) {
		usleep( 200000 );
		$lines = screen_top();
	}
	check( 'the reviewer is told', $lines, [ 'The AI added 1 suggestion.' ] );
	$waiting = array_values( $G::waiting( $post_id ) );
	check( 'the answer is in the review list, from the AI, waiting', array_map( static fn( $s ) => [ $s['field'], $s['label'], $s['source'], $s['status'], $s['evidence'] ], $waiting ), [
		[ 'start_date', '1938', 'ai', 'waiting', 'hiked to Zephyrcrest Lookout in 1938' ],
	] );
	check( 'fetching tags nothing', wp_get_object_terms( $post_id, 'start_date', [ 'fields' => 'names' ] ), [] );
	check( 'nothing is fetched twice', screen_top(), [] );

	// ---- When the assistant cannot be asked ----
	update_option( Scouting_AI_Tagger::OPTION, [ 'url' => 'http://127.0.0.1:8017', 'secret' => str_repeat( 'wrong', 8 ) ] );
	check( 'wrong secret: said plainly, nothing remembered', [ $S::ask( $post_id ), (string) get_post_meta( $post_id, $S::JOB_META, true ) ], [ 'failed', '' ] );
	update_option( Scouting_AI_Tagger::OPTION, [ 'url' => 'http://127.0.0.1:8017', 'secret' => 'wp-suggest-check-secret-0123456789abcdef' ] );
	update_post_meta( $post_id, $S::JOB_META, str_repeat( '0', 32 ) );
	check( 'a job the assistant no longer has', screen_top(), [ 'The AI could not answer for 1 post. Ask again from the Posts list.' ] );
	check( 'and it is not asked about again', (string) get_post_meta( $post_id, $S::JOB_META, true ), '' );

	// ---- New code keeps to WordPress's own functions ----
	foreach ( [ 'post-suggestions', 'result-mapper' ] as $name ) {
		$source = file_get_contents( dirname( __DIR__, 2 ) . '/wp-content/plugins/scouting-ai-tagger/includes/class-scouting-ai-tagger-' . $name . '.php' );
		check( "$name: no \$wpdb", strpos( $source, '$wpdb' ), false );
	}
} finally {
	wp_set_current_user( 0 );
	wp_delete_post( $post_id, true );
	wp_delete_post( $empty_id, true );
	foreach ( $terms as $taxonomy => $term_id ) {
		wp_delete_term( $term_id, $taxonomy );
	}
	if ( is_numeric( $reader ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( (int) $reader );
	}
	if ( false === $saved_option ) {
		delete_option( Scouting_AI_Tagger::OPTION );
	} else {
		update_option( Scouting_AI_Tagger::OPTION, $saved_option );
	}
}

if ( $failures ) {
	echo "$failures POST SUGGESTION CHECK(S) FAILED\n";
	exit( 1 );
}
echo "ALL POST SUGGESTION CHECKS PASSED\n";
