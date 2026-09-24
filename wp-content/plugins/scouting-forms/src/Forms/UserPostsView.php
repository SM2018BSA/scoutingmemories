<?php

namespace ScoutingMemories\Forms\Forms;

/**
 * UserPostsView
 *
 * Replaces Formidable View 1186 (Posts View) in my-account.php.
 * Displays the current user's submitted memories with status badges and pagination.
 */
class UserPostsView extends FormHandler {

    public static function registerHooks(): void {
        add_shortcode('sm_user_posts', [__CLASS__, 'render']);
    }

    public static function render(): string {
        $userId = get_current_user_id();
        if (!$userId) {
            return '<div class="alert alert-warning">' . __('Please log in to view your submitted posts.', 'scouting-forms') . '</div>';
        }

        $paged = max(1, get_query_var('paged', 1), isset($_GET['sm_paged']) ? (int) $_GET['sm_paged'] : 1);

        $query = new \WP_Query([
            'author'         => $userId,
            'post_type'      => 'post',
            'post_status'    => ['publish', 'pending', 'draft'],
            'posts_per_page' => 12,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC'
        ]);

        return self::renderView('dashboard/posts-tab', [
            'query' => $query,
            'paged' => $paged
        ]);
    }
}
