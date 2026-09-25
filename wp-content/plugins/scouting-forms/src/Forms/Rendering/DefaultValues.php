<?php

namespace ScoutingMemories\Forms\Forms\Rendering;

use ScoutingMemories\Forms\Compat\Hooks;
use ScoutingMemories\Forms\Views\TemplateTags;
use ScoutingMemories\Forms\Support\ShortcodeTrust;

/**
 * DefaultValues
 *
 * A field's starting value, with the Formidable default-value shortcodes the site uses resolved:
 * [user_id], [email], [user_login], [display_name], [first_name], [last_name], [user_meta key=x],
 * [get param="name"] (from the page URL), [date], [date format="Y"], [time], [sitename],
 * [post_id], and [frm-field-value ...] (through the plugin's own version).
 */
class DefaultValues {

    /**
     * @return mixed
     */
    public static function resolve(array $field) {
        return Hooks::defaultValue(self::resolveOwn($field), $field);
    }

    /**
     * @param array<string, mixed> $field
     * @return mixed
     */
    private static function resolveOwn(array $field) {
        $default = $field['default_value'];
        if (is_array($default)) {
            return $default;
        }
        $default = (string) $default;
        // Older forms keep a shortcode default in "dyn_default_value" (Formidable still reads it)
        if ($default === '' && !empty($field['field_options']['dyn_default_value']) && is_string($field['field_options']['dyn_default_value'])) {
            $default = $field['field_options']['dyn_default_value'];
        }
        // A default that is exactly one [user_meta key=x] keeps the stored value as it is (a list
        // of councils stays a list)
        if (preg_match('/^\s*\[user_meta key=["\']?([A-Za-z0-9_\-]+)["\']?\s*\]\s*$/', $default, $m)) {
            $userId = get_current_user_id();
            $resolved = $userId ? get_user_meta($userId, $m[1], true) : '';
            if (is_array($resolved)) {
                return array_values(array_filter(array_map('strval', $resolved), 'strlen'));
            }
            $resolved = (string) $resolved;
        } else {
            $resolved = self::shortcodes($default);
        }

        // A Dynamic field stores entry IDs; a text default (a state name) selects the matching entry
        if ($field['type'] === 'data' && is_string($resolved)) {
            return DynamicOptions::idForText($field, $resolved);
        }
        return $resolved;
    }

    private static function shortcodes(string $default): string {
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

        $default = preg_replace_callback('/\[get param=["\']?([A-Za-z0-9_\-]+)["\']?\]/', static function ($m) {
            return isset($_GET[$m[1]]) && !is_array($_GET[$m[1]]) ? sanitize_text_field(wp_unslash($_GET[$m[1]])) : '';
        }, $default);

        // [user_meta key=x], [date format="Y"], [post_id] (the page the form is on)
        $default = preg_replace_callback('/\[user_meta key=["\']?([A-Za-z0-9_\-]+)["\']?\s*\]/', static function ($m) use ($user) {
            $value = $user->ID ? get_user_meta($user->ID, $m[1], true) : '';
            return is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
        }, $default);
        $default = preg_replace_callback('/\[date format=["\']?([^"\'\]]+)["\']?\s*\]/', static function ($m) {
            return wp_date($m[1]);
        }, $default);
        $default = str_replace('[post_id]', (string) (int) get_the_ID(), $default);

        // Formidable shortcodes written in the field settings, e.g. [frm-field-value ...]
        if (strpos($default, '[') !== false) {
            $default = html_entity_decode(wp_strip_all_tags(ShortcodeTrust::trusted(static fn() => do_shortcode(TemplateTags::ownShortcodes($default)))), ENT_QUOTES);
        }
        return $default;
    }
}
