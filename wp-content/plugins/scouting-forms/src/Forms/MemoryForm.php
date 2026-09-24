<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Models\ArchiveRepository;
use ScoutingMemories\Forms\Models\MemoryPost;
use ScoutingMemories\Forms\Models\UserDefaults;

/**
 * MemoryForm
 *
 * Replaces Formidable Form 6 (Add a Post) in add-post.php.
 * Handles front-end submission of historical memories with auto-populated defaults.
 */
class MemoryForm extends FormHandler {

    public static function registerHooks(): void {
        add_shortcode('sm_add_memory_form', [__CLASS__, 'render']);
        add_action('init', [__CLASS__, 'handleSubmission']);
    }

    public static function handleSubmission(): void {
        if (!isset($_POST['sm_action']) || $_POST['sm_action'] !== 'submit_memory') {
            return;
        }

        if (!self::verifyNonce('sm_submit_memory')) {
            wp_die(__('Security verification failed.', 'scouting-forms'));
        }

        $userId = get_current_user_id();
        if (!$userId) {
            wp_die(__('You must be logged in to submit a memory.', 'scouting-forms'));
        }

        // Prepare entity slugs from posted IDs
        $postData = $_POST;

        // Resolve Council Slug
        if (!empty($_POST['council_id'])) {
            $councils = ArchiveRepository::getCouncils(null, false, '', 1, 0);
            // Look up slug
            global $wpdb;
            $slug = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}frm_item_metas WHERE item_id = %d AND field_id = %d",
                (int) $_POST['council_id'],
                ArchiveRepository::FID_COUNCIL_SLUG
            ));
            if ($slug) {
                $postData['council_slugs'] = [$slug];
            }
        }

        // Resolve Camp Slug
        if (!empty($_POST['camp_id'])) {
            global $wpdb;
            $slug = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}frm_item_metas WHERE item_id = %d AND field_id = %d",
                (int) $_POST['camp_id'],
                ArchiveRepository::FID_CAMP_SLUG
            ));
            if ($slug) {
                $postData['camp_slugs'] = [$slug];
            }
        }

        // Resolve Lodge Slug
        if (!empty($_POST['lodge_id'])) {
            global $wpdb;
            $slug = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}frm_item_metas WHERE item_id = %d AND field_id = %d",
                (int) $_POST['lodge_id'],
                ArchiveRepository::FID_LODGE_SLUG
            ));
            if ($slug) {
                $postData['lodge_slugs'] = [$slug];
            }
        }

        // Resolve State Slug / Code
        if (!empty($_POST['state_id'])) {
            global $wpdb;
            $slug = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}frm_item_metas WHERE item_id = %d AND field_id = %d",
                (int) $_POST['state_id'],
                ArchiveRepository::FID_STATE_ACL
            ));
            if ($slug) {
                $postData['state_slugs'] = [$slug];
            }
        }

        $result = MemoryPost::create($postData, $_FILES, $userId);

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        // Redirect to post or thank you page
        $redirect = add_query_arg('memory_submitted', '1', get_permalink($result));
        wp_safe_redirect($redirect);
        exit;
    }

    public static function render(): string {
        $userId = get_current_user_id();
        if (!$userId) {
            return '<div class="alert alert-warning">' . __('Please log in to submit a memory.', 'scouting-forms') . '</div>';
        }

        wp_enqueue_script('sm-cascading-selects');

        // Load contributor defaults
        $defaults = UserDefaults::getForUser($userId);
        $states   = ArchiveRepository::getStates();

        $councils = [];
        $selectedState = !empty($defaults['state']) ? (int) $defaults['state'] : 0;
        if ($selectedState) {
            $councils = ArchiveRepository::getCouncils($selectedState);
        }

        $camps  = [];
        $lodges = [];
        $selectedCouncil = !empty($defaults['council']) ? (int) $defaults['council'] : 0;
        if ($selectedCouncil) {
            $camps  = ArchiveRepository::getCamps($selectedCouncil);
            $lodges = ArchiveRepository::getLodges($selectedCouncil);
        }

        // Get public categories
        $categories = get_categories(['hide_empty' => false]);

        return self::renderView('forms/memory-form', [
            'defaults'   => $defaults,
            'states'     => $states,
            'councils'   => $councils,
            'camps'      => $camps,
            'lodges'     => $lodges,
            'categories' => $categories
        ]);
    }
}
