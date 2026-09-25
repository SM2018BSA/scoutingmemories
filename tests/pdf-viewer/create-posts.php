<?php
/**
 * Create the posts the browser tests visit. Test environment only.
 *
 *   php create-posts.php /path/to/test/wordpress
 *
 * Prints nothing on success. Re-running replaces the previous test posts.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$wp_dir = rtrim($argv[1] ?? '/tmp/scouting-pdf-wp', '/');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wp_dir . '/wp-load.php';

update_option('permalink_structure', '/%postname%/');
update_option('default_comment_status', 'open');
update_option('comment_moderation', 0);
update_option('comment_previously_approved', 0);
update_option('show_avatars', 0);

// Remove earlier runs
foreach (get_posts(array('post_type' => 'any', 'numberposts' => -1, 'meta_key' => 'sm_test', 'post_status' => 'any')) as $old) {
    wp_delete_post($old->ID, true);
}

$uploads = content_url('uploads/fixtures');
$guide = $uploads . '/leaders-guide.pdf';
$scan = $uploads . '/scan-only.pdf';

function sm_test_post($slug, $title, $content, $meta = array()) {
    $id = wp_insert_post(array(
        'post_name'      => $slug,
        'post_title'     => $title,
        'post_content'   => $content,
        'post_status'    => 'publish',
        'comment_status' => 'open',
    ));
    update_post_meta($id, 'sm_test', 1);
    foreach ($meta as $key => $value) {
        update_post_meta($id, $key, $value);
    }
    return $id;
}

// A document with archive metadata, like a real Scouting Memories post
$id = sm_test_post('leaders-guide', 'Camp Tahquitz Leaders Guide, 1952', '<p>Guide for troop leaders.</p>[pdf-embedder url="' . $guide . '"]', array(
    'identifier'                => 'SM-TEST-0042',
    'date_of_original'          => 'June 1952',
    'date_of_digital'           => '2021',
    'publisher_of_digital'      => 'Test Council Archives',
    'meta_location'             => 'Camp Tahquitz, California',
    'meta_physical_description' => '12 pages, stapled booklet',
));
wp_insert_comment(array(
    'comment_post_ID'  => $id,
    'comment_author'   => 'Historian',
    'comment_content'  => 'The waterfront rules on p. 3 match the 1953 edition. See also page ii and page did.',
    'comment_approved' => 1,
));

// Two documents on one post; the second opens at PDF page 6 with downloads turned off
sm_test_post('two-documents', 'Two documents', implode("\n\n", array(
    '[pdf-embedder url="' . $scan . '" title="Untranscribed scan"]',
    '<p>Second document:</p>',
    '[pdf-embedder url="' . $guide . '" title="Leaders guide copy" page="6" download="no"]',
)));

// PDFs on other servers are shown as links, never loaded into the viewer
sm_test_post('external-pdf', 'External PDF', implode("\n\n", array(
    '[pdf-embedder url="https://files.example.com/evil.pdf" title="Remote file"]',
    '<p><a href="https://files.example.com/other.pdf">Another remote file</a></p>',
    '[pdf-embedder url="javascript:alert(1)//.pdf"]',
)));

// Markup left by the old commercial plugin and by entity-escaping
sm_test_post('legacy-markup', 'Legacy markup', implode("\n\n", array(
    '<p><a href="' . $guide . '">Leaders guide (plain link)</a></p>',
    '&#91;pdf-embedder url=&quot;' . $scan . '&quot;&#93;',
)));

// Markup-looking text in a title and an archive field must show as text, never run
sm_test_post('xss-attempt', 'Script test', '[pdf-embedder url="' . $guide . '" title="&lt;img src=x onerror=alert(1)&gt;"]', array(
    'meta_physical_description' => '&lt;b&gt;bold&lt;/b&gt;',
));

// A PDF on "storage" that the browser can't load directly (its certificate isn't trusted by
// the browser), so the viewer has to fall back to the site's stream endpoint
sm_test_post('proxied', 'Proxied document', '[pdf-embedder url="https://pdf-proxy-test.example/fixtures/leaders-guide.pdf" title="Proxied guide"]');

flush_rewrite_rules();
