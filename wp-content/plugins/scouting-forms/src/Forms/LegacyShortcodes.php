<?php

namespace ScoutingMemories\Forms\Forms;

/**
 * LegacyShortcodes
 *
 * Shortcodes from the plugin's first version, used by the 2026 theme ([sm_add_council_form],
 * [sm_account_profile], ...). They used to be separate, simplified forms with their own
 * handlers, which skipped the checks every other form goes through (who may use the form,
 * validation, spam check, allowed files, the theme's hooks). They now show the site's real forms,
 * the same ones the current theme uses on those pages.
 */
class LegacyShortcodes {

    /** shortcode => [form ID, show title] */
    private const FORMS = [
        'sm_add_council_form' => [8, false],
        'sm_add_camp_form' => [11, false],
        'sm_add_lodge_form' => [7, false],
        'sm_add_memory_form' => [6, false],
        'sm_account_profile' => [22, false],
        'sm_user_defaults' => [34, false],
    ];

    public static function registerHooks(): void {
        foreach (self::FORMS as $tag => [$formId, $title]) {
            add_shortcode($tag, static function ($atts = []) use ($formId, $title): string {
                $atts = is_array($atts) ? $atts : [];
                return DynamicFormRenderer::renderShortcode([
                    'id' => (string) $formId,
                    'title' => $title ? '1' : '0',
                    'description' => (string) ($atts['description'] ?? '1'),
                ]);
            });
        }
    }
}
