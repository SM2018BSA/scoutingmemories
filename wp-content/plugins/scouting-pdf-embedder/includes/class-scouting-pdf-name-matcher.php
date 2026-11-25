<?php
/**
 * Finds the site's own councils, camps, lodges and states, and years, in a post's document
 * text. No AI: a name counts only when it is there in whole words, and each find keeps the
 * line and page it came from so a reviewer can check it.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_Name_Matcher {

    const FIRST_YEAR = 1910;
    const EVIDENCE_LENGTH = 200;
    /** Dates are read from the first pages with text, where a document names its own date */
    const DATE_PAGES = 2;

    const STATES = array(
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia',
        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois',
        'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana',
        'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
        'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon',
        'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota',
        'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia',
        'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
    );

    /** taxonomy => list of array(term_id, label, phrase), built once per request */
    private static $names = array();

    /**
     * The names to look for in one taxonomy
     */
    private static function names($taxonomy) {
        if (isset(self::$names[$taxonomy])) {
            return self::$names[$taxonomy];
        }
        $names = array();
        $terms = taxonomy_exists($taxonomy) ? get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false)) : array();
        foreach (is_array($terms) ? $terms : array() as $term) {
            if ($taxonomy === 'state') {
                // State terms are two letters, which would match almost any text: look for the full name
                $code = strtoupper(trim($term->name));
                $phrase = isset(self::STATES[$code]) ? self::STATES[$code] : '';
                $label = $phrase !== '' ? $phrase . ' (' . $code . ')' : '';
            } else {
                $phrase = Scouting_PDF_Term_Names::phrase($term->name, $taxonomy);
                $label = Scouting_PDF_Term_Names::readable($term->name, $taxonomy);
            }
            if ($phrase !== '') {
                $names[] = array('term_id' => (int) $term->term_id, 'label' => $label, 'phrase' => $phrase);
            }
        }
        self::$names[$taxonomy] = $names;
        return $names;
    }

    /**
     * Suggestions for one post, from the text kept with it. Tags the post already has are
     * left out. The result goes to Scouting_PDF_Tag_Suggestions::add().
     */
    public static function suggestions_for($post_id) {
        $post = get_post((int) $post_id);
        if (!$post) {
            return array();
        }
        $pages = array();
        foreach (Scouting_PDF_Transcript::documents_in_viewer_order($post) as $document) {
            foreach ($document['pages'] as $page => $text) {
                if (trim($text) !== '') {
                    $pages[] = array('viewer' => $document['viewer'], 'page' => $page, 'text' => $text, 'lower' => mb_strtolower($text));
                }
            }
        }
        if (!$pages) {
            return array();
        }
        $suggestions = array();
        foreach (array('state', 'council', 'camp', 'lodge') as $taxonomy) {
            foreach (self::names($taxonomy) as $name) {
                if (has_term($name['term_id'], $taxonomy, $post)) {
                    continue;
                }
                $found = self::find($pages, $name['phrase']);
                if ($found) {
                    $suggestions[] = array(
                        'field' => $taxonomy, 'value' => $name['term_id'], 'label' => $name['label'], 'phrase' => $name['phrase'],
                        'source' => 'match',
                    ) + $found;
                }
            }
        }
        return array_merge($suggestions, self::date_suggestions($post, $pages));
    }

    /**
     * The first page a phrase is on, with its line: array(viewer, page, evidence), or null
     */
    private static function find($pages, $phrase) {
        $first_word = mb_strtolower((string) strtok($phrase, " -"));
        $pattern = Scouting_PDF_Term_Names::pattern($phrase);
        foreach ($pages as $page) {
            // The quick test first: most names are in no document at all
            if (mb_strpos($page['lower'], $first_word) === false || !preg_match($pattern, $page['text'], $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            return array('viewer' => $page['viewer'], 'page' => $page['page'], 'evidence' => self::line_around($page['text'], $match[0][1]));
        }
        return null;
    }

    /**
     * The earliest and the latest year on the document's first pages
     */
    private static function date_suggestions($post, $pages) {
        $last_year = (int) current_time('Y');
        $years = array();
        foreach (array_slice($pages, 0, self::DATE_PAGES) as $page) {
            if (!preg_match_all('/(?<!\d)(19\d\d|20\d\d)(?!\d)/', $page['text'], $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[1] as $match) {
                $year = (int) $match[0];
                if ($year >= self::FIRST_YEAR && $year <= $last_year && !isset($years[$year])) {
                    $years[$year] = array('viewer' => $page['viewer'], 'page' => $page['page'], 'evidence' => self::line_around($page['text'], $match[1]));
                }
            }
        }
        if (!$years) {
            return array();
        }
        ksort($years);
        $suggestions = array();
        foreach (array('start_date' => array_key_first($years), 'end_date' => array_key_last($years)) as $taxonomy => $year) {
            if (taxonomy_exists($taxonomy) && !has_term((string) $year, $taxonomy, $post)) {
                $suggestions[] = array(
                    'field' => $taxonomy, 'value' => (string) $year, 'label' => (string) $year, 'phrase' => (string) $year,
                    'source' => 'match',
                ) + $years[$year];
            }
        }
        return $suggestions;
    }

    /**
     * The line of text around a byte offset, shortened to what fits a table cell
     */
    private static function line_around($text, $offset) {
        $start = strrpos(substr($text, 0, $offset), "\n");
        $start = $start === false ? 0 : $start + 1;
        $end = strpos($text, "\n", $offset);
        // On a very long line, begin a little before the find so it is in what is shown
        $lead = $offset - $start > 120 ? '…' : '';
        $start = $lead !== '' ? $offset - 80 : $start;
        $line = $lead . trim(mb_strcut($text, $start, ($end === false ? strlen($text) : $end) - $start));
        // A name split over two lines: take the next line too
        if ($end !== false && strlen($line) < 60) {
            $next = strpos($text, "\n", $end + 1);
            $line = trim($line . ' ' . trim(substr($text, $end + 1, ($next === false ? strlen($text) : $next) - $end - 1)));
        }
        return mb_strlen($line) > self::EVIDENCE_LENGTH ? rtrim(mb_substr($line, 0, self::EVIDENCE_LENGTH - 1)) . '…' : $line;
    }
}
