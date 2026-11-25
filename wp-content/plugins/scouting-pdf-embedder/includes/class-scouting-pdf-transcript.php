<?php
/**
 * "Text of this document": the text read from a post's PDFs, printed under the post's
 * content as ordinary HTML, so readers, screen readers and search engines all get it.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_Transcript {

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
    }

    public function append_to_content($content) {
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        return $content . self::html(get_the_ID());
    }

    /**
     * The section for one post, or '' when the post is not published or has no text
     */
    public static function html($post_id) {
        $post = get_post((int) $post_id);
        if (!$post || $post->post_status !== 'publish' || post_password_required($post)) {
            return '';
        }
        $documents = self::documents_in_viewer_order($post);
        if (!$documents) {
            return '';
        }
        wp_enqueue_script('scouting-pdf-transcript',
            plugins_url('assets/js/scouting-pdf-transcript.js', SCOUTING_PDF_PLUGIN_DIR . 'scouting-pdf-embedder.php'),
            array(), SCOUTING_PDF_VERSION, true);
        ob_start();
        load_template(SCOUTING_PDF_PLUGIN_DIR . 'templates/transcript.php', false, array('documents' => $documents));
        return ob_get_clean();
    }

    /**
     * The post's documents that have text, each with the number its viewer has on the page
     * (1 for the first PDF in the post, 2 for the second …), which page links are built from.
     * Returns a list of array('viewer' => n, 'pages' => page number => text).
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
                $documents[$doc_key] = array('viewer' => $viewer, 'pages' => $stored[$doc_key]);
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
