<?php
/**
 * The site's terms are spelled for machines: Camp_Wabaningo_16145, Evanston_Council_124,
 * Camp_Red_Bird__start_date_uncertain__16528. This turns them into the words a person
 * reads, and into the phrase to look for in a document's text.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_Term_Names {

    const MIN_PHRASE_LENGTH = 7;

    /** Words that name nothing on their own */
    const GENERIC = array(
        'none', 'unknown', 'camp', 'lodge', 'council', 'scout camp', 'scout camps', 'boy scouts',
        'scout reservation', 'boy scouts of america', 'area council', 'summer camp',
    );

    /**
     * For showing: spaces for the underscores, and a camp's trailing number (an internal
     * one) dropped. Council and lodge numbers are part of their names.
     */
    public static function readable($name, $taxonomy) {
        $name = trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', (string) $name)));
        if ($taxonomy === 'camp') {
            $name = trim(preg_replace('/\s+\d+$/', '', $name));
        }
        return $name;
    }

    /**
     * For finding in text: the name without its number and without notes ("start date
     * uncertain", "renamed …"), or '' when what is left would match almost anything.
     */
    public static function phrase($name, $taxonomy) {
        // Two underscores in a row and " - " set a note or a second name apart
        $name = preg_replace('/_\d*$/', '', trim((string) $name));
        $segments = preg_split('/__+|\s+-\s+|_-_/', $name);
        $kept = array();
        foreach ($segments as $segment) {
            $segment = trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', $segment)));
            $segment = trim(preg_replace('/\s+\d+$/', '', $segment));
            if ($segment !== '' && !preg_match('/\b(uncertain|renamed|unknown|closed|merged)\b/i', $segment)) {
                $kept[] = $segment;
            }
        }
        if (!$kept) {
            return '';
        }
        if ($taxonomy === 'camp') {
            // "Camp Seminole - Davie FL": the camp's own name is the first part with two words
            $phrase = '';
            foreach ($kept as $segment) {
                if (str_word_count($segment) >= 2) {
                    $phrase = $segment;
                    break;
                }
            }
            $phrase = $phrase !== '' ? $phrase : $kept[0];
        } else {
            // "Evanston - North Shore Council" is one name
            $phrase = implode(' ', $kept);
        }
        $lower = strtolower($phrase);
        if (strlen($phrase) < self::MIN_PHRASE_LENGTH || in_array($lower, self::GENERIC, true) || strpos($lower, 'test') === 0) {
            return '';
        }
        return $phrase;
    }

    /**
     * A pattern that finds the phrase as whole words, in any case, with any spacing,
     * line break or dash between its words
     */
    public static function pattern($phrase) {
        $words = preg_split('/[\s\-]+/', trim((string) $phrase), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_map(function ($word) {
            return preg_quote($word, '/');
        }, $words);
        return '/(?<![\p{L}\p{N}])' . implode('[\s\-–—]+', $words) . '(?![\p{L}\p{N}])/iu';
    }
}
