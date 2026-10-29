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
    check('ocr refused page kept for a retry, refusals not counted', array($page5[0]->status, (int) $page5[0]->asks), array('failed', 0));

    // Limits: user b has spent 1 ask today (page 4) against a limit of 1
    $with(array('user_limit' => 1), function() use ($ask, $doc) {
        $r = $ask(array('url' => $doc, 'page' => 6));
        check('ocr reader limit', array($r[0], $r[1]), array(429, 'ocr_limit'));
    });
    wp_set_current_user($users[0]);
    $with(array('site_limit' => 1), function() use ($ask, $doc) {
        $r = $ask(array('url' => $doc, 'page' => 7));
        check('ocr site limit', array($r[0], $r[1]), array(429, 'ocr_limit'));
    });
    check('ocr limits send nothing', count($sent), 5);

    // Two requests at the same moment both pass the first limit check: user a (1 ask, page 3,
    // limit 2) asks for page 8 while another of their requests saves page 9 in between. The ask
    // that went over is taken back, and nothing is sent.
    $racing = false;
    $race = function($query) use (&$racing, $wpdb, $ocr_table, $S, $users) {
        if (!$racing && strpos($query, 'INSERT IGNORE INTO') === 0 && strpos($query, '/php-checks/guide.pdf') !== false) {
            $racing = true;
            $wpdb->insert($ocr_table, array('doc_key' => str_repeat('0', 32), 'page' => 9, 'pdf_url' => "$S/php-checks/other.pdf", 'status' => 'queued', 'requested_by' => $users[0], 'requested_at' => current_time('mysql')));
        }
        return $query;
    };
    add_filter('query', $race);
    $with(array('user_limit' => 2), function() use ($ask, $doc) {
        $r = $ask(array('url' => $doc, 'page' => 8));
        check('ocr limit checked again after saving', array($r[0], $r[1]), array(429, 'ocr_limit'));
    });
    remove_filter('query', $race);
    check('ocr ...the other request got in first', $racing, true);
    check('ocr ...the ask over the limit was taken back', count(array_filter($rows(), function($row) { return (int) $row->page === 8; })), 0);
    check('ocr ...and nothing sent', count($sent), 5);

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

// ---- Background reading: the document queue and its dispatcher ----
// Same mocked service; documents are seeded straight into the docs table
$Q = Scouting_PDF_OCR_Queue::get_instance();
$docs_table = $wpdb->prefix . 'scouting_pdf_ocr_docs';
$sent = array();
$answers = array();
$make_doc = function($name, $extra = array()) use ($wpdb, $docs_table, $O, $S) {
    $url = "$S/php-checks/$name.pdf";
    $wpdb->insert($docs_table, array_merge(array(
        'doc_key' => $O::doc_key($url), 'pdf_url' => $url, 'label' => $name,
        'status' => 'queued', 'source' => 'bulk', 'enqueued_at' => current_time('mysql'),
    ), $extra));
    return (object) array('id' => (int) $wpdb->insert_id, 'url' => $url, 'key' => $O::doc_key($url));
};
$doc_row = function($d) use ($wpdb, $docs_table) {
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $docs_table WHERE id = %d", $d->id));
};
$drop_doc = function($d) use ($wpdb, $docs_table, $ocr_table) {
    $wpdb->delete($docs_table, array('id' => $d->id));
    $wpdb->delete($ocr_table, array('doc_key' => $d->key));
};
$qrows = function($d) use ($wpdb, $ocr_table) {
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM $ocr_table WHERE doc_key = %s ORDER BY page", $d->key));
};
$tick = function($changes = array()) use ($with, $Q) {
    return $with(array_merge(array('queue_enabled' => 1), $changes), function() use ($Q) { return $Q->tick(); });
};
$jid = function($n) { return str_pad(dechex($n), 32, '0', STR_PAD_LEFT); };
$page_of = function($req) { return json_decode($req['args']['body'], true)['page']; };
update_option('scouting_pdf_ocr_queue_state', 'running', false);
delete_transient('scouting_pdf_ocr_backoff');

try {
    // The whole life of one 3-page document: page 1 first, its answer names the count
    $d = $make_doc('queue-life');
    $answers[] = array(202, array('id' => $jid(1), 'status' => 'queued', 'page' => 1));
    check('queue tick runs', $tick(), true);
    check('queue takes the document', $doc_row($d)->status, 'working');
    check('queue sent page 1 first', array(count($sent), json_decode($sent[0]['args']['body'], true)), array(1, array('url' => $d->url, 'page' => 1)));
    $r = $qrows($d);
    check('queue row: bulk, no ask, nobody', array($r[0]->source, (int) $r[0]->asks, (int) $r[0]->requested_by), array('bulk', 0, 0));

    $answers[] = array(200, array('id' => $jid(1), 'status' => 'done', 'page' => 1, 'text' => 'One', 'words' => array(), 'engine' => 'x', 'pages' => 3));
    $answers[] = array(202, array('id' => $jid(2), 'status' => 'queued', 'page' => 2));
    $answers[] = array(202, array('id' => $jid(3), 'status' => 'queued', 'page' => 3));
    $tick();
    check('queue learned the page count', (int) $doc_row($d)->total_pages, 3);
    $r = $qrows($d);
    check('queue page 1 saved, waiting for a reviewer', array($r[0]->status, $r[0]->review_status), array('done', 'pending'));
    check('queue sent pages 2 and 3', array(count($sent), $page_of($sent[2]), $page_of($sent[3])), array(4, 2, 3));

    $answers[] = array(200, array('id' => $jid(2), 'status' => 'done', 'page' => 2, 'text' => 'Two', 'words' => array(), 'engine' => 'x', 'pages' => 3));
    $answers[] = array(200, array('id' => $jid(3), 'status' => 'done', 'page' => 3, 'text' => " \n", 'words' => array(), 'engine' => 'x', 'pages' => 3));
    $tick();
    $row = $doc_row($d);
    check('queue document done with the right counters', array($row->status, (int) $row->pages_done, (int) $row->pages_failed, $row->finished_at !== null), array('done', 3, 0, true));
    $r = $qrows($d);
    check('queue empty page needs no checking', array($r[2]->status, $r[2]->review_status), array('empty', 'auto'));
    $drop_doc($d);

    // Never more than 3 pages in flight
    $d = $make_doc('queue-cap', array('total_pages' => 5));
    $answers[] = array(202, array('id' => $jid(11), 'status' => 'queued', 'page' => 1));
    $answers[] = array(202, array('id' => $jid(12), 'status' => 'queued', 'page' => 2));
    $answers[] = array(202, array('id' => $jid(13), 'status' => 'queued', 'page' => 3));
    $before = count($sent);
    $tick();
    check('queue sends three pages at most', count($sent) - $before, 3);
    $answers[] = array(200, array('id' => $jid(11), 'status' => 'working', 'page' => 1));
    $answers[] = array(200, array('id' => $jid(12), 'status' => 'working', 'page' => 2));
    $answers[] = array(200, array('id' => $jid(13), 'status' => 'working', 'page' => 3));
    $before = count($sent);
    $tick();
    check('queue waits while three are in flight', count($sent) - $before, 3); // three polls, no new pages
    check('queue pages 4 and 5 have no rows yet', count($qrows($d)), 3);
    $drop_doc($d);

    // Page 1 failing fails the whole document
    $d = $make_doc('queue-first-fail');
    $answers[] = array(202, array('id' => $jid(21), 'status' => 'queued', 'page' => 1));
    $tick();
    $answers[] = array(200, array('id' => $jid(21), 'status' => 'failed', 'page' => 1, 'error' => 'download_failed'));
    $tick();
    $row = $doc_row($d);
    check('queue document failed when page 1 failed', array($row->status, $row->finished_at !== null), array('failed', true));
    $drop_doc($d);

    // A refusal sets the ten-minute backoff and takes the fresh row back
    $d = $make_doc('queue-busy');
    $answers[] = array(429, array('error' => 'hourly_limit'));
    $tick();
    check('queue refused: the fresh row is taken back', count($qrows($d)), 0);
    check('queue refused: backing off', Scouting_PDF_OCR_Queue::backoff_until() > time(), true);
    $before = count($sent);
    check('queue backing off: tick stands back', $tick(), false);
    check('queue backing off: nothing sent', count($sent) - $before, 0);
    delete_transient('scouting_pdf_ocr_backoff');
    $drop_doc($d);

    // A job the service forgot: that page fails, the document still completes around it
    $d = $make_doc('queue-forgot', array('total_pages' => 2));
    $answers[] = array(202, array('id' => $jid(31), 'status' => 'queued', 'page' => 1));
    $answers[] = array(202, array('id' => $jid(32), 'status' => 'queued', 'page' => 2));
    $tick();
    $answers[] = array(200, array('id' => $jid(31), 'status' => 'done', 'page' => 1, 'text' => 'One', 'words' => array(), 'engine' => 'x', 'pages' => 2));
    $answers[] = array(404, array('error' => 'not_found'));
    $tick();
    $row = $doc_row($d);
    check('queue forgot job: page failed, document done', array($row->status, (int) $row->pages_done, (int) $row->pages_failed), array('done', 1, 1));
    $drop_doc($d);

    // The lock: a held one stands a tick down, a dead run's is taken over
    $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", 'scouting_pdf_ocr_tick_lock', (string) time()));
    check('queue lock held: tick does nothing', $tick(), false);
    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", (string) (time() - 121), 'scouting_pdf_ocr_tick_lock'));
    check('queue lock of a dead run is taken over', $tick(), true);
    check('queue lock released after the tick', $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'scouting_pdf_ocr_tick_lock')), null);

    // A reader already read page 1: the count is learned without a new row
    $d = $make_doc('queue-prior');
    $wpdb->insert($ocr_table, array('doc_key' => $d->key, 'page' => 1, 'pdf_url' => $d->url, 'status' => 'done', 'source' => 'reader', 'review_status' => 'pending', 'asks' => 1, 'text' => 'One', 'engine' => 'x', 'requested_by' => 1, 'requested_at' => current_time('mysql'), 'finished_at' => current_time('mysql')));
    $answers[] = array(200, array('id' => $jid(41), 'status' => 'done', 'page' => 1, 'text' => 'One', 'words' => array(), 'engine' => 'x', 'pages' => 2));
    $answers[] = array(202, array('id' => $jid(42), 'status' => 'queued', 'page' => 2));
    $tick();
    check('queue learned the count from a reader-read page 1', (int) $doc_row($d)->total_pages, 2);
    $r = $qrows($d);
    check('queue no second row for page 1, page 2 sent', array(count($r), (int) $r[1]->page, $r[1]->status), array(2, 2, 'queued'));
    $drop_doc($d);

    // Queue pages never touch readers' daily allowances
    $wpdb->insert($ocr_table, array('doc_key' => str_repeat('9', 32), 'page' => 1, 'pdf_url' => "$S/php-checks/bulk-seed.pdf", 'status' => 'done', 'source' => 'bulk', 'asks' => 5, 'requested_at' => current_time('mysql'), 'finished_at' => current_time('mysql')));
    $quser = wp_insert_user(array('user_login' => 'ocr-check-q', 'user_pass' => wp_generate_password(), 'user_email' => 'ocr-check-q@example.com', 'role' => 'subscriber'));
    wp_set_current_user($quser);
    $answers[] = array(202, array('id' => $jid(51), 'status' => 'queued', 'page' => 1));
    $with(array('user_limit' => 1, 'site_limit' => 1), function() use ($ask, $S) {
        check('queue pages do not count against reader limits', $ask(array('url' => "$S/php-checks/fresh.pdf", 'page' => 1))[1], 'queued');
    });
    wp_set_current_user(0);

    // Auto-approve: new text arrives already checked off
    $d = $make_doc('queue-auto');
    $answers[] = array(202, array('id' => $jid(61), 'status' => 'queued', 'page' => 1));
    $tick(array('auto_approve' => 1));
    $answers[] = array(200, array('id' => $jid(61), 'status' => 'done', 'page' => 1, 'text' => 'One', 'words' => array(), 'engine' => 'x', 'pages' => 1));
    $tick(array('auto_approve' => 1));
    $r = $qrows($d);
    check('queue auto-approve skips checking', $r[0]->review_status, 'auto');
    check('queue one-page document done', $doc_row($d)->status, 'done');
    $drop_doc($d);

    // Paused: nothing taken, nothing sent (pages in flight would still be polled)
    update_option('scouting_pdf_ocr_queue_state', 'paused', false);
    $d = $make_doc('queue-paused');
    $before = count($sent);
    $tick();
    check('queue paused: document left waiting, nothing sent', array($doc_row($d)->status, count($sent) - $before), array('queued', 0));
    update_option('scouting_pdf_ocr_queue_state', 'running', false);
    $drop_doc($d);

    // Switched off
    check('queue off: tick stands back', $with(array('queue_enabled' => 0), function() use ($Q) { return $Q->tick(); }), false);

    // The cron event follows the setting
    $with(array('queue_enabled' => 1), function() use ($Q) { $Q->maybe_schedule(); });
    check('queue cron scheduled when on', (bool) wp_next_scheduled('scouting_pdf_ocr_tick'), true);
    $with(array('queue_enabled' => 0), function() use ($Q) { $Q->maybe_schedule(); });
    check('queue cron cleared when off', (bool) wp_next_scheduled('scouting_pdf_ocr_tick'), false);

    // The dashboard's inline tick steps in when cron is late, and only then
    $stale_tick = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - 120);
    update_option('scouting_pdf_ocr_last_tick', $stale_tick, false);
    $with(array('queue_enabled' => 1), function() use ($Q) { $Q->tick_if_overdue(); });
    check('queue late: the dashboard tick steps in', get_option('scouting_pdf_ocr_last_tick') !== $stale_tick, true);
    $fresh_tick = get_option('scouting_pdf_ocr_last_tick');
    $with(array('queue_enabled' => 1), function() use ($Q) { $Q->tick_if_overdue(); });
    check('queue fresh: the dashboard tick stands back', get_option('scouting_pdf_ocr_last_tick'), $fresh_tick);

    // ---- Finding documents: every PDF in a post, auto-enqueue, the site scan ----
    $all = function($content) use ($E) { return $E::all_pdfs_in_content($content); };
    check('finds shortcode url', $all('x [pdf-embedder url="' . "$S/a.pdf" . '" title="A"] y'), array("$S/a.pdf"));
    check('finds underscore shortcode', $all('[pdf_embedder url="' . "$S/b.pdf" . '"]'), array("$S/b.pdf"));
    check('finds entity-mangled shortcode', $all('&#91;pdf-embedder url=&#8220;' . "$S/c.pdf" . '&#8221;&#93;'), array("$S/c.pdf"));
    check('finds legacy block comment', $all('<!-- wp:pdfemb/pdf-embedder-viewer {"url":"' . "$S/d.pdf" . '","title":"D"} --><div></div><!-- /wp:pdfemb/pdf-embedder-viewer -->'), array("$S/d.pdf"));
    check('finds scouting block comment', $all('<!-- wp:scouting/pdf-viewer {"url":"' . "$S/e.pdf" . '"} /-->'), array("$S/e.pdf"));
    check('finds old block markup', $all('<div class="wp-block-pdfemb-pdf-embedder-viewer"><a href="' . "$S/f.pdf" . '" class="pdfemb-viewer">F</a></div>'), array("$S/f.pdf"));
    check('finds plain paragraph link', $all('<p><a href="' . "$S/g.pdf" . '">G</a></p>'), array("$S/g.pdf"));
    check('keeps document order, each once', $all('<p><a href="' . "$S/one.pdf" . '">1</a></p>[pdf-embedder url="' . "$S/two.pdf" . '"]<a href="' . "$S/one.pdf" . '">again</a>'), array("$S/one.pdf", "$S/two.pdf"));
    check('leaves other hosts out', $all('<a href="https://evil.example.com/x.pdf">x</a><a href="' . "$S/h.pdf" . '">h</a>'), array("$S/h.pdf"));

    $test_posts = array();
    $mk_post = function($content, $status = 'publish') use (&$test_posts, $with) {
        return $with(array('queue_enabled' => 1), function() use ($content, $status, &$test_posts) {
            $id = wp_insert_post(array('post_title' => 'OCR queue check', 'post_content' => $content, 'post_status' => $status, 'post_type' => 'post'));
            $test_posts[] = $id;
            return $id;
        });
    };
    $doc_count = function() use ($wpdb, $docs_table, $S) {
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $docs_table WHERE pdf_url LIKE %s", "$S/php-checks/%"));
    };
    $pid = $mk_post('<p><a href="' . "$S/php-checks/auto-a.pdf" . '">A</a></p><p><a href="http://storage.scoutingmemories.org/php-checks/auto-b.pdf">B</a></p>');
    check('posts: two documents queued on save', $doc_count(), 2);
    $front = $wpdb->get_row($wpdb->prepare("SELECT * FROM $docs_table WHERE pdf_url = %s", "$S/php-checks/auto-a.pdf"));
    check('posts: queued like a submission', array($front->source, (int) $front->priority, $front->status, (int) $front->post_id), array('auto', 1, 'queued', $pid));
    check('posts: http spelling stored once, as https', (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $docs_table WHERE doc_key = %s", $O::doc_key("$S/php-checks/auto-b.pdf"))), 1);
    $with(array('queue_enabled' => 1), function() use ($pid) {
        wp_update_post(array('ID' => $pid, 'post_title' => 'OCR queue check again'));
    });
    check('posts: saving again adds nothing', $doc_count(), 2);
    $mk_post('<p><a href="' . "$S/php-checks/auto-c.pdf" . '">C</a></p>', 'pending');
    check('posts: a pending submission is queued too', $doc_count(), 3);
    $mk_post('<p><a href="' . "$S/php-checks/auto-d.pdf" . '">D</a></p>', 'draft');
    check('posts: a draft is not queued', $doc_count(), 3);
    $with(array('queue_enabled' => 1, 'auto_enqueue' => 0), function() use (&$test_posts, $S) {
        $test_posts[] = wp_insert_post(array('post_title' => 'OCR queue check', 'post_content' => '<p><a href="' . "$S/php-checks/auto-e.pdf" . '">E</a></p>', 'post_status' => 'publish', 'post_type' => 'post'));
    });
    check('posts: nothing queued with the setting off', $doc_count(), 3);

    $wpdb->query("DELETE FROM $docs_table");
    $b1 = Scouting_PDF_OCR_Queue::scan_batch(0, 2);
    check('scan: a batch walks its two posts', $b1['posts_scanned'], 2);
    check('scan: not done after one batch', $b1['done'], false);
    $b2 = Scouting_PDF_OCR_Queue::scan_batch($b1['last_id'], 2);
    check('scan: the next batch starts after the first', $b2['last_id'] > $b1['last_id'], true);
    $after = 0;
    $guard = 0;
    do {
        $b = Scouting_PDF_OCR_Queue::scan_batch($after, 50);
        $after = $b['last_id'];
    } while (!$b['done'] && ++$guard < 200);
    check('scan: finishes', $b['done'], true);
    // auto-a, auto-b and the pending auto-c again, plus auto-e, whose post was published
    // while auto-enqueue was off: the scan reads every published and pending post
    check('scan: found every storage document in the posts', $doc_count(), 4);
    $scanned = $wpdb->get_row($wpdb->prepare("SELECT * FROM $docs_table WHERE pdf_url = %s", "$S/php-checks/auto-a.pdf"));
    check('scan: waits its turn behind submissions', array($scanned->source, (int) $scanned->priority), array('bulk', 0));
} finally {
    wp_set_current_user(0);
    if (!empty($quser)) {
        wp_delete_user($quser);
    }
    if (!empty($test_posts)) {
        foreach ($test_posts as $id) {
            if ($id && !is_wp_error($id)) {
                wp_delete_post($id, true);
            }
        }
    }
    $wpdb->query("DELETE FROM $docs_table");
    $wpdb->query("DELETE FROM $ocr_table WHERE $ours");
    delete_option('scouting_pdf_ocr_queue_state');
    delete_option('scouting_pdf_ocr_last_tick');
    delete_transient('scouting_pdf_ocr_backoff');
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s", 'scouting_pdf_ocr_tick_lock'));
    wp_clear_scheduled_hook('scouting_pdf_ocr_tick');
}

// ---- The dashboard's endpoints: who may call what, and what they answer ----
$admin_users = array();
foreach (array('subscriber' => 'ocr-check-sub', 'editor' => 'ocr-check-ed', 'administrator' => 'ocr-check-adm') as $role => $login) {
    $old = get_user_by('login', $login);
    if ($old) {
        wp_delete_user($old->ID);
    }
    $admin_users[$role] = wp_insert_user(array('user_login' => $login, 'user_pass' => wp_generate_password(), 'user_email' => $login . '@example.com', 'role' => $role));
}
$call = function($method, $path, $body = null, $query = array()) {
    $r = new WP_REST_Request($method, $path);
    if ($body !== null) {
        $r->set_header('Content-Type', 'application/json');
        $r->set_body(wp_json_encode($body));
    }
    foreach ($query as $k => $v) {
        $r->set_param($k, $v);
    }
    $res = rest_do_request($r);
    $data = $res->get_data();
    return array($res->get_status(), is_array($data) && isset($data['code']) ? $data['code'] : '', $data, $res);
};
$seed_page = function($d, $page, $status, $review, $text = 'T', $source = 'bulk') use ($wpdb, $ocr_table) {
    $wpdb->insert($ocr_table, array('doc_key' => $d->key, 'page' => $page, 'pdf_url' => $d->url, 'status' => $status,
        'source' => $source, 'review_status' => $review, 'asks' => 0, 'text' => $status === 'done' ? $text : null,
        'engine' => 'x', 'requested_at' => current_time('mysql'), 'finished_at' => current_time('mysql')));
    return (int) $wpdb->insert_id;
};
update_option('scouting_pdf_ocr_queue_state', 'paused', false);
$sent = array();
$answers = array();
$matrix_post = 0;

try {
    // One finished document: pages 1-3 read (waiting for a reviewer), page 4 failed
    $d = $make_doc('admin-doc', array('status' => 'done', 'total_pages' => 4, 'pages_done' => 3, 'pages_failed' => 1,
        'started_at' => current_time('mysql'), 'finished_at' => current_time('mysql')));
    $p1 = $seed_page($d, 1, 'done', 'pending', 'T');
    $p2 = $seed_page($d, 2, 'done', 'pending', 'T');
    $p3 = $seed_page($d, 3, 'done', 'pending', 'T');
    $p4 = $seed_page($d, 4, 'failed', '', '');
    $matrix_post = wp_insert_post(array('post_title' => 'OCR admin check', 'post_status' => 'publish', 'post_type' => 'post',
        'post_content' => '<p><a href="' . "$S/php-checks/mx-1.pdf" . '">1</a></p><p><a href="' . "$S/php-checks/mx-2.pdf" . '">2</a></p>'));

    // Who is let in: logged out 401, subscriber 403 everywhere, editor 403 on the queue
    $v1 = '/scouting-pdf/v1';
    $matrix = array(
        array('GET',  "$v1/ocr/admin/status", null, array()),
        array('POST', "$v1/ocr/admin/queue", array('action' => 'pause'), array()),
        array('GET',  "$v1/ocr/admin/docs", null, array()),
        array('POST', "$v1/ocr/admin/docs", array('url' => "$S/php-checks/matrix.pdf"), array()),
        array('POST', "$v1/ocr/admin/docs/{$d->id}/retry", array(), array()),
        array('POST', "$v1/ocr/admin/docs/{$d->id}/remove", array(), array()),
        array('POST', "$v1/ocr/admin/scan", array('after_id' => 0), array()),
        array('GET',  "$v1/ocr/admin/doc-text", null, array('doc_key' => $d->key)),
        array('POST', "$v1/ocr/admin/approve/$p1", array(), array()),
        array('POST', "$v1/ocr/admin/approve-doc", array('doc_key' => $d->key), array()),
        array('POST', "$v1/ocr/admin/enqueue-post", array('post_id' => $matrix_post), array()),
    );
    wp_set_current_user(0);
    foreach ($matrix as $i => $m) {
        check("admin endpoints: logged out refused ($i)", $call($m[0], $m[1], $m[2], $m[3])[0], 401);
    }
    wp_set_current_user($admin_users['subscriber']);
    foreach ($matrix as $i => $m) {
        check("admin endpoints: a subscriber is refused ($i)", $call($m[0], $m[1], $m[2], $m[3])[0], 403);
    }
    wp_set_current_user($admin_users['editor']);
    foreach (array(1, 3, 4, 5, 6) as $i) {
        $m = $matrix[$i];
        check("admin endpoints: an editor cannot run the queue ($i)", $call($m[0], $m[1], $m[2], $m[3])[0], 403);
    }

    // The Queue tab's list: order, filter, the checked count (as a reviewer)
    $d_q = $make_doc('admin-waiting', array('priority' => 1));
    $d_w = $make_doc('admin-working', array('status' => 'working', 'started_at' => current_time('mysql')));
    $r = $call('GET', "$v1/ocr/admin/docs");
    check('docs list: total and order', array($r[2]['total'], $r[2]['docs'][0]['label'], $r[2]['docs'][1]['label'], $r[2]['docs'][2]['label']),
        array(3, 'admin-working', 'admin-waiting', 'admin-doc'));
    check('docs list: counters carried over', array($r[2]['docs'][2]['pages_done'], $r[2]['docs'][2]['pages_failed'], $r[2]['docs'][2]['pages_checked']), array(3, 1, 0));
    $r = $call('GET', "$v1/ocr/admin/docs", null, array('status' => 'queued'));
    check('docs list: the status filter', array($r[2]['total'], $r[2]['docs'][0]['label']), array(1, 'admin-waiting'));
    $drop_doc($d_q);
    $drop_doc($d_w);

    // The status poll (as a reviewer)
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1) { return $call('GET', "$v1/ocr/admin/status"); });
    check('status answers a reviewer', $r[0], 200);
    check('status shape', array_keys($r[2]), array('queue', 'backoff_until', 'last_tick', 'last_tick_age', 'docs', 'current', 'pages_unchecked', 'today'));
    check('status: paused', $r[2]['queue'], 'paused');
    check('status: the waiting text is counted', $r[2]['pages_unchecked'], 3);
    check('status: not cached', $r[3]->get_headers()['Cache-Control'], 'no-store');

    // Checking text (as a reviewer)
    $r = $call('GET', "$v1/ocr/admin/doc-text", null, array('doc_key' => $d->key));
    check('doc-text: the document and its pages', array($r[2]['doc']['label'], $r[2]['total_rows'], count($r[2]['pages'])), array('admin-doc', 4, 4));
    check('doc-text: a page row', array($r[2]['pages'][0]['page'], $r[2]['pages'][0]['status'], $r[2]['pages'][0]['review_status'], $r[2]['pages'][0]['text'], $r[2]['pages'][0]['edited']),
        array(1, 'done', 'pending', 'T', false));
    $r = $call('GET', "$v1/ocr/admin/doc-text", null, array('doc_key' => str_repeat('f', 32)));
    check('doc-text: a document never queued', array($r[2]['doc'], $r[2]['pages']), array(null, array()));

    $r = $call('POST', "$v1/ocr/admin/approve/$p1", array('text' => 'Corrected'));
    check('approve: with a correction', array($r[0], $r[2]['review_status']), array(200, 'approved'));
    $row = $wpdb->get_row($wpdb->prepare("SELECT text, text_original, review_status, reviewed_by FROM $ocr_table WHERE id = %d", $p1));
    check('approve: the correction saved, the machine text kept', array($row->text, $row->text_original, $row->review_status, (int) $row->reviewed_by),
        array('Corrected', 'T', 'approved', $admin_users['editor']));
    $call('POST', "$v1/ocr/admin/approve/$p1", array('text' => 'Corrected again'));
    $row = $wpdb->get_row($wpdb->prepare("SELECT text, text_original FROM $ocr_table WHERE id = %d", $p1));
    check('approve: a second correction keeps the first machine text', array($row->text, $row->text_original), array('Corrected again', 'T'));
    $call('POST', "$v1/ocr/admin/approve/$p2", array());
    $row = $wpdb->get_row($wpdb->prepare("SELECT text, text_original, review_status FROM $ocr_table WHERE id = %d", $p2));
    check('approve: unedited leaves the text alone', array($row->text, $row->text_original, $row->review_status), array('T', null, 'approved'));
    check('approve: text too long refused', $call('POST', "$v1/ocr/admin/approve/$p3", array('text' => str_repeat('a', 200001)))[1], 'text_too_long');
    check('approve: no such page', $call('POST', "$v1/ocr/admin/approve/999999", array())[1], 'no_such_page');
    check('approve: a failed page cannot be approved', $call('POST', "$v1/ocr/admin/approve/$p4", array())[1], 'no_such_page');
    $r = $call('POST', "$v1/ocr/admin/approve-doc", array('doc_key' => $d->key));
    check('approve-doc: approves what still waits', $r[2]['approved'], 1);
    $r = $call('GET', "$v1/ocr/admin/docs");
    check('docs list: the checked count follows', $r[2]['docs'][0]['pages_checked'], 3);

    // "Read this document" from the post's edit screen (as a reviewer)
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1, $matrix_post) {
        return $call('POST', "$v1/ocr/admin/enqueue-post", array('post_id' => $matrix_post));
    });
    check('enqueue-post: both documents queued', array($r[2]['added'], $r[2]['existing']), array(2, 0));
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1, $matrix_post) {
        return $call('POST', "$v1/ocr/admin/enqueue-post", array('post_id' => $matrix_post));
    });
    check('enqueue-post: asking again adds nothing', array($r[2]['added'], $r[2]['existing']), array(0, 2));
    check('enqueue-post: no such post', $with(array('queue_enabled' => 1), function() use ($call, $v1) {
        return $call('POST', "$v1/ocr/admin/enqueue-post", array('post_id' => 999999));
    })[1], 'no_such_post');

    // The queue's controls (as an administrator)
    wp_set_current_user($admin_users['administrator']);
    check('queue control: refused while background reading is off', $call('POST', "$v1/ocr/admin/queue", array('action' => 'pause'))[1], 'queue_off');
    set_transient('scouting_pdf_ocr_backoff', time() + 600, 600);
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1) { return $call('POST', "$v1/ocr/admin/queue", array('action' => 'resume')); });
    check('queue control: resume', $r[2]['queue'], 'running');
    check('queue control: resume clears the backoff', Scouting_PDF_OCR_Queue::backoff_until(), 0);
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1) { return $call('POST', "$v1/ocr/admin/queue", array('action' => 'pause')); });
    check('queue control: pause', $r[2]['queue'], 'paused');
    $before = count($sent);
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1) { return $call('POST', "$v1/ocr/admin/queue", array('action' => 'tick')); });
    check('queue control: run once now', array($r[2]['ticked'], count($sent) - $before), array(true, 0));
    check('queue control: a made-up action is refused', $call('POST', "$v1/ocr/admin/queue", array('action' => 'explode'))[0], 400);

    // Add one document by its link (as an administrator)
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1, $S) { return $call('POST', "$v1/ocr/admin/docs", array('url' => "$S/php-checks/add-one.pdf")); });
    check('add by link: added to the front', $r[0], 201);
    $added_doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $docs_table WHERE doc_key = %s", $O::doc_key("$S/php-checks/add-one.pdf")));
    check('add by link: the row', array($added_doc->source, (int) $added_doc->priority, (int) $added_doc->enqueued_by, $added_doc->label),
        array('bulk', 1, $admin_users['administrator'], 'add-one.pdf'));
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1, $S) { return $call('POST', "$v1/ocr/admin/docs", array('url' => "$S/php-checks/add-one.pdf")); });
    check('add by link: already in the queue', array($r[0], $r[2]['status']), array(200, 'already_queued'));
    $wpdb->update($docs_table, array('status' => 'done'), array('id' => $added_doc->id));
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1, $S) { return $call('POST', "$v1/ocr/admin/docs", array('url' => "$S/php-checks/add-one.pdf")); });
    check('add by link: already read', $r[2]['status'], 'already_read');
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1) { return $call('POST', "$v1/ocr/admin/docs", array('url' => 'https://evil.example.com/a.pdf')); });
    check('add by link: the link check speaks', $r[1], 'bad_host');

    // Try again and Remove (as an administrator)
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1, $d) { return $call('POST', "$v1/ocr/admin/docs/{$d->id}/retry", array()); });
    check('retry: a done document with failures goes back in line', $r[2]['status'], 'queued');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $docs_table WHERE id = %d", $d->id));
    check('retry: counters and page count kept', array($row->status, (int) $row->pages_done, (int) $row->pages_failed, (int) $row->total_pages, $row->started_at, $row->finished_at),
        array('queued', 3, 0, 4, null, null));
    check('retry: the failed page rows are gone', (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ocr_table WHERE doc_key = %s AND status = 'failed'", $d->key)), 0);
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1, $d) { return $call('POST', "$v1/ocr/admin/docs/{$d->id}/retry", array()); });
    check('retry: nothing to try again', array($r[0], $r[1]), array(409, 'not_retryable'));
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1, $d) { return $call('POST', "$v1/ocr/admin/docs/{$d->id}/remove", array()); });
    check('remove: a waiting document leaves the queue', $r[2]['removed'], true);
    check('remove: its text stays', (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ocr_table WHERE doc_key = %s", $d->key)), 3);
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1, $added_doc) { return $call('POST', "$v1/ocr/admin/docs/{$added_doc->id}/remove", array()); });
    check('remove: a read document is refused', array($r[0], $r[1]), array(409, 'already_started'));
    check('remove: no such document', $with(array('queue_enabled' => 1), function() use ($call, $v1) { return $call('POST', "$v1/ocr/admin/docs/999999/remove", array()); })[1], 'no_such_doc');

    // The scan endpoint answers batch bookkeeping (as an administrator)
    $r = $with(array('queue_enabled' => 1), function() use ($call, $v1) { return $call('POST', "$v1/ocr/admin/scan", array('after_id' => 999999999)); });
    check('scan endpoint: past the last post', array($r[2]['done'], $r[2]['posts_scanned']), array(true, 0));

    // The status poll keeps the queue moving when cron is late
    $wpdb->query("DELETE FROM $docs_table WHERE status = 'queued'");
    update_option('scouting_pdf_ocr_queue_state', 'running', false);
    $stale_tick = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - 120);
    update_option('scouting_pdf_ocr_last_tick', $stale_tick, false);
    $with(array('queue_enabled' => 1), function() use ($call, $v1) { $call('GET', "$v1/ocr/admin/status"); });
    check('status: a late queue is ticked in passing', get_option('scouting_pdf_ocr_last_tick') !== $stale_tick, true);
    update_option('scouting_pdf_ocr_queue_state', 'paused', false);

    // The whole surface is off with the feature
    check('admin endpoints: off with the feature', $with(array('enabled' => 0), function() use ($call, $v1) { return $call('GET', "$v1/ocr/admin/status"); })[1], 'ocr_off');
} finally {
    wp_set_current_user(0);
    foreach ($admin_users as $id) {
        if ($id && !is_wp_error($id)) {
            wp_delete_user($id);
        }
    }
    if ($matrix_post && !is_wp_error($matrix_post)) {
        wp_delete_post($matrix_post, true);
    }
    $wpdb->query("DELETE FROM $docs_table");
    $wpdb->query("DELETE FROM $ocr_table WHERE $ours");
    delete_option('scouting_pdf_ocr_queue_state');
    delete_option('scouting_pdf_ocr_last_tick');
    delete_transient('scouting_pdf_ocr_backoff');
}

if ($failures) {
    echo "$failures PHP CHECK(S) FAILED\n";
    exit(1);
}
echo "ALL PHP CHECKS PASSED\n";
