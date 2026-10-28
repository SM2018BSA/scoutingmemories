<?php

namespace ScoutingMemories\Forms\Ui;

use ScoutingMemories\Forms\Support\FormidableSettings;

/**
 * ButtonStyle
 *
 * How submit-type buttons (Submit, Next, Login, Reset Password) look. By default they look as
 * they did under Formidable: Bootstrap's btn-secondary in the colours of the form's Formidable
 * style (the frm_styles post, which stays in the database when Formidable is off; its default is
 * light blue). Administrators can switch to the site green under Scouting Forms > Settings.
 */
class ButtonStyle {

    /** Option set on the Settings page: 1 = site-green buttons */
    public const OPTION = 'sm_forms_green_buttons';

    /** Formidable style settings => CSS variables read by .sm-btn-formidable (forms-front.css) */
    private const COLOURS = [
        'submit_bg_color' => '--sm-submit-bg',
        'submit_border_color' => '--sm-submit-border',
        'submit_text_color' => '--sm-submit-color',
        'submit_hover_bg_color' => '--sm-submit-hover-bg',
        'submit_hover_border_color' => '--sm-submit-hover-border',
        'submit_hover_color' => '--sm-submit-hover-color',
        'submit_active_bg_color' => '--sm-submit-active-bg',
        'submit_active_border_color' => '--sm-submit-active-border',
        'submit_active_color' => '--sm-submit-active-color',
    ];

    public static function green(): bool {
        return (bool) get_option(self::OPTION, false);
    }

    /**
     * Class and style attributes for a submit-type button.
     *
     * @param string $class Formidable's own class for the button (frm_button_submit, frm_next_page)
     * @param mixed $styleId The form's custom_style setting (see settings())
     */
    public static function attributes(string $class = '', $styleId = 1): string {
        $style = self::style($styleId);
        return 'class="' . esc_attr(trim($class . ' ' . self::classes($styleId))) . '"'
            . ($style !== '' ? ' style="' . esc_attr($style) . '"' : '');
    }

    /**
     * @param mixed $styleId
     */
    public static function classes($styleId = 1): string {
        if (self::green()) {
            return ThemeClasses::button('scout');
        }
        // A form Formidable left unstyled keeps the plain Bootstrap button it had
        return ThemeClasses::button(self::settings($styleId) === null ? 'secondary' : 'formidable');
    }

    /**
     * CSS variables with the Formidable style's button colours ('' when there are none to set;
     * the stylesheet then falls back to Formidable's default light blue).
     *
     * @param mixed $styleId
     */
    public static function style($styleId = 1): string {
        if (self::green()) {
            return '';
        }
        $settings = self::settings($styleId) ?? [];
        $css = '';
        foreach (self::COLOURS as $key => $var) {
            $colour = self::colour($settings[$key] ?? '');
            if ($colour !== '') {
                $css .= $var . ':' . $colour . ';';
            }
        }
        return $css;
    }

    /**
     * The settings of the Formidable style a form uses, or null when Formidable would not style
     * its submit button. custom_style is 0 for "no styling", 1 or empty for the default style
     * (the frm_styles post with menu_order 1), or a style's post ID.
     *
     * @param mixed $styleId
     * @return array<string, mixed>|null
     */
    private static function settings($styleId): ?array {
        static $cache = [];
        $key = (string) $styleId;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        if ($key === '0' || FormidableSettings::get('load_style') === 'none') {
            return $cache[$key] = null;
        }

        $post = null;
        if ((int) $styleId > 1) {
            $post = get_post((int) $styleId);
            if (!$post || $post->post_type !== 'frm_styles') {
                $post = null;
            }
        }
        if (!$post) {
            $posts = get_posts([
                'post_type' => 'frm_styles',
                'post_status' => 'publish',
                'numberposts' => 1,
                'orderby' => ['menu_order' => 'DESC', 'ID' => 'ASC'],
            ]);
            $post = $posts[0] ?? null;
        }

        $settings = [];
        if ($post) {
            // Formidable saves styles as JSON; very old ones were serialized
            $content = (string) $post->post_content;
            $decoded = json_decode($content, true);
            if (!is_array($decoded) && is_serialized($content)) {
                $decoded = @unserialize($content, ['allowed_classes' => false]);
            }
            $settings = is_array($decoded) ? $decoded : [];
        }

        // The style's "Disable submit button styling" box
        return $cache[$key] = empty($settings['submit_style']) ? $settings : null;
    }

    /**
     * A colour from a Formidable style (stored with or without "#"), or '' if it is not one.
     *
     * @param mixed $value
     */
    private static function colour($value): string {
        $value = trim((string) $value);
        if (preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value, $m)) {
            return '#' . $m[1];
        }
        if (preg_match('/^rgba?\(\s*[\d.]+%?\s*,\s*[\d.]+%?\s*,\s*[\d.]+%?\s*(,\s*[\d.]+%?\s*)?\)$/i', $value)) {
            return $value;
        }
        return strtolower($value) === 'transparent' ? 'transparent' : '';
    }
}
