<?php
/**
 * The site's search box also finds words inside the documents.
 *
 * WordPress's own search looks at a post's title, excerpt and content. Here the main search
 * query is widened, in pre_get_posts, with the posts whose document text (post meta) holds
 * the words; under each of those results a line names the pages and links to them. Paging,
 * counts and is_search() stay WordPress's own. Everything goes through WP_Query.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_Search {

    const MAX_WORDS = 6;
    const PAGES_SHOWN = 3;
    const SNIPPET_LENGTH = 160;
    const CACHE_GROUP = 'scouting_pdf_search';
    /** With several words, the posts holding the longest one are looked through for the others */
    const CANDIDATES = 300;

    private static $instance = null;

    /** The main search query this request widened, and what was found through documents */
    private $query = null;
    private $words = array();
    private $pdf_ids = array();

    /** The result line waits here until its result card has been printed */
    private $pending_line = '';
    private $line_printed_for = 0;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('pre_get_posts', array($this, 'widen_search'));
        add_filter('posts_search', array($this, 'drop_word_test'), 10, 2);
        add_filter('get_the_excerpt', array($this, 'excerpt_line'), 20, 2);
        add_action('the_post', array($this, 'after_previous_result'), 10, 2);
        add_action('loop_end', array($this, 'after_last_result'));
    }

    /**
     * The search words, cut the same way the documents' words are: lower case, each once
     */
    public static function words($search) {
        // A leading hyphen means "without this word" in WordPress's search
        $search = preg_replace('/(^|\s)-\S+/u', ' ', wp_strip_all_tags((string) $search));
        return array_slice(Scouting_PDF_Document_Text::words_of($search), 0, self::MAX_WORDS);
    }

    /**
     * Published posts without a password whose documents hold every word, newest first.
     * The database looks for one word only (the longest, so the fewest posts), in each
     * post's word list; the other words are checked here, in those posts' word lists.
     */
    private static function posts_with_words($words, $type) {
        usort($words, function ($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });
        $several = count($words) > 1;
        $ids = get_posts(array(
            'post_type'              => $type,
            'post_status'            => 'publish',
            'has_password'           => false,
            'fields'                 => 'ids',
            'posts_per_page'         => $several ? self::CANDIDATES : -1,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => array(array('key' => Scouting_PDF_Document_Text::WORDS_KEY, 'value' => $words[0], 'compare' => 'LIKE')),
        ));
        if (!$several) {
            return $ids;
        }
        $others = array_slice($words, 1);
        return array_values(array_filter($ids, function ($post_id) use ($others) {
            $list = (string) get_post_meta($post_id, Scouting_PDF_Document_Text::WORDS_KEY, true);
            foreach ($others as $word) {
                if (mb_strpos($list, $word) === false) {
                    return false;
                }
            }
            return true;
        }));
    }

    public function widen_search($query) {
        if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
            return;
        }
        $search = (string) $query->get('s');
        $words = self::words($search);
        if (!$words) {
            return;
        }
        $type = $query->get('post_type');
        $type = $type ? $type : 'any';
        $statuses = current_user_can('read_private_posts') ? array('publish', 'private') : array('publish');
        // Kept in the object cache until any post or its meta changes (WordPress moves the
        // "last changed" stamp then), so a search never shows text that has since changed
        $cache_key = md5(wp_json_encode(array($search, $type, $statuses))) . ':' . wp_cache_get_last_changed('posts');
        $found = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (!is_array($found)) {
            $common = array(
                'post_type'              => $type,
                'fields'                 => 'ids',
                'nopaging'               => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            );
            $found = array(
                // What WordPress's own search finds, in its own order
                'core' => get_posts($common + array('s' => $search, 'post_status' => $statuses)),
                'pdf'  => self::posts_with_words($words, $type),
            );
            wp_cache_set($cache_key, $found, self::CACHE_GROUP, HOUR_IN_SECONDS);
        }
        if (empty($found['pdf'])) {
            return; // nothing in any document: the search stays exactly WordPress's own
        }
        $ids = array_values(array_unique(array_map('intval', array_merge((array) $found['core'], (array) $found['pdf']))));
        $this->query = $query;
        $this->words = $words;
        $this->pdf_ids = array_map('intval', (array) $found['pdf']);
        // Never an empty list: WordPress reads that as "every post"
        $query->set('post__in', $ids ? $ids : array(0));
        $query->set('orderby', 'post__in');
    }

    /**
     * The posts are already chosen by ID, so the word test must not run a second time
     * (it would throw out the posts found through their documents)
     */
    public function drop_word_test($search, $query) {
        return ($this->query !== null && $query === $this->query) ? '' : $search;
    }

    /**
     * For themes that print an excerpt in search results: the line goes with the excerpt
     */
    public function excerpt_line($excerpt, $post = null) {
        $post = get_post($post);
        if (!$post || !$this->in_search_loop()) {
            return $excerpt;
        }
        $line = $this->result_line($post);
        if ($line === '') {
            return $excerpt;
        }
        $this->line_printed_for = $post->ID;
        $this->pending_line = '';
        return $excerpt . $line;
    }

    /**
     * For themes that print no excerpt: each result's line is printed right after its
     * card, which is when the loop moves on to the next result (or ends). Block themes build
     * the page as one piece of text, so printing in the middle is not possible there; they
     * show the line with the excerpt.
     */
    public function after_previous_result($post, $query = null) {
        if ($this->query === null || $query !== $this->query || wp_is_block_theme()) {
            return;
        }
        $this->print_pending_line();
        if ($post->ID !== $this->line_printed_for) {
            $this->pending_line = $this->result_line($post);
        }
    }

    public function after_last_result($query) {
        if ($this->query !== null && $query === $this->query && !wp_is_block_theme()) {
            $this->print_pending_line();
        }
    }

    private function print_pending_line() {
        if ($this->pending_line !== '') {
            // Built from escaped pieces in result_line()
            echo $this->pending_line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $this->pending_line = '';
        }
    }

    private function in_search_loop() {
        if ($this->query === null || !is_search()) {
            return false;
        }
        // A block theme's results list runs on its own copy of the search query, so the
        // loop checks below never hold there
        return wp_is_block_theme() || (in_the_loop() && is_main_query());
    }

    /**
     * "Found in the document text: page 7 — …Camp Tahosa opened in 1952…" for one result,
     * or '' when the words are not in its documents
     */
    private function result_line($post) {
        if (!in_array((int) $post->ID, $this->pdf_ids, true) || post_password_required($post)) {
            return '';
        }
        $hits = array();
        foreach (Scouting_PDF_Transcript::documents_in_viewer_order($post) as $document) {
            foreach ($document['pages'] as $page => $text) {
                $found = $this->words_in($text);
                if ($found) {
                    $hits[] = array('viewer' => $document['viewer'], 'page' => $page, 'text' => $text, 'found' => $found);
                }
            }
        }
        if (!$hits) {
            return '';
        }
        // Pages holding the most of the words first, then in page order
        usort($hits, function ($a, $b) {
            $by_words = count($b['found']) - count($a['found']);
            return $by_words ? $by_words : (($a['viewer'] - $b['viewer']) ? ($a['viewer'] - $b['viewer']) : ($a['page'] - $b['page']));
        });
        $html = '<div class="container card border-top-0 mb-3" data-pdf-search-hit><div class="card-body py-2">';
        foreach (array_slice($hits, 0, self::PAGES_SHOWN) as $hit) {
            $url = get_permalink($post) . Scouting_PDF_Transcript::page_hash($hit['viewer'], $hit['page'])
                . '&find=' . rawurlencode($hit['found'][0]);
            $html .= '<p class="mb-1">Found in the document text: <a href="' . esc_url($url) . '">'
                . esc_html(sprintf('page %d', $hit['page'])) . '</a> — '
                . $this->snippet($hit['text'], $hit['found']) . '</p>';
        }
        $more = count($hits) - self::PAGES_SHOWN;
        if ($more > 0) {
            $html .= '<p class="mb-1">' . esc_html(sprintf(_n('and %d more page', 'and %d more pages', $more, 'scouting-pdf-embedder'), $more)) . '</p>';
        }
        return $html . '</div></div>';
    }

    /**
     * Which of the search words one page's text holds
     */
    private function words_in($text) {
        $lower = mb_strtolower((string) $text);
        $found = array();
        foreach ($this->words as $word) {
            if (mb_strpos($lower, $word) !== false) {
                $found[] = $word;
            }
        }
        return $found;
    }

    /**
     * About 160 characters around the first word found, escaped, with the words marked
     */
    private function snippet($text, $words) {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
        $at = mb_strpos(mb_strtolower($text), $words[0]);
        $start = max(0, (int) $at - (int) (self::SNIPPET_LENGTH / 2));
        if ($start > 0) {
            // Begin at a word, not in the middle of one
            $space = mb_strpos($text, ' ', $start);
            $start = ($space !== false && $space < $at) ? $space + 1 : $start;
        }
        $piece = mb_substr($text, $start, self::SNIPPET_LENGTH);
        // Cut the piece at the words first and escape each part on its own, so a mark can
        // never land inside an escaped character
        $quoted = array_map(function ($word) {
            return preg_quote($word, '/');
        }, $words);
        $html = '';
        foreach (preg_split('/(' . implode('|', $quoted) . ')/iu', $piece, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $part) {
            $html .= ($i % 2) ? '<mark>' . esc_html($part) . '</mark>' : esc_html($part);
        }
        return ($start > 0 ? '…' : '') . $html . (mb_strlen($text) > $start + self::SNIPPET_LENGTH ? '…' : '');
    }
}
