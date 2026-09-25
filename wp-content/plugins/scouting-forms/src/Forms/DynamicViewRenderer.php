<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Views\EntryActions;
use ScoutingMemories\Forms\Views\ViewRenderer;

/**
 * DynamicViewRenderer
 *
 * [sm_view id=X] (and [display-frm-data id=X] once Formidable is gone): renders any Formidable
 * view from its frm_display post. The work is done in ScoutingMemories\Forms\Views.
 */
class DynamicViewRenderer extends FormHandler {

    public static function registerHooks(): void {
        add_shortcode('sm_view', [__CLASS__, 'renderShortcode']);
        add_shortcode('sm_show_entry', [ViewRenderer::class, 'showEntry']);
        EntryActions::registerHooks();

        // Fallbacks for existing Formidable view shortcodes, only when Formidable Views is not
        // loaded. Checked late on init so plugin load order can't shadow the real shortcodes.
        add_action('init', function () {
            if (!class_exists('FrmViewsDisplaysController') && !class_exists('FrmProDisplaysController')) {
                if (!shortcode_exists('display-frm-data')) {
                    add_shortcode('display-frm-data', [__CLASS__, 'renderShortcode']);
                }
                if (!shortcode_exists('frm-show-entry')) {
                    add_shortcode('frm-show-entry', [ViewRenderer::class, 'showEntry']);
                }
            }
        }, 999);
    }

    /**
     * [sm_view id="X" page_size="20" any_param="value"]
     */
    public static function renderShortcode($atts = []): string {
        return ViewRenderer::render(is_array($atts) ? $atts : []);
    }
}
