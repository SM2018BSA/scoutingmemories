<?php
/**
 * What search engines read in the head of a post that shows a document: a description of
 * the post as an archive item (schema.org JSON-LD) and a short description.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_Seo {

    const DESCRIPTION_LENGTH = 155;

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_head', array($this, 'print_head_tags'), 5);
        // A link to one page of a document's text is a page of its own for search engines
        add_filter('get_canonical_url', array($this, 'text_page_canonical'), 10, 2);
        add_filter('document_title_parts', array($this, 'text_page_title'));
        add_action('init', array($this, 'register_sitemap'));
    }

    public function print_head_tags() {
        if (!is_singular()) {
            return;
        }
        $post = get_queried_object();
        if (!($post instanceof WP_Post) || $post->post_status !== 'publish' || post_password_required($post)
                || Scouting_PDF_Embedder::first_pdf_in_content($post->post_content) === '') {
            return;
        }
        $description = self::description($post);
        if ($description !== '' && !self::another_plugin_describes()) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }
        echo '<script type="application/ld+json">'
            . wp_json_encode(self::archive_item($post, $description), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
            . '</script>' . "\n";
    }

    /**
     * The page of text this request shows by its own address, when it is not simply the
     * post's first page of text (that one is the post itself)
     */
    private static function text_page() {
        if (!is_singular()) {
            return null;
        }
        $asked = Scouting_PDF_Transcript::asked_page(get_queried_object_id());
        return ($asked && !$asked['first']) ? $asked : null;
    }

    /**
     * WordPress names the post as the one true address of every ?… variant of it, which
     * tells search engines to skip the variant. A page of text names itself instead.
     */
    public function text_page_canonical($canonical, $post) {
        $asked = self::text_page();
        if (!$asked || (int) $post->ID !== (int) get_queried_object_id()) {
            return $canonical;
        }
        return Scouting_PDF_Transcript::page_address($post, $asked['document']['doc_key'], $asked['page']);
    }

    public function text_page_title($parts) {
        $asked = self::text_page();
        if ($asked && isset($parts['title'])) {
            $parts['title'] .= sprintf(' (text of page %d)', $asked['page']);
        }
        return $parts;
    }

    public function register_sitemap() {
        if (function_exists('wp_register_sitemap_provider') && class_exists('Scouting_PDF_Text_Sitemap')) {
            wp_register_sitemap_provider('pdftext', new Scouting_PDF_Text_Sitemap());
        }
    }

    /**
     * SEO plugins print their own description; two would confuse search engines
     */
    private static function another_plugin_describes() {
        return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION')
            || defined('SEOPRESS_VERSION') || defined('THE_SEO_FRAMEWORK_VERSION');
    }

    /**
     * The post's own summary, or the beginning of its document's text
     */
    private static function description($post) {
        $asked = self::text_page();
        if ($asked) {
            // A page of text is described by its own words
            $text = $asked['document']['pages'][$asked['page']];
        } else {
            $text = has_excerpt($post) ? get_the_excerpt($post) : '';
        }
        if (trim($text) === '') {
            foreach (Scouting_PDF_Document_Text::documents($post->ID) as $pages) {
                foreach ($pages as $page_text) {
                    if (trim($page_text) !== '') {
                        $text = $page_text;
                        break 2;
                    }
                }
            }
        }
        $text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $text)));
        if (mb_strlen($text) > self::DESCRIPTION_LENGTH) {
            $text = rtrim(mb_substr($text, 0, self::DESCRIPTION_LENGTH - 1)) . '…';
        }
        return $text;
    }

    private static function term_names($post, $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            return array();
        }
        $terms = get_the_terms($post, $taxonomy);
        $names = array();
        foreach (is_array($terms) ? $terms : array() as $term) {
            $names[] = Scouting_PDF_Term_Names::readable($term->name, $taxonomy);
        }
        // "none" is the site's own way of saying a post has no such tag
        return array_values(array_unique(array_diff(array_filter($names, 'strlen'), array('none'))));
    }

    /**
     * schema.org ArchiveComponent for one post
     */
    private static function archive_item($post, $description) {
        $item = array(
            '@context'       => 'https://schema.org',
            '@type'          => 'ArchiveComponent',
            'name'           => wp_strip_all_tags(get_the_title($post)),
            'url'            => get_permalink($post),
            'holdingArchive' => array(
                '@type' => 'ArchiveOrganization',
                'name'  => 'Scouting Memories',
                'url'   => home_url('/'),
            ),
        );
        if ($description !== '') {
            $item['description'] = $description;
        }
        $date = trim((string) get_post_meta($post->ID, 'date_of_original', true));
        if ($date !== '') {
            $item['dateCreated'] = $date;
        }
        $places = array_merge(self::term_names($post, 'camp'), self::term_names($post, 'council'), self::term_names($post, 'state'));
        if ($places) {
            $item['spatialCoverage'] = array_map(function ($name) {
                return array('@type' => 'Place', 'name' => $name);
            }, $places);
        }
        $about = array_merge(self::term_names($post, 'council'), self::term_names($post, 'lodge'));
        if ($about) {
            $item['about'] = array_map(function ($name) {
                return array('@type' => 'Organization', 'name' => $name);
            }, $about);
        }
        return $item;
    }
}
