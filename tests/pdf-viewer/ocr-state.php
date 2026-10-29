<?php
/**
 * Set up the OCR state the browser tests need, in the test WordPress. Test environment only.
 *
 *   php ocr-state.php /path/to/test/wordpress <command>
 *
 *   on | off              switch the feature on (pointing at fake-ocr.mjs) or off
 *   clear                 forget the test documents' read pages and queue rows
 *   limits <site> <user>  pages per day for the whole site and per reader
 *   age <minutes>         make the test document's queued pages look that many minutes old
 *   rows                  print the test document's saved pages as JSON
 *   queue-on | queue-off  background reading on (and running) or off (and paused)
 *   auto-approve on|off   approve machine-read text automatically
 *   docs                  print the test documents' queue rows as JSON
 *   approve <page>        mark one read test page as checked by a reviewer
 *   page-row <page>       print one test page's row (status, review, text) as JSON
 *   post-id <path>        print the ID of the post at that path
 *
 * Only rows of the browser tests' documents (links under /sm-tests/) are touched, so the PHP
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
$docs_table = $wpdb->prefix . 'scouting_pdf_ocr_docs';
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
        $wpdb->query("DELETE FROM $docs_table WHERE $ours");
        delete_option('scouting_pdf_ocr_last_tick');
        delete_transient('scouting_pdf_ocr_backoff');
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s", 'scouting_pdf_ocr_tick_lock'));
        break;
    case 'queue-on':
        update_option('scouting_pdf_ocr', array_merge($settings, array(
            'enabled' => 1, 'url' => 'http://127.0.0.1:8899', 'secret' => 'fake-ocr-test-secret', 'queue_enabled' => 1,
        )));
        update_option('scouting_pdf_ocr_queue_state', 'running', false);
        delete_transient('scouting_pdf_ocr_backoff');
        break;
    case 'queue-off':
        update_option('scouting_pdf_ocr', array_merge($settings, array('queue_enabled' => 0)));
        update_option('scouting_pdf_ocr_queue_state', 'paused', false);
        wp_clear_scheduled_hook('scouting_pdf_ocr_tick');
        break;
    case 'auto-approve':
        update_option('scouting_pdf_ocr', array_merge($settings, array('auto_approve' => ($argv[3] ?? '') === 'on' ? 1 : 0)));
        break;
    case 'docs':
        echo wp_json_encode($wpdb->get_results("SELECT doc_key, pdf_url, label, status, total_pages, pages_done, pages_failed, priority, source FROM $docs_table WHERE $ours ORDER BY id")), "\n";
        break;
    case 'approve':
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET review_status = 'approved', reviewed_by = 1, reviewed_at = %s WHERE status = 'done' AND page = %d AND $ours",
            current_time('mysql'), (int) ($argv[3] ?? 0)
        ));
        break;
    case 'page-row':
        echo wp_json_encode($wpdb->get_row($wpdb->prepare(
            "SELECT page, status, review_status, source, text, text_original FROM $table WHERE page = %d AND $ours", (int) ($argv[3] ?? 0)
        ))), "\n";
        break;
    case 'post-id':
        echo (int) url_to_postid(home_url((string) ($argv[3] ?? ''))), "\n";
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
