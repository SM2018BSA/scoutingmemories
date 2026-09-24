<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Historical Camps Archive', 'scouting-forms'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=scouting-archives')); ?>" class="page-title-action">📖 <?php esc_html_e('User Guide & Walkthrough', 'scouting-forms'); ?></a>
    <a href="<?php echo esc_url(home_url('/add-a-camp/')); ?>" class="page-title-action" target="_blank"><?php esc_html_e('Add New Camp', 'scouting-forms'); ?></a>
    <hr class="wp-header-end">

    <form method="get" style="margin: 15px 0;">
        <input type="hidden" name="page" value="scouting-archives-camps">
        <p class="search-box">
            <label class="screen-reader-text" for="post-search-input"><?php esc_html_e('Search Camps', 'scouting-forms'); ?>:</label>
            <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search); ?>">
            <input type="submit" id="search-submit" class="button" value="<?php esc_attr_e('Search Camps', 'scouting-forms'); ?>">
        </p>
    </form>

    <table class="wp-list-table widefat fixed striped table-view-list">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-primary"><?php esc_html_e('Camp Name', 'scouting-forms'); ?></th>
                <th scope="col" class="manage-column" style="width: 250px;"><?php esc_html_e('Slug', 'scouting-forms'); ?></th>
                <th scope="col" class="manage-column" style="width: 100px;"><?php esc_html_e('Active', 'scouting-forms'); ?></th>
                <th scope="col" class="manage-column" style="width: 150px;"><?php esc_html_e('Dates', 'scouting-forms'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($camps)): foreach ($camps as $camp): ?>
                <tr>
                    <td class="column-primary">
                        <strong><?php echo esc_html($camp['name']); ?></strong>
                        <button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e('Show more details', 'scouting-forms'); ?></span></button>
                    </td>
                    <td><code><?php echo esc_html($camp['slug']); ?></code></td>
                    <td>
                        <span class="badge" style="display:inline-block; padding:3px 8px; border-radius:3px; font-weight:600; background: <?php echo ($camp['active'] === 'Yes') ? '#d1e7dd; color:#0f5132' : '#f8f9fa; color:#6c757d'; ?>">
                            <?php echo esc_html($camp['active']); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html(($camp['start_date'] ?: '?') . ' – ' . ($camp['end_date'] ?: 'Present')); ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4"><?php esc_html_e('No camps found.', 'scouting-forms'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="tablenav bottom" style="margin-top: 10px;">
        <div class="tablenav-pages">
            <?php if ($page > 1): ?>
                <a class="prev-page button" href="<?php echo esc_url(add_query_arg(['paged' => $page - 1])); ?>">&lsaquo; <?php esc_html_e('Previous', 'scouting-forms'); ?></a>
            <?php endif; ?>
            <span class="paging-input" style="margin: 0 10px;"><?php printf(esc_html__('Page %d', 'scouting-forms'), $page); ?></span>
            <?php if (count($camps) >= $limit): ?>
                <a class="next-page button" href="<?php echo esc_url(add_query_arg(['paged' => $page + 1])); ?>"><?php esc_html_e('Next', 'scouting-forms'); ?> &rsaquo;</a>
            <?php endif; ?>
        </div>
    </div>
</div>
