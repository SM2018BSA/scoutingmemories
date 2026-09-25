<?php

namespace ScoutingMemories\Forms\Support;

/**
 * FormidableSettings
 *
 * Reads Formidable's global settings (the `frm_options` option) whether or not Formidable is
 * active. With Formidable active the option unserializes to a FrmSettings object; without it,
 * PHP gives an __PHP_Incomplete_Class. Both are read as a plain array of their public properties.
 */
class FormidableSettings {

    /**
     * @return array<string, mixed>
     */
    public static function all(): array {
        static $settings = null;
        if ($settings !== null) {
            return $settings;
        }

        $raw = get_option('frm_options');
        if (is_array($raw)) {
            $settings = $raw;
        } elseif (is_object($raw)) {
            $settings = [];
            foreach ((array) $raw as $key => $value) {
                // Cast keys of non-public props look like "\0Class\0prop"; keep only public ones
                if (is_string($key) && strpos($key, "\0") === false) {
                    $settings[$key] = $value;
                }
            }
        } else {
            $settings = [];
        }
        return $settings;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * reCAPTCHA keys configured in Formidable (site key is public, secret stays server side).
     *
     * @return array{site_key:string, secret:string, type:string}
     */
    public static function recaptcha(): array {
        return [
            'site_key' => (string) self::get('pubkey', ''),
            'secret' => (string) self::get('privkey', ''),
            'type' => (string) self::get('re_type', ''),
        ];
    }
}
