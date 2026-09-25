<?php

namespace ScoutingMemories\Forms\Forms\Rendering;

/**
 * DefaultValues
 *
 * A field's starting value, with the Formidable default-value shortcodes the site uses resolved:
 * [user_id], [email], [user_login], [display_name], [first_name], [last_name],
 * [get param="name"] (from the page URL), [date], [time], [sitename].
 * Phase 3 extends this as more forms move over.
 */
class DefaultValues {

    /**
     * @return mixed
     */
    public static function resolve(array $field) {
        $default = $field['default_value'];
        if (is_array($default)) {
            return $default;
        }
        $default = (string) $default;
        if ($default === '' || strpos($default, '[') === false) {
            return $default;
        }

        $user = wp_get_current_user();
        $default = strtr($default, [
            '[user_id]' => $user->ID ? (string) $user->ID : '',
            '[email]' => $user->ID ? $user->user_email : '',
            '[user_login]' => $user->ID ? $user->user_login : '',
            '[display_name]' => $user->ID ? $user->display_name : '',
            '[first_name]' => $user->ID ? (string) $user->first_name : '',
            '[last_name]' => $user->ID ? (string) $user->last_name : '',
            '[date]' => wp_date(get_option('date_format')),
            '[time]' => wp_date(get_option('time_format')),
            '[sitename]' => get_bloginfo('name'),
        ]);

        return preg_replace_callback('/\[get param=["\']?([A-Za-z0-9_\-]+)["\']?\]/', static function ($m) {
            return isset($_GET[$m[1]]) && !is_array($_GET[$m[1]]) ? sanitize_text_field(wp_unslash($_GET[$m[1]])) : '';
        }, $default);
    }
}
