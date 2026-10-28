<?php

namespace ScoutingMemories\Forms\Rest;

use ScoutingMemories\Forms\Forms\EntryService;
use ScoutingMemories\Forms\Forms\Rendering\FieldRenderer;
use ScoutingMemories\Forms\Models\EntryRepository;
use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Support\Permissions;
use ScoutingMemories\Forms\Support\TestData;
use ScoutingMemories\Forms\Views\EntryValues;
use WP_REST_Request;
use WP_REST_Response;

/**
 * ApiController
 *
 * REST API for the admin builder (wp-json/scouting-forms/v1). Every route checks the capability
 * Formidable would (Permissions::can accepts the frm_* or the plugin's sm_* capability): forms need
 * view/edit_forms, views need edit_displays, entries need view/create/edit/delete_entries.
 * Entries are created and changed through EntryService, so the admin gets the same validation and
 * form actions as the front end; deleting removes child entries and trashes a linked post.
 * Request data is never unserialized; view HTML is filtered for people without unfiltered_html.
 */
class ApiController {

    private const NAMESPACE = 'scouting-forms/v1';

    public static function registerHooks(): void {
        add_action('rest_api_init', [__CLASS__, 'registerRoutes']);
    }

    public static function registerRoutes(): void {
        $can = static function (string $cap): callable {
            return static function () use ($cap): bool {
                return Permissions::can($cap);
            };
        };

        register_rest_route(self::NAMESPACE, '/forms', [
            'methods' => 'GET', 'callback' => [__CLASS__, 'getForms'], 'permission_callback' => $can('view_forms'),
        ]);
        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'getForm'], 'permission_callback' => $can('view_forms')],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'updateForm'], 'permission_callback' => $can('edit_forms')],
        ]);
        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)/builder', [
            'methods' => 'GET', 'callback' => [__CLASS__, 'getFormBuilder'], 'permission_callback' => $can('view_forms'),
        ]);
        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)/actions', [
            'methods' => 'POST', 'callback' => [__CLASS__, 'createAction'], 'permission_callback' => $can('edit_forms'),
        ]);
        register_rest_route(self::NAMESPACE, '/actions/(?P<id>\d+)', [
            ['methods' => 'POST', 'callback' => [__CLASS__, 'updateAction'], 'permission_callback' => $can('edit_forms')],
            ['methods' => 'DELETE', 'callback' => [__CLASS__, 'deleteAction'], 'permission_callback' => $can('delete_forms')],
        ]);
        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)/fields', [
            'methods' => 'POST', 'callback' => [__CLASS__, 'saveFormFields'], 'permission_callback' => $can('edit_forms'),
        ]);
        register_rest_route(self::NAMESPACE, '/fields/(?P<id>\d+)', [
            'methods' => 'DELETE', 'callback' => [__CLASS__, 'deleteField'], 'permission_callback' => $can('delete_forms'),
        ]);

        register_rest_route(self::NAMESPACE, '/views', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'getViews'], 'permission_callback' => $can('edit_displays')],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'createView'], 'permission_callback' => $can('edit_displays')],
        ]);
        register_rest_route(self::NAMESPACE, '/views/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'getView'], 'permission_callback' => $can('edit_displays')],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'updateView'], 'permission_callback' => $can('edit_displays')],
            ['methods' => 'DELETE', 'callback' => [__CLASS__, 'deleteView'], 'permission_callback' => $can('edit_displays')],
        ]);

        register_rest_route(self::NAMESPACE, '/entries', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'getEntries'], 'permission_callback' => $can('view_entries')],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'createEntry'], 'permission_callback' => $can('create_entries')],
        ]);
        register_rest_route(self::NAMESPACE, '/entries/export', [
            'methods' => 'GET', 'callback' => [__CLASS__, 'exportEntries'], 'permission_callback' => $can('view_entries'),
        ]);
        register_rest_route(self::NAMESPACE, '/entries/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'getEntry'], 'permission_callback' => $can('view_entries')],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'updateEntry'], 'permission_callback' => $can('edit_entries')],
            ['methods' => 'DELETE', 'callback' => [__CLASS__, 'deleteEntry'], 'permission_callback' => $can('delete_entries')],
        ]);
    }

    // ------------------------------------------------------------------ forms

    public static function getForms(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT f.id, f.form_key, f.name, f.description, f.status, f.created_at, f.parent_form_id,
                (SELECT COUNT(*) FROM {$wpdb->prefix}frm_fields fi WHERE fi.form_id = f.id AND fi.type <> 'end_divider') AS field_count,
                (SELECT COUNT(*) FROM {$wpdb->prefix}frm_items it WHERE it.form_id = f.id AND it.is_draft = 0) AS entry_count
             FROM {$wpdb->prefix}frm_forms f
             WHERE f.status IN ('published', 'draft') AND f.is_template = 0
             ORDER BY f.name ASC",
            ARRAY_A
        );
        return new WP_REST_Response(['forms' => $results ?: []], 200);
    }

    public static function getForm(WP_REST_Request $request): WP_REST_Response {
        $form = FormRepository::find((int) $request->get_param('id'));
        if (!$form) {
            return new WP_REST_Response(['message' => __('Form not found.', 'scouting-forms')], 404);
        }
        $fields = [];
        foreach (FormRepository::fields((int) $form['id']) as $field) {
            $fields[] = self::fieldForClient($field);
        }
        return new WP_REST_Response(['form' => $form, 'fields' => $fields], 200);
    }

    public static function updateForm(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $formId = (int) $request->get_param('id');
        $form = FormRepository::find($formId);
        if (!$form) {
            return new WP_REST_Response(['message' => __('Form not found.', 'scouting-forms')], 404);
        }
        $data = (array) $request->get_json_params();

        $row = $wpdb->get_row($wpdb->prepare("SELECT options FROM {$wpdb->prefix}frm_forms WHERE id = %d", $formId), ARRAY_A);
        $options = maybe_unserialize($row['options'] ?? '');
        $options = is_array($options) ? $options : [];
        foreach (['submit_value' => 'sanitize_text_field', 'success_msg' => [self::class, 'settingsHtml'], 'edit_msg' => [self::class, 'settingsHtml'], 'edit_value' => 'sanitize_text_field'] as $key => $clean) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $options[$key] = $clean($data[$key]);
            }
        }

        $wpdb->update($wpdb->prefix . 'frm_forms', [
            'name' => sanitize_text_field((string) ($data['name'] ?? $form['name'])),
            'form_key' => sanitize_title((string) ($data['form_key'] ?? $form['key'])),
            'description' => self::settingsHtml((string) ($data['description'] ?? $form['description'])),
            'options' => maybe_serialize($options),
        ], ['id' => $formId], ['%s', '%s', '%s', '%s'], ['%d']);

        self::purgeCache();
        return new WP_REST_Response(['success' => true, 'message' => __('Form updated.', 'scouting-forms')], 200);
    }

    /**
     * Save the form's fields: {fields: [...in their new order...], deleted: [ids]}.
     * See FormBuilder::saveFields for what is checked and kept.
     */
    public static function saveFormFields(WP_REST_Request $request): WP_REST_Response {
        $formId = (int) $request->get_param('id');
        if (!FormRepository::find($formId)) {
            return new WP_REST_Response(['message' => __('Form not found.', 'scouting-forms')], 404);
        }
        $result = FormBuilder::saveFields($formId, (array) $request->get_json_params());
        if (!$result['ok']) {
            return new WP_REST_Response(['success' => false, 'message' => $result['message'], 'errors' => (object) $result['errors']], 422);
        }
        return new WP_REST_Response(['success' => true, 'message' => $result['message']] + (array) FormBuilder::forBuilder($formId), 200);
    }

    public static function getFormBuilder(WP_REST_Request $request): WP_REST_Response {
        $data = FormBuilder::forBuilder((int) $request->get_param('id'));
        if (!$data) {
            return new WP_REST_Response(['message' => __('Form not found.', 'scouting-forms')], 404);
        }
        $data['can_delete'] = Permissions::can('delete_forms');
        return new WP_REST_Response($data, 200);
    }

    public static function createAction(WP_REST_Request $request): WP_REST_Response {
        $formId = (int) $request->get_param('id');
        $type = (string) ($request->get_json_params()['type'] ?? '');
        $id = FormBuilder::createAction($formId, $type);
        if (!$id) {
            return new WP_REST_Response(['message' => __('This action cannot be added here.', 'scouting-forms')], 400);
        }
        return new WP_REST_Response(['success' => true, 'id' => $id] + (array) FormBuilder::forBuilder($formId), 201);
    }

    public static function updateAction(WP_REST_Request $request): WP_REST_Response {
        $post = self::actionPost((int) $request->get_param('id'));
        if (!$post) {
            return new WP_REST_Response(['message' => __('Action not found.', 'scouting-forms')], 404);
        }
        $result = FormBuilder::saveAction($post, (array) $request->get_json_params());
        if (!$result['ok']) {
            return new WP_REST_Response(['success' => false, 'message' => $result['message'], 'errors' => (object) $result['errors']], 422);
        }
        return new WP_REST_Response(['success' => true, 'message' => $result['message']] + (array) FormBuilder::forBuilder((int) $post->menu_order), 200);
    }

    public static function deleteAction(WP_REST_Request $request): WP_REST_Response {
        $post = self::actionPost((int) $request->get_param('id'));
        if (!$post || !in_array($post->post_excerpt, FormBuilder::EDITABLE_ACTIONS, true)) {
            return new WP_REST_Response(['message' => __('This action cannot be deleted here.', 'scouting-forms')], 404);
        }
        // To the trash, so it can be restored
        wp_trash_post($post->ID);
        FormBuilder::purgeCache();
        return new WP_REST_Response(['success' => true] + (array) FormBuilder::forBuilder((int) $post->menu_order), 200);
    }

    private static function actionPost(int $id): ?\WP_Post {
        $post = get_post($id);
        return $post && $post->post_type === 'frm_form_actions' && in_array($post->post_status, ['publish', 'draft'], true) ? $post : null;
    }

    public static function deleteField(WP_REST_Request $request): WP_REST_Response {
        $fieldId = (int) $request->get_param('id');
        $field = FormRepository::field($fieldId);
        if (!$field) {
            return new WP_REST_Response(['message' => __('Field not found.', 'scouting-forms')], 404);
        }
        $blocker = FormBuilder::deleteBlocker($field, [], FormBuilder::themeFieldIds());
        if ($blocker !== '') {
            return new WP_REST_Response(['message' => $blocker], 409);
        }
        FormBuilder::deleteFieldWithAnswers($field);
        self::purgeCache();
        return new WP_REST_Response(['success' => true, 'message' => __('Field deleted.', 'scouting-forms')], 200);
    }

    // ------------------------------------------------------------------ views

    public static function getViews(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT p.ID AS id, p.post_title AS title, p.post_name AS slug, p.post_status AS status,
                pm1.meta_value AS form_id, pm2.meta_value AS show_count, f.name AS form_name
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'frm_form_id'
             LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'frm_show_count'
             LEFT JOIN {$wpdb->prefix}frm_forms f ON pm1.meta_value = f.id
             WHERE p.post_type = 'frm_display' AND p.post_status IN ('publish', 'private', 'draft')
             ORDER BY p.post_title ASC",
            ARRAY_A
        );
        return new WP_REST_Response(['views' => $results ?: []], 200);
    }

    public static function getView(WP_REST_Request $request): WP_REST_Response {
        $viewId = (int) $request->get_param('id');
        $post = get_post($viewId);
        if (!$post || $post->post_type !== 'frm_display') {
            return new WP_REST_Response(['message' => __('View not found.', 'scouting-forms')], 404);
        }
        $formId = (int) get_post_meta($viewId, 'frm_form_id', true);
        $options = maybe_unserialize(get_post_meta($viewId, 'frm_options', true));
        $options = is_array($options) ? $options : [];
        $fields = [];
        foreach (FormRepository::fields($formId) as $field) {
            $fields[] = ['id' => $field['id'], 'name' => $field['name'], 'field_key' => $field['key'], 'type' => $field['type']];
        }
        return new WP_REST_Response([
            'view' => [
                'id' => $post->ID,
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'form_id' => $formId,
                'show_count' => get_post_meta($viewId, 'frm_show_count', true) ?: 'all',
                'content' => $post->post_content,
                'detail' => (string) get_post_meta($viewId, 'frm_dyncontent', true),
                'before_content' => $options['before_content'] ?? '',
                'after_content' => $options['after_content'] ?? '',
                'empty_msg' => $options['empty_msg'] ?? '',
                'page_size' => $options['page_size'] ?? '',
                'limit' => $options['limit'] ?? '',
                'where' => array_values((array) ($options['where'] ?? [])),
                'where_is' => array_values((array) ($options['where_is'] ?? [])),
                'where_val' => array_values((array) ($options['where_val'] ?? [])),
                'order_by' => array_values((array) ($options['order_by'] ?? [])),
                'order' => array_values((array) ($options['order'] ?? [])),
            ],
            'available_fields' => $fields,
        ], 200);
    }

    /**
     * HTML saved in form settings (descriptions, messages, HTML fields) is shown as saved, so it is
     * filtered here for anyone WordPress does not allow raw HTML, as for posts.
     */
    public static function settingsHtml(string $value): string {
        return current_user_can('unfiltered_html') ? $value : wp_kses_post($value);
    }

    public static function updateView(WP_REST_Request $request): WP_REST_Response {
        $viewId = (int) $request->get_param('id');
        $post = get_post($viewId);
        if (!$post || $post->post_type !== 'frm_display') {
            return new WP_REST_Response(['message' => __('View not found.', 'scouting-forms')], 404);
        }
        $data = (array) $request->get_json_params();
        $html = static function ($value): string {
            $value = is_string($value) ? $value : '';
            // View templates are HTML with shortcodes; only people allowed raw HTML keep it as is
            return current_user_can('unfiltered_html') ? $value : wp_kses_post($value);
        };

        $title = sanitize_text_field((string) ($data['title'] ?? $post->post_title));
        $content = $html($data['content'] ?? $post->post_content);
        // Only a real change is written (and dated)
        if ($title !== $post->post_title || $content !== $post->post_content) {
            wp_update_post(wp_slash(['ID' => $viewId, 'post_title' => $title, 'post_content' => $content]));
        }
        update_post_meta($viewId, 'frm_form_id', (int) ($data['form_id'] ?? get_post_meta($viewId, 'frm_form_id', true)));
        $show = (string) ($data['show_count'] ?? 'all');
        update_post_meta($viewId, 'frm_show_count', in_array($show, ['all', 'one', 'dynamic', 'calendar'], true) ? $show : 'all');
        if (isset($data['detail'])) {
            update_post_meta($viewId, 'frm_dyncontent', wp_slash($html($data['detail'])));
        }

        $options = maybe_unserialize(get_post_meta($viewId, 'frm_options', true));
        $options = is_array($options) ? $options : [];
        $options['before_content'] = $html($data['before_content'] ?? ($options['before_content'] ?? ''));
        $options['after_content'] = $html($data['after_content'] ?? ($options['after_content'] ?? ''));
        $options['empty_msg'] = $html($data['empty_msg'] ?? ($options['empty_msg'] ?? ''));
        // A blank number removes paging / the limit
        foreach (['page_size', 'limit'] as $key) {
            if (array_key_exists($key, $data) && is_scalar($data[$key])) {
                $new = trim((string) $data[$key]) === '' ? '' : absint($data[$key]);
                // "25" stored by Formidable and 25 sent back are the same setting
                if ((string) $new !== (string) ($options[$key] ?? '')) {
                    $options[$key] = $new;
                }
            }
        }
        // Filter and sort rows: only the view form's fields / entry columns and known operators
        if (isset($data['where']) || isset($data['order_by'])) {
            $rules = FormBuilder::cleanViewRules([
                'where' => $data['where'] ?? ($options['where'] ?? []),
                'where_is' => $data['where_is'] ?? ($options['where_is'] ?? []),
                'where_val' => $data['where_val'] ?? ($options['where_val'] ?? []),
                'order_by' => $data['order_by'] ?? ($options['order_by'] ?? []),
                'order' => $data['order'] ?? ($options['order'] ?? []),
            ], (int) get_post_meta($viewId, 'frm_form_id', true));
            foreach ($rules as $key => $list) {
                // Unchanged rules keep Formidable's stored form (its lists are numbered from 1)
                $stored = array_values(array_map('strval', array_filter((array) ($options[$key] ?? []), 'is_scalar')));
                if ($stored === $list && (isset($options[$key]) || $list !== [])) {
                    continue;
                }
                if (!isset($options[$key]) && $list === []) {
                    continue;
                }
                $options[$key] = $list;
            }
        }
        update_post_meta($viewId, 'frm_options', $options);

        self::purgeCache();
        return new WP_REST_Response(['success' => true, 'message' => __('View updated.', 'scouting-forms')], 200);
    }

    public static function createView(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        $formId = (int) ($data['form_id'] ?? 0);
        if (!FormRepository::find($formId)) {
            return new WP_REST_Response(['message' => __('Choose a form for the view.', 'scouting-forms')], 400);
        }
        $viewId = wp_insert_post([
            'post_title' => sanitize_text_field((string) ($data['title'] ?? __('New View', 'scouting-forms'))),
            'post_type' => 'frm_display',
            'post_status' => 'publish',
            'post_content' => '<tr><td>[id]</td></tr>',
        ], true);
        if (is_wp_error($viewId)) {
            return new WP_REST_Response(['message' => $viewId->get_error_message()], 500);
        }
        TestData::markPost((int) $viewId);
        update_post_meta($viewId, 'frm_form_id', $formId);
        update_post_meta($viewId, 'frm_show_count', 'all');
        update_post_meta($viewId, 'frm_options', [
            'before_content' => '<table class="table"><tbody>',
            'after_content' => '</tbody></table>',
            'empty_msg' => __('No Entries Found', 'scouting-forms'),
            'page_size' => 25,
        ]);
        self::purgeCache();
        return new WP_REST_Response(['success' => true, 'id' => $viewId], 201);
    }

    public static function deleteView(WP_REST_Request $request): WP_REST_Response {
        $viewId = (int) $request->get_param('id');
        $post = get_post($viewId);
        if (!$post || $post->post_type !== 'frm_display') {
            return new WP_REST_Response(['message' => __('View not found.', 'scouting-forms')], 404);
        }
        // Moved to the trash, so it can be restored
        wp_trash_post($viewId);
        self::purgeCache();
        return new WP_REST_Response(['success' => true], 200);
    }

    // ------------------------------------------------------------------ entries

    /**
     * /entries?form_id=8&page=1&limit=25&search=ohio&orderby=created_at&order=desc
     * Each entry comes with the display values of the form's first few fields (list columns).
     */
    public static function getEntries(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $formId = (int) $request->get_param('form_id');
        $form = FormRepository::find($formId);
        if (!$form) {
            return new WP_REST_Response(['message' => __('Choose a form.', 'scouting-forms')], 400);
        }
        $page = max(1, (int) $request->get_param('page'));
        $limit = min(100, max(10, (int) ($request->get_param('limit') ?: 25)));
        $search = trim(sanitize_text_field((string) $request->get_param('search')));
        $orderby = in_array($request->get_param('orderby'), ['id', 'created_at', 'updated_at', 'name'], true) ? (string) $request->get_param('orderby') : 'created_at';
        $order = strtolower((string) $request->get_param('order')) === 'asc' ? 'ASC' : 'DESC';

        $where = $wpdb->prepare('i.form_id = %d AND i.is_draft = 0', $formId);
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(
                " AND (i.item_key LIKE %s OR i.name LIKE %s OR i.id = %d OR i.id IN (SELECT item_id FROM {$wpdb->prefix}frm_item_metas WHERE meta_value LIKE %s))",
                $like,
                $like,
                ctype_digit($search) ? (int) $search : 0,
                $like
            );
        }
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}frm_items i WHERE {$where}");
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT i.id, i.item_key, i.name, i.user_id, i.post_id, i.created_at, i.updated_at FROM {$wpdb->prefix}frm_items i WHERE {$where} ORDER BY i.{$orderby} {$order}, i.id {$order} LIMIT %d OFFSET %d",
            $limit,
            ($page - 1) * $limit
        ), ARRAY_A);

        $columns = self::listColumns($formId);
        $values = new EntryValues($formId);
        $values->load(array_map(static fn($r) => (int) $r['id'], $rows));
        foreach ($rows as &$row) {
            $row['columns'] = [];
            foreach ($columns as $column) {
                $row['columns'][(string) $column['id']] = wp_strip_all_tags(html_entity_decode($values->display((int) $row['id'], (int) $column['id']), ENT_QUOTES));
            }
            $user = $row['user_id'] ? get_userdata((int) $row['user_id']) : null;
            $row['user'] = $user ? $user->display_name : '';
        }
        unset($row);

        return new WP_REST_Response([
            'entries' => $rows,
            'columns' => $columns,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $limit)),
        ], 200);
    }

    public static function getEntry(WP_REST_Request $request): WP_REST_Response {
        $entry = EntryRepository::find((int) $request->get_param('id'));
        if (!$entry) {
            return new WP_REST_Response(['message' => __('Entry not found.', 'scouting-forms')], 404);
        }
        $fields = FormRepository::fields((int) $entry['form_id']);
        $raw = EntryService::withoutPasswords($fields, EntryRepository::formValues((int) $entry['id'], $fields));
        $display = new EntryValues((int) $entry['form_id']);
        $display->load([(int) $entry['id']]);
        $shown = [];
        $clientFields = [];
        foreach ($fields as $field) {
            $clientFields[] = self::fieldForClient($field, $raw);
            $shown[(string) $field['id']] = wp_strip_all_tags(html_entity_decode($display->display((int) $entry['id'], (int) $field['id']), ENT_QUOTES));
        }
        return new WP_REST_Response([
            'entry' => $entry,
            'metas' => (object) $raw,
            'display' => (object) $shown,
            'fields' => $clientFields,
        ], 200);
    }

    public static function updateEntry(WP_REST_Request $request): WP_REST_Response {
        $entry = EntryRepository::find((int) $request->get_param('id'));
        if (!$entry) {
            return new WP_REST_Response(['message' => __('Entry not found.', 'scouting-forms')], 404);
        }
        $form = FormRepository::find((int) $entry['form_id']);
        $data = (array) $request->get_json_params();
        $result = EntryService::submit($form, self::postedFromClient((array) ($data['metas'] ?? [])), [
            'entry_id' => (int) $entry['id'], 'admin' => true, 'spam_check' => false, 'uploads' => false,
        ]);
        return self::resultResponse($result, 200);
    }

    public static function createEntry(WP_REST_Request $request): WP_REST_Response {
        $data = (array) $request->get_json_params();
        $form = FormRepository::find((int) ($data['form_id'] ?? 0));
        if (!$form) {
            return new WP_REST_Response(['message' => __('Choose a form.', 'scouting-forms')], 400);
        }
        $result = EntryService::submit($form, self::postedFromClient((array) ($data['metas'] ?? [])), [
            'admin' => true, 'spam_check' => false, 'uploads' => false,
        ]);
        return self::resultResponse($result, 201);
    }

    public static function deleteEntry(WP_REST_Request $request): WP_REST_Response {
        $entryId = (int) $request->get_param('id');
        if (!EntryRepository::find($entryId)) {
            return new WP_REST_Response(['message' => __('Entry not found.', 'scouting-forms')], 404);
        }
        EntryRepository::delete($entryId);
        self::purgeCache();
        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * /entries/export?form_id=8[&search=...]: CSV of the form's entries with display values.
     */
    public static function exportEntries(WP_REST_Request $request) {
        global $wpdb;
        $formId = (int) $request->get_param('form_id');
        $form = FormRepository::find($formId);
        if (!$form) {
            return new WP_REST_Response(['message' => __('Choose a form.', 'scouting-forms')], 400);
        }
        $fields = array_values(array_filter(FormRepository::fields($formId), static function ($f) {
            return !in_array($f['type'], array_merge(FieldRenderer::NON_INPUT_TYPES, ['password']), true);
        }));
        $ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}frm_items WHERE form_id = %d AND is_draft = 0 ORDER BY created_at ASC, id ASC",
            $formId
        )));

        $out = fopen('php://temp', 'w+');
        fputcsv($out, array_merge(array_map(static fn($f) => $f['name'], $fields), ['Entry ID', 'Entry Key', 'Created', 'Updated', 'User']));
        $values = new EntryValues($formId);
        foreach (array_chunk($ids, 500) as $chunk) {
            $values->load($chunk);
            foreach ($chunk as $id) {
                $entry = $values->entry($id);
                $line = [];
                foreach ($fields as $field) {
                    $line[] = self::csvCell(wp_strip_all_tags(html_entity_decode($values->display($id, (int) $field['id']), ENT_QUOTES)));
                }
                $user = !empty($entry['user_id']) ? get_userdata((int) $entry['user_id']) : null;
                fputcsv($out, array_merge($line, [$id, $entry['item_key'] ?? '', $entry['created_at'] ?? '', $entry['updated_at'] ?? '', $user ? $user->user_login : '']));
            }
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        $filename = sanitize_file_name($form['key'] . '-entries-' . gmdate('Y-m-d') . '.csv');
        $response = new WP_REST_Response(null, 200);
        // Send the file instead of JSON, for this response only
        $serve = static function ($served, $result) use (&$serve, $response, $csv, $filename) {
            if ($result !== $response) {
                return $served;
            }
            remove_filter('rest_pre_serve_request', $serve, 10);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo "\xEF\xBB\xBF" . $csv; // BOM so Excel reads UTF-8
            return true;
        };
        add_filter('rest_pre_serve_request', $serve, 10, 2);
        return $response;
    }

    // ------------------------------------------------------------------ helpers

    /**
     * A field as the builder uses it, with its choices when it has any.
     *
     * @param array<string, mixed> $field
     * @param array<int, mixed> $values
     * @return array<string, mixed>
     */
    private static function fieldForClient(array $field, array $values = []): array {
        $out = [
            'id' => (int) $field['id'],
            'name' => $field['name'],
            'description' => $field['description'],
            'description_text' => trim(wp_strip_all_tags(html_entity_decode($field['description'], ENT_QUOTES))),
            'field_key' => $field['key'],
            'type' => $field['type'],
            'required' => (bool) $field['required'],
            'default_value' => is_scalar($field['default_value']) ? (string) $field['default_value'] : '',
            'options' => $field['options'],
            'field_options' => array_intersect_key($field['field_options'], array_flip([
                'classes', 'placeholder', 'label', 'data_type', 'multiple', 'form_select', 'hide_field',
                'hide_field_cond', 'hide_opt', 'show_hide', 'any_all', 'in_section', 'repeat', 'post_field',
            ])),
        ];
        if (in_array($field['type'], ['select', 'radio', 'checkbox', 'data'], true)) {
            $choices = [];
            foreach (FieldRenderer::choiceList($field, $values) as $value => $label) {
                $choices[] = ['value' => (string) $value, 'label' => $label];
            }
            $out['choices'] = $choices;
        }
        return $out;
    }

    /**
     * The builder sends {field_id: value}; shape it like a posted form (item_meta).
     *
     * @param array<int|string, mixed> $metas
     * @return array<int|string, mixed>
     */
    private static function postedFromClient(array $metas): array {
        $posted = [];
        foreach ($metas as $key => $value) {
            if (is_numeric($key) || preg_match('/^conf_\d+$/', (string) $key)) {
                $posted[is_numeric($key) ? (int) $key : (string) $key] = is_array($value)
                    ? map_deep($value, static fn($v) => is_scalar($v) ? (string) $v : '')
                    : (is_scalar($value) ? (string) $value : '');
            }
        }
        return $posted;
    }

    /**
     * @param array<string, mixed> $result From EntryService::submit
     */
    private static function resultResponse(array $result, int $status): WP_REST_Response {
        if (!$result['ok']) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result['form_error'] !== '' ? $result['form_error'] : __('Please correct the marked fields.', 'scouting-forms'),
                'errors' => (object) $result['errors'],
            ], $result['form_error'] !== '' ? 403 : 422);
        }
        self::purgeCache();
        return new WP_REST_Response(['success' => true, 'id' => $result['entry_id']], $status);
    }

    /**
     * First few answer fields shown as columns in the entries list.
     *
     * @return array<int, array{id:int, name:string}>
     */
    private static function listColumns(int $formId): array {
        $columns = [];
        foreach (FormRepository::fields($formId) as $field) {
            if (in_array($field['type'], array_merge(FieldRenderer::NON_INPUT_TYPES, ['password', 'hidden', 'user_id', 'file']), true)) {
                continue;
            }
            $columns[] = ['id' => (int) $field['id'], 'name' => $field['name']];
            if (count($columns) === 4) {
                break;
            }
        }
        return $columns;
    }

    /**
     * Cells starting with = + - @ are prefixed so spreadsheets do not run them as formulas.
     */
    private static function csvCell(string $value): string {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'" . $value : $value;
    }

    private static function purgeCache(): void {
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('frm_entry');
        }
        if (class_exists('WpeCommon') && method_exists('WpeCommon', 'purge_memcached')) {
            \WpeCommon::purge_memcached();
        }
    }
}
