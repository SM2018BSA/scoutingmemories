<?php
/**
 * The text of a post's documents, kept with the post as post meta.
 *
 * WordPress's Plugin Handbook names post meta as the preferred home for a plugin's data
 * about a post, so the reading section, the site search and the tag suggestions all read the
 * text from here, through WordPress's own functions. This class is the only one that knows
 * how the text is laid out inside the meta value.
 *
 * One meta value per document in the post:
 *   line 1: the document's key
 *   then:   the pages in order, separated by a form feed (the page-break character), so
 *           page 7 is the 7th piece. A page the reading found no words on is an empty
 *           piece; a page that has not been read yet holds only the NOT_READ character.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_Document_Text {

    const META_KEY = '_scouting_pdf_text';
    const PAGE_BREAK = "\f";
    const NOT_READ = "\x1A";

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register'));
        add_action('scouting_pdf_document_text_changed', array($this, 'on_text_changed'));
        add_action('save_post', array($this, 'on_save_post'), 20, 2);
    }

    public function register() {
        register_post_meta('', self::META_KEY, array(
            'type'          => 'string',
            'description'   => 'The text read from one PDF in this post, page by page.',
            'single'        => false,
            'show_in_rest'  => false,
            'auth_callback' => '__return_false', // written by the plugin only, never from an edit screen
        ));
    }

    /**
     * One document's meta value from its pages (page number => text)
     */
    public static function encode($doc_key, $pages) {
        $last = $pages ? max(array_keys($pages)) : 0;
        $pieces = array();
        for ($page = 1; $page <= $last; $page++) {
            if (!isset($pages[$page])) {
                $pieces[] = self::NOT_READ;
                continue;
            }
            // A form feed inside a page would shift every later page number
            $pieces[] = trim(str_replace(array(self::PAGE_BREAK, self::NOT_READ), array("\n", ''), (string) $pages[$page]));
        }
        return $doc_key . "\n" . implode(self::PAGE_BREAK, $pieces);
    }

    /**
     * A meta value back into array(doc_key, pages); pages is page number => text, with ''
     * for a page without words. Pages not read yet are left out.
     */
    public static function decode($value) {
        $value = (string) $value;
        $cut = strpos($value, "\n");
        if ($cut === false) {
            return array($value, array());
        }
        $pages = array();
        foreach (explode(self::PAGE_BREAK, substr($value, $cut + 1)) as $i => $text) {
            if ($text !== self::NOT_READ) {
                $pages[$i + 1] = $text;
            }
        }
        return array(substr($value, 0, $cut), $pages);
    }

    /**
     * Every document of a post that has text: doc_key => (page number => text)
     */
    public static function documents($post_id) {
        $documents = array();
        foreach ((array) get_post_meta((int) $post_id, self::META_KEY, false) as $value) {
            list($doc_key, $pages) = self::decode($value);
            if ($doc_key !== '' && $pages) {
                $documents[$doc_key] = $pages;
            }
        }
        return $documents;
    }

    /**
     * Make a post's meta match what has been read of its documents today. Returns how many
     * of its documents have text. Safe to call any number of times.
     */
    public static function refresh_post($post_id) {
        $post = get_post((int) $post_id);
        if (!$post || wp_is_post_revision($post)) {
            return 0;
        }
        $wanted = array();
        foreach (array_keys(Scouting_PDF_OCR_Admin::post_documents($post)) as $doc_key) {
            $pages = Scouting_PDF_OCR::document_pages($doc_key);
            if ($pages && trim(implode('', $pages)) !== '') {
                $wanted[$doc_key] = self::encode($doc_key, $pages);
            }
        }
        $stored = array();
        foreach ((array) get_post_meta($post->ID, self::META_KEY, false) as $value) {
            list($doc_key) = self::decode($value);
            if (!isset($wanted[$doc_key]) || isset($stored[$doc_key])) {
                // The document left the post, lost its text, or is stored twice
                delete_post_meta($post->ID, self::META_KEY, wp_slash($value));
                continue;
            }
            $stored[$doc_key] = $value;
        }
        foreach ($wanted as $doc_key => $value) {
            if (!isset($stored[$doc_key])) {
                add_post_meta($post->ID, self::META_KEY, wp_slash($value));
            } elseif ($stored[$doc_key] !== $value) {
                update_post_meta($post->ID, self::META_KEY, wp_slash($value), $stored[$doc_key]);
            }
        }
        return count($wanted);
    }

    /**
     * A page of this document was read or corrected: refresh every post that shows it
     */
    public function on_text_changed($doc_key) {
        $doc_key = (string) $doc_key;
        if (!preg_match('/^[a-f0-9]{32}$/', $doc_key)) {
            return;
        }
        foreach (self::posts_showing($doc_key) as $post_id) {
            self::refresh_post($post_id);
        }
    }

    /**
     * A post was saved: its documents may have changed
     */
    public function on_save_post($post_id, $post) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !class_exists('Scouting_PDF_OCR_Admin')) {
            return;
        }
        self::refresh_post($post_id);
    }

    private static function post_types() {
        return array_values(array_diff(get_post_types(array('public' => true)), array('attachment')));
    }

    /**
     * The posts one document appears in: those already keeping its text, the post the queue
     * recorded, and posts whose content names the file
     */
    private static function posts_showing($doc_key) {
        $types = self::post_types();
        if (!$types) {
            return array();
        }
        $common = array(
            'post_type'      => $types,
            'post_status'    => array('publish', 'pending'),
            'fields'         => 'ids',
            'posts_per_page' => 50,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        );
        $ids = get_posts($common + array(
            'meta_query' => array(array('key' => self::META_KEY, 'value' => $doc_key . "\n", 'compare' => 'LIKE')),
        ));
        $source = Scouting_PDF_OCR::document_source($doc_key);
        if ($source['post_id']) {
            $ids[] = $source['post_id'];
        }
        $file = $source['url'] !== '' ? wp_basename((string) wp_parse_url($source['url'], PHP_URL_PATH)) : '';
        foreach (array_unique(array_filter(array($file, rawurldecode($file)))) as $name) {
            $ids = array_merge($ids, get_posts($common + array(
                's'              => $name,
                'sentence'       => true,
                'search_columns' => array('post_content'),
            )));
        }
        $showing = array();
        foreach (array_unique(array_map('intval', $ids)) as $post_id) {
            $post = get_post($post_id);
            if ($post && isset(Scouting_PDF_OCR_Admin::post_documents($post)[$doc_key])) {
                $showing[] = $post_id;
            }
        }
        return $showing;
    }
}
