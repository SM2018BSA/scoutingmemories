<?php
/**
 * View: User Defaults Form
 */
if (!defined('ABSPATH')) exit;
?>

<div class="sm-user-defaults-form p-3">
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong><?php esc_html_e('Saved!', 'scouting-forms'); ?></strong> <?php esc_html_e('Your contributor defaults have been updated.', 'scouting-forms'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <p class="text-muted">
        <?php esc_html_e('Set your default archival indexing and metadata values. These values will pre-populate automatically whenever you submit a new memory.', 'scouting-forms'); ?>
    </p>

    <form method="post" class="needs-validation">
        <?php wp_nonce_field('sm_update_defaults', '_sm_nonce'); ?>
        <input type="hidden" name="sm_action" value="update_defaults">

        <h5 class="border-bottom pb-2 mb-3 mt-4 text-primary"><?php esc_html_e('Default Historical Indexing', 'scouting-forms'); ?></h5>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="default_state" class="form-label"><?php esc_html_e('Default State', 'scouting-forms'); ?></label>
                <select class="form-select sm-state-select" id="default_state" name="default_state">
                    <option value=""><?php esc_html_e('-- Select State --', 'scouting-forms'); ?></option>
                    <?php foreach ($states as $st): ?>
                        <option value="<?php echo esc_attr($st['id']); ?>" <?php selected($defaults['state'] ?? '', $st['id']); ?>>
                            <?php echo esc_html($st['name'] . ($st['code'] ? " ({$st['code']})" : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="default_council" class="form-label"><?php esc_html_e('Default Council', 'scouting-forms'); ?></label>
                <select class="form-select sm-council-select" id="default_council" name="default_council" data-selected="<?php echo esc_attr($defaults['council'] ?? ''); ?>">
                    <option value=""><?php esc_html_e('-- Select Council --', 'scouting-forms'); ?></option>
                    <?php foreach ($councils as $c): ?>
                        <option value="<?php echo esc_attr($c['id']); ?>" <?php selected($defaults['council'] ?? '', $c['id']); ?>>
                            <?php echo esc_html($c['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="default_camp" class="form-label"><?php esc_html_e('Default Camp', 'scouting-forms'); ?></label>
                <select class="form-select sm-camp-select" id="default_camp" name="default_camp" data-selected="<?php echo esc_attr($defaults['camp'] ?? ''); ?>">
                    <option value=""><?php esc_html_e('-- Select Camp (Optional) --', 'scouting-forms'); ?></option>
                    <?php foreach ($camps as $camp): ?>
                        <option value="<?php echo esc_attr($camp['id']); ?>" <?php selected($defaults['camp'] ?? '', $camp['id']); ?>>
                            <?php echo esc_html($camp['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="default_lodge" class="form-label"><?php esc_html_e('Default Lodge', 'scouting-forms'); ?></label>
                <select class="form-select sm-lodge-select" id="default_lodge" name="default_lodge" data-selected="<?php echo esc_attr($defaults['lodge'] ?? ''); ?>">
                    <option value=""><?php esc_html_e('-- Select Lodge (Optional) --', 'scouting-forms'); ?></option>
                    <?php foreach ($lodges as $lodge): ?>
                        <option value="<?php echo esc_attr($lodge['id']); ?>" <?php selected($defaults['lodge'] ?? '', $lodge['id']); ?>>
                            <?php echo esc_html($lodge['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <h5 class="border-bottom pb-2 mb-3 mt-4 text-primary"><?php esc_html_e('Default Metadata & Provenance', 'scouting-forms'); ?></h5>

        <div class="row g-3">
            <div class="col-md-6">
                <label for="default_author" class="form-label"><?php esc_html_e('Author', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="default_author" name="default_author" value="<?php echo esc_attr($defaults['author'] ?? ''); ?>">
            </div>

            <div class="col-md-6">
                <label for="default_photographer" class="form-label"><?php esc_html_e('Photographer', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="default_photographer" name="default_photographer" value="<?php echo esc_attr($defaults['photographer'] ?? ''); ?>">
            </div>

            <div class="col-md-6">
                <label for="default_contributors" class="form-label"><?php esc_html_e('Contributors', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="default_contributors" name="default_contributors" value="<?php echo esc_attr($defaults['contributors'] ?? ''); ?>">
            </div>

            <div class="col-md-6">
                <label for="default_pub_digital" class="form-label"><?php esc_html_e('Publisher of Digital', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="default_pub_digital" name="default_pub_digital" value="<?php echo esc_attr($defaults['pub_digital'] ?? ''); ?>">
            </div>

            <div class="col-md-6">
                <label for="default_subject" class="form-label"><?php esc_html_e('Subject', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="default_subject" name="default_subject" value="<?php echo esc_attr($defaults['subject'] ?? ''); ?>">
            </div>

            <div class="col-md-6">
                <label for="default_location" class="form-label"><?php esc_html_e('Location', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="default_location" name="default_location" value="<?php echo esc_attr($defaults['location'] ?? ''); ?>">
            </div>

            <div class="col-md-12">
                <label for="default_phy_dsc" class="form-label"><?php esc_html_e('Physical Description', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="default_phy_dsc" name="default_phy_dsc" value="<?php echo esc_attr($defaults['phy_dsc'] ?? ''); ?>">
            </div>

            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary px-4"><?php esc_html_e('Save Defaults', 'scouting-forms'); ?></button>
            </div>
        </div>
    </form>
</div>
