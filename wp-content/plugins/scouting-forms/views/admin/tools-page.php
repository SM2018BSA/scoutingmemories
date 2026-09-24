<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Scouting Archive Maintenance Tools', 'scouting-forms'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=scouting-archives')); ?>" class="page-title-action">📖 <?php esc_html_e('User Guide & Walkthrough', 'scouting-forms'); ?></a>
    <hr class="wp-header-end">

    <?php if (!empty($updated)): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong><?php esc_html_e('Batch Update Completed Successfully!', 'scouting-forms'); ?></strong><br>
                <?php printf(esc_html__('Updated %d active Councils, %d active Camps, and %d active Lodges with end-date %s.', 'scouting-forms'), $councils_count, $camps_count, $lodges_count, esc_html($_GET['target_year'] ?? date('Y'))); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 700px; margin-top: 20px; padding: 20px;">
        <h2><span class="dashicons dashicons-calendar-alt" style="margin-right: 8px;"></span><?php esc_html_e('Batch Update Active End-Dates', 'scouting-forms'); ?></h2>
        <p>
            <?php esc_html_e('All Councils, Camps, and Lodges marked with Active status = "Yes" indicate ongoing entities. Use this tool to advance their End Date to the current year.', 'scouting-forms'); ?>
        </p>

        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('sm_batch_update_dates_nonce'); ?>
            <input type="hidden" name="sm_admin_action" value="batch_update_dates">

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="target_year"><?php esc_html_e('Set Active End-Date Year', 'scouting-forms'); ?></label></th>
                    <td>
                        <input type="text" id="target_year" name="target_year" value="<?php echo esc_attr(date('Y')); ?>" class="regular-text" style="width: 100px;">
                        <p class="description"><?php esc_html_e('Enter the year to set for all active indexing records.', 'scouting-forms'); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php esc_attr_e('Run Batch End-Date Update', 'scouting-forms'); ?>">
            </p>
        </form>
    </div>
</div>
