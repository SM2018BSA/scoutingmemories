<?php
/**
 * View: Add a Memory Form
 */
if (!defined('ABSPATH')) exit;
?>

<div class="sm-memory-form-container card shadow-sm p-4 my-4">
    <form method="post" enctype="multipart/form-data" class="needs-validation">
        <?php wp_nonce_field('sm_submit_memory', '_sm_nonce'); ?>
        <input type="hidden" name="sm_action" value="submit_memory">

        <!-- 1. Basic Post Details -->
        <h4 class="border-bottom pb-2 mb-3 text-primary">
            <i class="bi bi-file-earmark-text me-2"></i><?php esc_html_e('Memory Information', 'scouting-forms'); ?>
        </h4>

        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <label for="post_title" class="form-label font-weight-bold">
                    <?php esc_html_e('Memory Title', 'scouting-forms'); ?> <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control form-control-lg" id="post_title" name="post_title" 
                       placeholder="<?php esc_attr_e('e.g. 1964 Camp Russell Summer Camp Collection', 'scouting-forms'); ?>" required>
            </div>

            <div class="col-md-4">
                <label for="post_category" class="form-label font-weight-bold">
                    <?php esc_html_e('Category', 'scouting-forms'); ?> <span class="text-danger">*</span>
                </label>
                <select class="form-select form-select-lg" id="post_category" name="post_category" required>
                    <option value=""><?php esc_html_e('-- Select Category --', 'scouting-forms'); ?></option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat->term_id); ?>">
                            <?php echo esc_html($cat->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <label for="memory_file" class="form-label font-weight-bold">
                    <?php esc_html_e('Upload Photo or PDF Document', 'scouting-forms'); ?>
                </label>
                <input type="file" class="form-control" id="memory_file" name="memory_file" accept="image/*,application/pdf">
                <small class="text-muted"><?php esc_html_e('Accepted formats: JPEG, PNG, WEBP, GIF, PDF.', 'scouting-forms'); ?></small>
            </div>

            <div class="col-12">
                <label for="post_content" class="form-label font-weight-bold">
                    <?php esc_html_e('Story / Description', 'scouting-forms'); ?>
                </label>
                <textarea class="form-control" id="post_content" name="post_content" rows="6" 
                          placeholder="<?php esc_attr_e('Describe this historical scouting memory, its background, provenance, and significance...', 'scouting-forms'); ?>"></textarea>
            </div>
        </div>

        <!-- 2. Timeline & Indexing -->
        <h4 class="border-bottom pb-2 mb-3 mt-4 text-primary">
            <i class="bi bi-geo-alt me-2"></i><?php esc_html_e('Timeline & Archive Indexing', 'scouting-forms'); ?>
        </h4>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label for="state_id" class="form-label"><?php esc_html_e('State', 'scouting-forms'); ?></label>
                <select class="form-select sm-state-select" id="state_id" name="state_id">
                    <option value=""><?php esc_html_e('-- Select State --', 'scouting-forms'); ?></option>
                    <?php foreach ($states as $st): ?>
                        <option value="<?php echo esc_attr($st['id']); ?>" <?php selected($defaults['state'] ?? '', $st['id']); ?>>
                            <?php echo esc_html($st['name'] . ($st['code'] ? " ({$st['code']})" : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="council_id" class="form-label"><?php esc_html_e('Council', 'scouting-forms'); ?></label>
                <select class="form-select sm-council-select" id="council_id" name="council_id" data-selected="<?php echo esc_attr($defaults['council'] ?? ''); ?>">
                    <option value=""><?php esc_html_e('-- Select Council --', 'scouting-forms'); ?></option>
                    <?php foreach ($councils as $c): ?>
                        <option value="<?php echo esc_attr($c['id']); ?>" <?php selected($defaults['council'] ?? '', $c['id']); ?>>
                            <?php echo esc_html($c['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="camp_id" class="form-label"><?php esc_html_e('Camp (Optional)', 'scouting-forms'); ?></label>
                <select class="form-select sm-camp-select" id="camp_id" name="camp_id" data-selected="<?php echo esc_attr($defaults['camp'] ?? ''); ?>">
                    <option value=""><?php esc_html_e('-- Select Camp --', 'scouting-forms'); ?></option>
                    <?php foreach ($camps as $camp): ?>
                        <option value="<?php echo esc_attr($camp['id']); ?>" <?php selected($defaults['camp'] ?? '', $camp['id']); ?>>
                            <?php echo esc_html($camp['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="lodge_id" class="form-label"><?php esc_html_e('Lodge (Optional)', 'scouting-forms'); ?></label>
                <select class="form-select sm-lodge-select" id="lodge_id" name="lodge_id" data-selected="<?php echo esc_attr($defaults['lodge'] ?? ''); ?>">
                    <option value=""><?php esc_html_e('-- Select Lodge --', 'scouting-forms'); ?></option>
                    <?php foreach ($lodges as $lodge): ?>
                        <option value="<?php echo esc_attr($lodge['id']); ?>" <?php selected($defaults['lodge'] ?? '', $lodge['id']); ?>>
                            <?php echo esc_html($lodge['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="start_date" class="form-label"><?php esc_html_e('Start Date / Year', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="start_date" name="start_date" placeholder="e.g. 1964">
            </div>

            <div class="col-md-6">
                <label for="end_date" class="form-label"><?php esc_html_e('End Date / Year', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="end_date" name="end_date" placeholder="e.g. 1964">
            </div>
        </div>

        <!-- 3. Archival Metadata (Pre-filled from Defaults) -->
        <h4 class="border-bottom pb-2 mb-3 mt-4 text-primary">
            <i class="bi bi-tag me-2"></i><?php esc_html_e('Archival Provenance & Metadata', 'scouting-forms'); ?>
        </h4>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label for="publisher_of_digital" class="form-label"><?php esc_html_e('Publisher of Digital', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="publisher_of_digital" name="publisher_of_digital" 
                       value="<?php echo esc_attr($defaults['pub_digital'] ?? ''); ?>">
            </div>

            <div class="col-md-6">
                <label for="date_of_original" class="form-label"><?php esc_html_e('Date of Original', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="date_of_original" name="date_of_original" 
                       value="<?php echo esc_attr($defaults['date_original'] ?? ''); ?>">
            </div>

            <div class="col-md-6">
                <label for="identifier" class="form-label"><?php esc_html_e('Identifier', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="identifier" name="identifier" 
                       value="<?php echo esc_attr($defaults['identifier'] ?? ''); ?>">
            </div>

            <div class="col-md-6">
                <label for="meta_subject" class="form-label"><?php esc_html_e('Subject', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="meta_subject" name="meta_subject" 
                       value="<?php echo esc_attr($defaults['subject'] ?? ''); ?>">
            </div>

            <div class="col-md-6">
                <label for="meta_location" class="form-label"><?php esc_html_e('Location', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="meta_location" name="meta_location" 
                       value="<?php echo esc_attr($defaults['location'] ?? ''); ?>">
            </div>

            <div class="col-md-6">
                <label for="meta_physical_description" class="form-label"><?php esc_html_e('Physical Description', 'scouting-forms'); ?></label>
                <input type="text" class="form-control" id="meta_physical_description" name="meta_physical_description" 
                       value="<?php echo esc_attr($defaults['phy_dsc'] ?? ''); ?>">
            </div>
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn btn-primary btn-lg px-5">
                <i class="bi bi-cloud-upload me-2"></i><?php esc_html_e('Submit Memory', 'scouting-forms'); ?>
            </button>
        </div>
    </form>
</div>
