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

// ---- Page OCR: one spelling per document ----
$O = 'Scouting_PDF_OCR';
$c = function($url) use ($O) {
    $r = $O::canonical_url($url);
    return is_wp_error($r) ? $r->get_error_code() : $r;
};
$S = 'https://storage.scoutingmemories.org';
check('ocr link kept', $c("$S/2022/01/a.pdf"), "$S/2022/01/a.pdf");
check('ocr http link read over https', $c('http://storage.scoutingmemories.org/2022/01/a.pdf'), "$S/2022/01/a.pdf");
check('ocr same document code for http and https', $O::doc_key($c('http://storage.scoutingmemories.org/x/a.pdf')), $O::doc_key($c("$S/x/a.pdf")));
check('ocr upper-case host', $c('https://STORAGE.scoutingmemories.org/a.pdf'), "$S/a.pdf");
check('ocr query dropped', $c("$S/a.pdf?v=2"), "$S/a.pdf");
check('ocr spaces encoded one way', $c("$S/Camp%20Book.pdf"), "$S/Camp%20Book.pdf");
check('ocr plain and encoded spellings agree', $c("$S/Troop%2812%29%2C%201950.pdf"), "$S/Troop(12),%201950.pdf");
check('ocr document code is 32 hex characters', (bool) preg_match('/^[a-f0-9]{32}$/', $O::doc_key("$S/a.pdf")), true);
check('ocr refuses the site itself', $c('https://scoutingmemories.org/wp-content/uploads/a.pdf'), 'ocr_host');
check('ocr refuses other hosts', $c('https://evil.example.com/a.pdf'), 'bad_host');
check('ocr refuses non-pdf', $c("$S/a.php"), 'not_pdf');
check('ocr refuses encoded dot-dot', $c("$S/a/%2e%2e/b.pdf"), 'bad_url');
check('ocr refuses empty path piece', $c("$S//a.pdf"), 'bad_url');
check('ocr refuses encoded newline', $c("$S/a%0Ab.pdf"), 'bad_url');
check('ocr refuses encoded backslash', $c("$S/a%5Cb.pdf"), 'bad_url');
check('ocr refuses broken UTF-8', $c("$S/a%FF.pdf"), 'bad_url');
check('ocr refuses links over 1024 characters', $c("$S/" . str_repeat('a', 1000) . '.pdf'), 'bad_url');
check('ocr refuses links over 1024 characters once encoded', $c("$S/" . str_repeat('é', 400) . '.pdf'), 'bad_url');

// ---- Page OCR: on/off and https only ----
// Settings for these checks only, in memory (pre_option): the browser tests may be using the
// saved settings at the same time
$ocr_settings = array('enabled' => 1, 'url' => 'http://ocr-check.test', 'secret' => 'php-check-secret', 'site_limit' => 100, 'user_limit' => 3);
add_filter('pre_option_scouting_pdf_ocr', function() use (&$ocr_settings) { return $ocr_settings; });
$with = function($changes, $fn) use (&$ocr_settings) {
    $saved = $ocr_settings;
    $ocr_settings = array_merge($ocr_settings, $changes);
    try { return $fn(); } finally { $ocr_settings = $saved; }
};
check('ocr on with a test address', $O::enabled(), true);
check('ocr off when switched off', $with(array('enabled' => 0), function() use ($O) { return $O::enabled(); }), false);
check('ocr off without a secret', $with(array('secret' => ''), function() use ($O) { return $O::enabled(); }), false);
check('ocr refuses plain http to a real host', $with(array('url' => 'http://ocr.example.com'), function() use ($O) { return $O::enabled(); }), false);
check('ocr allows https', $with(array('url' => 'https://ocr.example.com'), function() use ($O) { return $O::enabled(); }), true);
check('ocr allows http to localhost', $with(array('url' => 'http://localhost:8000'), function() use ($O) { return $O::enabled(); }), true);
check('ocr attribute only for storage', $O::viewer_attr(content_url('uploads/fixtures/leaders-guide.pdf')), '');
check('ocr attribute for storage', $O::viewer_attr("$S/a.pdf"), ' data-pdf-ocr-key="' . $O::doc_key("$S/a.pdf") . '"');
check('ocr no attribute when off', $with(array('enabled' => 0), function() use ($O, $S) { return $O::viewer_attr("$S/a.pdf"); }), '');

// ---- Page OCR: the routes, with the service answered from here ----
// Every request to the service is caught and answered by this script; nothing leaves the machine
$sent = array();
$answers = array();
add_filter('pre_http_request', function($pre, $args, $url) use (&$sent, &$answers) {
    if (strpos($url, 'http://ocr-check.test/') !== 0) {
        return $pre;
    }
    $sent[] = array('url' => $url, 'args' => $args);
    $a = array_shift($answers);
    if (!$a) {
        return new WP_Error('http_request_failed', 'down');
    }
    return array('response' => array('code' => $a[0], 'message' => ''), 'body' => wp_json_encode($a[1]), 'headers' => array(), 'cookies' => array());
}, 10, 3);

require_once ABSPATH . 'wp-admin/includes/user.php';
global $wpdb;
$ocr_table = $wpdb->prefix . 'scouting_pdf_ocr';
$O::get_instance()->maybe_create_table();
$ours = $wpdb->prepare('pdf_url LIKE %s', "$S/php-checks/%");
$wpdb->query("DELETE FROM $ocr_table WHERE $ours");
$users = array();
foreach (array('ocr-check-a', 'ocr-check-b') as $login) {
    $old = get_user_by('login', $login);
    if ($old) {
        wp_delete_user($old->ID);
    }
    $users[] = wp_insert_user(array('user_login' => $login, 'user_pass' => wp_generate_password(), 'user_email' => $login . '@example.com', 'role' => 'subscriber'));
}
rest_get_server();
$ask = function($body) {
    $r = new WP_REST_Request('POST', '/scouting-pdf/v1/ocr');
    $r->set_header('Content-Type', 'application/json');
    $r->set_body(wp_json_encode($body));
    $res = rest_do_request($r);
    $data = $res->get_data();
    return array($res->get_status(), isset($data['code']) ? $data['code'] : (isset($data['status']) ? $data['status'] : ''), $data, $res);
};
$look = function($doc_key, $page) {
    $res = rest_do_request(new WP_REST_Request('GET', "/scouting-pdf/v1/ocr/$doc_key/$page"));
    $data = $res->get_data();
    return array($data['status'], $res->get_headers()['Cache-Control'] ?? '', $data);
};
$rows = function() use ($wpdb, $ocr_table, $ours) {
    return $wpdb->get_results("SELECT page, status, asks, requested_by, job_id FROM $ocr_table WHERE $ours ORDER BY id");
};
$job = str_repeat('ab', 16);
$doc = "$S/php-checks/guide.pdf";
$key = $O::doc_key($doc);

try {
    wp_set_current_user(0);
    check('ocr logged out cannot ask', $ask(array('url' => $doc, 'page' => 3))[0], 401);
    check('ocr nothing yet', $look($key, 3)[0], 'none');
    check('ocr "none" is not cached', $look($key, 3)[1], 'no-store');

    wp_set_current_user($users[0]);
    check('ocr bad page', $ask(array('url' => $doc, 'page' => 0))[1], 'bad_page');
    check('ocr page over 9999', $ask(array('url' => $doc, 'page' => 10000))[1], 'bad_page');
    check('ocr bad link', $ask(array('url' => 'https://evil.example.com/a.pdf', 'page' => 3))[1], 'bad_host');
    check('ocr no request sent for refusals', count($sent), 0);

    // A new page: one signed request, the clean link in the body only
    $answers[] = array(202, array('id' => $job, 'status' => 'queued', 'page' => 3, 'position' => 1));
    $r = $ask(array('url' => 'http://storage.scoutingmemories.org/php-checks/guide.pdf?x=1', 'page' => 3));
    check('ocr new page is queued', array($r[0], $r[1]), array(200, 'queued'));
    check('ocr answer names the document code', $r[2]['doc_key'], $key);
    check('ocr one request sent', count($sent), 1);
    $req = $sent[0];
    $h = $req['args']['headers'];
    check('ocr request address', $req['url'], 'http://ocr-check.test/v1/jobs');
    check('ocr request method', $req['args']['method'], 'POST');
    check('ocr request body', json_decode($req['args']['body'], true), array('url' => $doc, 'page' => 3));
    check('ocr request time is a number', (bool) preg_match('/^[0-9]{10}$/', $h['X-Scouting-Time']), true);
    check('ocr request signature', $h['X-Scouting-Signature'], hash_hmac('sha256', $h['X-Scouting-Time'] . "\nPOST\n/v1/jobs\n" . hash('sha256', $req['args']['body']), 'php-check-secret'));
    check('ocr request time limit', $req['args']['timeout'], 5);

    // The same page again, by someone else: no second job, no second row
    wp_set_current_user($users[1]);
    check('ocr same page again is still queued', $ask(array('url' => $doc, 'page' => 3))[1], 'queued');
    check('ocr no second request', count($sent), 1);
    check('ocr no second row', count($rows()), 1);
    check('ocr row saved with the job', array($rows()[0]->status, $rows()[0]->job_id, (int) $rows()[0]->requested_by), array('queued', $job, $users[0]));

    // Logged out readers only see the saved state; logged in, the service is asked and the text saved
    wp_set_current_user(0);
    check('ocr logged out sees queued', $look($key, 3)[0], 'queued');
    check('ocr logged out never asks the service', count($sent), 1);
    wp_set_current_user($users[0]);
    $answers[] = array(200, array('id' => $job, 'status' => 'done', 'page' => 3, 'text' => "Line one\nLine two", 'words' => array(), 'engine' => 'tesseract 5.3.4 eng'));
    $l = $look($key, 3);
    check('ocr finished text saved', array($l[0], $l[2]['text'], $l[2]['engine']), array('done', "Line one\nLine two", 'tesseract 5.3.4 eng'));
    check('ocr status question signed for its own path', $sent[1]['args']['headers']['X-Scouting-Signature'], hash_hmac('sha256', $sent[1]['args']['headers']['X-Scouting-Time'] . "\nGET\n/v1/jobs/$job\n" . hash('sha256', ''), 'php-check-secret'));
    check('ocr finished text may be cached briefly', $l[1], 'public, max-age=300');
    wp_set_current_user(0);
    check('ocr everyone sees the text', $look($key, 3)[2]['text'] ?? '', "Line one\nLine two");
    wp_set_current_user($users[1]);
    $r = $ask(array('url' => $doc, 'page' => 3));
    check('ocr asking for a read page returns its text', array($r[1], $r[2]['text'] ?? ''), array('done', "Line one\nLine two"));
    check('ocr ...without asking the service', count($sent), 2);

    // Nothing readable, a stopped service, a busy one
    $answers[] = array(202, array('id' => str_repeat('cd', 16), 'status' => 'done', 'page' => 4, 'text' => " \n ", 'words' => array(), 'engine' => 'x'));
    check('ocr page without words is "empty"', $ask(array('url' => $doc, 'page' => 4))[1], 'empty');
    $r = $ask(array('url' => $doc, 'page' => 5)); // no answer queued: the request fails
    check('ocr service down', array($r[0], $r[1]), array(503, 'ocr_service_down'));
    $answers[] = array(429, array('error' => 'queue_full'));
    $r = $ask(array('url' => $doc, 'page' => 5));
    check('ocr service busy', array($r[0], $r[1]), array(503, 'ocr_busy'));
    $page5 = array_values(array_filter($rows(), function($row) { return (int) $row->page === 5; }));
    check('ocr failed page kept for a retry, both tries counted', array($page5[0]->status, (int) $page5[0]->asks), array('failed', 2));

    // Limits: user b has spent 3 asks today (page 4 once, page 5 twice) against a limit of 3
    $r = $ask(array('url' => $doc, 'page' => 6));
    check('ocr reader limit', array($r[0], $r[1]), array(429, 'ocr_limit'));
    wp_set_current_user($users[0]);
    $with(array('site_limit' => 1), function() use ($ask, $doc) {
        $r = $ask(array('url' => $doc, 'page' => 7));
        check('ocr site limit', array($r[0], $r[1]), array(429, 'ocr_limit'));
    });
    check('ocr limits send nothing', count($sent), 5);

    // Switched off
    $with(array('enabled' => 0), function() use ($ask, $look, $doc, $key) {
        check('ocr off: asking refused', $ask(array('url' => $doc, 'page' => 3))[1], 'ocr_off');
        check('ocr off: looking says off', $look($key, 3)[0], 'off');
    });
} finally {
    wp_set_current_user(0);
    $wpdb->query("DELETE FROM $ocr_table WHERE $ours");
    foreach ($users as $id) {
        wp_delete_user($id);
    }
}

if ($failures) {
    echo "$failures PHP CHECK(S) FAILED\n";
    exit(1);
}
echo "ALL PHP CHECKS PASSED\n";
