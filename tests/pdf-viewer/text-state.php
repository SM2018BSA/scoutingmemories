<?php
/**
 * Set up the document text the browser tests need, in the test WordPress. Test environment only.
 *
 *   php text-state.php /path/to/test/wordpress <command>
 *
 *   seed               save read pages for the test document (pages 1 and 4 with text, page 2
 *                      without words, page 3 not read) and tell the plugin, as the OCR does
 *   clear              forget the test document's pages and every post's copy of document text
 *   drop-copies        forget only the posts' copies (what "Make document text available to
 *                      the site" brings back)
 *   documents <path>   print the copy the post at that path keeps, as JSON
 *   correct <page> <text>   a reviewer's correction, through the plugin's own approve route
 *   theme classic|default   switch to the classic test theme (search results like the live
 *                      theme's), or back to the theme the site had
 *   checks             server-side checks of the text classes; prints ALL TEXT CHECKS PASSED
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
Scouting_PDF_OCR::get_instance()->maybe_create_table();

const SM_TEXT_PDF = 'https://storage.scoutingmemories.org/sm-tests/leaders-guide.pdf';
$doc_key = Scouting_PDF_OCR::doc_key(Scouting_PDF_OCR::canonical_url(SM_TEXT_PDF));
$meta_key = Scouting_PDF_Document_Text::META_KEY;

function sm_text_save_page($doc_key, $page, $status, $text) {
    global $wpdb, $table;
    $wpdb->insert($table, array(
        'doc_key' => $doc_key, 'page' => $page, 'pdf_url' => SM_TEXT_PDF, 'status' => $status, 'source' => 'bulk',
        'review_status' => $status === 'done' ? 'pending' : 'auto', 'text' => $text,
        'requested_at' => current_time('mysql'), 'finished_at' => current_time('mysql'),
    ));
}

function sm_text_forget($doc_key) {
    global $wpdb, $table;
    $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE doc_key = %s", $doc_key));
}

// The test site's terms: two the test document names (a camp, a lodge), and ones it does not
function sm_text_terms() {
    return array(
        'camp'       => array('Zephyrcrest_Lookout__end_date_uncertain__9001', 'Camp_Nowhere_9002', 'none'),
        'lodge'      => array('Quillfeather_Lodge_', 'Quillfeather_'),
        'council'    => array('Otter_Patrol_Council_77'),
        'state'      => array('CA'),
        'start_date' => array(),
        'end_date'   => array(),
    );
}

function sm_text_forget_suggestions() {
    delete_post_meta_by_key(Scouting_PDF_Tag_Suggestions::META_KEY);
    delete_post_meta_by_key(Scouting_PDF_Tag_Suggestions::WAITING_KEY);
    $post_id = url_to_postid(home_url('/ocr-document/'));
    foreach (array_keys(sm_text_terms()) as $taxonomy) {
        if ($post_id && taxonomy_exists($taxonomy)) {
            wp_set_object_terms($post_id, array(), $taxonomy);
        }
    }
}

$page_1 = "Troop 12 hiked to Zephyrcrest Lookout in 1938.\nThe Quillfeather patrol carried the flag & the <b>map</b>.";
$page_4 = "Waterfront rules at Zephyrcrest: every Scout swims with a buddy.\n\nThe relay was won by the Otter patrol.";

switch ($argv[2] ?? '') {
    case 'seed':
        sm_text_forget($doc_key);
        sm_text_save_page($doc_key, 1, 'done', $page_1);
        sm_text_save_page($doc_key, 2, 'empty', '');
        sm_text_save_page($doc_key, 4, 'done', $page_4);
        do_action('scouting_pdf_document_text_changed', $doc_key);
        // Names the site knows, spelled the way the live site spells its terms
        sm_text_forget_suggestions();
        foreach (sm_text_terms() as $taxonomy => $names) {
            foreach ($names as $name) {
                if (!term_exists($name, $taxonomy)) {
                    wp_insert_term($name, $taxonomy);
                }
            }
        }
        break;
    case 'clear':
        sm_text_forget($doc_key);
        delete_post_meta_by_key($meta_key);
        delete_post_meta_by_key(Scouting_PDF_Document_Text::WORDS_KEY);
        sm_text_forget_suggestions();
        break;
    case 'suggestions':
        $post_id = url_to_postid(home_url((string) ($argv[3] ?? '')));
        echo wp_json_encode(array_values(Scouting_PDF_Tag_Suggestions::all($post_id))), "\n";
        break;
    case 'terms':
        $post_id = url_to_postid(home_url((string) ($argv[3] ?? '')));
        $terms = array();
        foreach (array_keys(sm_text_terms()) as $taxonomy) {
            $terms[$taxonomy] = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'names'));
        }
        echo wp_json_encode($terms), "\n";
        break;
    case 'drop-copies':
        delete_post_meta_by_key($meta_key);
        delete_post_meta_by_key(Scouting_PDF_Document_Text::WORDS_KEY);
        break;
    case 'documents':
        $post_id = url_to_postid(home_url((string) ($argv[3] ?? '')));
        echo wp_json_encode(Scouting_PDF_Document_Text::documents($post_id)), "\n";
        break;
    case 'correct':
        $row_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE doc_key = %s AND page = %d", $doc_key, (int) ($argv[3] ?? 0)));
        $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
        wp_set_current_user((int) $admins[0]);
        $request = new WP_REST_Request('POST', '/scouting-pdf/v1/ocr/admin/approve/' . $row_id);
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode(array('text' => (string) ($argv[4] ?? ''))));
        echo wp_json_encode(rest_do_request($request)->get_data()), "\n";
        break;
    case 'theme':
        if (($argv[3] ?? '') === 'classic') {
            if (get_stylesheet() !== 'sm-test-classic') {
                update_option('sm_test_previous_theme', get_stylesheet());
                switch_theme('sm-test-classic');
            }
        } elseif (get_option('sm_test_previous_theme')) {
            switch_theme(get_option('sm_test_previous_theme'));
            delete_option('sm_test_previous_theme');
        }
        break;
    case 'checks':
        $failures = 0;
        $check = function ($label, $actual, $expected) use (&$failures) {
            if ($actual !== $expected) {
                $failures++;
                echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
            }
        };
        $T = 'Scouting_PDF_Document_Text';

        // ---- The layout inside the meta value ----
        $pages = array(1 => "one\ntwo", 2 => '', 4 => "four \f with a page break inside");
        list($key, $back) = $T::decode($T::encode('abc', $pages));
        $check('decode: the key', $key, 'abc');
        $check('decode: read pages keep their numbers, the unread page 3 is left out', array_keys($back), array(1, 2, 4));
        $check('decode: text kept with its line breaks', $back[1], "one\ntwo");
        $check('decode: a page without words is empty', $back[2], '');
        $check('encode: a page break inside a page cannot shift the pages', $back[4], "four \n with a page break inside");
        $check('decode: a value with no pages', $T::decode('abc'), array('abc', array()));
        $check('encode: no pages', $T::encode('abc', array()), "abc\n");

        // ---- A post's copy follows what has been read ----
        sm_text_forget($doc_key);
        delete_post_meta_by_key($meta_key);
        delete_post_meta_by_key(Scouting_PDF_Document_Text::WORDS_KEY);
        $post_id = url_to_postid(home_url('/ocr-document/'));
        $check('the test post exists', $post_id > 0, true);
        $check('nothing read: no copy', $T::refresh_post($post_id), 0);
        $check('nothing read: no meta', get_post_meta($post_id, $meta_key, false), array());
        sm_text_save_page($doc_key, 2, 'empty', '');
        $check('only a page without words: still no copy', $T::refresh_post($post_id), 0);
        sm_text_save_page($doc_key, 1, 'done', $page_1);
        do_action('scouting_pdf_document_text_changed', $doc_key);
        $documents = $T::documents($post_id);
        $check('a read page reaches the post that shows the document', array_keys($documents), array($doc_key));
        $check('the text is stored exactly, markup and all', $documents[$doc_key][1], $page_1);
        $check('one meta value per document', count(get_post_meta($post_id, $meta_key, false)), 1);
        sm_text_save_page($doc_key, 4, 'done', "It\\'s a backslash \\ test");
        $T::refresh_post($post_id);
        $T::refresh_post($post_id);
        $check('refreshing twice keeps one value', count(get_post_meta($post_id, $meta_key, false)), 1);
        $check('backslashes survive', $T::documents($post_id)[$doc_key][4], "It\\'s a backslash \\ test");
        $check('other posts get nothing', $T::documents(url_to_postid(home_url('/leaders-guide/'))), array());
        $check('the word list for the search: each word once, lower case', (string) get_post_meta($post_id, $T::WORDS_KEY, true), ' troop 12 hiked to zephyrcrest lookout in 1938 the quillfeather patrol carried flag map it backslash test ');

        // ---- The document leaves the post: the copy goes with it ----
        $content = get_post_field('post_content', $post_id);
        wp_update_post(array('ID' => $post_id, 'post_content' => '<p>No document any more.</p>'));
        $check('document removed from the post: copy removed', get_post_meta($post_id, $meta_key, false), array());
        $check('and its word list with it', get_post_meta($post_id, $T::WORDS_KEY, true), '');
        wp_update_post(array('ID' => $post_id, 'post_content' => wp_slash($content)));
        $check('document back in the post: copy back', array_keys($T::documents($post_id)), array($doc_key));

        // ---- Hosts that cap the length of database reads (WP Engine drops reads over 16,384 characters) ----
        $long_reads = array();
        $watch = function ($query) use (&$long_reads) {
            if (strlen($query) > 16384 && preg_match('#^\s*select#i', $query)) {
                $long_reads[] = substr($query, 0, 80);
            }
            return $query;
        };
        add_filter('query', $watch);
        $long = str_repeat('Troop 12 hiked to the lookout and back again. ', 600); // about 27,000 characters
        sm_text_forget($doc_key);
        sm_text_save_page($doc_key, 1, 'done', $long);
        $T::refresh_post($post_id);
        $check('a long document is stored whole', strlen($T::documents($post_id)[$doc_key][1]), strlen(trim($long)));
        sm_text_save_page($doc_key, 2, 'done', $long . ' Second page.');
        $T::refresh_post($post_id);
        $check('a long document is replaced when it changes', array_keys($T::documents($post_id)[$doc_key]), array(1, 2));
        $check('still one value', count(get_post_meta($post_id, $meta_key, false)), 1);
        sm_text_forget($doc_key);
        $T::refresh_post($post_id);
        $check('a long document is removed when its text goes', get_post_meta($post_id, $meta_key, false), array());
        remove_filter('query', $watch);
        $check('no database read ever carried the document', $long_reads, array());

        // ---- Every page of text has an address search engines are told about ----
        sm_text_save_page($doc_key, 1, 'done', $page_1);
        sm_text_save_page($doc_key, 2, 'empty', '');
        sm_text_save_page($doc_key, 4, 'done', $page_4);
        $T::refresh_post($post_id);
        $sitemap = new Scouting_PDF_Text_Sitemap();
        $check('the sitemap lists the pages of text after the first, without the page that has no words', $sitemap->get_url_list(1), array(
            array('loc' => home_url('/ocr-document/') . '?pdf_text=' . $doc_key . '-4'),
        ));
        $check('one sitemap file is enough here', $sitemap->get_max_num_pages(), 1);
        $check('the sitemap is registered with WordPress', isset(wp_get_sitemap_providers()['pdftext']), true);
        sm_text_forget($doc_key);
        $T::refresh_post($post_id);

        // ---- The search words ----
        $S = 'Scouting_PDF_Search';
        $check('words: lower case, each once', $S::words('Camp  CAMP Tahosa'), array('camp', 'tahosa'));
        $check('words: quotes dropped, one-letter and excluded words left out', $S::words('"Camp Tahosa" a -lodge'), array('camp', 'tahosa'));
        $check('words: punctuation and hyphens cut words apart, as in the documents', $S::words('Ma-Ka-Ja-Wan, Tahosa!'), array('ma', 'ka', 'ja', 'wan', 'tahosa'));
        $check('words: at most six', count($S::words('a1 b2 c3 d4 e5 f6 g7 h8')), 6);
        $check('words: markup is not a word', $S::words('<script>alert(1)</script> <b>camp</b>'), array('camp'));

        // ---- The meta is the plugin's own: not editable through an edit screen or REST ----
        $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
        $check('nobody may edit the meta by hand', user_can((int) $admins[0], 'edit_post_meta', $post_id, $meta_key), false);

        // ---- New code keeps to WordPress's own functions ----
        $includes = WP_PLUGIN_DIR . '/scouting-pdf-embedder/includes/';
        // ---- Term names as words, and as the phrase to look for ----
        $N = 'Scouting_PDF_Term_Names';
        $check('readable: camp number dropped', $N::readable('Camp_Wabaningo_16145', 'camp'), 'Camp Wabaningo');
        $check('readable: council number kept', $N::readable('Evanston_Council_124', 'council'), 'Evanston Council 124');
        $check('phrase: notes and numbers dropped', $N::phrase('Camp_Red_Bird__start_date_uncertain__16528', 'camp'), 'Camp Red Bird');
        $check('phrase: a council with a dash is one name', $N::phrase('Evanston_-_North_Shore_Council_129', 'council'), 'Evanston North Shore Council');
        $check('phrase: a camp with a place after it', $N::phrase('Camp_Seminole_-_Davie__FL_-_renamed_Camp_Elmore_17303', 'camp'), 'Camp Seminole');
        $check('phrase: none names nothing', $N::phrase('none', 'camp'), '');
        $check('phrase: test terms are skipped', $N::phrase('test_635', 'camp'), '');
        $check('phrase: too short to be safe', $N::phrase('Camp_X_12', 'camp'), '');
        $check('pattern: whole words only', preg_match($N::pattern('Camp Red Bird'), 'At Camp Red Birdsong we'), 0);
        $check('pattern: any case, a line break between words', preg_match($N::pattern('Camp Red Bird'), "at CAMP RED\nBIRD, we"), 1);
        $check('pattern: a dash between words', preg_match($N::pattern('Evanston North Shore Council'), 'the Evanston-North Shore Council office'), 1);
        $check('pattern: markup characters are plain text', preg_match($N::pattern('A/B (C) Lodge'), 'the A/B (C) Lodge met'), 1);

        // ---- Suggestions: found in whole words, kept until decided, never twice ----
        sm_text_forget_suggestions();
        foreach (sm_text_terms() as $taxonomy => $names) {
            foreach ($names as $name) {
                if (!term_exists($name, $taxonomy)) {
                    wp_insert_term($name, $taxonomy);
                }
            }
        }
        sm_text_forget($doc_key);
        sm_text_save_page($doc_key, 1, 'done', $page_1);
        sm_text_save_page($doc_key, 4, 'done', $page_4);
        $T::refresh_post($post_id);
        $M = 'Scouting_PDF_Name_Matcher';
        $G = 'Scouting_PDF_Tag_Suggestions';
        $found = $M::suggestions_for($post_id);
        $summary = array_map(function ($s) { return $s['field'] . ': ' . $s['label'] . ' p' . $s['page']; }, $found);
        sort($summary);
        $check('the matcher finds the camp, the lodge and the year, and nothing else', $summary, array(
            'camp: Zephyrcrest Lookout end date uncertain p1', 'end_date: 1938 p1', 'lodge: Quillfeather p1', 'start_date: 1938 p1',
        ));
        $camp = current(array_filter($found, function ($s) { return $s['field'] === 'camp'; }));
        $check('the line it was found in', $camp['evidence'], 'Troop 12 hiked to Zephyrcrest Lookout in 1938. The Quillfeather patrol carried the flag & the <b>map</b>.');
        $check('all four are new', $G::add($post_id, $found), 4);
        $check('adding them again adds nothing', $G::add($post_id, $found), 0);
        $check('four wait', (int) get_post_meta($post_id, $G::WAITING_KEY, true), 4);
        $check('finding does not tag the post', wp_get_object_terms($post_id, 'camp', array('fields' => 'ids')), array());
        $camp_id = $G::id('camp', $camp['value']);
        $check('a decision is recorded', $G::decide($post_id, $camp_id, 'rejected')['status'], 'rejected');
        $check('a decided one cannot be decided again', $G::decide($post_id, $camp_id, 'added'), null);
        $check('three wait', (int) get_post_meta($post_id, $G::WAITING_KEY, true), 3);
        $check('a rejected one never comes back', $G::add($post_id, $M::suggestions_for($post_id)), 0);
        $lodge = current(array_filter($found, function ($s) { return $s['field'] === 'lodge'; }));
        wp_set_object_terms($post_id, (int) $lodge['value'], 'lodge', true);
        $check('a tag the post already has is not suggested', count(array_filter($M::suggestions_for($post_id), function ($s) { return $s['field'] === 'lodge'; })), 0);
        $check('an unknown field is refused', $G::add($post_id, array(array('field' => 'post_tag', 'value' => 1, 'label' => 'x', 'viewer' => 1, 'page' => 1, 'evidence' => '', 'phrase' => '', 'source' => 'match'))), 0);
        sm_text_forget_suggestions();

        foreach (array('document-text', 'text-sync', 'batch-button', 'transcript', 'search', 'seo', 'text-sitemap', 'term-names', 'tag-suggestions', 'name-matcher', 'suggestions-screen') as $name) {
            $source = file_get_contents($includes . 'class-scouting-pdf-' . $name . '.php');
            $check("$name: no \$wpdb", strpos($source, '$wpdb'), false);
            $check("$name: no SQL", preg_match('/\b(SELECT|INSERT INTO|UPDATE|DELETE FROM)\b\s/', $source), 0);
            $check("$name: stops when loaded directly", strpos($source, "if (!defined('ABSPATH'))") !== false, true);
        }

        sm_text_forget($doc_key);
        delete_post_meta_by_key($meta_key);
        delete_post_meta_by_key(Scouting_PDF_Document_Text::WORDS_KEY);
        if ($failures) {
            echo "$failures TEXT CHECK(S) FAILED\n";
            exit(1);
        }
        echo "ALL TEXT CHECKS PASSED\n";
        break;
    default:
        fwrite(STDERR, "Unknown command\n");
        exit(1);
}
