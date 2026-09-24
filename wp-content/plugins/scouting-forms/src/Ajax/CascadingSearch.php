<?php

namespace ScoutingMemories\Forms\Ajax;

use ScoutingMemories\Forms\Models\ArchiveRepository;

/**
 * CascadingSearch
 *
 * Handles AJAX requests for cascading State -> Council -> Camp/Lodge selects.
 */
class CascadingSearch {

    public static function registerHooks(): void {
        add_action('wp_ajax_sm_get_councils', [__CLASS__, 'getCouncils']);
        add_action('wp_ajax_nopriv_sm_get_councils', [__CLASS__, 'getCouncils']);

        add_action('wp_ajax_sm_get_camps_lodges', [__CLASS__, 'getCampsAndLodges']);
        add_action('wp_ajax_nopriv_sm_get_camps_lodges', [__CLASS__, 'getCampsAndLodges']);

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueueScripts']);
    }

    public static function enqueueScripts(): void {
        wp_register_script(
            'sm-cascading-selects',
            SM_FORMS_PLUGIN_URL . 'assets/js/cascading-selects.js',
            ['jquery'],
            SM_FORMS_VERSION,
            true
        );

        wp_localize_script('sm-cascading-selects', 'smAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('sm_cascading_nonce')
        ]);
    }

    public static function getCouncils(): void {
        check_ajax_referer('sm_cascading_nonce', 'security');

        $state_id = isset($_POST['state_id']) ? (int) $_POST['state_id'] : 0;
        $active_only = isset($_POST['active_only']) && $_POST['active_only'] === '1';

        $councils = ArchiveRepository::getCouncils($state_id ?: null, $active_only);

        wp_send_json_success($councils);
    }

    public static function getCampsAndLodges(): void {
        check_ajax_referer('sm_cascading_nonce', 'security');

        $council_id = isset($_POST['council_id']) ? (int) $_POST['council_id'] : 0;
        $state_id   = isset($_POST['state_id']) ? (int) $_POST['state_id'] : 0;

        $camps  = ArchiveRepository::getCamps($council_id ?: null, $state_id ?: null);
        $lodges = ArchiveRepository::getLodges($council_id ?: null, $state_id ?: null);

        wp_send_json_success([
            'camps'  => $camps,
            'lodges' => $lodges
        ]);
    }
}
