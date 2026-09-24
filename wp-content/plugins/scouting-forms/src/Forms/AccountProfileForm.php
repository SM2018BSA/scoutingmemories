<?php

namespace ScoutingMemories\Forms\Forms;

/**
 * AccountProfileForm
 *
 * Replaces Formidable Form 22 (Edit Account Info) in my-account.php.
 */
class AccountProfileForm extends FormHandler {

    public static function registerHooks(): void {
        add_shortcode('sm_account_profile', [__CLASS__, 'render']);
        add_action('init', [__CLASS__, 'handleSubmission']);
    }

    public static function handleSubmission(): void {
        if (!isset($_POST['sm_action']) || $_POST['sm_action'] !== 'update_profile') {
            return;
        }

        if (!self::verifyNonce('sm_update_profile')) {
            wp_die(__('Security verification failed.', 'scouting-forms'));
        }

        $userId = get_current_user_id();
        if (!$userId) {
            return;
        }

        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name  = sanitize_text_field($_POST['last_name'] ?? '');
        $email      = sanitize_email($_POST['email'] ?? '');

        // Update core user data
        wp_update_user([
            'ID'         => $userId,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'user_email' => $email,
        ]);

        // Handle Avatar Upload if provided
        if (!empty($_FILES['avatar_file']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $attach_id = media_handle_upload('avatar_file', 0);
            if (!is_wp_error($attach_id)) {
                update_user_meta($userId, 'sm_custom_avatar_id', $attach_id);
            }
        }

        wp_safe_redirect(add_query_arg('profile_updated', '1', wp_get_referer() ?: home_url('/my-account/')));
        exit;
    }

    public static function render(): string {
        $userId = get_current_user_id();
        if (!$userId) {
            return '<div class="alert alert-warning">' . __('Please log in to manage your account.', 'scouting-forms') . '</div>';
        }

        $user = get_userdata($userId);
        $avatar_id = get_user_meta($userId, 'sm_custom_avatar_id', true);
        $avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : get_avatar_url($userId);

        $success = isset($_GET['profile_updated']) && $_GET['profile_updated'] === '1';

        return self::renderView('forms/account-profile', [
            'user'       => $user,
            'avatar_url' => $avatar_url,
            'success'    => $success,
        ]);
    }
}
