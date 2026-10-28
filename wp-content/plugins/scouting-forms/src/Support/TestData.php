<?php

namespace ScoutingMemories\Forms\Support;

/**
 * TestData
 *
 * On a local copy, everything the plugin creates is marked as test data so it can be removed
 * in one step (Scouting Forms > Test Tools): entries get an item_key starting with "smtest-",
 * and posts, attachments and users get a `_sm_test_data` meta flag. On the live site nothing is
 * marked and cleanup refuses to run.
 */
class TestData {

    public const KEY_PREFIX = 'smtest-';
    public const META_FLAG = '_sm_test_data';

    /**
     * Entry key for a new Formidable entry: "smtest-..." locally, unchanged on live.
     */
    public static function itemKey(string $baseKey): string {
        $baseKey = sanitize_title($baseKey);
        return Environment::isLocal() ? self::KEY_PREFIX . $baseKey : $baseKey;
    }

    public static function markPost(int $postId): void {
        if (Environment::isLocal() && $postId > 0) {
            update_post_meta($postId, self::META_FLAG, 1);
        }
    }

    public static function markUser(int $userId): void {
        if (Environment::isLocal() && $userId > 0) {
            update_user_meta($userId, self::META_FLAG, 1);
        }
    }

    /**
     * What cleanup would remove, without removing anything.
     *
     * @return array{entries:int, posts:int, users:int}
     */
    public static function counts(): array {
        global $wpdb;
        return [
            'entries' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}frm_items WHERE item_key LIKE %s",
                $wpdb->esc_like(self::KEY_PREFIX) . '%'
            )),
            'posts' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                self::META_FLAG
            )),
            'users' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s",
                self::META_FLAG
            )),
        ];
    }

    /**
     * Delete all marked test data. Local copies only; never touches administrators.
     *
     * @return array{entries:int, posts:int, users:int}
     */
    public static function cleanup(): array {
        if (!Environment::isLocal()) {
            return ['entries' => 0, 'posts' => 0, 'users' => 0];
        }

        global $wpdb;
        $removed = ['entries' => 0, 'posts' => 0, 'users' => 0];

        $entryIds = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}frm_items WHERE item_key LIKE %s",
            $wpdb->esc_like(self::KEY_PREFIX) . '%'
        ));
        if ($entryIds) {
            $in = implode(',', array_map('intval', $entryIds));
            $wpdb->query("DELETE FROM {$wpdb->prefix}frm_item_metas WHERE item_id IN ({$in})");
            $removed['entries'] = (int) $wpdb->query("DELETE FROM {$wpdb->prefix}frm_items WHERE id IN ({$in})");
        }

        $postIds = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
            self::META_FLAG
        ));
        foreach ($postIds as $postId) {
            $isAttachment = get_post_type((int) $postId) === 'attachment';
            $deleted = $isAttachment ? wp_delete_attachment((int) $postId, true) : wp_delete_post((int) $postId, true);
            if ($deleted) {
                $removed['posts']++;
            }
        }

        $userIds = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
            self::META_FLAG
        ));
        if ($userIds) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            foreach ($userIds as $userId) {
                $user = get_userdata((int) $userId);
                if ($user && !in_array('administrator', (array) $user->roles, true) && wp_delete_user((int) $userId)) {
                    $removed['users']++;
                }
            }
        }

        wp_cache_flush();
        return $removed;
    }
}
