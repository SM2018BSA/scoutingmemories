<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Models\ArchiveRepository;
use ScoutingMemories\Forms\Support\Permissions;

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

    /**
     * Who may run the end-dates tool. It rewrites the end date of every active council, camp and
     * lodge, so it is for administrators only (owner, 2026-11-24): the same people who get the
     * Admin tab. Checked by role, not by manage_options: a person holding administrator plus a
     * second role can lose that capability to the second role.
     */
    public static function canUpdateEndDates(): bool {
        return Permissions::hasRole('administrator');
    }

    public static function handleBatchUpdate(): void {
        // Own action name: the theme's My Account page handles `smp_action=update_end_dates`
        // itself, and the plugin must not intercept it while both are in use.
        if (!isset($_GET['sm_indexing_action']) || $_GET['sm_indexing_action'] !== 'update_end_dates') {
            return;
        }

        // A link click changes data, so require a nonce to block cross-site requests
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'sm_update_end_dates')) {
            wp_die(__('Security check failed. Please reload the page and try again.', 'scouting-forms'), 403);
        }

        if (!self::canUpdateEndDates()) {
            wp_die(__('Permission denied.', 'scouting-forms'), 403);
        }

        $counts = ArchiveRepository::updateActiveEndDates();
        $redirect = remove_query_arg(['sm_indexing_action', '_wpnonce'], wp_get_referer() ?: home_url('/my-account/'));
        $redirect = add_query_arg([
            'updated_end_dates' => '1',
            'tab'               => 'myIndexing',
            'tab2'              => 'tools'
        ], $redirect);

        wp_safe_redirect($redirect);
        exit;
    }

    public static function render(): string {
        $search = sanitize_text_field(wp_unslash($_GET['idx_search'] ?? ''));
        $tab    = sanitize_text_field(wp_unslash($_GET['idx_tab'] ?? 'councils'));
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
            'updated'  => $updated,
            'canUpdate' => self::canUpdateEndDates()
        ]);
    }
}
