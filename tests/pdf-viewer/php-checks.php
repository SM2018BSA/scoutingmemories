<?php
/**
 * Server-side checks for the Scouting PDF Embedder, run inside the test WordPress.
 *
 *   php php-checks.php /path/to/test/wordpress
 *
 * Test environment only. Prints each failure and exits 1, or prints ALL PHP CHECKS PASSED.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$wp_dir = rtrim($argv[1] ?? '/tmp/scouting-pdf-wp', '/');
$_SERVER['HTTP_HOST'] = 'localhost:8888';
$_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);
require $wp_dir . '/wp-load.php';

$failures = 0;
function check($label, $actual, $expected) {
    global $failures;
    if ($actual !== $expected) {
        $failures++;
        echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
    }
}

$E = 'Scouting_PDF_Embedder';

// ---- What the stream endpoint will fetch ----
$v = function($url) use ($E) {
    $r = $E::validate_proxy_url($url);
    return is_wp_error($r) ? $r->get_error_code() : $r;
};
check('storage https', $v('https://storage.scoutingmemories.org/2022/01/a.pdf'), 'https://storage.scoutingmemories.org/2022/01/a.pdf');
check('storage http is fetched over https', $v('http://storage.scoutingmemories.org/a.pdf'), 'https://storage.scoutingmemories.org/a.pdf');
check('upper-case host and extension', $v('https://STORAGE.scoutingmemories.org/A.PDF'), 'https://storage.scoutingmemories.org/A.PDF');
check('query kept', $v('https://storage.scoutingmemories.org/a.pdf?v=2'), 'https://storage.scoutingmemories.org/a.pdf?v=2');
check('port 443 ok', $v('https://storage.scoutingmemories.org:443/a.pdf'), 'https://storage.scoutingmemories.org/a.pdf');
check('other host', $v('https://evil.example.com/a.pdf'), 'bad_host');
check('site itself is not proxied', $v('https://scoutingmemories.org.evil.com/a.pdf'), 'bad_host');
check('loopback IP', $v('http://127.0.0.1/a.pdf'), 'bad_host');
check('metadata IP', $v('http://169.254.169.254/latest/a.pdf'), 'bad_host');
check('odd port', $v('https://storage.scoutingmemories.org:8080/a.pdf'), 'bad_port');
check('credentials', $v('https://u:p@storage.scoutingmemories.org/a.pdf'), 'bad_url');
check('file scheme', $v('file:///etc/passwd.pdf'), 'bad_url');
check('ftp scheme', $v('ftp://storage.scoutingmemories.org/a.pdf'), 'bad_scheme');
check('not a pdf', $v('https://storage.scoutingmemories.org/a.php'), 'not_pdf');
check('pdf only in query', $v('https://storage.scoutingmemories.org/get.php?f=a.pdf'), 'not_pdf');
check('newline in path', $v("https://storage.scoutingmemories.org/a\nb.pdf"), 'bad_url');
check('null byte', $v("https://storage.scoutingmemories.org/a.pdf\0.php"), 'bad_url');
check('space in query', $v('https://storage.scoutingmemories.org/a.pdf?x=1 2'), 'bad_url');
check('garbage', $v('not a url'), 'bad_url');

// ---- Address checks (the endpoint pins the address it checked) ----
check('public IP', $E::resolve_public_ip('8.8.8.8'), '8.8.8.8');
check('loopback', $E::resolve_public_ip('127.0.0.1'), false);
check('localhost', $E::resolve_public_ip('localhost'), false);
check('private 10/8', $E::resolve_public_ip('10.1.2.3'), false);
check('private 192.168/16', $E::resolve_public_ip('192.168.0.10'), false);
check('link-local metadata', $E::resolve_public_ip('169.254.169.254'), false);
check('unresolvable', $E::resolve_public_ip('no-such-host.invalid'), false);

// ---- Which PDFs the viewer embeds ----
check('embed storage', $E::is_embeddable_url('https://storage.scoutingmemories.org/a.pdf'), true);
check('embed own uploads', $E::is_embeddable_url(content_url('uploads/fixtures/leaders-guide.pdf')), true);
check('no embed elsewhere', $E::is_embeddable_url('https://files.example.com/a.pdf'), false);
check('no embed look-alike host', $E::is_embeddable_url('https://storage.scoutingmemories.org.evil.com/a.pdf'), false);
check('no embed javascript', $E::is_embeddable_url('javascript:alert(1)//storage.scoutingmemories.org/a.pdf'), false);
check('no embed non-pdf', $E::is_embeddable_url('https://storage.scoutingmemories.org/a.html'), false);

// ---- First PDF in a post, for the citation tags ----
check('first pdf from shortcode', $E::first_pdf_in_content('[pdf-embedder url="https://files.example.com/x.pdf"] [pdf-embedder url="https://storage.scoutingmemories.org/y.pdf"]'), 'https://storage.scoutingmemories.org/y.pdf');
check('first pdf from link', $E::first_pdf_in_content('<a href="https://storage.scoutingmemories.org/z.pdf">z</a>'), 'https://storage.scoutingmemories.org/z.pdf');
check('no pdf', $E::first_pdf_in_content('<p>nothing</p>'), '');

// ---- Rendered viewer markup ----
$plugin = $E::get_instance();
$html = $plugin->build_viewer_html('https://storage.scoutingmemories.org/a.pdf', '"><script>alert(1)</script>');
check('title escaped in markup', strpos($html, '<script>alert(1)</script>'), false);
check('viewer rendered', strpos($html, 'data-pdf-viewer') !== false, true);
check('download on by default', strpos($html, 'data-pdf-download="1"') !== false, true);
$html = do_shortcode('[pdf-embedder url="https://storage.scoutingmemories.org/a.pdf" download="no" page="4"]');
check('download="no"', strpos($html, 'data-pdf-download="0"') !== false, true);
check('page="4"', strpos($html, 'data-pdf-start-page="4"') !== false, true);
check('site-wide download filter', (function() use ($plugin) {
    add_filter('scouting_pdf_allow_download', '__return_false');
    $out = $plugin->build_viewer_html('https://storage.scoutingmemories.org/a.pdf');
    remove_filter('scouting_pdf_allow_download', '__return_false');
    return strpos($out, 'data-pdf-download="0"') !== false;
})(), true);
$html = do_shortcode('[pdf-embedder url="https://files.example.com/a.pdf" title="Remote"]');
check('remote pdf becomes a link', trim($html), '<p class="scouting-pdf-link"><a href="https://files.example.com/a.pdf" rel="noopener nofollow">Remote</a></p>');
check('javascript url dropped', do_shortcode('[pdf-embedder url="javascript:alert(1)//x.pdf"]'), '');
$content = apply_filters('the_content', '<p><a href="https://files.example.com/b.pdf">B</a></p>');
check('remote plain link untouched', strpos($content, 'data-pdf-viewer'), false);
$content = apply_filters('the_content', '<p><a href="https://storage.scoutingmemories.org/b.pdf">B</a></p>');
check('storage plain link becomes viewer', strpos($content, 'data-pdf-viewer') !== false, true);

// ---- Stream endpoint registration ----
$routes = rest_get_server()->get_routes();
check('stream route registered', isset($routes['/scouting-pdf/v1/stream']), true);
check('ajax proxy removed', has_action('wp_ajax_nopriv_scouting_pdf_proxy'), false);

if ($failures) {
    echo "$failures PHP CHECK(S) FAILED\n";
    exit(1);
}
echo "ALL PHP CHECKS PASSED\n";
