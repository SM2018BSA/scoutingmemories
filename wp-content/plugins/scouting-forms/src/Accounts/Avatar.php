<?php

namespace ScoutingMemories\Forms\Accounts;

/**
 * Avatar
 *
 * Members can upload a picture when they register (saved as the user meta frm_avatar_id, an
 * attachment ID). Formidable Registration shows it wherever WordPress shows an avatar; once the
 * add-on is gone this does the same.
 */
class Avatar {

    public static function registerHooks(): void {
        add_action('init', static function () {
            if (!class_exists('FrmRegAvatarController')) {
                add_filter('pre_get_avatar_data', [__CLASS__, 'data'], 10, 2);
            }
        }, 999);
    }

    /**
     * @param array<string, mixed> $args
     * @param mixed $idOrEmail
     * @return array<string, mixed>
     */
    public static function data(array $args, $idOrEmail): array {
        $userId = 0;
        if (is_numeric($idOrEmail)) {
            $userId = (int) $idOrEmail;
        } elseif ($idOrEmail instanceof \WP_User) {
            $userId = (int) $idOrEmail->ID;
        } elseif ($idOrEmail instanceof \WP_Post) {
            $userId = (int) $idOrEmail->post_author;
        } elseif ($idOrEmail instanceof \WP_Comment) {
            $userId = (int) $idOrEmail->user_id;
        } elseif (is_string($idOrEmail) && is_email($idOrEmail)) {
            $user = get_user_by('email', $idOrEmail);
            $userId = $user ? (int) $user->ID : 0;
        }
        $attachment = $userId ? (int) get_user_meta($userId, 'frm_avatar_id', true) : 0;
        if ($attachment) {
            $size = (int) ($args['size'] ?? 96);
            $url = wp_get_attachment_image_url($attachment, [$size, $size]);
            if ($url) {
                $args['url'] = $url;
                $args['found_avatar'] = true;
            }
        }
        return $args;
    }
}
