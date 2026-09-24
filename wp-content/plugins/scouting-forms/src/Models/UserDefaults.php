<?php

namespace ScoutingMemories\Forms\Models;

/**
 * UserDefaults
 *
 * Manages contributor persistent working defaults in user_meta.
 * 100% compatible with existing Scouting Memories metadata keys.
 */
class UserDefaults {

    const META_KEYS = [
        'state'        => 'user_state',
        'council'      => 'user_council',
        'camp'         => 'user_camp',
        'lodge'        => 'user_lodge',
        'author'       => 'author',
        'photographer' => 'photographer',
        'contributors' => 'contributors',
        'date_original'=> 'date_original',
        'identifier'   => 'identifier',
        'pub_digital'  => 'pub_digital',
        'date_digital' => 'date_digital',
        'subject'      => 'subject',
        'location'     => 'location',
        'phy_dsc'      => 'phy_dsc',
        // Slugs
        'state_slug'   => 'state_slug',
        'council_slug' => 'council_slug',
        'camp_slug'    => 'camp_slug',
        'lodge_slug'   => 'lodge_slug',
    ];

    /**
     * Get all defaults for a user
     *
     * @param int|null $userId
     * @return array
     */
    public static function getForUser(?int $userId = null): array {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return [];
        }

        $defaults = [];
        foreach (self::META_KEYS as $name => $meta_key) {
            $val = get_user_meta($userId, $meta_key, true);
            $defaults[$name] = $val !== false ? $val : '';
        }

        return $defaults;
    }

    /**
     * Save defaults for a user
     *
     * @param array $data
     * @param int|null $userId
     * @return bool
     */
    public static function saveForUser(array $data, ?int $userId = null): bool {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return false;
        }

        foreach (self::META_KEYS as $name => $meta_key) {
            if (isset($data[$name])) {
                $value = $data[$name];
                
                // Keep arrays clean
                if (is_array($value)) {
                    $value = array_filter(array_unique($value));
                } else {
                    $value = sanitize_text_field($value);
                }

                update_user_meta($userId, $meta_key, $value);
            }
        }

        return true;
    }
}
