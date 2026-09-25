<?php

namespace ScoutingMemories\Forms\Models;

use ScoutingMemories\Forms\Support\TestData;

/**
 * MemoryPost
 *
 * Handles creation and updating of Scouting Memories posts,
 * synchronizing ACF metadata, entity slugs, and WordPress taxonomies.
 */
class MemoryPost {

    /**
     * Create a new memory post
     *
     * @param array $data Form submission data
     * @param array $files Uploaded files ($_FILES)
     * @param int|null $userId
     * @return int|\WP_Error Post ID on success, WP_Error on failure
     */
    public static function create(array $data, array $files = [], ?int $userId = null) {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        if (!$userId || !user_can($userId, 'edit_posts')) {
            // Check if user has create_posts role or permission
            $user = get_userdata($userId);
            if (!$user || (!in_array('create_posts', (array) $user->allcaps) && !in_array('administrator', (array) $user->roles))) {
                return new \WP_Error('permission_denied', __('You do not have permission to create memories.', 'scouting-forms'));
            }
        }

        $title = sanitize_text_field($data['post_title'] ?? '');
        $content = wp_kses_post($data['post_content'] ?? '');
        $category_id = (int) ($data['post_category'] ?? 0);

        if (empty($title)) {
            return new \WP_Error('missing_title', __('Please provide a title for the memory.', 'scouting-forms'));
        }

        // Determine post status based on user role (pending review or publish)
        $post_status = current_user_can('publish_posts') ? 'publish' : 'pending';

        $post_args = [
            'post_title'    => $title,
            'post_content'  => $content,
            'post_status'   => $post_status,
            'post_author'   => $userId,
            'post_type'     => 'post',
        ];

        if ($category_id > 0) {
            $post_args['post_category'] = [$category_id];
        }

        $post_id = wp_insert_post($post_args, true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        TestData::markPost((int) $post_id);

        // 1. Process File Uploads (Images or PDF)
        if (!empty($files['memory_file']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $attach_id = media_handle_upload('memory_file', $post_id);
            if (!is_wp_error($attach_id)) {
                TestData::markPost((int) $attach_id);
                $mime = get_post_mime_type($attach_id);
                if (strpos($mime, 'image') !== false) {
                    set_post_thumbnail($post_id, $attach_id);
                }
                update_post_meta($post_id, 'memory_attachment_id', $attach_id);
            }
        }

        // 2. Save Archival Metadata
        $meta_fields = [
            'publisher_of_digital'        => sanitize_text_field($data['publisher_of_digital'] ?? ''),
            'date_of_original'            => sanitize_text_field($data['date_of_original'] ?? ''),
            'date_of_digital'             => sanitize_text_field($data['date_of_digital'] ?? ''),
            'identifier'                  => sanitize_text_field($data['identifier'] ?? ''),
            'meta_subject'                => sanitize_text_field($data['meta_subject'] ?? ''),
            'meta_location'               => sanitize_text_field($data['meta_location'] ?? ''),
            'meta_physical_description'   => sanitize_text_field($data['meta_physical_description'] ?? ''),
            'start_date'                  => sanitize_text_field($data['start_date'] ?? ''),
            'end_date'                    => sanitize_text_field($data['end_date'] ?? ''),
        ];

        foreach ($meta_fields as $key => $val) {
            update_post_meta($post_id, $key, $val);
        }

        // 3. Process Entities (State, Council, Camp, Lodge)
        $state_slugs   = (array) ($data['state_slugs'] ?? []);
        $council_slugs = (array) ($data['council_slugs'] ?? []);
        $camp_slugs    = (array) ($data['camp_slugs'] ?? []);
        $lodge_slugs   = (array) ($data['lodge_slugs'] ?? []);

        // Save raw slugs into postmeta (compatible with single-post.php and Post.php)
        update_post_meta($post_id, 'state_slugs', $state_slugs);
        update_post_meta($post_id, 'council_slugs', $council_slugs);
        update_post_meta($post_id, 'camp_slugs', $camp_slugs);
        update_post_meta($post_id, 'lodge_slugs', $lodge_slugs);

        update_post_meta($post_id, 'state', $state_slugs);
        update_post_meta($post_id, 'council', $council_slugs);
        update_post_meta($post_id, 'camp', $camp_slugs);
        update_post_meta($post_id, 'lodge', $lodge_slugs);

        // 4. Synchronize Custom Taxonomies
        self::syncTaxonomy($post_id, 'state', $state_slugs);
        self::syncTaxonomy($post_id, 'council', $council_slugs);
        self::syncTaxonomy($post_id, 'camp', $camp_slugs);
        self::syncTaxonomy($post_id, 'lodge', $lodge_slugs);

        if (!empty($data['start_date'])) {
            self::syncTaxonomy($post_id, 'start_date', [sanitize_text_field($data['start_date'])]);
        }
        if (!empty($data['end_date'])) {
            self::syncTaxonomy($post_id, 'end_date', [sanitize_text_field($data['end_date'])]);
        }

        return $post_id;
    }

    /**
     * Helper to synchronize terms with post
     */
    private static function syncTaxonomy(int $postId, string $taxonomy, array $terms): void {
        if (!taxonomy_exists($taxonomy)) {
            return;
        }

        $term_ids = [];
        foreach ($terms as $term_slug) {
            if (empty($term_slug)) continue;

            $existing = get_term_by('slug', $term_slug, $taxonomy);
            if ($existing) {
                $term_ids[] = (int) $existing->term_id;
            } else {
                $created = wp_insert_term(str_replace('_', ' ', $term_slug), $taxonomy, ['slug' => $term_slug]);
                if (!is_wp_error($created)) {
                    $term_ids[] = (int) $created['term_id'];
                }
            }
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($postId, $term_ids, $taxonomy, false);
        }
    }
}
