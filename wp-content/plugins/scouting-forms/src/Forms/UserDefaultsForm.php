<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Models\ArchiveRepository;
use ScoutingMemories\Forms\Models\UserDefaults;

/**
 * UserDefaultsForm
 *
 * Replaces Formidable Form 34 (Edit Account Defaults) in my-account.php.
 */
class UserDefaultsForm extends FormHandler {

    public static function registerHooks(): void {
        add_shortcode('sm_user_defaults', [__CLASS__, 'render']);
        add_action('init', [__CLASS__, 'handleSubmission']);
    }

    public static function handleSubmission(): void {
        if (!isset($_POST['sm_action']) || $_POST['sm_action'] !== 'update_defaults') {
            return;
        }

        if (!self::verifyNonce('sm_update_defaults')) {
            wp_die(__('Security verification failed.', 'scouting-forms'));
        }

        $userId = get_current_user_id();
        if (!$userId) {
            return;
        }

        $data = [
            'state'        => sanitize_text_field($_POST['default_state'] ?? ''),
            'council'      => sanitize_text_field($_POST['default_council'] ?? ''),
            'camp'         => sanitize_text_field($_POST['default_camp'] ?? ''),
            'lodge'        => sanitize_text_field($_POST['default_lodge'] ?? ''),
            'author'       => sanitize_text_field($_POST['default_author'] ?? ''),
            'photographer' => sanitize_text_field($_POST['default_photographer'] ?? ''),
            'contributors' => sanitize_text_field($_POST['default_contributors'] ?? ''),
            'date_original'=> sanitize_text_field($_POST['default_date_original'] ?? ''),
            'identifier'   => sanitize_text_field($_POST['default_identifier'] ?? ''),
            'pub_digital'  => sanitize_text_field($_POST['default_pub_digital'] ?? ''),
            'date_digital' => sanitize_text_field($_POST['default_date_digital'] ?? ''),
            'subject'      => sanitize_text_field($_POST['default_subject'] ?? ''),
            'location'     => sanitize_text_field($_POST['default_location'] ?? ''),
            'phy_dsc'      => sanitize_text_field($_POST['default_phy_dsc'] ?? ''),
        ];

        UserDefaults::saveForUser($data, $userId);

        wp_safe_redirect(add_query_arg('defaults_updated', '1', wp_get_referer() ?: home_url('/my-account/')));
        exit;
    }

    public static function render(): string {
        $userId = get_current_user_id();
        if (!$userId) {
            return '<div class="alert alert-warning">' . __('Please log in to manage your defaults.', 'scouting-forms') . '</div>';
        }

        wp_enqueue_script('sm-cascading-selects');

        $defaults = UserDefaults::getForUser($userId);
        $states   = ArchiveRepository::getStates();

        // If user already has a state selected, preload councils
        $councils = [];
        $selectedState = !empty($defaults['state']) ? (int) $defaults['state'] : 0;
        if ($selectedState) {
            $councils = ArchiveRepository::getCouncils($selectedState);
        }

        // If council is selected, preload camps & lodges
        $camps  = [];
        $lodges = [];
        $selectedCouncil = !empty($defaults['council']) ? (int) $defaults['council'] : 0;
        if ($selectedCouncil) {
            $camps  = ArchiveRepository::getCamps($selectedCouncil);
            $lodges = ArchiveRepository::getLodges($selectedCouncil);
        }

        $success = isset($_GET['defaults_updated']) && $_GET['defaults_updated'] === '1';

        return self::renderView('forms/user-defaults', [
            'defaults' => $defaults,
            'states'   => $states,
            'councils' => $councils,
            'camps'    => $camps,
            'lodges'   => $lodges,
            'success'  => $success,
        ]);
    }
}
