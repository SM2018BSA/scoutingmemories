<?php
/**
 * Every page of document text in WordPress's own sitemap (/wp-sitemap.xml), so search
 * engines are told about each one and need not find them by following Next page links.
 *
 * WordPress's sitemap lists the posts already; a post is also its document's first page of
 * text. This adds the other pages: /post-name/?pdf_text=<document>-<page>.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_Sitemaps_Provider')) {
    return;
}

class Scouting_PDF_Text_Sitemap extends WP_Sitemaps_Provider {

    /** Posts per sitemap file: a post gives one address per read page, a long one hundreds */
    const POSTS_PER_FILE = 50;

    public function __construct() {
        $this->name = 'pdftext';
        $this->object_type = 'pdftext';
    }

    /**
     * Published posts without a password that keep document text, oldest first
     */
    private function posts_query($page_num, $fields = '') {
        return new WP_Query(array(
            'post_type'              => array_values(array_diff(get_post_types(array('public' => true)), array('attachment'))),
            'post_status'            => 'publish',
            'has_password'           => false,
            'posts_per_page'         => self::POSTS_PER_FILE,
            'paged'                  => max(1, (int) $page_num),
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'fields'                 => $fields,
            'ignore_sticky_posts'    => true,
            'update_post_term_cache' => false,
            'meta_query'             => array(array('key' => Scouting_PDF_Document_Text::WORDS_KEY, 'compare' => 'EXISTS')),
        ));
    }

    /**
     * @param int    $page_num       Which sitemap file.
     * @param string $object_subtype Not used: there is one kind of text page.
     * @return array[] One array('loc' => address) per page of text.
     */
    public function get_url_list($page_num, $object_subtype = '') {
        $urls = array();
        foreach ($this->posts_query($page_num)->posts as $post) {
            foreach (Scouting_PDF_Transcript::documents_in_viewer_order($post) as $document) {
                $first = Scouting_PDF_Transcript::nearest_page($document['pages'], 1);
                foreach ($document['pages'] as $page => $text) {
                    // The first page of text is the post itself; a page without words has nothing to index
                    if ($page !== $first && trim($text) !== '') {
                        $urls[] = array('loc' => Scouting_PDF_Transcript::page_address($post, $document['doc_key'], $page));
                    }
                }
            }
        }
        return $urls;
    }

    public function get_max_num_pages($object_subtype = '') {
        return (int) $this->posts_query(1, 'ids')->max_num_pages;
    }
}
