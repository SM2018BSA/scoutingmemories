<?php
/**
 * View: Account Profile Form
 */
if (!defined('ABSPATH')) exit;
?>

<div class="sm-account-profile-form p-3">
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong><?php esc_html_e('Success!', 'scouting-forms'); ?></strong> <?php esc_html_e('Your profile has been updated.', 'scouting-forms'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="needs-validation">
        <?php wp_nonce_field('sm_update_profile', '_sm_nonce'); ?>
        <input type="hidden" name="sm_action" value="update_profile">

        <div class="row g-3">
            <div class="col-md-6">
                <label for="first_name" class="form-label"><?php esc_html_e('First Name', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo esc_attr($user->first_name); ?>" required>
            </div>

            <div class="col-md-6">
                <label for="last_name" class="form-label"><?php esc_html_e('Last Name', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo esc_attr($user->last_name); ?>" required>
            </div>

            <div class="col-md-12">
                <label for="email" class="form-label"><?php esc_html_e('Email Address', 'scouting-forms'); ?></label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required>
            </div>

            <div class="col-md-12">
                <label class="form-label"><?php esc_html_e('Current Avatar', 'scouting-forms'); ?></label>
                <div class="d-flex align-items-center gap-3 mb-2">
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="Avatar" class="rounded-circle border" style="width: 80px; height: 80px; object-fit: cover;">
                </div>
                <label for="avatar_file" class="form-label small text-muted"><?php esc_html_e('Upload New Avatar Image (Optional)', 'scouting-forms'); ?></label>
                <input type="file" class="form-control" id="avatar_file" name="avatar_file" accept="image/*">
            </div>

            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary px-4"><?php esc_html_e('Save Account Details', 'scouting-forms'); ?></button>
            </div>
        </div>
    </form>
</div>
