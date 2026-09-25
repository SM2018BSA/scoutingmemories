<?php

namespace ScoutingMemories\Forms\Tools;

use ScoutingMemories\Forms\Support\Environment;

/**
 * CompareTool
 *
 * Local development only. Shows a Formidable form or view and the plugin's version of it side by
 * side, inside the real site theme, so styling and behaviour can be checked for parity:
 *
 *   /?sm_compare=form&id=2        (Formidable [formidable id=2]  vs  [sm_form id=2])
 *   /?sm_compare=view&id=1172     (Formidable [display-frm-data id=1172]  vs  [sm_view id=1172])
 *   /?sm_compare=list             (links to every form and view)
 *
 * Access: an administrator on a local copy, or a signed link from previewUrl() that expires after
 * an hour (for automated checks without logging in). On the live site the tool does nothing.
 */
class CompareTool {

    public static function registerHooks(): void {
        add_action('template_redirect', [__CLASS__, 'maybeRender'], 1);
    }

    /**
     * Signed, time-limited link to the tool (generate from WP-CLI / php -r on the local copy).
     */
    public static function previewUrl(string $type, int $id = 0, int $ttl = 3600): string {
        $expires = time() + $ttl;
        return add_query_arg([
            'sm_compare' => $type,
            'id' => $id,
            'expires' => $expires,
            'sig' => self::signature($type, $id, $expires),
        ], home_url('/'));
    }

    public static function maybeRender(): void {
        if (!isset($_GET['sm_compare']) || !Environment::isLocal()) {
            return;
        }
        if (!self::isAllowed()) {
            wp_die(esc_html__('The compare tool needs an administrator login or a valid preview link.', 'scouting-forms'), 403);
        }

        $type = sanitize_key(wp_unslash($_GET['sm_compare']));
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        get_header();
        echo '<main class="container my-5">';
        echo '<h1 class="h3 mb-3">' . esc_html__('Scouting Forms: compare with Formidable', 'scouting-forms') . '</h1>';
        echo '<p class="text-muted">' . esc_html__('Local copy only. Left: Formidable, as the live site shows it today. Right: the Scouting Forms plugin.', 'scouting-forms') . '</p>';

        if ($type === 'form' && $id) {
            self::renderPair(
                sprintf('[formidable id=%d title=1 description=1]', $id),
                sprintf('[sm_form id=%d title=1 description=1]', $id),
                self::formName($id)
            );
        } elseif ($type === 'view' && $id) {
            self::renderPair(
                sprintf('[display-frm-data id=%d]', $id),
                sprintf('[sm_view id=%d]', $id),
                get_the_title($id)
            );
        }

        self::renderIndex();
        echo '</main>';
        get_footer();
        exit;
    }

    private static function renderPair(string $formidableShortcode, string $pluginShortcode, string $title): void {
        echo '<h2 class="h5 mb-3">' . esc_html($title) . '</h2>';
        echo '<div class="row g-4 mb-5">';
        foreach ([
            ['Formidable', $formidableShortcode],
            ['Scouting Forms', $pluginShortcode],
        ] as [$label, $shortcode]) {
            echo '<div class="col-lg-6" data-sm-compare="' . esc_attr(sanitize_title($label)) . '">';
            echo '<div class="small text-uppercase text-muted fw-semibold mb-2">' . esc_html($label) . ' <code>' . esc_html($shortcode) . '</code></div>';
            echo '<div class="border rounded p-3 bg-white">' . do_shortcode($shortcode) . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    private static function renderIndex(): void {
        global $wpdb;
        $forms = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}frm_forms WHERE status = 'published' AND is_template = 0 ORDER BY id");
        $views = get_posts(['post_type' => 'frm_display', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC']);

        echo '<div class="row g-4">';
        echo '<div class="col-md-6"><h2 class="h6">' . esc_html__('Forms', 'scouting-forms') . '</h2><ul class="small">';
        foreach ($forms as $form) {
            echo '<li><a href="' . esc_url(self::linkFor('form', (int) $form->id)) . '">#' . (int) $form->id . ' ' . esc_html($form->name) . '</a></li>';
        }
        echo '</ul></div>';
        echo '<div class="col-md-6"><h2 class="h6">' . esc_html__('Views', 'scouting-forms') . '</h2><ul class="small">';
        foreach ($views as $view) {
            echo '<li><a href="' . esc_url(self::linkFor('view', $view->ID)) . '">#' . (int) $view->ID . ' ' . esc_html($view->post_title) . '</a></li>';
        }
        echo '</ul></div></div>';
    }

    /**
     * Links inside the tool keep the current signed access, or use the admin session.
     */
    private static function linkFor(string $type, int $id): string {
        if (isset($_GET['sig'])) {
            return self::previewUrl($type, $id);
        }
        return add_query_arg(['sm_compare' => $type, 'id' => $id], home_url('/'));
    }

    private static function isAllowed(): bool {
        if (current_user_can('manage_options')) {
            return true;
        }
        $type = isset($_GET['sm_compare']) ? sanitize_key(wp_unslash($_GET['sm_compare'])) : '';
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        $expires = isset($_GET['expires']) ? absint($_GET['expires']) : 0;
        $sig = isset($_GET['sig']) ? sanitize_text_field(wp_unslash($_GET['sig'])) : '';
        return $expires >= time() && $sig !== '' && hash_equals(self::signature($type, $id, $expires), $sig);
    }

    private static function signature(string $type, int $id, int $expires): string {
        return hash_hmac('sha256', "sm_compare|{$type}|{$id}|{$expires}", wp_salt('auth'));
    }

    private static function formName(int $id): string {
        global $wpdb;
        $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}frm_forms WHERE id = %d", $id));
        return $name ? sprintf('#%d %s', $id, $name) : sprintf('#%d', $id);
    }
}
