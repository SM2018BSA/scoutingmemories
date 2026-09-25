<?php
/**
 * Test Tools (local development copies only).
 *
 * @var array{entries:int, posts:int, users:int} $counts
 * @var array<int, array<string, mixed>> $mailLog
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Scouting Forms: Test Tools', 'scouting-forms'); ?></h1>
    <p><?php esc_html_e('This page only exists on a local copy of the site. Nothing here affects the live website.', 'scouting-forms'); ?></p>

    <?php if (isset($_GET['removed_entries'])) : ?>
        <div class="notice notice-success is-dismissible"><p>
            <?php echo esc_html(sprintf(
                __('Removed %1$d test entries, %2$d test posts/files and %3$d test users.', 'scouting-forms'),
                (int) $_GET['removed_entries'],
                (int) ($_GET['removed_posts'] ?? 0),
                (int) ($_GET['removed_users'] ?? 0)
            )); ?>
        </p></div>
    <?php endif; ?>
    <?php if (isset($_GET['mail_cleared'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Test mail log cleared.', 'scouting-forms'); ?></p></div>
    <?php endif; ?>

    <h2><?php esc_html_e('Test data', 'scouting-forms'); ?></h2>
    <p><?php esc_html_e('On this local copy, everything Scouting Forms creates is marked as test data: entries get a key starting with "smtest-", and posts, uploaded files and users get a hidden test flag.', 'scouting-forms'); ?></p>
    <table class="widefat striped" style="max-width: 480px;">
        <tbody>
            <tr><td><?php esc_html_e('Test entries', 'scouting-forms'); ?></td><td><strong><?php echo (int) $counts['entries']; ?></strong></td></tr>
            <tr><td><?php esc_html_e('Test posts and uploaded files', 'scouting-forms'); ?></td><td><strong><?php echo (int) $counts['posts']; ?></strong></td></tr>
            <tr><td><?php esc_html_e('Test users (administrators are never removed)', 'scouting-forms'); ?></td><td><strong><?php echo (int) $counts['users']; ?></strong></td></tr>
        </tbody>
    </table>
    <form method="post" style="margin-top: 12px;">
        <?php wp_nonce_field('sm_test_tools'); ?>
        <input type="hidden" name="sm_admin_action" value="cleanup_test_data">
        <?php submit_button(__('Delete all test data', 'scouting-forms'), 'delete', 'submit', false, array_sum($counts) ? [] : ['disabled' => 'disabled']); ?>
    </form>

    <h2 style="margin-top: 32px;"><?php esc_html_e('Intercepted email', 'scouting-forms'); ?></h2>
    <p><?php esc_html_e('On this local copy, email from Scouting Forms is never sent. The newest 50 messages are kept here instead.', 'scouting-forms'); ?></p>
    <?php if (!$mailLog) : ?>
        <p><em><?php esc_html_e('No email has been intercepted yet.', 'scouting-forms'); ?></em></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead><tr>
                <th style="width: 150px;"><?php esc_html_e('Time', 'scouting-forms'); ?></th>
                <th><?php esc_html_e('To', 'scouting-forms'); ?></th>
                <th><?php esc_html_e('Subject', 'scouting-forms'); ?></th>
                <th><?php esc_html_e('Message', 'scouting-forms'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($mailLog as $mail) : ?>
                <tr>
                    <td><?php echo esc_html($mail['time']); ?></td>
                    <td><?php echo esc_html($mail['to']); ?></td>
                    <td><?php echo esc_html($mail['subject']); ?></td>
                    <td>
                        <details>
                            <summary><?php esc_html_e('Show', 'scouting-forms'); ?></summary>
                            <?php if (!empty($mail['headers'])) : ?>
                                <pre style="white-space: pre-wrap; color: #646970;"><?php echo esc_html($mail['headers']); ?></pre>
                            <?php endif; ?>
                            <pre style="white-space: pre-wrap;"><?php echo esc_html(wp_strip_all_tags($mail['body'])); ?></pre>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" style="margin-top: 12px;">
            <?php wp_nonce_field('sm_test_tools'); ?>
            <input type="hidden" name="sm_admin_action" value="clear_mail_log">
            <?php submit_button(__('Clear mail log', 'scouting-forms'), 'secondary', 'submit', false); ?>
        </form>
    <?php endif; ?>
</div>
