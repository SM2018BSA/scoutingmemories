<?php
/**
 * "Text of this document": the text read from a post's PDFs, under the post's content.
 *
 * The section stays out of sight until the reader presses Read text in the viewer. It
 * shows one page of text at a time: the first page comes with the post itself, and every
 * other page is fetched when the reader asks for it (htmx, an open-source library, swaps
 * the page in). The Previous/Next links are real links too, so they work without scripts.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_Transcript {

    const AJAX_ACTION = 'scouting_pdf_text_page';
    /** ?pdf_text=<document key>-<page> opens a post with that page of text showing */
    const QUERY_VAR = 'pdf_text';
    const HTMX_VERSION = '2.0.11';

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // After shortcodes and blocks have become the viewer (priorities 9 to 11)
        add_filter('the_content', array($this, 'append_to_content'), 20);
        add_action('wp_ajax_' . self::AJAX_ACTION, array($this, 'ajax_page'));
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, array($this, 'ajax_page'));
    }

    public function append_to_content($content) {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        return $content . self::html(get_the_ID());
    }

    /**
     * May this post's document text be shown to whoever is asking?
     */
    private static function readable_post($post_id) {
        // Nothing asked for is nothing: get_post() would answer with "the current post"
        $post = $post_id ? get_post($post_id) : null;
        return ($post && $post->post_status === 'publish' && !post_password_required($post)) ? $post : null;
    }

    /**
     * The sections for one post (one per document that has text), or '' when the post is
     * not published or has no text
     */
    public static function html($post_id) {
        $post = self::readable_post($post_id);
        $documents = $post ? self::documents_in_viewer_order($post) : array();
        if (!$documents) {
            return '';
        }
        wp_enqueue_script('htmx',
            plugins_url('assets/vendor/htmx/htmx.min.js', SCOUTING_PDF_PLUGIN_DIR . 'scouting-pdf-embedder.php'),
            array(), self::HTMX_VERSION, true);
        wp_enqueue_script('scouting-pdf-transcript',
            plugins_url('assets/js/scouting-pdf-transcript.js', SCOUTING_PDF_PLUGIN_DIR . 'scouting-pdf-embedder.php'),
            array('htmx'), SCOUTING_PDF_VERSION, true);

        $asked = self::asked_page($post);
        $html = '';
        foreach ($documents as $document) {
            $opened = $asked && $document['doc_key'] === $asked['document']['doc_key'];
            ob_start();
            load_template(SCOUTING_PDF_PLUGIN_DIR . 'templates/transcript.php', false, array(
                'post'     => $post,
                'document' => $document,
                'several'  => count($documents) > 1,
                'open'     => $opened,
                'page'     => $opened ? $asked['page'] : self::nearest_page($document['pages'], 1),
            ));
            $html .= ob_get_clean();
        }
        return $html;
    }

    /**
     * The page of text a link asks for (?pdf_text=<document key>-<page>): array('document' =>
     * …, 'page' => the read page shown for it, 'first' => it is the document's first page of
     * text), or null when the address asks for none or for one this post does not have.
     */
    public static function asked_page($post) {
        if (!isset($_GET[self::QUERY_VAR]) || !preg_match('/^([a-f0-9]{32})-([0-9]{1,4})$/', (string) wp_unslash($_GET[self::QUERY_VAR]), $found)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public text, read only.
            return null;
        }
        $post = self::readable_post($post);
        foreach ($post ? self::documents_in_viewer_order($post) : array() as $document) {
            if ($document['doc_key'] === $found[1]) {
                $page = self::nearest_page($document['pages'], (int) $found[2]);
                return array('document' => $document, 'page' => $page, 'first' => $page === self::nearest_page($document['pages'], 1));
            }
        }
        return null;
    }

    /**
     * GET admin-ajax.php?action=scouting_pdf_text_page&post=..&doc=..&page=..: one page of
     * one document's text, as the piece of HTML the section swaps in. Public, read only.
     */
    public function ajax_page() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- public text, read only.
        $post = self::readable_post(isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0);
        $doc_key = isset($_GET['doc']) ? sanitize_key(wp_unslash($_GET['doc'])) : '';
        $page = isset($_GET['page']) ? absint(wp_unslash($_GET['page'])) : 1;
        // phpcs:enable
        $document = null;
        foreach ($post ? self::documents_in_viewer_order($post) : array() as $candidate) {
            if ($candidate['doc_key'] === $doc_key) {
                $document = $candidate;
            }
        }
        if (!$document) {
            status_header(404);
            wp_die(esc_html('This text is not available.'), '', array('response' => 404));
        }
        // Text changes only when a page is read or corrected: browsers may keep it a few minutes
        header('Cache-Control: ' . (is_user_logged_in() ? 'private, no-cache' : 'public, max-age=300'));
        self::render_page($post, $document, self::nearest_page($document['pages'], $page));
        wp_die();
    }

    /**
     * Print one page of a document's text with its Previous/Next links
     */
    public static function render_page($post, $document, $page) {
        load_template(SCOUTING_PDF_PLUGIN_DIR . 'templates/transcript-page.php', false, array(
            'post'     => $post,
            'document' => $document,
            'page'     => $page,
        ));
    }

    /**
     * The read page to show for a page number: that page, else the next read one, else the last
     */
    public static function nearest_page($pages, $wanted) {
        $numbers = array_keys($pages);
        foreach ($numbers as $number) {
            if ($number >= $wanted) {
                return $number;
            }
        }
        return $numbers ? end($numbers) : 0;
    }

    /**
     * Where the section fetches a page from; the page number is added by the caller
     */
    public static function fetch_url($post, $doc_key, $page = 0) {
        $args = array('action' => self::AJAX_ACTION, 'post' => $post->ID, 'doc' => $doc_key);
        if ($page) {
            $args['page'] = (int) $page;
        }
        return add_query_arg($args, admin_url('admin-ajax.php'));
    }

    /**
     * The ordinary link to one page of text, for readers and crawlers without scripts
     */
    public static function page_link($post, $doc_key, $page) {
        return self::page_address($post, $doc_key, $page) . '#' . self::section_id($doc_key);
    }

    /**
     * The address of one page of text as search engines know it (no #part)
     */
    public static function page_address($post, $doc_key, $page) {
        return add_query_arg(self::QUERY_VAR, $doc_key . '-' . (int) $page, get_permalink($post));
    }

    public static function section_id($doc_key) {
        return 'scouting-pdf-text-' . $doc_key;
    }

    /**
     * For the viewer's toolbar: the document key when the post being shown keeps text for
     * this PDF (so the Read text button is offered), else ''
     */
    public static function doc_key_with_text($url) {
        $post = self::readable_post(get_the_ID());
        $canonical = Scouting_PDF_OCR::canonical_url($url);
        if (!$post || is_wp_error($canonical)) {
            return '';
        }
        $doc_key = Scouting_PDF_OCR::doc_key($canonical);
        $documents = Scouting_PDF_Document_Text::documents($post->ID);
        return isset($documents[$doc_key]) ? $doc_key : '';
    }

    /**
     * The post's documents that have text, each with the number its viewer has on the page
     * (1 for the first PDF in the post, 2 for the second …), which page links are built from.
     * Returns a list of array('doc_key' => …, 'viewer' => n, 'pages' => page number => text).
     */
    public static function documents_in_viewer_order($post) {
        $stored = Scouting_PDF_Document_Text::documents($post->ID);
        if (!$stored) {
            return array();
        }
        $documents = array();
        $viewer = 0;
        foreach (Scouting_PDF_Embedder::all_pdfs_in_content((string) $post->post_content) as $url) {
            $viewer++;
            $canonical = Scouting_PDF_OCR::canonical_url($url);
            if (is_wp_error($canonical)) {
                continue;
            }
            $doc_key = Scouting_PDF_OCR::doc_key($canonical);
            if (isset($stored[$doc_key])) {
                $documents[$doc_key] = array('doc_key' => $doc_key, 'viewer' => $viewer, 'pages' => $stored[$doc_key]);
            }
        }
        return array_values($documents);
    }

    /**
     * The link that opens one page of one of the post's viewers: #page=7, #pdf2-page=7
     */
    public static function page_hash($viewer, $page) {
        return '#' . ($viewer > 1 ? 'pdf' . (int) $viewer . '-' : '') . 'page=' . (int) $page;
    }
}

/**
 * Template tag for themes that place the section themselves:
 * <?php if (function_exists('scouting_pdf_transcript')) { scouting_pdf_transcript(); } ?>
 */
function scouting_pdf_transcript($post_id = 0) {
    remove_filter('the_content', array(Scouting_PDF_Transcript::get_instance(), 'append_to_content'), 20);
    // Built from escaped pieces in templates/transcript.php
    echo Scouting_PDF_Transcript::html($post_id ? $post_id : get_the_ID()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
