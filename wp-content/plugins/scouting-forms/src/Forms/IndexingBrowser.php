<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Models\ArchiveRepository;

/**
 * IndexingBrowser
 *
 * Replaces Formidable Views 1172, 1179, 1180 in my-account.php tab "Indexing".
 * Provides search, pagination, and the active end-dates maintenance tool.
 */
class IndexingBrowser extends FormHandler {

    public static function registerHooks(): void {
        add_shortcode('sm_indexing_browser', [__CLASS__, 'render']);
        add_action('init', [__CLASS__, 'handleBatchUpdate']);
    }

    public static function handleBatchUpdate(): void {
        if (!isset($_GET['smp_action']) || $_GET['smp_action'] !== 'update_end_dates') {
            return;
        }

        if (!current_user_can('edit_others_posts') && !in_array('index_contributor', (array) wp_get_current_user()->roles)) {
            wp_die(__('Permission denied.', 'scouting-forms'));
        }

        $counts = ArchiveRepository::updateActiveEndDates();
        $redirect = remove_query_arg('smp_action', wp_get_referer() ?: home_url('/my-account/'));
        $redirect = add_query_arg([
            'updated_end_dates' => '1',
            'tab'               => 'myIndexing',
            'tab2'              => 'tools'
        ], $redirect);

        wp_safe_redirect($redirect);
        exit;
    }

    public static function render(): string {
        $search = sanitize_text_field($_GET['idx_search'] ?? '');
        $tab    = sanitize_text_field($_GET['idx_tab'] ?? 'councils');
        $page   = max(1, (int) ($_GET['idx_paged'] ?? 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $councils = [];
        $camps    = [];
        $lodges   = [];

        if ($tab === 'councils') {
            $councils = ArchiveRepository::getCouncils(null, false, $search, $limit, $offset);
        } elseif ($tab === 'camps') {
            $camps = ArchiveRepository::getCamps(null, null, $search, $limit, $offset);
        } elseif ($tab === 'lodges') {
            $lodges = ArchiveRepository::getLodges(null, null, $search, $limit, $offset);
        }

        $updated = isset($_GET['updated_end_dates']) && $_GET['updated_end_dates'] === '1';

        return self::renderView('dashboard/indexing-tab', [
            'tab'      => $tab,
            'search'   => $search,
            'page'     => $page,
            'limit'    => $limit,
            'councils' => $councils,
            'camps'    => $camps,
            'lodges'   => $lodges,
            'updated'  => $updated
        ]);
    }
}
