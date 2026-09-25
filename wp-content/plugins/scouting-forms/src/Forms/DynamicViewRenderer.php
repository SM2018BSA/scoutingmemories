<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Ui\ThemeClasses;

/**
 * DynamicViewRenderer
 *
 * Dynamically renders any Formidable/Scouting view from database tables (post_type = 'frm_display').
 * Replaces tags [id], [created_at], [field_id], [field_key], [editlink], [deletelink], and [if ...].
 * Compliant with WP Engine hosting and encapsulated with Tailwind CSS v4.
 */
class DynamicViewRenderer extends FormHandler {

    public static function registerHooks(): void {
        add_shortcode('sm_view', [__CLASS__, 'renderShortcode']);

        // Fallback for existing [display-frm-data] content, only when Formidable Views is not
        // loaded. Checked late on init so plugin load order can't shadow the real shortcode.
        add_action('init', function () {
            if (!class_exists('FrmViewsDisplaysController') && !class_exists('FrmProDisplaysController') && !shortcode_exists('display-frm-data')) {
                add_shortcode('display-frm-data', [__CLASS__, 'renderFormidableFallback']);
            }
        }, 999);
    }

    /**
     * Fallback for [display-frm-data id=X filter=limited]
     */
    public static function renderFormidableFallback(array $atts = []): string {
        return self::renderShortcode($atts);
    }

    /**
     * [sm_view id="X" filter="limited" page_size="20"]
     */
    public static function renderShortcode(array $atts = []): string {
        $atts = shortcode_atts([
            'id'        => 0,
            'key'       => '',
            'filter'    => '',
            'page_size' => 0,
            'order_by'  => 'created_at',
            'order'     => 'DESC',
        ], $atts, 'sm_view');

        global $wpdb;
        $viewId = (int) $atts['id'];
        $viewKey = sanitize_title($atts['key']);

        $viewPost = null;
        if ($viewId > 0) {
            $viewPost = get_post($viewId);
        } elseif (!empty($viewKey)) {
            $viewPost = get_page_by_path($viewKey, OBJECT, 'frm_display');
        }

        if (!$viewPost || $viewPost->post_type !== 'frm_display') {
            return "<!-- Scouting Forms: View not found (ID: {$atts['id']}, Key: {$atts['key']}) -->";
        }

        $formId = (int) get_post_meta($viewPost->ID, 'frm_form_id', true);
        $options = maybe_unserialize(get_post_meta($viewPost->ID, 'frm_options', true));
        if (!is_array($options)) {
            $options = [];
        }

        $beforeContent = $options['before_content'] ?? '';
        $afterContent = $options['after_content'] ?? '';
        $emptyMsg = $options['empty_msg'] ?? __('No entries found.', 'scouting-forms');
        $rowTemplate = $viewPost->post_content;

        // Fetch field mapping for form: [field_id => field_key] and [field_key => field_id]
        $fields = $wpdb->get_results($wpdb->prepare(
            "SELECT id, field_key, name, type FROM wp_frm_fields WHERE form_id = %d",
            $formId
        ));

        $fieldMap = []; // key => id
        $idToKey = [];  // id => key
        $fieldTypes = []; // id => type
        foreach ($fields as $f) {
            $fieldMap[$f->field_key] = (int)$f->id;
            $idToKey[(int)$f->id] = $f->field_key;
            $fieldTypes[(int)$f->id] = $f->type;
        }

        // Build query for entries
        $whereSql = "form_id = %d AND is_draft = 0";
        $params = [$formId];

        // If filter is limited to current user
        if ($atts['filter'] === 'limited' || ($options['user_id'] ?? '') === 'current') {
            $currentUserId = get_current_user_id();
            if ($currentUserId > 0) {
                $whereSql .= " AND user_id = %d";
                $params[] = $currentUserId;
            } else {
                return '<div class="alert alert-warning">' . __('Please log in to view your entries.', 'scouting-forms') . '</div>';
            }
        }

        $limit = (int) ($atts['page_size'] ?: ($options['limit'] ?? 100));
        if ($limit <= 0 || $limit > 500) {
            $limit = 100;
        }

        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM wp_frm_items WHERE {$whereSql} ORDER BY id DESC LIMIT %d",
            array_merge($params, [$limit])
        ));

        if (empty($entries)) {
            return '<div class="' . ThemeClasses::SCOPE . ' sm-view-empty p-3 text-muted small">' . wp_kses_post($emptyMsg) . '</div>';
        }

        // Preload metas for all entries in one batch
        $entryIds = array_map(function($e) { return (int) $e->id; }, $entries);
        $idsPlaceholder = implode(',', $entryIds);
        $rawMetas = $wpdb->get_results("SELECT item_id, field_id, meta_value FROM wp_frm_item_metas WHERE item_id IN ({$idsPlaceholder})");

        $metasByEntry = [];
        foreach ($rawMetas as $rm) {
            $metasByEntry[(int)$rm->item_id][(int)$rm->field_id] = $rm->meta_value;
        }

        // Render each row
        $rowsHtml = '';
        foreach ($entries as $entry) {
            $rowMeta = $metasByEntry[(int)$entry->id] ?? [];
            $rowOutput = $rowTemplate;

            // 1. Process Conditionals: [if field]...[/if field]
            $rowOutput = preg_replace_callback('/\[if\s+([a-zA-Z0-9_\-]+)\](.*?)\[\/if\s+\1\]/s', function($matches) use ($rowMeta, $fieldMap) {
                $tag = $matches[1];
                $inner = $matches[2];
                $fieldId = is_numeric($tag) ? (int)$tag : ($fieldMap[$tag] ?? 0);
                $val = $rowMeta[$fieldId] ?? '';
                return !empty($val) ? $inner : '';
            }, $rowOutput);

            // 2. Replace Entry Core Tags
            $rowOutput = str_replace('[id]', (string)$entry->id, $rowOutput);
            $rowOutput = str_replace('[item_key]', esc_attr($entry->item_key), $rowOutput);
            $rowOutput = str_replace('[created_at]', esc_html(date_i18n(get_option('date_format'), strtotime($entry->created_at))), $rowOutput);
            $rowOutput = str_replace('[updated_at]', esc_html(date_i18n(get_option('date_format'), strtotime($entry->updated_at))), $rowOutput);

            // 3. Replace Action Links
            $rowOutput = preg_replace_callback('/\[editlink\s*([^\]]*)\]/', function($matches) use ($entry) {
                $parsedAtts = shortcode_parse_atts($matches[1]);
                $label = $parsedAtts['label'] ?? __('Edit', 'scouting-forms');
                $pageId = (int)($parsedAtts['page_id'] ?? 0);
                $class = $parsedAtts['class'] ?? ThemeClasses::button('secondary', 'xs');
                $editUrl = $pageId > 0 ? add_query_arg(['entry' => $entry->id], get_permalink($pageId)) : '#';
                return '<a href="' . esc_url($editUrl) . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a>';
            }, $rowOutput);

            $rowOutput = preg_replace_callback('/\[deletelink\s*([^\]]*)\]/', function($matches) use ($entry) {
                $parsedAtts = shortcode_parse_atts($matches[1]);
                $label = $parsedAtts['label'] ?? __('Delete', 'scouting-forms');
                $class = $parsedAtts['class'] ?? ThemeClasses::button('danger', 'xs');
                return '<a href="#" data-entry-id="' . esc_attr($entry->id) . '" class="sm-delete-entry ' . esc_attr($class) . '" onclick="return confirm(\'Are you sure you want to delete this entry?\');">' . esc_html($label) . '</a>';
            }, $rowOutput);

            // 4. Replace Field Shortcodes [123] and [field_key]
            foreach ($rowMeta as $fieldId => $val) {
                $fieldKey = $idToKey[$fieldId] ?? '';
                $type = $fieldTypes[$fieldId] ?? 'text';
                $displayVal = self::formatFieldValue($val, $type);

                $rowOutput = str_replace('[' . $fieldId . ']', $displayVal, $rowOutput);
                if ($fieldKey) {
                    $rowOutput = str_replace('[' . $fieldKey . ']', $displayVal, $rowOutput);
                }
            }

            // Clean up any remaining unreplaced field tags
            $rowOutput = preg_replace('/\[[a-zA-Z0-9_\-]+\]/', '', $rowOutput);

            $rowsHtml .= $rowOutput;
        }

        $fullHtml = $beforeContent . $rowsHtml . $afterContent;

        // Process any nested shortcodes in before/after content (e.g. form search shortcodes).
        // The wrapper scopes the plugin's front-end CSS without changing the view's own markup.
        wp_enqueue_style('sm-forms-front');
        return '<div class="' . ThemeClasses::SCOPE . ' sm-view">' . do_shortcode($fullHtml) . '</div>';
    }

    /**
     * Format field value for display
     */
    private static function formatFieldValue($val, string $type): string {
        if (is_array($val)) {
            return esc_html(implode(', ', $val));
        }

        $unserialized = maybe_unserialize($val);
        if (is_array($unserialized)) {
            return esc_html(implode(', ', $unserialized));
        }

        // If file attachment ID
        if ($type === 'file' && is_numeric($val) && (int)$val > 0) {
            $imgUrl = wp_get_attachment_image_url((int)$val, 'thumbnail');
            if ($imgUrl) {
                return '<img src="' . esc_url($imgUrl) . '" class="sm-thumb rounded shadow-sm" alt="" />';
            }
        }

        return esc_html((string)$val);
    }
}
