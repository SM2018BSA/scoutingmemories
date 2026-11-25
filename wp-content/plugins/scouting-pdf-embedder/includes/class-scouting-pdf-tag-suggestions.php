<?php
/**
 * Tag suggestions for a post, kept with the post as post meta until a person decides.
 * Nothing here changes a post's tags; that happens only when a reviewer presses Add tag.
 *
 * One suggestion: id, field (a taxonomy), value (the term's ID, or the year for a date),
 * label (what the reviewer reads), viewer, page, evidence (the line it was found in),
 * source (match | ai), status (waiting | added | rejected).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_Tag_Suggestions {

    const META_KEY = '_scouting_pdf_tag_suggestions';
    /** How many of a post's suggestions wait for a decision; lets the screen list those posts */
    const WAITING_KEY = '_scouting_pdf_suggestions_waiting';

    /** The fields suggestions are made for: taxonomy => what the reviewer reads */
    const FIELDS = array(
        'state'      => 'State',
        'council'    => 'Council',
        'camp'       => 'Camp',
        'lodge'      => 'Lodge',
        'start_date' => 'Start date',
        'end_date'   => 'End date',
    );

    public static function register() {
        foreach (array(self::META_KEY => 'array', self::WAITING_KEY => 'integer') as $key => $type) {
            register_post_meta('', $key, array(
                'type'          => $type,
                'single'        => true,
                'show_in_rest'  => false,
                'auth_callback' => '__return_false', // written by the plugin only
            ));
        }
    }

    public static function id($field, $value) {
        return md5($field . '|' . $value);
    }

    /**
     * Every suggestion ever made for a post, decided or not: id => suggestion
     */
    public static function all($post_id) {
        $stored = get_post_meta((int) $post_id, self::META_KEY, true);
        return is_array($stored) ? $stored : array();
    }

    public static function waiting($post_id) {
        return array_filter(self::all($post_id), function ($suggestion) {
            return $suggestion['status'] === 'waiting';
        });
    }

    /**
     * Add suggestions that were never made for this post before (a decided one never
     * comes back). Returns how many were new.
     */
    public static function add($post_id, $suggestions) {
        $all = self::all($post_id);
        $new = 0;
        foreach ($suggestions as $suggestion) {
            if (!isset(self::FIELDS[$suggestion['field']])) {
                continue;
            }
            $id = self::id($suggestion['field'], $suggestion['value']);
            if (isset($all[$id])) {
                continue;
            }
            $all[$id] = array(
                'id'       => $id,
                'field'    => $suggestion['field'],
                'value'    => (string) $suggestion['value'],
                'label'    => (string) $suggestion['label'],
                'viewer'   => (int) $suggestion['viewer'],
                'page'     => (int) $suggestion['page'],
                'evidence' => (string) $suggestion['evidence'],
                'phrase'   => (string) $suggestion['phrase'],
                'source'   => $suggestion['source'] === 'ai' ? 'ai' : 'match',
                'status'   => 'waiting',
            );
            $new++;
        }
        if ($new) {
            self::save($post_id, $all);
        }
        return $new;
    }

    /**
     * Record a reviewer's decision. Returns the suggestion, or null when there is none waiting.
     */
    public static function decide($post_id, $id, $status) {
        $all = self::all($post_id);
        if (!isset($all[$id]) || $all[$id]['status'] !== 'waiting' || !in_array($status, array('added', 'rejected'), true)) {
            return null;
        }
        $all[$id]['status'] = $status;
        $all[$id]['decided_by'] = get_current_user_id();
        $all[$id]['decided_at'] = current_time('mysql');
        self::save($post_id, $all);
        return $all[$id];
    }

    private static function save($post_id, $all) {
        update_post_meta((int) $post_id, self::META_KEY, wp_slash($all));
        $waiting = count(array_filter($all, function ($suggestion) {
            return $suggestion['status'] === 'waiting';
        }));
        if ($waiting) {
            update_post_meta((int) $post_id, self::WAITING_KEY, $waiting);
        } else {
            delete_post_meta((int) $post_id, self::WAITING_KEY);
        }
    }
}
