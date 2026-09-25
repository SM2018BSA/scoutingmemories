<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Models\ArchiveRepository;
use ScoutingMemories\Forms\Models\EntryRepository;

/**
 * IndexEntityForms
 *
 * Replaces Formidable Forms 8, 11, 7 for adding Councils, Camps, and Lodges.
 */
class IndexEntityForms extends FormHandler {

    public static function registerHooks(): void {
        add_shortcode('sm_add_council_form', [__CLASS__, 'renderCouncilForm']);
        add_shortcode('sm_add_camp_form', [__CLASS__, 'renderCampForm']);
        add_shortcode('sm_add_lodge_form', [__CLASS__, 'renderLodgeForm']);

        add_action('init', [__CLASS__, 'handleCouncilSubmission']);
        add_action('init', [__CLASS__, 'handleCampSubmission']);
        add_action('init', [__CLASS__, 'handleLodgeSubmission']);
    }

    public static function handleCouncilSubmission(): void {
        if (!isset($_POST['sm_action']) || $_POST['sm_action'] !== 'add_council') {
            return;
        }

        if (!self::verifyNonce('sm_add_council')) {
            wp_die(__('Security check failed.', 'scouting-forms'));
        }

        self::checkPermission();

        $name       = sanitize_text_field($_POST['council_name'] ?? '');
        $number     = sanitize_text_field($_POST['council_number'] ?? '');
        $state_id   = (int) ($_POST['state_id'] ?? 0);
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date   = sanitize_text_field($_POST['end_date'] ?? '');
        $active     = isset($_POST['active']) && $_POST['active'] === 'Yes' ? 'Yes' : 'No';

        $slug = sanitize_title($name . ($number ? "_{$number}" : ''));

        self::saveArchiveItem(ArchiveRepository::FORM_COUNCILS, $name, $slug, [
            ArchiveRepository::FID_COUNCIL_NAME   => $name,
            ArchiveRepository::FID_COUNCIL_NUM    => $number,
            ArchiveRepository::FID_COUNCIL_STATE  => $state_id,
            ArchiveRepository::FID_COUNCIL_START  => $start_date,
            ArchiveRepository::FID_COUNCIL_END    => $end_date,
            ArchiveRepository::FID_COUNCIL_SLUG   => $slug,
            ArchiveRepository::FID_COUNCIL_ACTIVE => $active,
        ]);

        wp_safe_redirect(add_query_arg(['council_added' => '1'], home_url('/add-a-council/')));
        exit;
    }

    public static function handleCampSubmission(): void {
        if (!isset($_POST['sm_action']) || $_POST['sm_action'] !== 'add_camp') {
            return;
        }

        if (!self::verifyNonce('sm_add_camp')) {
            wp_die(__('Security check failed.', 'scouting-forms'));
        }

        self::checkPermission();

        $name       = sanitize_text_field($_POST['camp_name'] ?? '');
        $state_id   = (int) ($_POST['state_id'] ?? 0);
        $council_id = (int) ($_POST['council_id'] ?? 0);
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date   = sanitize_text_field($_POST['end_date'] ?? '');
        $active     = isset($_POST['active']) && $_POST['active'] === 'Yes' ? 'Yes' : 'No';

        $slug = sanitize_title($name);

        self::saveArchiveItem(ArchiveRepository::FORM_CAMPS, $name, $slug, [
            ArchiveRepository::FID_CAMP_NAME    => $name,
            ArchiveRepository::FID_CAMP_STATE   => $state_id,
            ArchiveRepository::FID_CAMP_COUNCIL => $council_id,
            ArchiveRepository::FID_CAMP_START   => $start_date,
            ArchiveRepository::FID_CAMP_END     => $end_date,
            ArchiveRepository::FID_CAMP_SLUG    => $slug,
            ArchiveRepository::FID_CAMP_ACTIVE  => $active,
        ]);

        wp_safe_redirect(add_query_arg(['camp_added' => '1'], home_url('/add-a-camp/')));
        exit;
    }

    public static function handleLodgeSubmission(): void {
        if (!isset($_POST['sm_action']) || $_POST['sm_action'] !== 'add_lodge') {
            return;
        }

        if (!self::verifyNonce('sm_add_lodge')) {
            wp_die(__('Security check failed.', 'scouting-forms'));
        }

        self::checkPermission();

        $name       = sanitize_text_field($_POST['lodge_name'] ?? '');
        $number     = sanitize_text_field($_POST['lodge_number'] ?? '');
        $state_id   = (int) ($_POST['state_id'] ?? 0);
        $council_id = (int) ($_POST['council_id'] ?? 0);
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date   = sanitize_text_field($_POST['end_date'] ?? '');
        $active     = isset($_POST['active']) && $_POST['active'] === 'Yes' ? 'Yes' : 'No';

        $slug = sanitize_title($name . ($number ? "_{$number}" : ''));

        self::saveArchiveItem(ArchiveRepository::FORM_LODGES, $name, $slug, [
            ArchiveRepository::FID_LODGE_NAME    => $name,
            ArchiveRepository::FID_LODGE_NUM     => $number,
            ArchiveRepository::FID_LODGE_STATE   => $state_id,
            ArchiveRepository::FID_LODGE_COUNCIL => $council_id,
            ArchiveRepository::FID_LODGE_START   => $start_date,
            ArchiveRepository::FID_LODGE_END     => $end_date,
            ArchiveRepository::FID_LODGE_SLUG    => $slug,
            ArchiveRepository::FID_LODGE_ACTIVE  => $active,
        ]);

        wp_safe_redirect(add_query_arg(['lodge_added' => '1'], home_url('/add-a-lodge/')));
        exit;
    }

    private static function checkPermission(): void {
        $user = wp_get_current_user();
        if (!$user->ID || (!in_array('index_contributor', (array) $user->roles) && !current_user_can('edit_others_posts'))) {
            wp_die(__('You do not have permission to add indexing entries.', 'scouting-forms'));
        }
    }

    private static function saveArchiveItem(int $formId, string $name, string $itemKey, array $fieldValues): int {
        return EntryRepository::create($formId, $fieldValues, [
            'key' => $itemKey . '_' . wp_rand(100, 999),
            'name' => $name,
        ]);
    }

    public static function renderCouncilForm(): string {
        self::checkPermission();
        $states = ArchiveRepository::getStates();
        $added = isset($_GET['council_added']);

        return self::renderView('forms/council-form', [
            'states' => $states,
            'added'  => $added
        ]);
    }

    public static function renderCampForm(): string {
        self::checkPermission();
        wp_enqueue_script('sm-cascading-selects');
        $states   = ArchiveRepository::getStates();
        $councils = ArchiveRepository::getCouncils();
        $added    = isset($_GET['camp_added']);

        return self::renderView('forms/camp-form', [
            'states'   => $states,
            'councils' => $councils,
            'added'    => $added
        ]);
    }

    public static function renderLodgeForm(): string {
        self::checkPermission();
        wp_enqueue_script('sm-cascading-selects');
        $states   = ArchiveRepository::getStates();
        $councils = ArchiveRepository::getCouncils();
        $added    = isset($_GET['lodge_added']);

        return self::renderView('forms/lodge-form', [
            'states'   => $states,
            'councils' => $councils,
            'added'    => $added
        ]);
    }
}
