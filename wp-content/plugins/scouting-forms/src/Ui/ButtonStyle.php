<?php

namespace ScoutingMemories\Forms\Ui;

use ScoutingMemories\Forms\Support\FormidableSettings;

/**
 * ButtonStyle
 *
 * Which colour submit-type buttons (Submit, Next, Login, Reset Password) get. By default the
 * site's blue button; a form whose Formidable style says "no styling" (the frm_styles post, which
 * stays in the database when Formidable is off) gets the plain grey one, as it had. Administrators
 * can switch to the site green under Scouting Forms > Settings. The class lists themselves are
 * ThemeClasses' (Tailwind utility classes); nothing here writes a style attribute.
 */
class ButtonStyle {

    /** Option set on the Settings page: 1 = site-green buttons */
    public const OPTION = 'sm_forms_green_buttons';

    public static function green(): bool {
        return (bool) get_option(self::OPTION, false);
    }

    /**
     * The class attribute for a submit-type button.
     *
     * @param string $class Formidable's own class for the button (frm_button_submit, frm_next_page): a bare hook
     * @param mixed $styleId The form's custom_style setting (see settings())
     */
    public static function attributes(string $class = '', $styleId = 1): string {
        return 'class="' . esc_attr(ThemeClasses::classes($class, self::classes($styleId))) . '"';
    }

    /**
     * @param mixed $styleId
     */
    public static function classes($styleId = 1): string {
        if (self::green()) {
            return ThemeClasses::button('scout');
        }
        // A form Formidable left unstyled keeps the plain grey button it had
        return ThemeClasses::button(self::settings($styleId) === null ? 'secondary' : 'primary');
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
}
