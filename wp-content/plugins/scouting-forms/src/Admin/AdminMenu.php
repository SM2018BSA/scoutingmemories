<?php

namespace ScoutingMemories\Forms\Admin;

use ScoutingMemories\Forms\Models\ArchiveRepository;
use ScoutingMemories\Forms\Support\Environment;
use ScoutingMemories\Forms\Support\Mailer;
use ScoutingMemories\Forms\Support\TestData;

/**
 * AdminMenu
 *
 * Provides native WP Admin management for the Vue 3 + Reka UI Form Builder,
 * Views & Template Studio, Entries Manager, Historical Archives, and User Guide.
 */
class AdminMenu {

    public static function registerHooks(): void {
        add_action('admin_menu', [__CLASS__, 'addAdminPages']);
        add_action('admin_init', [__CLASS__, 'handleAdminActions']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueueAdminAssets']);
        add_action('admin_notices', [__CLASS__, 'displayAdminNotice']);
        add_action('wp_ajax_sm_dismiss_guide_notice', [__CLASS__, 'dismissNotice']);
    }

    public static function addAdminPages(): void {
        // Main Menu: Opens the Vue 3 + Reka UI Forms Studio & Builder
        add_menu_page(
            __('Scouting Forms & Views Studio', 'scouting-forms'),
            __('Scouting Forms', 'scouting-forms'),
            'manage_options',
            'scouting-forms-builder',
            [__CLASS__, 'renderBuilderPage'],
            'dashicons-feedback',
            25
        );

        // Submenu 1: Forms & Builder (matches top-level slug)
        add_submenu_page(
            'scouting-forms-builder',
            __('Forms & Fields Studio', 'scouting-forms'),
            __('📋 Forms & Fields', 'scouting-forms'),
            'manage_options',
            'scouting-forms-builder',
            [__CLASS__, 'renderBuilderPage']
        );

        // Submenu 2: Views Studio
        add_submenu_page(
            'scouting-forms-builder',
            __('Views & Templates Studio', 'scouting-forms'),
            __('👁️ Views Studio', 'scouting-forms'),
            'manage_options',
            'scouting-forms-views',
            [__CLASS__, 'renderBuilderPage']
        );

        // Submenu 3: Entries Manager
        add_submenu_page(
            'scouting-forms-builder',
            __('Entries Manager', 'scouting-forms'),
            __('📑 Entries Manager', 'scouting-forms'),
            'manage_options',
            'scouting-forms-entries',
            [__CLASS__, 'renderBuilderPage']
        );

        // Submenu 4: Councils
        add_submenu_page(
            'scouting-forms-builder',
            __('Historical Councils Archive', 'scouting-forms'),
            __('🏛️ Councils (2,323)', 'scouting-forms'),
            'manage_options',
            'scouting-archives-councils',
            [__CLASS__, 'renderCouncilsPage']
        );

        // Submenu 5: Camps
        add_submenu_page(
            'scouting-forms-builder',
            __('Historical Camps Archive', 'scouting-forms'),
            __('🏕️ Camps (3,552)', 'scouting-forms'),
            'manage_options',
            'scouting-archives-camps',
            [__CLASS__, 'renderCampsPage']
        );

        // Submenu 6: Lodges
        add_submenu_page(
            'scouting-forms-builder',
            __('Order of the Arrow Lodges', 'scouting-forms'),
            __('🏹 Lodges (1,003)', 'scouting-forms'),
            'manage_options',
            'scouting-archives-lodges',
            [__CLASS__, 'renderLodgesPage']
        );

        // Submenu 7: Archive Tools
        add_submenu_page(
            'scouting-forms-builder',
            __('Archive Maintenance Tools', 'scouting-forms'),
            __('⚙️ Archive Tools', 'scouting-forms'),
            'manage_options',
            'scouting-archives-tools',
            [__CLASS__, 'renderToolsPage']
        );

        // Local development only: test data cleanup and intercepted mail log
        if (Environment::isLocal()) {
            add_submenu_page(
                'scouting-forms-builder',
                __('Test Tools (local only)', 'scouting-forms'),
                __('🧪 Test Tools', 'scouting-forms'),
                'manage_options',
                'scouting-forms-test-tools',
                [__CLASS__, 'renderTestToolsPage']
            );
        }

        // Submenu 8: User Guide & Walkthrough
        add_submenu_page(
            'scouting-forms-builder',
            __('Overview & Walkthrough', 'scouting-forms'),
            __('📖 User Guide', 'scouting-forms'),
            'manage_options',
            'scouting-archives',
            [__CLASS__, 'renderGuidePage']
        );
    }

    public static function enqueueAdminAssets(string $hook): void {
        $is_builder_page = (strpos($hook, 'scouting-forms') !== false);
        $is_archive_page = (strpos($hook, 'scouting-archives') !== false);

        if (!$is_builder_page && !$is_archive_page) {
            return;
        }

        // Always ensure canonical Tailwind CSS v4 is registered and available
        wp_enqueue_style('tailwindcss-v4');

        if ($is_builder_page) {
            $builder_css = SM_FORMS_PLUGIN_DIR . 'assets/builder/builder.css';
            $builder_js  = SM_FORMS_PLUGIN_DIR . 'assets/builder/builder.js';

            $ver_css = file_exists($builder_css) ? (string) filemtime($builder_css) : SM_FORMS_VERSION;
            $ver_js  = file_exists($builder_js) ? (string) filemtime($builder_js) : SM_FORMS_VERSION;

            wp_enqueue_style(
                'sm-builder-css',
                SM_FORMS_PLUGIN_URL . 'assets/builder/builder.css',
                [],
                $ver_css
            );

            wp_enqueue_script(
                'sm-builder-js',
                SM_FORMS_PLUGIN_URL . 'assets/builder/builder.js',
                [],
                $ver_js,
                true
            );

            wp_localize_script('sm-builder-js', 'smBuilderConfig', [
                'restUrl'     => rest_url('scouting-forms/v1'),
                'nonce'       => wp_create_nonce('wp_rest'),
                'adminUrl'    => admin_url(),
                'activeTheme' => get_stylesheet(),
                'hook'        => $hook
            ]);
        }

        if ($is_archive_page) {
            wp_enqueue_style(
                'sm-admin-guide-css',
                SM_FORMS_PLUGIN_URL . 'assets/css/admin-guide.css',
                [],
                SM_FORMS_VERSION
            );

            wp_enqueue_script(
                'sm-admin-guide-js',
                SM_FORMS_PLUGIN_URL . 'assets/js/admin-guide.js',
                ['jquery'],
                SM_FORMS_VERSION,
                true
            );

            wp_localize_script('sm-admin-guide-js', 'smAjax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('sm_cascading_nonce'),
            ]);
        }
    }

    public static function displayAdminNotice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Only on the Dashboard and Plugins screens, so it never sits on top of Formidable's
        // (or anyone else's) admin pages while both plugins are in use
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['dashboard', 'plugins'], true)) {
            return;
        }

        $dismissed = get_user_meta(get_current_user_id(), 'sm_dismiss_guide_notice', true);
        if ($dismissed) {
            return;
        }

        $builder_url  = admin_url('admin.php?page=scouting-forms-builder');
        $guide_url    = admin_url('admin.php?page=scouting-archives');
        $nonce        = wp_create_nonce('sm_dismiss_notice_nonce');
        ?>
        <div class="notice notice-info is-dismissible sm-admin-notice" style="border-left: 4px solid #2563eb; background: #ffffff; padding: 14px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin: 15px 0;">
            <p style="font-size: 14px; margin: 0 0 8px 0; color: #0f172a;">
                <strong style="color: #1e3a8a;">🎉 Scouting Forms & Views Studio is active!</strong>
                Manage your 23 forms, 266 fields, 14 views, and historical archives from the left sidebar under <strong>Scouting Forms</strong>.
            </p>
            <p style="margin: 0;">
                <a href="<?php echo esc_url($builder_url); ?>" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8;">
                    📋 Open Forms & Views Studio
                </a>
                <a href="<?php echo esc_url($guide_url); ?>" class="button button-secondary" style="margin-left: 8px;">
                    📖 User Guide & Walkthrough
                </a>
            </p>
        </div>
        <script>
        jQuery(document).on('click', '.sm-admin-notice .notice-dismiss', function() {
            jQuery.post(ajaxurl, {
                action: 'sm_dismiss_guide_notice',
                nonce: '<?php echo esc_js($nonce); ?>'
            });
        });
        </script>
        <?php
    }

    public static function dismissNotice(): void {
        check_ajax_referer('sm_dismiss_notice_nonce', 'nonce');
        update_user_meta(get_current_user_id(), 'sm_dismiss_guide_notice', 1);
        wp_send_json_success();
    }

    public static function renderTestToolsPage(): void {
        if (!current_user_can('manage_options') || !Environment::isLocal()) {
            wp_die(__('Permission denied.', 'scouting-forms'));
        }
        $counts = TestData::counts();
        $mailLog = Mailer::getLog();
        include SM_FORMS_PLUGIN_DIR . 'views/admin/test-tools-page.php';
    }

    /**
     * Test Tools actions (local copies only): delete marked test data, clear the mail log.
     */
    private static function handleTestToolsActions(string $action): void {
        if (!Environment::isLocal() || !current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'scouting-forms'));
        }
        check_admin_referer('sm_test_tools');

        $args = ['page' => 'scouting-forms-test-tools'];
        if ($action === 'cleanup_test_data') {
            $removed = TestData::cleanup();
            $args['removed_entries'] = $removed['entries'];
            $args['removed_posts'] = $removed['posts'];
            $args['removed_users'] = $removed['users'];
        } elseif ($action === 'clear_mail_log') {
            Mailer::clearLog();
            $args['mail_cleared'] = 1;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function handleAdminActions(): void {
        $action = isset($_POST['sm_admin_action']) ? sanitize_key(wp_unslash($_POST['sm_admin_action'])) : '';
        if (in_array($action, ['cleanup_test_data', 'clear_mail_log'], true)) {
            self::handleTestToolsActions($action);
        }
        if ($action !== 'batch_update_dates') {
            return;
        }

        if (!check_admin_referer('sm_batch_update_dates_nonce')) {
            wp_die(__('Security check failed.', 'scouting-forms'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'scouting-forms'));
        }

        $year = sanitize_text_field($_POST['target_year'] ?? date('Y'));
        $counts = ArchiveRepository::updateActiveEndDates($year);

        wp_safe_redirect(add_query_arg([
            'page'             => 'scouting-archives-tools',
            'dates_updated'    => '1',
            'updated_councils' => $counts['councils'],
            'updated_camps'    => $counts['camps'],
            'updated_lodges'   => $counts['lodges'],
        ], admin_url('admin.php')));
        exit;
    }

    public static function renderBuilderPage(): void {
        include SM_FORMS_PLUGIN_DIR . 'views/admin/builder-page.php';
    }

    public static function renderGuidePage(): void {
        $counts = ArchiveRepository::getArchiveCounts();
        $states = ArchiveRepository::getStates();

        include SM_FORMS_PLUGIN_DIR . 'views/admin/guide-page.php';
    }

    public static function renderCouncilsPage(): void {
        $search = sanitize_text_field($_GET['s'] ?? '');
        $page   = max(1, (int) ($_GET['paged'] ?? 1));
        $limit  = 30;
        $offset = ($page - 1) * $limit;

        $councils = ArchiveRepository::getCouncils(null, false, $search, $limit, $offset);

        include SM_FORMS_PLUGIN_DIR . 'views/admin/councils-page.php';
    }

    public static function renderCampsPage(): void {
        $search = sanitize_text_field($_GET['s'] ?? '');
        $page   = max(1, (int) ($_GET['paged'] ?? 1));
        $limit  = 30;
        $offset = ($page - 1) * $limit;

        $camps = ArchiveRepository::getCamps(null, null, $search, $limit, $offset);

        include SM_FORMS_PLUGIN_DIR . 'views/admin/camps-page.php';
    }

    public static function renderLodgesPage(): void {
        $search = sanitize_text_field($_GET['s'] ?? '');
        $page   = max(1, (int) ($_GET['paged'] ?? 1));
        $limit  = 30;
        $offset = ($page - 1) * $limit;

        $lodges = ArchiveRepository::getLodges(null, null, $search, $limit, $offset);

        include SM_FORMS_PLUGIN_DIR . 'views/admin/lodges-page.php';
    }

    public static function renderToolsPage(): void {
        $updated = isset($_GET['dates_updated']) && $_GET['dates_updated'] === '1';
        $councils_count = (int) ($_GET['updated_councils'] ?? 0);
        $camps_count    = (int) ($_GET['updated_camps'] ?? 0);
        $lodges_count   = (int) ($_GET['updated_lodges'] ?? 0);

        include SM_FORMS_PLUGIN_DIR . 'views/admin/tools-page.php';
    }
}
