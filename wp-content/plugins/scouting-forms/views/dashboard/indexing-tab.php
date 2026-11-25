<?php
/**
 * View: Indexing Browser Tab
 *
 * Styled with Tailwind utility classes only (Ui\ThemeClasses holds the shared lists).
 */
if (!defined('ABSPATH')) exit;

use ScoutingMemories\Forms\Ui\ThemeClasses;

// Sub-tab => [label, the URL parameters that open it]
$subtabs = [
    'councils' => [__('Councils', 'scouting-forms'), ['idx_tab' => 'councils', 'idx_paged' => 1]],
    'camps'    => [__('Camps', 'scouting-forms'), ['idx_tab' => 'camps', 'idx_paged' => 1]],
    'lodges'   => [__('Lodges', 'scouting-forms'), ['idx_tab' => 'lodges', 'idx_paged' => 1]],
    'tools'    => [__('Maintenance Tools', 'scouting-forms'), ['idx_tab' => 'tools']],
];
?>

<div class="sm-indexing-browser <?php echo esc_attr(ThemeClasses::PANEL); ?>">
    <?php if (!empty($updated)): ?>
        <?php
        echo ThemeClasses::closableAlert(
            'success',
            '<strong>' . esc_html__('End Dates Updated!', 'scouting-forms') . '</strong> '
            . esc_html__('All active Councils, Camps, and Lodges have been successfully updated with the current year.', 'scouting-forms'),
            'alert'
        );
        ?>
    <?php endif; ?>

    <!-- Sub-tabs for Indexing -->
    <ul class="<?php echo esc_attr(ThemeClasses::PILLS); ?>" id="indexing-subtabs">
        <?php foreach ($subtabs as $name => [$label, $args]): ?>
            <li>
                <a class="<?php echo esc_attr(($tab === $name) ? ThemeClasses::PILL_HERE : ThemeClasses::PILL); ?>"<?php echo ($tab === $name) ? ' aria-current="page"' : ''; ?> href="<?php echo esc_url(add_query_arg($args)); ?>">
                    <?php echo esc_html($label); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($tab !== 'tools'): ?>
        <!-- Search bar -->
        <form method="get" class="mb-4 flex flex-wrap items-start gap-2">
            <input type="hidden" name="idx_tab" value="<?php echo esc_attr($tab); ?>">
            <div class="min-w-0 grow">
                <input type="text" name="idx_search" class="<?php echo esc_attr(ThemeClasses::FIELD_SM); ?>"
                       placeholder="<?php printf(esc_attr__('Search %s by name, number, or slug...', 'scouting-forms'), ucfirst($tab)); ?>"
                       value="<?php echo esc_attr($search); ?>">
            </div>
            <div>
                <button type="submit" class="<?php echo esc_attr(ThemeClasses::button('primary', 'sm')); ?>"><?php esc_html_e('Search', 'scouting-forms'); ?></button>
                <?php if ($search): ?>
                    <a href="<?php echo esc_url(remove_query_arg('idx_search')); ?>" class="<?php echo esc_attr(ThemeClasses::button('outline', 'sm')); ?>"><?php esc_html_e('Clear', 'scouting-forms'); ?></a>
                <?php endif; ?>
            </div>
            <div class="ml-auto">
                <a href="<?php echo esc_url(home_url("/add-a-{$tab}/")); ?>" class="<?php echo esc_attr(ThemeClasses::button('success', 'sm')); ?>">
                    <?php printf(esc_html__('Add %s', 'scouting-forms'), rtrim(ucfirst($tab), 's')); ?>
                </a>
            </div>
        </form>

        <div class="sm-scroll-x <?php echo esc_attr(ThemeClasses::TABLE_SCROLL); ?>">
            <table class="<?php echo esc_attr(ThemeClasses::classes(ThemeClasses::TABLE, ThemeClasses::TABLE_BOX)); ?>">
                <thead class="<?php echo esc_attr(ThemeClasses::TABLE_HEAD); ?>">
                    <tr>
                        <th class="<?php echo esc_attr(ThemeClasses::TH); ?>"><?php esc_html_e('Name', 'scouting-forms'); ?></th>
                        <?php if ($tab !== 'camps'): ?>
                            <th class="<?php echo esc_attr(ThemeClasses::TH); ?>"><?php esc_html_e('Number', 'scouting-forms'); ?></th>
                        <?php endif; ?>
                        <th class="<?php echo esc_attr(ThemeClasses::TH); ?>"><?php esc_html_e('Slug', 'scouting-forms'); ?></th>
                        <th class="<?php echo esc_attr(ThemeClasses::TH); ?>"><?php esc_html_e('Active', 'scouting-forms'); ?></th>
                        <th class="<?php echo esc_attr(ThemeClasses::TH); ?>"><?php esc_html_e('Timeline', 'scouting-forms'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $records = ($tab === 'councils') ? $councils : (($tab === 'camps') ? $camps : $lodges);
                    if (!empty($records)):
                        foreach ($records as $item):
                    ?>
                        <tr class="<?php echo esc_attr(ThemeClasses::TABLE_ROW_ALT); ?>">
                            <td class="align-middle <?php echo esc_attr(ThemeClasses::TD); ?>"><strong><?php echo esc_html($item['name']); ?></strong></td>
                            <?php if ($tab !== 'camps'): ?>
                                <td class="align-middle <?php echo esc_attr(ThemeClasses::TD); ?>"><?php echo esc_html($item['number'] ?: '—'); ?></td>
                            <?php endif; ?>
                            <td class="align-middle <?php echo esc_attr(ThemeClasses::TD); ?>"><code class="font-mono text-sm text-pink-600"><?php echo esc_html($item['slug']); ?></code></td>
                            <td class="align-middle <?php echo esc_attr(ThemeClasses::TD); ?>">
                                <span class="<?php echo esc_attr(ThemeClasses::badge(($item['active'] === 'Yes') ? 'success' : 'secondary')); ?>">
                                    <?php echo esc_html($item['active']); ?>
                                </span>
                            </td>
                            <td class="align-middle <?php echo esc_attr(ThemeClasses::TD); ?>">
                                <small class="<?php echo esc_attr(ThemeClasses::MUTED_SMALL); ?>">
                                    <?php echo esc_html(($item['start_date'] ?: '?') . ' - ' . ($item['end_date'] ?: 'Present')); ?>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="5" class="border-b border-line px-2 py-6 text-center text-grey">
                                <?php esc_html_e('No records found.', 'scouting-forms'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Next / Prev Pagination -->
        <div class="mt-4 <?php echo esc_attr(ThemeClasses::BAR); ?>">
            <div>
                <?php if ($page > 1): ?>
                    <a href="<?php echo esc_url(add_query_arg(['idx_paged' => $page - 1])); ?>" class="<?php echo esc_attr(ThemeClasses::button('outline', 'sm')); ?>">
                        &laquo; <?php esc_html_e('Previous Page', 'scouting-forms'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <span class="<?php echo esc_attr(ThemeClasses::MUTED_SMALL); ?>"><?php printf(esc_html__('Page %d', 'scouting-forms'), $page); ?></span>
            <div>
                <?php if (count($records) >= $limit): ?>
                    <a href="<?php echo esc_url(add_query_arg(['idx_paged' => $page + 1])); ?>" class="<?php echo esc_attr(ThemeClasses::button('outline', 'sm')); ?>">
                        <?php esc_html_e('Next Page', 'scouting-forms'); ?> &raquo;
                    </a>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Tools Tab -->
        <div class="mx-auto mt-6 w-3/4 shadow-sm <?php echo esc_attr(ThemeClasses::CARD); ?>">
            <div class="<?php echo esc_attr(ThemeClasses::CARD_HEADER); ?>">
                <h5 class="m-0 font-slab text-xl leading-tight font-medium"><?php esc_html_e('Update Active End Dates', 'scouting-forms'); ?></h5>
            </div>
            <div class="<?php echo esc_attr(ThemeClasses::CARD_BODY); ?>">
                <p class="mb-4">
                    <?php esc_html_e('Click below to automatically update the End Date values for all historical indexing (Councils, Camps, and Lodges) marked as "Active" with the current calendar year.', 'scouting-forms'); ?>
                </p>
                <div class="<?php echo esc_attr(ThemeClasses::alert('warning', ThemeClasses::ALERT_SMALL)); ?>">
                    <?php esc_html_e('This is the native replacement for the Formidable maintenance action.', 'scouting-forms'); ?>
                </div>
                <?php if (!empty($canUpdate)): ?>
                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['sm_indexing_action' => 'update_end_dates']), 'sm_update_end_dates')); ?>" class="<?php echo esc_attr(ThemeClasses::button('primary')); ?>">
                        <?php echo esc_html(sprintf(__('Run Update for %s', 'scouting-forms'), wp_date('Y'))); ?>
                    </a>
                <?php else: ?>
                    <p class="sm-end-dates-admin-only mb-0 <?php echo esc_attr(ThemeClasses::MUTED_SMALL); ?>"><?php esc_html_e('Only an administrator can run this tool.', 'scouting-forms'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
