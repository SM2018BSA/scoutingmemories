<?php
/**
 * View: User Posts Tab
 */
if (!defined('ABSPATH')) exit;
?>

<div class="sm-user-posts-container p-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="m-0"><?php esc_html_e('My Submitted Memories', 'scouting-forms'); ?></h4>
        <a href="<?php echo esc_url(home_url('/add-a-post/')); ?>" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i> <?php esc_html_e('Add New Memory', 'scouting-forms'); ?>
        </a>
    </div>

    <?php if ($query->have_posts()): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle border">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;"><?php esc_html_e('Image', 'scouting-forms'); ?></th>
                        <th><?php esc_html_e('Title', 'scouting-forms'); ?></th>
                        <th><?php esc_html_e('Status', 'scouting-forms'); ?></th>
                        <th><?php esc_html_e('Indexing', 'scouting-forms'); ?></th>
                        <th><?php esc_html_e('Date', 'scouting-forms'); ?></th>
                        <th class="text-end"><?php esc_html_e('Actions', 'scouting-forms'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($query->have_posts()): $query->the_post(); 
                        $status = get_post_status();
                        $status_badge = 'secondary';
                        $status_label = ucfirst($status);
                        if ($status === 'publish') {
                            $status_badge = 'success';
                            $status_label = __('Published', 'scouting-forms');
                        } elseif ($status === 'pending') {
                            $status_badge = 'warning text-dark';
                            $status_label = __('Pending Review', 'scouting-forms');
                        } elseif ($status === 'draft') {
                            $status_badge = 'secondary';
                            $status_label = __('Draft', 'scouting-forms');
                        }

                        $councils = wp_get_post_terms(get_the_ID(), 'council', ['fields' => 'names']);
                        $camps    = wp_get_post_terms(get_the_ID(), 'camp', ['fields' => 'names']);
                    ?>
                        <tr>
                            <td>
                                <?php if (has_post_thumbnail()): ?>
                                    <?php the_post_thumbnail([50, 50], ['class' => 'rounded border']); ?>
                                <?php else: ?>
                                    <div class="bg-light rounded border text-muted d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                        <i class="bi bi-card-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><a href="<?php the_permalink(); ?>" class="text-decoration-none"><?php the_title(); ?></a></strong>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo esc_attr($status_badge); ?>"><?php echo esc_html($status_label); ?></span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php 
                                    $tags = array_merge((array)$councils, (array)$camps);
                                    echo esc_html(!empty($tags) ? implode(', ', array_slice($tags, 0, 2)) : '—'); 
                                    ?>
                                </small>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo get_the_date('M j, Y'); ?></small>
                            </td>
                            <td class="text-end">
                                <a href="<?php the_permalink(); ?>" class="btn btn-outline-primary btn-sm" target="_blank">
                                    <?php esc_html_e('View', 'scouting-forms'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; wp_reset_postdata(); ?>
                </tbody>
            </table>
        </div>

        <?php
        // Pagination
        $total_pages = $query->max_num_pages;
        if ($total_pages > 1): ?>
            <nav aria-label="Posts pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($paged == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo esc_url(add_query_arg('sm_paged', $i)); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-info">
            <?php esc_html_e('You have not submitted any memories yet.', 'scouting-forms'); ?>
            <a href="<?php echo esc_url(home_url('/add-a-post/')); ?>" class="alert-link"><?php esc_html_e('Click here to share a memory.', 'scouting-forms'); ?></a>
        </div>
    <?php endif; ?>
</div>
