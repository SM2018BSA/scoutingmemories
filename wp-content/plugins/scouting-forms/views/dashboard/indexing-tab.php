<?php
/**
 * View: Indexing Browser Tab
 */
if (!defined('ABSPATH')) exit;
?>

<div class="sm-indexing-browser p-3">
    <?php if (!empty($updated)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong><?php esc_html_e('End Dates Updated!', 'scouting-forms'); ?></strong> 
            <?php esc_html_e('All active Councils, Camps, and Lodges have been successfully updated with the current year.', 'scouting-forms'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Sub-tabs for Indexing -->
    <ul class="nav nav-pills mb-4" id="indexing-subtabs">
        <li class="nav-item">
            <a class="nav-link <?php echo ($tab === 'councils') ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['idx_tab' => 'councils', 'idx_paged' => 1])); ?>">
                <i class="bi bi-diagram-3 me-1"></i> <?php esc_html_e('Councils', 'scouting-forms'); ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($tab === 'camps') ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['idx_tab' => 'camps', 'idx_paged' => 1])); ?>">
                <i class="bi bi-tree me-1"></i> <?php esc_html_e('Camps', 'scouting-forms'); ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($tab === 'lodges') ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['idx_tab' => 'lodges', 'idx_paged' => 1])); ?>">
                <i class="bi bi-award me-1"></i> <?php esc_html_e('Lodges', 'scouting-forms'); ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($tab === 'tools') ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['idx_tab' => 'tools'])); ?>">
                <i class="bi bi-tools me-1"></i> <?php esc_html_e('Maintenance Tools', 'scouting-forms'); ?>
            </a>
        </li>
    </ul>

    <?php if ($tab !== 'tools'): ?>
        <!-- Search bar -->
        <form method="get" class="row g-2 mb-3">
            <input type="hidden" name="idx_tab" value="<?php echo esc_attr($tab); ?>">
            <div class="col-auto flex-grow-1">
                <input type="text" name="idx_search" class="form-control form-control-sm" 
                       placeholder="<?php printf(esc_attr__('Search %s by name, number, or slug...', 'scouting-forms'), ucfirst($tab)); ?>" 
                       value="<?php echo esc_attr($search); ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm"><?php esc_html_e('Search', 'scouting-forms'); ?></button>
                <?php if ($search): ?>
                    <a href="<?php echo esc_url(remove_query_arg('idx_search')); ?>" class="btn btn-outline-secondary btn-sm"><?php esc_html_e('Clear', 'scouting-forms'); ?></a>
                <?php endif; ?>
            </div>
            <div class="col-auto ms-auto">
                <a href="<?php echo esc_url(home_url("/add-a-{$tab}/")); ?>" class="btn btn-success btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> <?php printf(esc_html__('Add %s', 'scouting-forms'), rtrim(ucfirst($tab), 's')); ?>
                </a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-striped border align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?php esc_html_e('Name', 'scouting-forms'); ?></th>
                        <?php if ($tab !== 'camps'): ?>
                            <th><?php esc_html_e('Number', 'scouting-forms'); ?></th>
                        <?php endif; ?>
                        <th><?php esc_html_e('Slug', 'scouting-forms'); ?></th>
                        <th><?php esc_html_e('Active', 'scouting-forms'); ?></th>
                        <th><?php esc_html_e('Timeline', 'scouting-forms'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $records = ($tab === 'councils') ? $councils : (($tab === 'camps') ? $camps : $lodges);
                    if (!empty($records)):
                        foreach ($records as $item):
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($item['name']); ?></strong></td>
                            <?php if ($tab !== 'camps'): ?>
                                <td><?php echo esc_html($item['number'] ?: '—'); ?></td>
                            <?php endif; ?>
                            <td><code><?php echo esc_html($item['slug']); ?></code></td>
                            <td>
                                <span class="badge bg-<?php echo ($item['active'] === 'Yes') ? 'success' : 'secondary'; ?>">
                                    <?php echo esc_html($item['active']); ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo esc_html(($item['start_date'] ?: '?') . ' - ' . ($item['end_date'] ?: 'Present')); ?>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <?php esc_html_e('No records found.', 'scouting-forms'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Next / Prev Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
                <?php if ($page > 1): ?>
                    <a href="<?php echo esc_url(add_query_arg(['idx_paged' => $page - 1])); ?>" class="btn btn-outline-secondary btn-sm">
                        &laquo; <?php esc_html_e('Previous Page', 'scouting-forms'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <span class="text-muted small"><?php printf(esc_html__('Page %d', 'scouting-forms'), $page); ?></span>
            <div>
                <?php if (count($records) >= $limit): ?>
                    <a href="<?php echo esc_url(add_query_arg(['idx_paged' => $page + 1])); ?>" class="btn btn-outline-secondary btn-sm">
                        <?php esc_html_e('Next Page', 'scouting-forms'); ?> &raquo;
                    </a>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Tools Tab -->
        <div class="card w-75 mx-auto mt-4 shadow-sm">
            <div class="card-header bg-light">
                <h5 class="card-title m-0"><i class="bi bi-clock-history me-2"></i><?php esc_html_e('Update Active End Dates', 'scouting-forms'); ?></h5>
            </div>
            <div class="card-body p-4">
                <p class="card-text">
                    <?php esc_html_e('Click below to automatically update the End Date values for all historical indexing (Councils, Camps, and Lodges) marked as "Active" with the current calendar year.', 'scouting-forms'); ?>
                </p>
                <div class="alert alert-warning small">
                    <i class="bi bi-info-circle me-1"></i>
                    <?php esc_html_e('This is the native replacement for the Formidable maintenance action.', 'scouting-forms'); ?>
                </div>
                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['sm_indexing_action' => 'update_end_dates']), 'sm_update_end_dates')); ?>" class="btn btn-primary px-4">
                    <i class="bi bi-arrow-repeat me-1"></i> <?php echo esc_html(sprintf(__('Run Update for %s', 'scouting-forms'), wp_date('Y'))); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
