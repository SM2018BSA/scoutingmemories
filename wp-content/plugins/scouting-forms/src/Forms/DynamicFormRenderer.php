<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Actions\ActionRunner;
use ScoutingMemories\Forms\Actions\RegisterAction;
use ScoutingMemories\Forms\Actions\EntryShortcodes;
use ScoutingMemories\Forms\Forms\Logic\FieldLogic;
use ScoutingMemories\Forms\Forms\Rendering\FormTemplate;
use ScoutingMemories\Forms\Forms\Submission\SpamGuard;
use ScoutingMemories\Forms\Forms\Submission\Validator;
use ScoutingMemories\Forms\Models\EntryRepository;
use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Models\PostFields;
use ScoutingMemories\Forms\Support\FormidableSettings;
use ScoutingMemories\Forms\Support\Permissions;
use ScoutingMemories\Forms\Support\TestData;

/**
 * DynamicFormRenderer
 *
 * [sm_form id=X] (and [formidable id=X] once Formidable is gone): renders any Formidable form from
 * Formidable's tables and handles its submission.
 *
 * Submissions are processed on template_redirect, before any output, so an on_submit action can
 * redirect. The result (values, errors, success message) is kept for the shortcode to render.
 * Pipeline: nonce -> SpamGuard -> Validator -> uploads -> EntryRepository -> ActionRunner.
 */
class DynamicFormRenderer extends FormHandler {

    /** @var array<int, array<string, mixed>> Submission results by form ID for this request */
    private static array $results = [];

    public static function registerHooks(): void {
        add_shortcode('sm_form', [__CLASS__, 'renderShortcode']);
        add_action('template_redirect', [__CLASS__, 'processSubmission'], 5);

        // Fallback for existing [formidable] content, only when Formidable itself is not loaded.
        // Checked late on init so plugin load order can't let us shadow Formidable's shortcode.
        add_action('init', function () {
            if (!class_exists('FrmFormsController') && !shortcode_exists('formidable')) {
                add_shortcode('formidable', [__CLASS__, 'renderFormidableFallback']);
            }
        }, 999);
    }

    /**
     * Fallback for [formidable id=X] or [formidable key=X]
     */
    public static function renderFormidableFallback($atts = []): string {
        return self::renderShortcode(is_array($atts) ? $atts : []);
    }

    /**
     * [sm_form id="X" title="1" description="1"]
     */
    public static function renderShortcode($atts = []): string {
        $atts = shortcode_atts([
            'id'          => 0,
            'key'         => '',
            'title'       => 'false',
            'description' => 'false',
            'minimize'    => 'false',
        ], is_array($atts) ? $atts : [], 'sm_form');

        $form = FormRepository::find((int) $atts['id'], sanitize_title((string) $atts['key']));
        if (!$form) {
            return '<!-- Scouting Forms: form not found (ID: ' . (int) $atts['id'] . ', key: ' . esc_html((string) $atts['key']) . ') -->';
        }

        $fields = FormRepository::fields($form['id']);
        $state = self::$results[$form['id']] ?? null;
        if ($state === null) {
            $state = ['values' => [], 'errors' => []];
            // An Edit link (?frm_action=edit&entry=ID) opens the entry for someone allowed to edit it
            $editId = self::editTarget($form);
            if ($editId) {
                $state['values'] = EntryRepository::formValues($editId, $fields);
                $state['entry_id'] = $editId;
                // Account fields show the account's current details (Formidable Registration)
                $register = RegisterAction::forForm((int) $form['id'], 'update', $state['values']);
                if ($register) {
                    $state['values'] = RegisterAction::prefillFromUser($register, $fields, $state['values']);
                }
            }
        }

        $truthy = ['1', 'true', 'yes'];
        return FormTemplate::render(
            $form,
            $fields,
            $state,
            [
                'title' => in_array(strtolower((string) $atts['title']), $truthy, true),
                'description' => in_array(strtolower((string) $atts['description']), $truthy, true),
            ]
        );
    }

    /**
     * Handle a POST from a plugin-rendered form (runs on template_redirect).
     */
    public static function processSubmission(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['sm_form_id'])) {
            return;
        }
        $form = FormRepository::find(absint($_POST['sm_form_id']));
        if (!$form) {
            return;
        }
        self::$results[$form['id']] = self::handle($form);
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed> State for FormTemplate::render()
     */
    public static function handle(array $form): array {
        $fields = FormRepository::fields($form['id']);
        $posted = isset($_POST['item_meta']) && is_array($_POST['item_meta']) ? wp_unslash($_POST['item_meta']) : [];

        $editId = isset($_POST['sm_entry_id']) ? absint($_POST['sm_entry_id']) : 0;
        $nonce = isset($_POST['_sm_form_nonce']) ? sanitize_text_field(wp_unslash($_POST['_sm_form_nonce'])) : '';
        $nonceAction = $editId ? 'sm_update_entry_' . $editId : 'sm_submit_form_' . $form['id'];
        if (!wp_verify_nonce($nonce, $nonceAction)) {
            return ['values' => [], 'errors' => [], 'form_error' => __('Security check failed. Please refresh the page and try again.', 'scouting-forms')];
        }

        $entry = [];
        if ($editId) {
            // Checked again here: the entry must belong to this form and the user must still be allowed
            $entry = EntryRepository::find($editId);
            if (!$entry || (int) $entry['form_id'] !== (int) $form['id'] || !$form['editable'] || !Permissions::canEditEntry($entry, $form)) {
                return ['values' => [], 'errors' => [], 'form_error' => __('You do not have permission to edit this entry.', 'scouting-forms')];
            }
        }

        $validated = Validator::validate($fields, $posted, $editId);

        // Register User action: whose account this is, and the add-on's own checks
        $register = RegisterAction::forForm((int) $form['id'], $editId ? 'update' : 'create', $validated['values']);
        if ($register) {
            $validated['values'] = RegisterAction::prepareValues($register, $fields, $validated['values'], (bool) $editId);
            $passwordId = RegisterAction::passwordFieldId($register);
            if ($passwordId && RegisterAction::selectedUser($fields, $validated['values']) && trim((string) ($validated['values'][$passwordId] ?? '')) === '') {
                // The password is optional when an existing account is updated
                unset($validated['errors'][$passwordId]);
            }
            $validated['errors'] += RegisterAction::validate($register, $fields, $validated['values']);
        }

        $spamError = SpamGuard::check((int) $form['id'], $fields);
        if ($spamError !== '') {
            return ['values' => $validated['values'], 'errors' => [], 'form_error' => $spamError];
        }

        $errors = $validated['errors'] + ($editId ? [] : self::checkRequiredFiles($fields));
        if ($errors) {
            return ['values' => self::withoutPasswords($fields, $validated['values']), 'errors' => $errors, 'entry_id' => $editId];
        }

        $values = $validated['values'] + self::saveUploads($fields);

        // Passwords are handed to the Register User action only, never stored with the entry
        $secret = $values;
        $values = self::withoutPasswords($fields, $values);

        if ($editId) {
            return self::update($form, $fields, $entry, $values, $validated['rows'], $register ? $secret : null, $register);
        }

        // Repeating sections are saved as child entries once the parent entry exists
        foreach (array_keys($validated['rows']) as $sectionId) {
            unset($values[$sectionId]);
        }

        $name = EntryRepository::nameFromValues($fields, $values, $form['name']);
        $entryId = EntryRepository::create((int) $form['id'], $values, ['name' => $name]);
        if (!$entryId) {
            return ['values' => $validated['values'], 'errors' => [], 'form_error' => __('Your submission could not be saved. Please try again.', 'scouting-forms')];
        }
        foreach ($validated['rows'] as $sectionId => $rows) {
            $childIds = self::saveRows($entryId, $name, (int) $sectionId, $rows);
            if ($childIds) {
                EntryRepository::updateField($entryId, (int) $sectionId, $childIds);
                $values[(int) $sectionId] = $childIds;
            }
        }

        $context = [
            'form' => $form,
            'fields' => $fields,
            'values' => $values,
            'entry' => EntryRepository::find($entryId),
        ];
        if ($register) {
            // First, as in Formidable: the account exists before the other actions run
            RegisterAction::run($register, ['values' => $secret] + $context);
            $context['entry'] = EntryRepository::find($entryId);
            $context['values'] = EntryRepository::formValues($entryId, $fields) + $values;
        }
        $actions = ActionRunner::run('create', $context);

        // "Do not store entries": like Formidable, the entry exists only while its actions run
        if (!empty($form['options']['no_save'])) {
            EntryRepository::delete($entryId);
        }

        return self::afterSubmit($form, $context, $actions['on_submit']);
    }

    /**
     * What to show after a successful submission: the on_submit action (or the form's own
     * success settings) decides between a message, a redirect or another page's content.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $onSubmit
     * @return array<string, mixed>
     */
    private static function afterSubmit(array $form, array $context, ?array $onSubmit, bool $updated = false): array {
        $settings = $onSubmit ?? $form['options'];
        if ($updated && $onSubmit === null) {
            // After an edit Formidable uses the form's edit_* settings
            $opts = $form['options'];
            $settings = [
                'success_action' => $opts['edit_action'] ?? 'message',
                'success_url' => $opts['edit_url'] ?? '',
                'success_page_id' => $opts['edit_page_id'] ?? '',
                'success_msg' => $opts['edit_msg'] ?? FormidableSettings::pro('edit_msg', __('Your submission was successfully saved.', 'scouting-forms')),
                'show_form' => true,
            ];
        }
        $action = (string) ($settings['success_action'] ?? 'message');
        $replace = static function (string $text, bool $html) use ($context): string {
            return EntryShortcodes::replace($text, $context['form'], $context['fields'], $context['values'], $context['entry'], $html);
        };

        if ($action === 'redirect' && !empty($settings['success_url'])) {
            $url = esc_url_raw($replace((string) $settings['success_url'], false));
            if ($url !== '') {
                wp_redirect($url);
                exit;
            }
        }

        if ($action === 'page' && !empty($settings['success_page_id'])) {
            $page = get_post((int) $settings['success_page_id']);
            if ($page && $page->post_status === 'publish') {
                return ['values' => [], 'errors' => [], 'message' => apply_filters('the_content', $page->post_content), 'show_form' => false];
            }
        }

        $message = (string) ($settings['success_msg'] ?? '');
        if ($message === '') {
            $message = __('Your responses were successfully submitted. Thank you!', 'scouting-forms');
        }

        $state = [
            'values' => [],
            'errors' => [],
            'message' => wpautop(wp_kses_post(do_shortcode($replace($message, true)))),
            'show_form' => !empty($settings['show_form']),
        ];
        if ($updated) {
            // The edited entry stays open in the form, with its saved values
            $state['entry_id'] = (int) $context['entry']['id'];
            $state['values'] = EntryRepository::formValues((int) $context['entry']['id'], $context['fields']);
        }
        return $state;
    }

    /**
     * Save an edited entry: its values (Formidable's update rules, see EntryRepository::update),
     * its repeating-section rows (existing rows updated, new rows added, removed rows deleted),
     * then the form's "update" actions and edit confirmation.
     *
     * @param array<string, mixed> $form
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $entry
     * @param array<int, mixed> $values
     * @param array<int, array<string, array<int, mixed>>> $rows
     * @return array<string, mixed>
     */
    private static function update(array $form, array $fields, array $entry, array $values, array $rows, ?array $secret = null, ?array $register = null): array {
        $entryId = (int) $entry['id'];
        // Actions (the post action especially) need every submitted value, including the ones
        // that are not stored on the entry
        $submitted = $values;
        $keep = [];
        foreach ($fields as $field) {
            $id = (int) $field['id'];
            // Values this user may not see are kept as they are, and so is the entry's owner
            // (the User ID field holds whoever created it, not whoever edits it)
            if (!FieldLogic::visibleToUser($field) || $field['type'] === 'user_id') {
                $keep[] = $id;
                unset($values[$id]);
            }
            // Post-mapped values live on the post (the Add a Post action updates it, Phase 4)
            if ((int) $entry['post_id'] > 0 && PostFields::mapping($field)) {
                $keep[] = $id;
                unset($values[$id]);
            }
            if ($field['type'] === 'divider' && !empty($field['field_options']['repeat'])) {
                $keep[] = $id;
                unset($values[$id]);
            }
        }

        EntryRepository::update($entryId, $values, $keep);

        foreach ($fields as $field) {
            if ($field['type'] !== 'divider' || empty($field['field_options']['repeat']) || !FieldLogic::visibleToUser($field)) {
                continue;
            }
            $sectionId = (int) $field['id'];
            $childIds = self::updateRows($entryId, (string) $entry['name'], $field, $rows[$sectionId] ?? []);
            if ($childIds) {
                EntryRepository::updateField($entryId, $sectionId, $childIds);
                $values[$sectionId] = $childIds;
            } else {
                EntryRepository::update($entryId, [], array_diff(self::storedFieldIds($entryId), [$sectionId]));
            }
        }

        foreach ($values as $id => $value) {
            $submitted[$id] = $value;
        }
        $context = [
            'form' => $form,
            'fields' => $fields,
            'values' => $submitted,
            'entry' => EntryRepository::find($entryId),
        ];
        if ($register && $secret !== null) {
            RegisterAction::run($register, ['values' => $secret + $submitted] + $context);
        }
        $actions = ActionRunner::run('update', $context);
        return self::afterSubmit($form, $context, $actions['on_submit'], true);
    }

    /**
     * Rows of a repeating section on an edited entry. Row keys that are this entry's child IDs
     * update those children; other rows become new children; children with no row are deleted.
     *
     * @param array<string, mixed> $section
     * @param array<string, array<int, mixed>> $rows
     * @return int[] The section's child entry IDs after saving
     */
    private static function updateRows(int $parentId, string $parentName, array $section, array $rows): array {
        global $wpdb;
        $childFormId = (int) ($section['field_options']['form_select'] ?? 0);
        $existing = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}frm_items WHERE parent_item_id = %d AND form_id = %d",
            $parentId,
            $childFormId
        )));

        $ids = [];
        foreach ($rows as $key => $row) {
            if (ctype_digit((string) $key) && in_array((int) $key, $existing, true)) {
                EntryRepository::update((int) $key, $row);
                $ids[] = (int) $key;
                continue;
            }
            $childId = EntryRepository::create($childFormId, $row, ['name' => $parentName, 'parent_item_id' => $parentId]);
            if ($childId) {
                $ids[] = $childId;
            }
        }
        foreach (array_diff($existing, $ids) as $removed) {
            EntryRepository::delete($removed);
        }
        return $ids;
    }

    /**
     * @return int[]
     */
    private static function storedFieldIds(int $entryId): array {
        global $wpdb;
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT field_id FROM {$wpdb->prefix}frm_item_metas WHERE item_id = %d AND field_id <> 0",
            $entryId
        )));
    }

    /**
     * Values without any password field (never saved, never shown again after an error).
     *
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed> $values
     * @return array<int, mixed>
     */
    private static function withoutPasswords(array $fields, array $values): array {
        foreach ($fields as $field) {
            if ($field['type'] === 'password') {
                unset($values[(int) $field['id']]);
            }
        }
        return $values;
    }

    /**
     * The entry an Edit link opens, following Formidable (FrmProEntriesHelper::allow_form_edit):
     * the form must allow editing and the user must be allowed to edit that entry; otherwise the
     * page shows a new, empty form. On forms limited to one entry per user, the user's own entry
     * opens by itself.
     *
     * @param array<string, mixed> $form
     */
    private static function editTarget(array $form): int {
        global $wpdb;
        if (!is_user_logged_in() || empty($form['editable'])) {
            return 0;
        }

        $action = isset($_GET['frm_action']) ? sanitize_key(wp_unslash($_GET['frm_action'])) : '';
        $requested = isset($_GET['entry']) ? sanitize_title(wp_unslash($_GET['entry'])) : '';
        $entryId = 0;
        if ($action === 'edit' && $requested !== '') {
            $entryId = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}frm_items WHERE form_id = %d AND (id = %d OR item_key = %s) LIMIT 1",
                (int) $form['id'],
                ctype_digit($requested) ? (int) $requested : 0,
                $requested
            ));
        } elseif (!empty($form['options']['single_entry']) && in_array('user', (array) ($form['options']['single_entry_type'] ?? []), true)) {
            $entryId = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}frm_items WHERE form_id = %d AND user_id = %d AND is_draft = 0 ORDER BY id DESC LIMIT 1",
                (int) $form['id'],
                get_current_user_id()
            ));
        }
        if (!$entryId) {
            return 0;
        }
        $entry = EntryRepository::find($entryId);
        return $entry && Permissions::canEditEntry($entry, $form) ? $entryId : 0;
    }

    /**
     * One child entry per row of a repeating section, named after the parent entry like
     * Formidable's. Returns the child entry IDs the section field stores.
     *
     * @param array<string, array<int, mixed>> $rows
     * @return int[]
     */
    private static function saveRows(int $parentId, string $parentName, int $sectionId, array $rows): array {
        $section = FormRepository::field($sectionId);
        $childFormId = $section ? (int) ($section['field_options']['form_select'] ?? 0) : 0;
        if (!$childFormId) {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            $childId = EntryRepository::create($childFormId, $row, [
                'name' => $parentName,
                'parent_item_id' => $parentId,
            ]);
            if ($childId) {
                $ids[] = $childId;
            }
        }
        return $ids;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<int, string>
     */
    private static function checkRequiredFiles(array $fields): array {
        $errors = [];
        foreach ($fields as $field) {
            if ($field['type'] !== 'file' || !$field['required']) {
                continue;
            }
            $key = 'file_' . $field['id'];
            if (empty($_FILES[$key]['name'])) {
                $message = (string) ($field['field_options']['blank'] ?? '');
                $errors[(int) $field['id']] = str_replace('[field_name]', $field['name'], $message !== '' ? $message : '[field_name] cannot be blank.');
            }
        }
        return $errors;
    }

    /**
     * Upload files to the media library (WP Stateless offloads them on the live site).
     *
     * @param array<int, array<string, mixed>> $fields
     * @return array<int, int> field_id => attachment ID
     */
    private static function saveUploads(array $fields): array {
        $saved = [];
        foreach ($fields as $field) {
            $key = 'file_' . $field['id'];
            if ($field['type'] !== 'file' || empty($_FILES[$key]['name'])) {
                continue;
            }
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $attachId = media_handle_upload($key, 0);
            if (!is_wp_error($attachId)) {
                TestData::markPost((int) $attachId);
                $saved[(int) $field['id']] = (int) $attachId;
            }
        }
        return $saved;
    }
}
