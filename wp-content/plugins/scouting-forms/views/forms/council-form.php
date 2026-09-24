<?php
/**
 * View: Add Council Form
 */
if (!defined('ABSPATH')) exit;
?>

<div class="sm-add-council-container card shadow-sm p-4 my-4">
    <?php if (!empty($added)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong><?php esc_html_e('Council Added!', 'scouting-forms'); ?></strong> <?php esc_html_e('The council has been saved to the historical archive.', 'scouting-forms'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="post" class="needs-validation">
        <?php wp_nonce_field('sm_add_council', '_sm_nonce'); ?>
        <input type="hidden" name="sm_action" value="add_council">

        <div class="row g-3">
            <div class="col-md-8">
                <label for="council_name" class="form-label font-weight-bold"><?php esc_html_e('Council Name', 'scouting-forms'); ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="council_name" name="council_name" placeholder="e.g. General Herkimer Council" required>
            </div>

            <div class="col-md-4">
                <label for="council_number" class="form-label font-weight-bold"><?php esc_html_e('Council Number', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="council_number" name="council_number" placeholder="e.g. 400">
            </div>

            <div class="col-md-6">
                <label for="state_id" class="form-label font-weight-bold"><?php esc_html_e('State', 'scouting-forms'); ?> <span class="text-danger">*</span></label>
                <select class="form-select" id="state_id" name="state_id" required>
                    <option value=""><?php esc_html_e('-- Select State --', 'scouting-forms'); ?></option>
                    <?php foreach ($states as $st): ?>
                        <option value="<?php echo esc_attr($st['id']); ?>"><?php echo esc_html($st['name'] . ($st['code'] ? " ({$st['code']})" : '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="start_date" class="form-label"><?php esc_html_e('Start Year', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="start_date" name="start_date" placeholder="e.g. 1934">
            </div>

            <div class="col-md-3">
                <label for="end_date" class="form-label"><?php esc_html_e('End Year', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="end_date" name="end_date" placeholder="e.g. 2001">
            </div>

            <div class="col-md-12">
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="active" name="active" value="Yes" checked>
                    <label class="form-check-label" for="active"><?php esc_html_e('Currently Active Council', 'scouting-forms'); ?></label>
                </div>
            </div>

            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary px-4"><?php esc_html_e('Save Council', 'scouting-forms'); ?></button>
            </div>
        </div>
    </form>
</div>
