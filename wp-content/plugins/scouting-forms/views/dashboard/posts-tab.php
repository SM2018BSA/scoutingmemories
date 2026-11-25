<?php
/**
 * View: User Posts Tab
 *
 * Styled with Tailwind utility classes only (Ui\ThemeClasses holds the shared lists).
 */
if (!defined('ABSPATH')) exit;

use ScoutingMemories\Forms\Ui\ThemeClasses;

// Post status => [badge colour, label]
$statuses = [
    'publish' => ['success', __('Published', 'scouting-forms')],
    'pending' => ['warning', __('Pending Review', 'scouting-forms')],
    'draft'   => ['secondary', __('Draft', 'scouting-forms')],
];
?>

<div class="sm-user-posts-container <?php echo esc_attr(ThemeClasses::PANEL); ?>">
    <div class="mb-6 <?php echo esc_attr(ThemeClasses::BAR); ?>">
        <h4 class="m-0 font-slab text-2xl leading-tight font-medium"><?php esc_html_e('My Submitted Memories', 'scouting-forms'); ?></h4>
        <a href="<?php echo esc_url(home_url('/add-a-post/')); ?>" class="<?php echo esc_attr(ThemeClasses::button('success', 'sm')); ?>">
            <?php esc_html_e('Add New Memory', 'scouting-forms'); ?>
        </a>
    </div>

    <?php if ($query->have_posts()): ?>
        <div class="sm-scroll-x <?php echo esc_attr(ThemeClasses::TABLE_SCROLL); ?>">
            <table class="<?php echo esc_attr(ThemeClasses::classes(ThemeClasses::TABLE, ThemeClasses::TABLE_BOX)); ?>">
                <thead class="<?php echo esc_attr(ThemeClasses::TABLE_HEAD); ?>">
                    <tr>
                        <th class="w-[70px] <?php echo esc_attr(ThemeClasses::TH); ?>"><?php esc_html_e('Image', 'scouting-forms'); ?></th>
                        <th class="<?php echo esc_attr(ThemeClasses::TH); ?>"><?php esc_html_e('Title', 'scouting-forms'); ?></th>
                        <th class="<?php echo esc_attr(ThemeClasses::TH); ?>"><?php esc_html_e('Status', 'scouting-forms'); ?></th>
                        <th class="<?php echo esc_attr(ThemeClasses::TH); ?>"><?php esc_html_e('Indexing', 'scouting-forms'); ?></th>
                        <th class="<?php echo esc_attr(ThemeClasses::TH); ?>"><?php esc_html_e('Date', 'scouting-forms'); ?></th>
                        <th class="border-b border-line p-2 text-right font-bold"><?php esc_html_e('Actions', 'scouting-forms'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($query->have_posts()): $query->the_post();
                        $status = get_post_status();
                        [$status_badge, $status_label] = $statuses[$status] ?? ['secondary', ucfirst((string) $status)];

                        $councils = wp_get_post_terms(get_the_ID(), 'council', ['fields' => 'names']);
                        $camps    = wp_get_post_terms(get_the_ID(), 'camp', ['fields' => 'names']);
                    ?>
                        <tr class="<?php echo esc_attr(ThemeClasses::TABLE_ROW); ?>">
                            <td class="align-middle <?php echo esc_attr(ThemeClasses::TD); ?>">
                                <?php if (has_post_thumbnail()): ?>
                                    <?php the_post_thumbnail([50, 50], ['class' => ThemeClasses::THUMB_SMALL]); ?>
                                <?php else: ?>
                                    <div class="<?php echo esc_attr(ThemeClasses::THUMB_EMPTY); ?>" aria-hidden="true"></div>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle <?php echo esc_attr(ThemeClasses::TD); ?>">
                                <strong><a href="<?php the_permalink(); ?>" class="text-blue no-underline hover:text-blue-link-hover"><?php the_title(); ?></a></strong>
                            </td>
                            <td class="align-middle <?php echo esc_attr(ThemeClasses::TD); ?>">
                                <span class="<?php echo esc_attr(ThemeClasses::badge($status_badge)); ?>"><?php echo esc_html($status_label); ?></span>
                            </td>
                            <td class="align-middle <?php echo esc_attr(ThemeClasses::TD); ?>">
                                <small class="<?php echo esc_attr(ThemeClasses::MUTED_SMALL); ?>">
                                    <?php
                                    $tags = array_merge((array)$councils, (array)$camps);
                                    echo esc_html(!empty($tags) ? implode(', ', array_slice($tags, 0, 2)) : '—');
                                    ?>
                                </small>
                            </td>
                            <td class="align-middle <?php echo esc_attr(ThemeClasses::TD); ?>">
                                <small class="<?php echo esc_attr(ThemeClasses::MUTED_SMALL); ?>"><?php echo get_the_date('M j, Y'); ?></small>
                            </td>
                            <td class="text-right align-middle <?php echo esc_attr(ThemeClasses::TD); ?>">
                                <a href="<?php the_permalink(); ?>" class="<?php echo esc_attr(ThemeClasses::button('outline-primary', 'sm')); ?>" target="_blank">
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
            <nav aria-label="Posts pagination" class="mt-6">
                <ul class="justify-center <?php echo esc_attr(ThemeClasses::PAGING); ?>">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li<?php echo ($paged == $i) ? ' aria-current="page"' : ''; ?>>
                            <a class="<?php echo esc_attr(($paged == $i) ? ThemeClasses::PAGE_LINK_HERE : ThemeClasses::PAGE_LINK); ?>" href="<?php echo esc_url(add_query_arg('sm_paged', $i)); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

    <?php else: ?>
        <div class="<?php echo esc_attr(ThemeClasses::alert('info')); ?>">
            <?php esc_html_e('You have not submitted any memories yet.', 'scouting-forms'); ?>
            <a href="<?php echo esc_url(home_url('/add-a-post/')); ?>" class="<?php echo esc_attr(ThemeClasses::ALERT_LINK); ?>"><?php esc_html_e('Click here to share a memory.', 'scouting-forms'); ?></a>
        </div>
    <?php endif; ?>
</div>
