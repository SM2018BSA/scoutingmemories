<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Actions\EntryShortcodes;
use ScoutingMemories\Forms\Actions\RegisterAction;
use ScoutingMemories\Forms\Forms\Rendering\FormTemplate;
use ScoutingMemories\Forms\Models\EntryRepository;
use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Support\FormAccess;
use ScoutingMemories\Forms\Support\FormidableSettings;
use ScoutingMemories\Forms\Support\Permissions;

/**
 * DynamicFormRenderer
 *
 * [sm_form id=X] (and [formidable id=X] once Formidable is gone): renders any Formidable form from
 * Formidable's tables and handles its submission.
 *
 * Submissions are processed on template_redirect, before any output, so an on_submit action can
 * redirect. The result (values, errors, success message) is kept for the shortcode to render.
 * The save itself is EntryService (validation, spam check, entry, actions); this class checks the
 * nonce and shows the confirmation (message, redirect or page).
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

        // Forms only some people may use show a notice instead (Support\FormAccess)
        if (!FormAccess::allowed($form)) {
            wp_enqueue_style('sm-forms-front');
            return '<div class="sm-forms"><div class="alert alert-info" role="status">' . FormAccess::message($form) . '</div></div>';
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
        $posted = isset($_POST['item_meta']) && is_array($_POST['item_meta']) ? wp_unslash($_POST['item_meta']) : [];

        $editId = isset($_POST['sm_entry_id']) ? absint($_POST['sm_entry_id']) : 0;
        $nonce = isset($_POST['_sm_form_nonce']) ? sanitize_text_field(wp_unslash($_POST['_sm_form_nonce'])) : '';
        $nonceAction = $editId ? 'sm_update_entry_' . $editId : 'sm_submit_form_' . $form['id'];
        if (!wp_verify_nonce($nonce, $nonceAction)) {
            return ['values' => [], 'errors' => [], 'form_error' => __('Security check failed. Please refresh the page and try again.', 'scouting-forms')];
        }

        $result = EntryService::submit($form, $posted, ['entry_id' => $editId]);
        if (!$result['ok']) {
            $state = ['values' => $result['values'], 'errors' => $result['errors'], 'entry_id' => $editId];
            if ($result['form_error'] !== '') {
                $state['form_error'] = $result['form_error'];
            }
            return $state;
        }
        return self::afterSubmit($form, $result['context'], $result['on_submit'], $result['updated']);
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
}
