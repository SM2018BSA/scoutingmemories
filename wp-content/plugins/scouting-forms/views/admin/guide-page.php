<?php
if (!defined('ABSPATH')) exit;

$theme_obj = wp_get_theme();
$current_theme_name = $theme_obj->get('Name');
$current_theme_slug = get_stylesheet();
$is_2026_active = ($current_theme_slug === 'Scouting-Memories-2026');
?>

<div class="wrap sm-guide-wrap">

    <!-- Hero Header -->
    <div class="sm-guide-hero">
        <div class="sm-guide-hero-top">
            <div>
                <h1>
                    <span class="dashicons dashicons-archive"></span>
                    <?php esc_html_e('Scouting Forms & Archives — Administration Guide', 'scouting-forms'); ?>
                </h1>
                <p>
                    <?php esc_html_e('Your independent, high-performance form and archive management system built on modern Roots.io architecture. Decoupled from Formidable Forms for faster queries, zero monthly plugin fees, and total front-end & back-end control.', 'scouting-forms'); ?>
                </p>

                <div class="sm-hero-badges">
                    <span class="sm-pill <?php echo $is_2026_active ? 'sm-pill-success' : 'sm-pill-info'; ?>">
                        <span class="dashicons dashicons-art" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php printf(esc_html__('Active Theme: %s', 'scouting-forms'), esc_html($current_theme_name)); ?>
                    </span>
                    <span class="sm-pill sm-pill-success">
                        <span class="dashicons dashicons-yes-alt" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php esc_html_e('Roots.io Engine: Active', 'scouting-forms'); ?>
                    </span>
                    <span class="sm-pill sm-pill-warning">
                        <span class="dashicons dashicons-shield" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php esc_html_e('Zero Data Loss Mode', 'scouting-forms'); ?>
                    </span>
                </div>
            </div>

            <div class="sm-hero-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=scouting-archives-councils')); ?>" class="sm-btn sm-btn-primary">
                    <span class="dashicons dashicons-database-view"></span>
                    <?php esc_html_e('Browse Archives', 'scouting-forms'); ?>
                </a>
                <a href="<?php echo esc_url(home_url('/my-account/')); ?>" class="sm-btn sm-btn-outline" target="_blank">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e('View Front-End', 'scouting-forms'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Live Telemetry / Database Stats -->
    <div class="sm-stats-grid">
        <div class="sm-stat-card">
            <div class="sm-stat-icon councils">🏛️</div>
            <div class="sm-stat-details">
                <div class="sm-stat-val"><?php echo number_format($counts['councils'] ?? 0); ?></div>
                <div class="sm-stat-label"><?php esc_html_e('Historical Councils', 'scouting-forms'); ?></div>
            </div>
        </div>

        <div class="sm-stat-card">
            <div class="sm-stat-icon camps">🏕️</div>
            <div class="sm-stat-details">
                <div class="sm-stat-val"><?php echo number_format($counts['camps'] ?? 0); ?></div>
                <div class="sm-stat-label"><?php esc_html_e('Scout Camps', 'scouting-forms'); ?></div>
            </div>
        </div>

        <div class="sm-stat-card">
            <div class="sm-stat-icon lodges">🏹</div>
            <div class="sm-stat-details">
                <div class="sm-stat-val"><?php echo number_format($counts['lodges'] ?? 0); ?></div>
                <div class="sm-stat-label"><?php esc_html_e('OA Lodges', 'scouting-forms'); ?></div>
            </div>
        </div>

        <div class="sm-stat-card">
            <div class="sm-stat-icon states">🗺️</div>
            <div class="sm-stat-details">
                <div class="sm-stat-val"><?php echo number_format($counts['states'] ?? 0); ?></div>
                <div class="sm-stat-label"><?php esc_html_e('US States & Regions', 'scouting-forms'); ?></div>
            </div>
        </div>

        <div class="sm-stat-card">
            <div class="sm-stat-icon posts">📝</div>
            <div class="sm-stat-details">
                <div class="sm-stat-val"><?php echo number_format($counts['posts'] ?? 0); ?></div>
                <div class="sm-stat-label"><?php esc_html_e('Memory Entries', 'scouting-forms'); ?></div>
            </div>
        </div>
    </div>

    <!-- Walkthrough Layout -->
    <div class="sm-walkthrough-layout">

        <!-- Navigation Sidebar -->
        <aside class="sm-steps-nav">
            <div class="sm-steps-nav-title"><?php esc_html_e('Interactive Walkthrough', 'scouting-forms'); ?></div>
            <ul class="sm-step-list">
                <li>
                    <a href="#step-1" class="sm-step-item active" data-step="1">
                        <span class="sm-step-num">1</span>
                        <span><?php esc_html_e('Where to Find in Backend', 'scouting-forms'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#step-2" class="sm-step-item" data-step="2">
                        <span class="sm-step-num">2</span>
                        <span><?php esc_html_e('Managing Archive Entities', 'scouting-forms'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#step-3" class="sm-step-item" data-step="3">
                        <span class="sm-step-num">3</span>
                        <span><?php esc_html_e('Forms & Shortcodes', 'scouting-forms'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#step-4" class="sm-step-item" data-step="4">
                        <span class="sm-step-num">4</span>
                        <span><?php esc_html_e('Cascading AJAX Search', 'scouting-forms'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#step-5" class="sm-step-item" data-step="5">
                        <span class="sm-step-num">5</span>
                        <span><?php esc_html_e('A/B Testing with 2026 Theme', 'scouting-forms'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#step-6" class="sm-step-item" data-step="6">
                        <span class="sm-step-num">6</span>
                        <span><?php esc_html_e('Batch Tools & End Dates', 'scouting-forms'); ?></span>
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Content Area -->
        <main class="sm-step-content-container">

            <!-- STEP 1: Backend Navigation -->
            <div id="sm-step-1" class="sm-step-panel active">
                <div class="sm-step-panel-header">
                    <span class="sm-step-tag"><?php esc_html_e('Step 1 of 6', 'scouting-forms'); ?></span>
                    <h2><?php esc_html_e('Where is the Plugin in WP Admin?', 'scouting-forms'); ?></h2>
                    <p><?php esc_html_e('How to locate and access every management screen provided by Scouting Forms in the WordPress admin menu.', 'scouting-forms'); ?></p>
                </div>

                <div class="sm-callout info">
                    <h4>📍 <?php esc_html_e('Direct Location in WP Admin', 'scouting-forms'); ?></h4>
                    <p><?php esc_html_e('Look in your WordPress Admin left sidebar menu. You will find a menu item named "Scouting Archives" with an archive box icon, located right below Comments and above Appearance.', 'scouting-forms'); ?></p>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 220px;"><?php esc_html_e('Menu Item', 'scouting-forms'); ?></th>
                            <th style="width: 250px;"><?php esc_html_e('Direct Link', 'scouting-forms'); ?></th>
                            <th><?php esc_html_e('Purpose & Features', 'scouting-forms'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>📖 <?php esc_html_e('Overview & Guide', 'scouting-forms'); ?></strong></td>
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=scouting-archives')); ?>"><?php esc_html_e('Open Overview & Guide', 'scouting-forms'); ?></a></td>
                            <td><?php esc_html_e('This interactive dashboard, step-by-step walkthrough, shortcode palette, and live AJAX tester.', 'scouting-forms'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>🏛️ <?php esc_html_e('Councils', 'scouting-forms'); ?></strong></td>
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=scouting-archives-councils')); ?>"><?php esc_html_e('Open Councils', 'scouting-forms'); ?></a></td>
                            <td><?php esc_html_e('Search, inspect, and filter 2,312 historical councils, council numbers, slugs, and active status.', 'scouting-forms'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>🏕️ <?php esc_html_e('Camps', 'scouting-forms'); ?></strong></td>
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=scouting-archives-camps')); ?>"><?php esc_html_e('Open Camps', 'scouting-forms'); ?></a></td>
                            <td><?php esc_html_e('Browse and search 3,480 Scout camps, associated councils, and operational year ranges.', 'scouting-forms'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>🏹 <?php esc_html_e('Lodges', 'scouting-forms'); ?></strong></td>
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=scouting-archives-lodges')); ?>"><?php esc_html_e('Open Lodges', 'scouting-forms'); ?></a></td>
                            <td><?php esc_html_e('Browse and search 980 Order of the Arrow lodges, official lodge numbers, and historical statuses.', 'scouting-forms'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>⚙️ <?php esc_html_e('Archive Tools', 'scouting-forms'); ?></strong></td>
                            <td><a href="<?php echo esc_url(admin_url('admin.php?page=scouting-archives-tools')); ?>"><?php esc_html_e('Open Tools', 'scouting-forms'); ?></a></td>
                            <td><?php esc_html_e('Batch-update active end dates for the current year across all active entities in one click.', 'scouting-forms'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- STEP 2: Managing Archive Entities -->
            <div id="sm-step-2" class="sm-step-panel">
                <div class="sm-step-panel-header">
                    <span class="sm-step-tag"><?php esc_html_e('Step 2 of 6', 'scouting-forms'); ?></span>
                    <h2><?php esc_html_e('Managing Historical Archives & Search', 'scouting-forms'); ?></h2>
                    <p><?php esc_html_e('Understand how 6,700+ historical records are stored, indexed, and displayed in the backend without slow database joins.', 'scouting-forms'); ?></p>
                </div>

                <div class="sm-callout">
                    <h4>🔒 <?php esc_html_e('Zero Data Migration & Non-Destructive Storage', 'scouting-forms'); ?></h4>
                    <p><?php esc_html_e('The plugin queries existing archive tables directly with ultra-fast indexed caching. No records were modified or deleted when switching away from Formidable Forms. If you ever switch back, your data is 100% intact.', 'scouting-forms'); ?></p>
                </div>

                <h3><?php esc_html_e('Search & Filter Capabilities:', 'scouting-forms'); ?></h3>
                <ul style="list-style: disc; margin-left: 20px; line-height: 1.8;">
                    <li><strong><?php esc_html_e('Councils Screen:', 'scouting-forms'); ?></strong> <?php esc_html_e('Search by council name or council number (e.g. "Greater New York" or "640"). Paginated 30 items per page with instant active/historic badges.', 'scouting-forms'); ?></li>
                    <li><strong><?php esc_html_e('Camps Screen:', 'scouting-forms'); ?></strong> <?php esc_html_e('Search camps across any state or council. View active dates (e.g. 1928 – Present).', 'scouting-forms'); ?></li>
                    <li><strong><?php esc_html_e('Lodges Screen:', 'scouting-forms'); ?></strong> <?php esc_html_e('Search Order of the Arrow lodges by name or official OA lodge number.', 'scouting-forms'); ?></li>
                </ul>

                <div style="margin-top: 24px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=scouting-archives-councils')); ?>" class="button button-primary">
                        <?php esc_html_e('Go to Councils Archive ›', 'scouting-forms'); ?>
                    </a>
                </div>
            </div>

            <!-- STEP 3: Frontend Forms & Shortcodes -->
            <div id="sm-step-3" class="sm-step-panel">
                <div class="sm-step-panel-header">
                    <span class="sm-step-tag"><?php esc_html_e('Step 3 of 6', 'scouting-forms'); ?></span>
                    <h2><?php esc_html_e('Frontend Forms & Shortcodes Reference', 'scouting-forms'); ?></h2>
                    <p><?php esc_html_e('The plugin includes 8 native shortcodes replacing Formidable forms across the site. Click any shortcode below to copy it.', 'scouting-forms'); ?></p>
                </div>

                <div class="sm-shortcode-grid">

                    <div class="sm-shortcode-card">
                        <div class="sm-sc-header">
                            <code class="sm-sc-code">[sm_account_profile]</code>
                            <button type="button" class="sm-copy-btn" data-clipboard="[sm_account_profile]"><?php esc_html_e('Copy', 'scouting-forms'); ?></button>
                        </div>
                        <p class="sm-sc-desc"><?php esc_html_e('Contributor profile editor (Display name, bio, email, website). Replaces Form 1.', 'scouting-forms'); ?></p>
                        <div class="sm-sc-meta"><?php esc_html_e('Used in: my-account.php (Home tab)', 'scouting-forms'); ?></div>
                    </div>

                    <div class="sm-shortcode-card">
                        <div class="sm-sc-header">
                            <code class="sm-sc-code">[sm_user_defaults]</code>
                            <button type="button" class="sm-copy-btn" data-clipboard="[sm_user_defaults]"><?php esc_html_e('Copy', 'scouting-forms'); ?></button>
                        </div>
                        <p class="sm-sc-desc"><?php esc_html_e('Saves contributor default State, Council, Camp, Lodge, and Year into user_meta. Replaces Form 4.', 'scouting-forms'); ?></p>
                        <div class="sm-sc-meta"><?php esc_html_e('Used in: my-account.php (Defaults tab)', 'scouting-forms'); ?></div>
                    </div>

                    <div class="sm-shortcode-card">
                        <div class="sm-sc-header">
                            <code class="sm-sc-code">[sm_user_posts]</code>
                            <button type="button" class="sm-copy-btn" data-clipboard="[sm_user_posts]"><?php esc_html_e('Copy', 'scouting-forms'); ?></button>
                        </div>
                        <p class="sm-sc-desc"><?php esc_html_e('Displays contributor published, draft, and pending memories with quick edit/delete buttons.', 'scouting-forms'); ?></p>
                        <div class="sm-sc-meta"><?php esc_html_e('Used in: my-account.php (Posts tab)', 'scouting-forms'); ?></div>
                    </div>

                    <div class="sm-shortcode-card">
                        <div class="sm-sc-header">
                            <code class="sm-sc-code">[sm_indexing_browser]</code>
                            <button type="button" class="sm-copy-btn" data-clipboard="[sm_indexing_browser]"><?php esc_html_e('Copy', 'scouting-forms'); ?></button>
                        </div>
                        <p class="sm-sc-desc"><?php esc_html_e('Front-end public historical archives browser with instant search and tabs. Replaces Formidable view.', 'scouting-forms'); ?></p>
                        <div class="sm-sc-meta"><?php esc_html_e('Used in: my-account.php (Indexing tab)', 'scouting-forms'); ?></div>
                    </div>

                    <div class="sm-shortcode-card">
                        <div class="sm-sc-header">
                            <code class="sm-sc-code">[sm_add_memory_form]</code>
                            <button type="button" class="sm-copy-btn" data-clipboard="[sm_add_memory_form]"><?php esc_html_e('Copy', 'scouting-forms'); ?></button>
                        </div>
                        <p class="sm-sc-desc"><?php esc_html_e('Complete memory submission form with title, rich editor, dynamic AJAX cascading selects, and media uploads.', 'scouting-forms'); ?></p>
                        <div class="sm-sc-meta"><?php esc_html_e('Used in: add-post.php (/add-a-post/)', 'scouting-forms'); ?></div>
                    </div>

                    <div class="sm-shortcode-card">
                        <div class="sm-sc-header">
                            <code class="sm-sc-code">[sm_add_council_form]</code>
                            <button type="button" class="sm-copy-btn" data-clipboard="[sm_add_council_form]"><?php esc_html_e('Copy', 'scouting-forms'); ?></button>
                        </div>
                        <p class="sm-sc-desc"><?php esc_html_e('Submit new historical council (Name, number, headquarters, state, start/end dates).', 'scouting-forms'); ?></p>
                        <div class="sm-sc-meta"><?php esc_html_e('Used in: add-council.php (/add-a-council/)', 'scouting-forms'); ?></div>
                    </div>

                    <div class="sm-shortcode-card">
                        <div class="sm-sc-header">
                            <code class="sm-sc-code">[sm_add_camp_form]</code>
                            <button type="button" class="sm-copy-btn" data-clipboard="[sm_add_camp_form]"><?php esc_html_e('Copy', 'scouting-forms'); ?></button>
                        </div>
                        <p class="sm-sc-desc"><?php esc_html_e('Submit new Scout camp with state, council association, and operating dates.', 'scouting-forms'); ?></p>
                        <div class="sm-sc-meta"><?php esc_html_e('Used in: add-camp.php (/add-a-camp/)', 'scouting-forms'); ?></div>
                    </div>

                    <div class="sm-shortcode-card">
                        <div class="sm-sc-header">
                            <code class="sm-sc-code">[sm_add_lodge_form]</code>
                            <button type="button" class="sm-copy-btn" data-clipboard="[sm_add_lodge_form]"><?php esc_html_e('Copy', 'scouting-forms'); ?></button>
                        </div>
                        <p class="sm-sc-desc"><?php esc_html_e('Submit new Order of the Arrow lodge with council affiliation and lodge number.', 'scouting-forms'); ?></p>
                        <div class="sm-sc-meta"><?php esc_html_e('Used in: add-lodge.php (/add-a-lodge/)', 'scouting-forms'); ?></div>
                    </div>

                </div>
            </div>

            <!-- STEP 4: Cascading AJAX & Live Playground -->
            <div id="sm-step-4" class="sm-step-panel">
                <div class="sm-step-panel-header">
                    <span class="sm-step-tag"><?php esc_html_e('Step 4 of 6', 'scouting-forms'); ?></span>
                    <h2><?php esc_html_e('Cascading AJAX Selects & Live Playground', 'scouting-forms'); ?></h2>
                    <p><?php esc_html_e('Watch how selecting a State asynchronously loads its Councils, and selecting a Council loads its Camps and Lodges.', 'scouting-forms'); ?></p>
                </div>

                <div class="sm-playground-box">
                    <div class="sm-playground-header">
                        <span class="dashicons dashicons-admin-generic" style="color: #2563eb;"></span>
                        <?php esc_html_e('Live AJAX Cascading Test Playground', 'scouting-forms'); ?>
                    </div>

                    <div class="sm-form-row">
                        <label for="sm-test-state"><?php esc_html_e('1. Select State:', 'scouting-forms'); ?></label>
                        <select id="sm-test-state">
                            <option value=""><?php esc_html_e('— Choose a State to Test AJAX —', 'scouting-forms'); ?></option>
                            <?php if (!empty($states)): foreach ($states as $st): ?>
                                <option value="<?php echo esc_attr($st['id']); ?>"><?php echo esc_html($st['name']); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <div class="sm-form-row">
                        <label for="sm-test-council"><?php esc_html_e('2. Council (Populated via AJAX):', 'scouting-forms'); ?></label>
                        <select id="sm-test-council" disabled>
                            <option value=""><?php esc_html_e('— First choose a State —', 'scouting-forms'); ?></option>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="sm-form-row">
                            <label for="sm-test-camp"><?php esc_html_e('3a. Camps (Populated via AJAX):', 'scouting-forms'); ?></label>
                            <select id="sm-test-camp" disabled>
                                <option value=""><?php esc_html_e('— Choose a Council —', 'scouting-forms'); ?></option>
                            </select>
                        </div>
                        <div class="sm-form-row">
                            <label for="sm-test-lodge"><?php esc_html_e('3b. Lodges (Populated via AJAX):', 'scouting-forms'); ?></label>
                            <select id="sm-test-lodge" disabled>
                                <option value=""><?php esc_html_e('— Choose a Council —', 'scouting-forms'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="sm-live-status">
                        <span class="sm-status-indicator"></span>
                        <span id="sm-test-status"><?php esc_html_e('Select any State above to trigger live AJAX calls against your local database.', 'scouting-forms'); ?></span>
                    </div>
                </div>

                <div class="sm-callout info">
                    <h4>💡 <?php esc_html_e('How it works under the hood', 'scouting-forms'); ?></h4>
                    <p><?php esc_html_e('Frontend forms enqueue assets/js/cascading-selects.js. When a dropdown changes, an authenticated AJAX request is sent to admin-ajax.php with an active security nonce. The response is cached in memory for sub-millisecond response times.', 'scouting-forms'); ?></p>
                </div>
            </div>

            <!-- STEP 5: A/B Testing Workflow -->
            <div id="sm-step-5" class="sm-step-panel">
                <div class="sm-step-panel-header">
                    <span class="sm-step-tag"><?php esc_html_e('Step 5 of 6', 'scouting-forms'); ?></span>
                    <h2><?php esc_html_e('Safe Side-by-Side A/B Testing Workflow', 'scouting-forms'); ?></h2>
                    <p><?php esc_html_e('You can seamlessly toggle between the original site configuration and the new 2026 Roots.io configuration anytime with zero risk.', 'scouting-forms'); ?></p>
                </div>

                <div class="sm-ab-grid">
                    <div class="sm-ab-card <?php echo !$is_2026_active ? 'active-theme' : ''; ?>">
                        <div class="sm-ab-title">
                            <span><?php esc_html_e('Original Setup', 'scouting-forms'); ?></span>
                            <?php if (!$is_2026_active): ?>
                                <span class="sm-pill sm-pill-success"><?php esc_html_e('Currently Active', 'scouting-forms'); ?></span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size: 13px; color: #64748b; margin: 0 0 10px 0;"><code>themes/scoutingmemories</code></p>
                        <ul class="sm-ab-features">
                            <li>⚠️ <?php esc_html_e('Relies on Formidable Forms plugin', 'scouting-forms'); ?></li>
                            <li>⚠️ <?php esc_html_e('50+ hardcoded field IDs in code', 'scouting-forms'); ?></li>
                            <li>⚠️ <?php esc_html_e('Slow template execution on large tables', 'scouting-forms'); ?></li>
                            <li>✅ <?php esc_html_e('Untouched reference code', 'scouting-forms'); ?></li>
                        </ul>
                    </div>

                    <div class="sm-ab-card <?php echo $is_2026_active ? 'active-theme' : ''; ?>">
                        <div class="sm-ab-title">
                            <span><?php esc_html_e('New 2026 Setup', 'scouting-forms'); ?></span>
                            <?php if ($is_2026_active): ?>
                                <span class="sm-pill sm-pill-success"><?php esc_html_e('Currently Active', 'scouting-forms'); ?></span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size: 13px; color: #64748b; margin: 0 0 10px 0;"><code>themes/Scouting-Memories-2026</code></p>
                        <ul class="sm-ab-features">
                            <li>🚀 <?php esc_html_e('Driven by custom scouting-forms plugin', 'scouting-forms'); ?></li>
                            <li>🚀 <?php esc_html_e('PSR-4 namespaced Models & Controllers', 'scouting-forms'); ?></li>
                            <li>🚀 <?php esc_html_e('Real-time AJAX Cascading Selects', 'scouting-forms'); ?></li>
                            <li>🚀 <?php esc_html_e('Zero reliance on Formidable Pro licenses', 'scouting-forms'); ?></li>
                        </ul>
                    </div>
                </div>

                <div class="sm-callout">
                    <h4>🔄 <?php esc_html_e('How to Switch Between Themes for A/B Testing:', 'scouting-forms'); ?></h4>
                    <ol style="margin: 8px 0 0 20px; line-height: 1.8; font-size: 13px; color: #334155;">
                        <li><?php esc_html_e('Go to WP Admin &rarr; Appearance &rarr; Themes.', 'scouting-forms'); ?></li>
                        <li><?php esc_html_e('To test the new setup: Click "Activate" on "Scouting Memories 2026".', 'scouting-forms'); ?></li>
                        <li><?php esc_html_e('To return to legacy: Click "Activate" on "Scouting Memories" (the original).', 'scouting-forms'); ?></li>
                        <li><?php esc_html_e('All posts, entries, and user preferences remain intact across both themes.', 'scouting-forms'); ?></li>
                    </ol>
                </div>

                <div style="margin-top: 20px;">
                    <a href="<?php echo esc_url(admin_url('themes.php')); ?>" class="button button-primary">
                        <?php esc_html_e('Go to Appearance &rarr; Themes ›', 'scouting-forms'); ?>
                    </a>
                </div>
            </div>

            <!-- STEP 6: Batch Tools & End Dates -->
            <div id="sm-step-6" class="sm-step-panel">
                <div class="sm-step-panel-header">
                    <span class="sm-step-tag"><?php esc_html_e('Step 6 of 6', 'scouting-forms'); ?></span>
                    <h2><?php esc_html_e('Batch Tools & Annual Rollover', 'scouting-forms'); ?></h2>
                    <p><?php esc_html_e('Easily advance end dates for all currently active councils, camps, and lodges without custom database scripts.', 'scouting-forms'); ?></p>
                </div>

                <div class="sm-callout info">
                    <h4>⚙️ <?php esc_html_e('The Annual Active End-Date Updater', 'scouting-forms'); ?></h4>
                    <p><?php esc_html_e('In the legacy theme, updating active councils/camps required hitting a special URL query parameter. In Scouting Forms, this is now a safe, one-click administrative tool with nonces and admin capability checks.', 'scouting-forms'); ?></p>
                </div>

                <p style="line-height: 1.6; color: #475569;">
                    <?php esc_html_e('When a new calendar year begins, open Archive Tools, verify the Target Year (defaults to the current year), and click "Run Safe Batch Update". All entities flagged as active (Active = "Yes") will have their End Date updated to reflect the new year.', 'scouting-forms'); ?>
                </p>

                <div style="margin-top: 24px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=scouting-archives-tools')); ?>" class="button button-primary">
                        <?php esc_html_e('Open Archive Tools ›', 'scouting-forms'); ?>
                    </a>
                </div>
            </div>

            <!-- Stepper Controls (Bottom) -->
            <div class="sm-step-controls">
                <button type="button" id="sm-btn-prev" class="sm-btn sm-btn-outline" style="color: #334155; border-color: #cbd5e1; display: none;">
                    ‹ <?php esc_html_e('Previous Step', 'scouting-forms'); ?>
                </button>
                <div style="flex-grow: 1;"></div>
                <button type="button" id="sm-btn-next" class="sm-btn sm-btn-primary">
                    <?php esc_html_e('Next Step ›', 'scouting-forms'); ?>
                </button>
            </div>

        </main>
    </div>

</div>
