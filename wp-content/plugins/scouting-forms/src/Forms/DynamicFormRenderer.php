<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Actions\ActionRunner;
use ScoutingMemories\Forms\Actions\EntryShortcodes;
use ScoutingMemories\Forms\Forms\Rendering\FormTemplate;
use ScoutingMemories\Forms\Forms\Submission\SpamGuard;
use ScoutingMemories\Forms\Forms\Submission\Validator;
use ScoutingMemories\Forms\Models\EntryRepository;
use ScoutingMemories\Forms\Models\FormRepository;
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

        $truthy = ['1', 'true', 'yes'];
        return FormTemplate::render(
            $form,
            FormRepository::fields($form['id']),
            self::$results[$form['id']] ?? ['values' => [], 'errors' => []],
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

        $nonce = isset($_POST['_sm_form_nonce']) ? sanitize_text_field(wp_unslash($_POST['_sm_form_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'sm_submit_form_' . $form['id'])) {
            return ['values' => [], 'errors' => [], 'form_error' => __('Security check failed. Please refresh the page and try again.', 'scouting-forms')];
        }

        $validated = Validator::validate($fields, $posted);
        $spamError = SpamGuard::check((int) $form['id'], $fields);
        if ($spamError !== '') {
            return ['values' => $validated['values'], 'errors' => [], 'form_error' => $spamError];
        }

        $errors = $validated['errors'] + self::checkRequiredFiles($fields);
        if ($errors) {
            return ['values' => $validated['values'], 'errors' => $errors];
        }

        $values = $validated['values'] + self::saveUploads($fields);

        $entryId = EntryRepository::create((int) $form['id'], $values, [
            'name' => EntryRepository::nameFromValues($fields, $values, $form['name']),
        ]);
        if (!$entryId) {
            return ['values' => $validated['values'], 'errors' => [], 'form_error' => __('Your submission could not be saved. Please try again.', 'scouting-forms')];
        }

        $context = [
            'form' => $form,
            'fields' => $fields,
            'values' => $values,
            'entry' => EntryRepository::find($entryId),
        ];
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
    private static function afterSubmit(array $form, array $context, ?array $onSubmit): array {
        $settings = $onSubmit ?? $form['options'];
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

        return [
            'values' => [],
            'errors' => [],
            'message' => wpautop(wp_kses_post(do_shortcode($replace($message, true)))),
            'show_form' => !empty($settings['show_form']),
        ];
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
