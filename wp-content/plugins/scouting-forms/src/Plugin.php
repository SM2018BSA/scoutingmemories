<?php

namespace ScoutingMemories\Forms;

use ScoutingMemories\Forms\Accounts\AccountPages;
use ScoutingMemories\Forms\Accounts\Avatar;
use ScoutingMemories\Forms\Admin\AdminMenu;
use ScoutingMemories\Forms\Ajax\CascadingSearch;
use ScoutingMemories\Forms\Ajax\DynamicFields;
use ScoutingMemories\Forms\Forms\AccountProfileForm;
use ScoutingMemories\Forms\Forms\DynamicFormRenderer;
use ScoutingMemories\Forms\Forms\DynamicViewRenderer;
use ScoutingMemories\Forms\Forms\IndexEntityForms;
use ScoutingMemories\Forms\Forms\IndexingBrowser;
use ScoutingMemories\Forms\Forms\MemoryForm;
use ScoutingMemories\Forms\Forms\UserDefaultsForm;
use ScoutingMemories\Forms\Forms\UserPostsView;
use ScoutingMemories\Forms\Rest\ApiController;
use ScoutingMemories\Forms\Support\Capabilities;
use ScoutingMemories\Forms\Support\Mailer;
use ScoutingMemories\Forms\Tools\CompareTool;

/**
 * Plugin
 *
 * Central orchestrator and service registry for Scouting Forms & Archives.
 */
class Plugin {

    private static ?Plugin $instance = null;

    /**
     * Singleton instance
     */
    public static function getInstance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Boot the plugin and register components
     */
    public function boot(): void {
        // 1. Unified Tailwind CSS v4 Registration
        add_action('wp_enqueue_scripts', [__CLASS__, 'registerUnifiedStyles'], 1);
        add_action('admin_enqueue_scripts', [__CLASS__, 'registerUnifiedStyles'], 1);

        // 2. REST API Services
        ApiController::registerHooks();

        // 3. AJAX Services
        CascadingSearch::registerHooks();
        DynamicFields::registerHooks();
        Mailer::registerHooks();
        Capabilities::registerHooks();
        AccountPages::registerHooks();
        Avatar::registerHooks();

        // 4. Frontend Forms & Shortcodes
        DynamicFormRenderer::registerHooks();
        DynamicViewRenderer::registerHooks();
        AccountProfileForm::registerHooks();
        UserDefaultsForm::registerHooks();
        UserPostsView::registerHooks();
        IndexingBrowser::registerHooks();
        MemoryForm::registerHooks();
        IndexEntityForms::registerHooks();

        // 5. WP Admin Management
        if (is_admin()) {
            AdminMenu::registerHooks();
        }

        // 6. Local development: side-by-side comparison with Formidable (does nothing on live)
        CompareTool::registerHooks();
    }

    /**
     * Register single, authoritative Tailwind CSS v4 bundle
     * Accessible by theme, pdf-embedder, forms, and builder
     */
    public static function registerUnifiedStyles(): void {
        // Front end: small add-on sheet for the site's Bootstrap (enqueued by forms/views as they render)
        $front_css = SM_FORMS_PLUGIN_DIR . 'assets/css/forms-front.css';
        wp_register_style(
            'sm-forms-front',
            SM_FORMS_PLUGIN_URL . 'assets/css/forms-front.css',
            [],
            file_exists($front_css) ? (string) filemtime($front_css) : SM_FORMS_VERSION
        );

        // Front end: field logic, dependent Dynamic fields, sections, repeaters, pages
        $front_js = SM_FORMS_PLUGIN_DIR . 'assets/js/forms-front.js';
        wp_register_script(
            'sm-forms-front',
            SM_FORMS_PLUGIN_URL . 'assets/js/forms-front.js',
            [],
            file_exists($front_js) ? (string) filemtime($front_js) : SM_FORMS_VERSION,
            true
        );

        // Tailwind is only for the wp-admin builder screens, never the public site
        if (!is_admin()) {
            return;
        }

        $css_file = SM_FORMS_PLUGIN_DIR . 'assets/css/tailwindcss.css';
        $ver = file_exists($css_file) ? (string) filemtime($css_file) : SM_FORMS_VERSION;

        wp_register_style(
            'tailwindcss-v4',
            SM_FORMS_PLUGIN_URL . 'assets/css/tailwindcss.css',
            [],
            $ver
        );
    }
}
