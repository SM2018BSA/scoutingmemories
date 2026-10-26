<?php
/**
 * Set up the OCR state the browser tests need, in the test WordPress. Test environment only.
 *
 *   php ocr-state.php /path/to/test/wordpress <command>
 *
 *   on | off              switch the feature on (pointing at fake-ocr.mjs) or off
 *   clear                 forget the test document's read pages
 *   limits <site> <user>  pages per day for the whole site and per reader
 *   age <minutes>         make the test document's queued pages look that many minutes old
 *   rows                  print the test document's saved pages as JSON
 *
 * Only rows of the browser tests' document (links under /sm-tests/) are touched, so the PHP
 * checks, which use their own links, can run at the same time.
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

global $wpdb;
$table = $wpdb->prefix . 'scouting_pdf_ocr';
$ours = $wpdb->prepare('pdf_url LIKE %s', 'https://storage.scoutingmemories.org/sm-tests/%');
Scouting_PDF_OCR::get_instance()->maybe_create_table();
$settings = get_option('scouting_pdf_ocr');
$settings = is_array($settings) ? $settings : array();

switch ($argv[2] ?? '') {
    case 'on':
        update_option('scouting_pdf_ocr', array_merge($settings, array(
            'enabled' => 1, 'url' => 'http://127.0.0.1:8899', 'secret' => 'fake-ocr-test-secret',
        )));
        break;
    case 'off':
        update_option('scouting_pdf_ocr', array_merge($settings, array('enabled' => 0)));
        break;
    case 'clear':
        $wpdb->query("DELETE FROM $table WHERE $ours");
        break;
    case 'limits':
        update_option('scouting_pdf_ocr', array_merge($settings, array(
            'site_limit' => (int) ($argv[3] ?? 100), 'user_limit' => (int) ($argv[4] ?? 20),
        )));
        break;
    case 'age':
        $when = date('Y-m-d H:i:s', strtotime(current_time('mysql')) - 60 * (int) ($argv[3] ?? 0));
        $wpdb->query($wpdb->prepare("UPDATE $table SET requested_at = %s WHERE status = 'queued' AND $ours", $when));
        break;
    case 'rows':
        echo wp_json_encode($wpdb->get_results("SELECT doc_key, page, status, asks, requested_by, pdf_url FROM $table WHERE $ours ORDER BY id")), "\n";
        break;
    default:
        fwrite(STDERR, "Unknown command\n");
        exit(1);
}
