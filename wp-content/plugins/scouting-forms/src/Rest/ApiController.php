<?php

namespace ScoutingMemories\Forms\Rest;

use ScoutingMemories\Forms\Models\EntryRepository;
use ScoutingMemories\Forms\Support\TestData;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * ApiController
 *
 * Provides REST API endpoints for Forms, Fields, Views, and Entries management.
 * 100% compatible with existing Formidable database tables with zero data loss.
 * Built according to WP Engine hosting guidelines (object caching, prepared queries, no PHP sessions).
 */
class ApiController {

    const NAMESPACE = 'scouting-forms/v1';

    public static function registerHooks(): void {
        add_action('rest_api_init', [__CLASS__, 'registerRoutes']);
    }

    public static function registerRoutes(): void {
        // Forms
        register_rest_route(self::NAMESPACE, '/forms', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'getForms'],
            'permission_callback' => [__CLASS__, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'getForm'],
                'permission_callback' => [__CLASS__, 'checkAdminPermission'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [__CLASS__, 'updateForm'],
                'permission_callback' => [__CLASS__, 'checkAdminPermission'],
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)/fields', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'saveFormFields'],
            'permission_callback' => [__CLASS__, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/fields/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [__CLASS__, 'deleteField'],
            'permission_callback' => [__CLASS__, 'checkAdminPermission'],
        ]);

        // Views
        register_rest_route(self::NAMESPACE, '/views', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'getViews'],
                'permission_callback' => [__CLASS__, 'checkAdminPermission'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'createView'],
                'permission_callback' => [__CLASS__, 'checkAdminPermission'],
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/views/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'getView'],
                'permission_callback' => [__CLASS__, 'checkAdminPermission'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [__CLASS__, 'updateView'],
                'permission_callback' => [__CLASS__, 'checkAdminPermission'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [__CLASS__, 'deleteView'],
                'permission_callback' => [__CLASS__, 'checkAdminPermission'],
            ]
        ]);

        // Entries
        register_rest_route(self::NAMESPACE, '/entries', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'getEntries'],
                'permission_callback' => [__CLASS__, 'checkAdminPermission'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'createEntry'],
                'permission_callback' => [__CLASS__, 'checkAdminPermission'],
            ]
        ]);

        register_rest_route(self::NAMESPACE, '/entries/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'getEntry'],
                'permission_callback' => [__CLASS__, 'checkAdminPermission'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [__CLASS__, 'updateEntry'],
                'permission_callback' => [__CLASS__, 'checkAdminPermission'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [__CLASS__, 'deleteEntry'],
                'permission_callback' => [__CLASS__, 'checkAdminPermission'],
            ]
        ]);
    }

    public static function checkAdminPermission(): bool {
        return current_user_can('manage_options');
    }

    /**
     * Purge caches on mutation (WP Engine compatible)
     */
    private static function purgeCache(string $key = ''): void {
        if ($key) {
            wp_cache_delete($key);
        }
        wp_cache_delete('sm_archive_counts');

        if (class_exists('WpeCommon') && method_exists('WpeCommon', 'purge_memcached')) {
            \WpeCommon::purge_memcached();
        }
    }

    // ==========================================
    // FORMS
    // ==========================================

    public static function getForms(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $forms_table  = $wpdb->prefix . 'frm_forms';
        $fields_table = $wpdb->prefix . 'frm_fields';
        $items_table  = $wpdb->prefix . 'frm_items';

        $query = "
            SELECT 
                f.id,
                f.form_key,
                f.name,
                f.description,
                f.status,
                f.created_at,
                (SELECT COUNT(*) FROM {$fields_table} fi WHERE fi.form_id = f.id AND fi.type NOT IN ('end_divider')) AS field_count,
                (SELECT COUNT(*) FROM {$items_table} it WHERE it.form_id = f.id) AS entry_count
            FROM {$forms_table} f
            WHERE f.status = 'published' OR f.status = 'draft'
            ORDER BY f.name ASC
        ";

        $results = $wpdb->get_results($query, ARRAY_A);
        return new WP_REST_Response(['forms' => $results ?: []], 200);
    }

    public static function getForm(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $form_id      = (int) $request->get_param('id');
        $forms_table  = $wpdb->prefix . 'frm_forms';
        $fields_table = $wpdb->prefix . 'frm_fields';

        $form = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$forms_table} WHERE id = %d", $form_id),
            ARRAY_A
        );

        if (!$form) {
            return new WP_REST_Response(['error' => 'Form not found'], 404);
        }

        $form['options'] = maybe_unserialize($form['options']);

        // Fetch fields ordered by field_order
        $fields = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$fields_table} WHERE form_id = %d ORDER BY field_order ASC, id ASC", $form_id),
            ARRAY_A
        );

        $parsed_fields = [];
        if ($fields) {
            foreach ($fields as $f) {
                $f['field_options'] = maybe_unserialize($f['field_options']);
                $f['options']       = maybe_unserialize($f['options']);
                $f['required']      = (bool) $f['required'];
                $f['field_order']   = (int) $f['field_order'];
                $parsed_fields[]    = $f;
            }
        }

        return new WP_REST_Response([
            'form'   => $form,
            'fields' => $parsed_fields
        ], 200);
    }

    public static function updateForm(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $form_id = (int) $request->get_param('id');
        $data    = $request->get_json_params();

        $forms_table = $wpdb->prefix . 'frm_forms';

        $name        = sanitize_text_field($data['name'] ?? '');
        $form_key    = sanitize_text_field($data['form_key'] ?? '');
        $description = wp_kses_post($data['description'] ?? '');

        // Fetch existing form to merge options
        $existing = $wpdb->get_row($wpdb->prepare("SELECT options FROM {$forms_table} WHERE id = %d", $form_id), ARRAY_A);
        $options  = maybe_unserialize($existing['options'] ?? []);

        if (isset($data['submit_value'])) {
            $options['submit_value'] = sanitize_text_field($data['submit_value']);
        }
        if (isset($data['success_msg'])) {
            $options['success_msg'] = wp_kses_post($data['success_msg']);
        }

        $wpdb->update(
            $forms_table,
            [
                'name'        => $name,
                'form_key'    => $form_key,
                'description' => $description,
                'options'     => maybe_serialize($options)
            ],
            ['id' => $form_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        self::purgeCache();
        return new WP_REST_Response(['success' => true, 'message' => 'Form updated successfully'], 200);
    }

    public static function saveFormFields(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $form_id = (int) $request->get_param('id');
        $data    = $request->get_json_params();
        $fields  = $data['fields'] ?? [];

        $fields_table = $wpdb->prefix . 'frm_fields';

        foreach ($fields as $order => $f) {
            $field_id      = (int) ($f['id'] ?? 0);
            $name          = sanitize_text_field($f['name'] ?? '');
            $type          = sanitize_text_field($f['type'] ?? 'text');
            $field_key     = sanitize_text_field($f['field_key'] ?? ('field_' . uniqid()));
            $required      = !empty($f['required']) ? 1 : 0;
            $default_value = sanitize_text_field($f['default_value'] ?? '');
            $options       = maybe_serialize($f['options'] ?? []);

            $field_options = maybe_unserialize($f['field_options'] ?? []);
            if (!is_array($field_options)) $field_options = [];
            if (isset($f['classes'])) $field_options['classes'] = sanitize_text_field($f['classes']);
            if (isset($f['placeholder'])) $field_options['placeholder'] = sanitize_text_field($f['placeholder']);

            if ($field_id > 0) {
                // Update existing field
                $wpdb->update(
                    $fields_table,
                    [
                        'name'          => $name,
                        'type'          => $type,
                        'field_key'     => $field_key,
                        'required'      => $required,
                        'default_value' => $default_value,
                        'options'       => $options,
                        'field_options' => maybe_serialize($field_options),
                        'field_order'   => $order + 1
                    ],
                    ['id' => $field_id],
                    ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d'],
                    ['%d']
                );
            } else {
                // Insert new field
                $wpdb->insert(
                    $fields_table,
                    [
                        'form_id'       => $form_id,
                        'name'          => $name,
                        'type'          => $type,
                        'field_key'     => $field_key,
                        'required'      => $required,
                        'default_value' => $default_value,
                        'options'       => $options,
                        'field_options' => maybe_serialize($field_options),
                        'field_order'   => $order + 1,
                        'created_at'    => current_time('mysql', 1)
                    ],
                    ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s']
                );
            }
        }

        self::purgeCache();
        return new WP_REST_Response(['success' => true, 'message' => 'Fields saved successfully'], 200);
    }

    public static function deleteField(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $field_id     = (int) $request->get_param('id');
        $fields_table = $wpdb->prefix . 'frm_fields';

        $wpdb->delete($fields_table, ['id' => $field_id], ['%d']);
        self::purgeCache();

        return new WP_REST_Response(['success' => true, 'message' => 'Field deleted'], 200);
    }

    // ==========================================
    // VIEWS
    // ==========================================

    public static function getViews(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $forms_table = $wpdb->prefix . 'frm_forms';

        $query = "
            SELECT 
                p.ID as id,
                p.post_title as title,
                p.post_name as slug,
                pm1.meta_value as form_id,
                pm2.meta_value as show_count,
                f.name as form_name
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'frm_form_id'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'frm_show_count'
            LEFT JOIN {$forms_table} f ON pm1.meta_value = f.id
            WHERE p.post_type = 'frm_display'
            ORDER BY p.post_title ASC
        ";

        $results = $wpdb->get_results($query, ARRAY_A);
        return new WP_REST_Response(['views' => $results ?: []], 200);
    }

    public static function getView(WP_REST_Request $request): WP_REST_Response {
        $view_id = (int) $request->get_param('id');
        $post    = get_post($view_id);

        if (!$post || $post->post_type !== 'frm_display') {
            return new WP_REST_Response(['error' => 'View not found'], 404);
        }

        $form_id    = (int) get_post_meta($view_id, 'frm_form_id', true);
        $show_count = get_post_meta($view_id, 'frm_show_count', true) ?: 'all';
        $options    = get_post_meta($view_id, 'frm_options', true);
        if (!is_array($options)) $options = maybe_unserialize($options) ?: [];

        // Also fetch form fields for tag inserter
        global $wpdb;
        $fields_table = $wpdb->prefix . 'frm_fields';
        $fields = $wpdb->get_results(
            $wpdb->prepare("SELECT id, name, field_key, type FROM {$fields_table} WHERE form_id = %d ORDER BY field_order ASC", $form_id),
            ARRAY_A
        );

        return new WP_REST_Response([
            'view' => [
                'id'             => $post->ID,
                'title'          => $post->post_title,
                'slug'           => $post->post_name,
                'form_id'        => $form_id,
                'show_count'     => $show_count,
                'content'        => $post->post_content,
                'before_content' => $options['before_content'] ?? '',
                'after_content'  => $options['after_content'] ?? '',
                'empty_msg'      => $options['empty_msg'] ?? 'No Entries Found',
                'page_size'      => $options['page_size'] ?? 25,
                'where'          => $options['where'] ?? [],
                'where_is'       => $options['where_is'] ?? [],
                'where_val'      => $options['where_val'] ?? []
            ],
            'available_fields' => $fields ?: []
        ], 200);
    }

    public static function updateView(WP_REST_Request $request): WP_REST_Response {
        $view_id = (int) $request->get_param('id');
        $data    = $request->get_json_params();

        $title      = sanitize_text_field($data['title'] ?? '');
        $form_id    = (int) ($data['form_id'] ?? 0);
        $show_count = sanitize_text_field($data['show_count'] ?? 'all');
        $content    = $data['content'] ?? '';

        wp_update_post([
            'ID'           => $view_id,
            'post_title'   => $title,
            'post_content' => $content
        ]);

        update_post_meta($view_id, 'frm_form_id', $form_id);
        update_post_meta($view_id, 'frm_show_count', $show_count);

        $options = get_post_meta($view_id, 'frm_options', true);
        if (!is_array($options)) $options = maybe_unserialize($options) ?: [];

        $options['before_content'] = $data['before_content'] ?? '';
        $options['after_content']  = $data['after_content'] ?? '';
        $options['empty_msg']      = sanitize_text_field($data['empty_msg'] ?? 'No Entries Found');
        $options['page_size']      = (int) ($data['page_size'] ?? 25);
        $options['where']          = $data['where'] ?? [];
        $options['where_is']       = $data['where_is'] ?? [];
        $options['where_val']      = $data['where_val'] ?? [];

        update_post_meta($view_id, 'frm_options', maybe_serialize($options));

        self::purgeCache();
        return new WP_REST_Response(['success' => true, 'message' => 'View updated successfully'], 200);
    }

    public static function createView(WP_REST_Request $request): WP_REST_Response {
        $data    = $request->get_json_params();
        $title   = sanitize_text_field($data['title'] ?? 'New View');
        $form_id = (int) ($data['form_id'] ?? 8);

        $view_id = wp_insert_post([
            'post_title'   => $title,
            'post_type'    => 'frm_display',
            'post_status'  => 'publish',
            'post_content' => '<tr><td>[id]</td></tr>'
        ]);

        if (is_wp_error($view_id)) {
            return new WP_REST_Response(['error' => $view_id->get_error_message()], 500);
        }
        TestData::markPost((int) $view_id);

        update_post_meta($view_id, 'frm_form_id', $form_id);
        update_post_meta($view_id, 'frm_show_count', 'all');

        $options = [
            'before_content' => '<table class="table"><tbody>',
            'after_content'  => '</tbody></table>',
            'empty_msg'      => 'No Entries Found',
            'page_size'      => 25
        ];
        update_post_meta($view_id, 'frm_options', maybe_serialize($options));

        self::purgeCache();
        return new WP_REST_Response(['success' => true, 'id' => $view_id], 201);
    }

    public static function deleteView(WP_REST_Request $request): WP_REST_Response {
        $view_id = (int) $request->get_param('id');
        wp_delete_post($view_id, true);
        self::purgeCache();
        return new WP_REST_Response(['success' => true], 200);
    }

    // ==========================================
    // ENTRIES
    // ==========================================

    public static function getEntries(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $form_id = (int) $request->get_param('form_id');
        $page    = max(1, (int) $request->get_param('page'));
        $limit   = min(50, max(10, (int) ($request->get_param('limit') ?: 25)));
        $offset  = ($page - 1) * $limit;

        $items_table = $wpdb->prefix . 'frm_items';
        $metas_table = $wpdb->prefix . 'frm_item_metas';

        $where_sql = $form_id ? $wpdb->prepare("WHERE form_id = %d", $form_id) : "";

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$items_table} {$where_sql}");

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, item_key, name, user_id, form_id, created_at FROM {$items_table} {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return new WP_REST_Response([
            'entries' => $items ?: [],
            'total'   => $total,
            'page'    => $page,
            'pages'   => ceil($total / $limit)
        ], 200);
    }

    public static function getEntry(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $entry_id    = (int) $request->get_param('id');
        $items_table = $wpdb->prefix . 'frm_items';
        $metas_table = $wpdb->prefix . 'frm_item_metas';
        $fields_table= $wpdb->prefix . 'frm_fields';

        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$items_table} WHERE id = %d", $entry_id), ARRAY_A);
        if (!$item) {
            return new WP_REST_Response(['error' => 'Entry not found'], 404);
        }

        // Metas
        $metas = $wpdb->get_results(
            $wpdb->prepare("SELECT field_id, meta_value FROM {$metas_table} WHERE item_id = %d", $entry_id),
            ARRAY_A
        );

        $meta_map = [];
        if ($metas) {
            foreach ($metas as $m) {
                $meta_map[$m['field_id']] = $m['meta_value'];
            }
        }

        // Form fields
        $fields = $wpdb->get_results(
            $wpdb->prepare("SELECT id, name, type, field_key, field_order FROM {$fields_table} WHERE form_id = %d ORDER BY field_order ASC", $item['form_id']),
            ARRAY_A
        );

        return new WP_REST_Response([
            'entry'  => $item,
            'metas'  => $meta_map,
            'fields' => $fields ?: []
        ], 200);
    }

    public static function updateEntry(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $entry_id    = (int) $request->get_param('id');
        $data        = $request->get_json_params();
        $metas       = $data['metas'] ?? [];
        $metas_table = $wpdb->prefix . 'frm_item_metas';

        foreach ($metas as $field_id => $val) {
            $field_id = (int) $field_id;
            $val_str  = is_array($val) ? maybe_serialize($val) : sanitize_text_field($val);

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$metas_table} WHERE item_id = %d AND field_id = %d",
                $entry_id,
                $field_id
            ));

            if ($exists) {
                $wpdb->update($metas_table, ['meta_value' => $val_str], ['id' => $exists], ['%s'], ['%d']);
            } else {
                $wpdb->insert($metas_table, [
                    'item_id'    => $entry_id,
                    'field_id'   => $field_id,
                    'meta_value' => $val_str,
                    'created_at' => current_time('mysql', 1)
                ], ['%d', '%d', '%s', '%s']);
            }
        }

        self::purgeCache();
        return new WP_REST_Response(['success' => true, 'message' => 'Entry updated'], 200);
    }

    public static function createEntry(WP_REST_Request $request): WP_REST_Response {
        $data        = $request->get_json_params();
        $form_id     = (int) ($data['form_id'] ?? 0);
        $metas       = is_array($data['metas'] ?? null) ? $data['metas'] : [];

        $clean = [];
        foreach ($metas as $field_id => $val) {
            $clean[(int) $field_id] = is_array($val) ? map_deep($val, 'sanitize_text_field') : sanitize_text_field((string) $val);
        }

        $item_key = 'sm-' . wp_generate_password(10, false, false);
        $entry_id = EntryRepository::create($form_id, $clean, [
            'key' => $item_key,
            'name' => sanitize_text_field($data['name'] ?? $item_key),
        ]);
        if (!$entry_id) {
            return new WP_REST_Response(['success' => false, 'message' => __('The entry could not be saved.', 'scouting-forms')], 500);
        }

        self::purgeCache();
        return new WP_REST_Response(['success' => true, 'id' => $entry_id], 201);
    }

    public static function deleteEntry(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $entry_id    = (int) $request->get_param('id');
        $items_table = $wpdb->prefix . 'frm_items';
        $metas_table = $wpdb->prefix . 'frm_item_metas';

        $wpdb->delete($items_table, ['id' => $entry_id], ['%d']);
        $wpdb->delete($metas_table, ['item_id' => $entry_id], ['%d']);

        self::purgeCache();
        return new WP_REST_Response(['success' => true], 200);
    }
}
