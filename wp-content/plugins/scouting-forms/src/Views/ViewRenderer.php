<?php

namespace ScoutingMemories\Forms\Views;

use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Ui\ThemeClasses;
use ScoutingMemories\Forms\Support\Permissions;
use ScoutingMemories\Forms\Support\ShortcodeTrust;

/**
 * ViewRenderer
 *
 * Renders a Formidable view: before content, one row template per entry, after content, and
 * paging, or the detail template for one entry when the view is "dynamic" and ?entry= is set.
 *
 * Shortcode attributes other than the ones below become parameters for [get param=name] in the
 * view's filters and templates, like Formidable (the theme passes search_param this way, nested
 * views pass state= and roles=). filter="limited" is Formidable's content-filter switch, not a
 * user filter; views limited to the current user do that with their own "current_user" filter.
 */
class ViewRenderer {

    private const OWN_ATTS = ['id', 'key', 'filter', 'wpautop', 'limit', 'page_size', 'order_by', 'order'];
    private const MAX_DEPTH = 4;

    private static int $depth = 0;

    /**
     * @param array<string, mixed> $atts
     */
    public static function render(array $atts): string {
        $view = ViewRepository::find((int) ($atts['id'] ?? 0), sanitize_title((string) ($atts['key'] ?? '')));
        if (!$view || !in_array($view['status'], ['publish', 'private'], true)) {
            return '<!-- Scouting Forms: view not found (ID: ' . (int) ($atts['id'] ?? 0) . ') -->';
        }
        if (self::$depth >= self::MAX_DEPTH) {
            return '<!-- Scouting Forms: views nested too deeply -->';
        }

        $view = self::applyAtts($view, $atts);
        $params = self::params($atts);

        self::$depth++;
        try {
            $html = EntryActions::restore(self::filterContent(self::content($view, $params), $view, $atts));
        } finally {
            self::$depth--;
        }

        // Option-list views (search dropdowns) go inside the theme's <select>: no wrapper there
        if (preg_match('/^\s*<option\b/i', $html)) {
            return $html;
        }

        wp_enqueue_style('sm-forms-front');
        $notice = self::$depth === 0 ? EntryActions::notice() : '';
        return '<div class="' . ThemeClasses::SCOPE . ' sm-view" data-view="' . (int) $view['id'] . '">' . $notice . $html . '</div>';
    }

    /**
     * [sm_show_entry id=X]: every field of one entry as a two-column table (Formidable's
     * [frm-show-entry] default).
     *
     * @param array<string, mixed> $atts
     */
    public static function showEntry($atts = []): string {
        $entryId = absint(is_array($atts) ? ($atts['id'] ?? 0) : 0);
        $entry = $entryId ? \ScoutingMemories\Forms\Models\EntryRepository::find($entryId) : [];
        if (!$entry) {
            return '';
        }
        // Outside view templates only people who may see entries (or the entry's owner) see it
        if (!ShortcodeTrust::isTrusted() && !Permissions::can('view_entries') && (!is_user_logged_in() || (int) $entry['user_id'] !== get_current_user_id())) {
            return '';
        }
        $values = new EntryValues((int) $entry['form_id']);
        $values->load([$entryId]);

        $rows = '';
        foreach (FormRepository::fields((int) $entry['form_id']) as $field) {
            if (in_array($field['type'], ['html', 'divider', 'end_divider', 'break', 'submit', 'captcha', 'summary', 'form', 'password'], true)) {
                continue;
            }
            $shown = $values->display($entryId, (int) $field['id']);
            if (trim(wp_strip_all_tags($shown, true)) === '' && strpos($shown, '<img') === false) {
                continue;
            }
            $rows .= '<tr><th scope="row">' . esc_html($field['name']) . '</th><td>' . $shown . '</td></tr>';
        }
        return '<table class="table table-sm frm-show-entry"><tbody>' . $rows . '</tbody></table>';
    }

    /**
     * @param array<string, mixed> $view
     * @param array<string, string> $params
     */
    private static function content(array $view, array $params): string {
        $values = new EntryValues((int) $view['form_id']);

        // Single entry of a dynamic view
        if ($view['show'] === 'dynamic' && $view['detail'] !== '' && isset($_GET[$view['param']])) {
            $entryId = self::entryFromParam($view, sanitize_text_field(wp_unslash($_GET[$view['param']])));
            if ($entryId) {
                $values->load([$entryId]);
                return TemplateTags::render($view['detail'], $entryId, $values, $view, $params);
            }
        }

        $pageParam = 'frm-page-' . $view['id'];
        $page = isset($_GET[$pageParam]) ? max(1, absint($_GET[$pageParam])) : 1;
        $result = EntryQuery::run($view, $params, $page);

        $options = $view['options'];
        if (!$result['ids']) {
            $empty = trim((string) ($options['empty_msg'] ?? ''));
            return $empty !== '' ? '<div class="frm_no_entries">' . self::fillParams($empty, $params) . '</div>' : '';
        }

        $values->load($result['ids']);
        $rows = '';
        foreach ($result['ids'] as $entryId) {
            $rows .= TemplateTags::render($view['content'], $entryId, $values, $view, $params);
        }

        $before = self::fillParams((string) ($options['before_content'] ?? ''), $params);
        $after = self::fillParams((string) ($options['after_content'] ?? ''), $params);

        return $before . $rows . $after . self::pagination($pageParam, $result['page'], $result['pages'], ['view' => get_post((int) $view['id'])] + $params);
    }

    /**
     * Formidable's content filter setting: from the shortcode, the view, or "limited" by default.
     * "limited" runs the_content's formatting (curly quotes, paragraphs) and shortcodes; "1" runs
     * the full the_content filter; "0" only runs shortcodes. Nested shortcodes are pointed at this
     * plugin's own versions first.
     *
     * @param array<string, mixed> $view
     * @param array<string, mixed> $atts
     */
    private static function filterContent(string $html, array $view, array $atts): string {
        $html = TemplateTags::ownShortcodes($html);
        $filter = (string) ($atts['filter'] ?? '');
        if ($filter === '') {
            $filter = (string) get_post_meta($view['id'], 'frm_active_preview_filter', true);
        }
        if (!in_array($filter, ['0', '1', 'limited'], true)) {
            $filter = 'limited';
        }

        if ($filter === '1') {
            return apply_filters('the_content', $html);
        }
        if ($filter === 'limited') {
            if (has_filter('the_content', 'wptexturize')) {
                $html = wptexturize($html);
            }
            $autop = (string) ($atts['wpautop'] ?? '');
            if ($autop !== '0' && (has_filter('the_content', 'wpautop') || $autop === '1')) {
                $html = wpautop($html);
            }
            $html = shortcode_unautop(wp_filter_content_tags($html));
        }
        // View templates are written by administrators: their shortcodes may show entry data
        return ShortcodeTrust::trusted(static fn() => do_shortcode($html));
    }

    /**
     * [get param=x] in before/after content.
     *
     * @param array<string, string> $params
     */
    private static function fillParams(string $html, array $params): string {
        return preg_replace_callback('/\[get param=["\']?([A-Za-z0-9_\-]+)["\']?[^\]\[]*\]/', static function ($m) use ($params) {
            return esc_html((string) ($params[$m[1]] ?? ''));
        }, $html);
    }

    private static function entryFromParam(array $view, string $idOrKey): int {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}frm_items WHERE form_id = %d AND is_draft = 0 AND (id = %d OR item_key = %s) LIMIT 1",
            (int) $view['form_id'],
            ctype_digit($idOrKey) ? (int) $idOrKey : 0,
            $idOrKey
        ));
    }

    /**
     * limit, page_size, order_by and order on the shortcode override the view's settings.
     *
     * @param array<string, mixed> $view
     * @param array<string, mixed> $atts
     * @return array<string, mixed>
     */
    private static function applyAtts(array $view, array $atts): array {
        foreach (['limit', 'page_size'] as $name) {
            if (isset($atts[$name]) && $atts[$name] !== '') {
                $view['options'][$name] = absint($atts[$name]);
            }
        }
        if (!empty($atts['order_by'])) {
            $view['options']['order_by'] = [sanitize_key((string) $atts['order_by'])];
            $view['options']['order'] = [strtoupper((string) ($atts['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC'];
        }
        return $view;
    }

    /**
     * URL parameters, then shortcode attributes on top (scalars only).
     *
     * @param array<string, mixed> $atts
     * @return array<string, string>
     */
    private static function params(array $atts): array {
        $params = [];
        foreach ($_GET as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $params[$name] = sanitize_text_field(wp_unslash((string) $value));
            }
        }
        foreach ($atts as $name => $value) {
            if (is_string($name) && is_scalar($value) && !in_array($name, self::OWN_ATTS, true)) {
                $params[$name] = sanitize_text_field((string) $value);
            }
        }
        return $params;
    }

    /**
     * @param array<string, mixed> $atts For Formidable's page link filters (the theme adds anchors)
     */
    private static function pagination(string $param, int $page, int $pages, array $atts = []): string {
        if ($pages <= 1) {
            return '';
        }
        $link = static function (int $n, string $label, bool $active = false, bool $disabled = false, string $aria = '') use ($param, $page, $pages, $atts): string {
            $class = 'page-item' . ($active ? ' active' : '') . ($disabled ? ' disabled' : '');
            $attr = $aria !== '' ? ' aria-label="' . esc_attr($aria) . '"' : '';
            if ($active || $disabled) {
                return '<li class="' . $class . '"' . ($active ? ' aria-current="page"' : '') . '><span class="page-link"' . $attr . '>' . $label . '</span></li>';
            }
            $filter = $label === '&laquo;' ? 'frm_prev_page_link' : ($label === '&raquo;' ? 'frm_next_page_link'
                : ($n === 1 ? 'frm_first_page_link' : ($n === $pages ? 'frm_last_page_link' : 'frm_page_link')));
            $url = (string) apply_filters($filter, add_query_arg($param, $n), $atts);
            return '<li class="' . $class . '"><a class="page-link" href="' . esc_url($url) . '"' . $attr . '>' . $label . '</a></li>';
        };

        // Like Formidable, no previous arrow on the first page and no next arrow on the last
        $items = $page > 1 ? $link($page - 1, '&laquo;', false, false, __('Previous page', 'scouting-forms')) : '';
        $shown = [];
        foreach ([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages] as $n) {
            if ($n >= 1 && $n <= $pages) {
                $shown[$n] = true;
            }
        }
        ksort($shown);
        $prev = 0;
        foreach (array_keys($shown) as $n) {
            if ($prev && $n > $prev + 1) {
                $items .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
            }
            $items .= $link($n, (string) $n, $n === $page);
            $prev = $n;
        }
        $items .= $page < $pages ? $link($page + 1, '&raquo;', false, false, __('Next page', 'scouting-forms')) : '';

        return '<nav class="frm_pagination_cont" aria-label="' . esc_attr__('Pages', 'scouting-forms') . '"><ul class="pagination frm_pagination flex-wrap">' . $items . '</ul></nav>';
    }
}
