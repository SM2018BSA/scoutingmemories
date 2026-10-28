<?php

namespace ScoutingMemories\Forms\Views;

use ScoutingMemories\Forms\Models\EntryRepository;
use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Support\Permissions;

/**
 * EntryActions
 *
 * The data-changing buttons views show: [deletelink] and [frm-entry-update-field]. Each is a
 * small POST form with a nonce tied to the entry (and, for updates, to the exact field and value
 * the view offers), checked again against the user's permissions when it is handled.
 */
class EntryActions {

    public const DONE_PARAM = 'sm_entry_done';

    /** @var array<string, string> Placeholder => button HTML, swapped in after content filters */
    private static array $pending = [];

    public static function registerHooks(): void {
        add_action('template_redirect', [__CLASS__, 'handle'], 4);
    }

    /**
     * @param array<string, string> $fields Extra hidden fields (sm_field, sm_value, sm_confirm)
     */
    public static function button(string $action, int $entryId, array $fields, string $label, string $class): string {
        $confirm = $fields['sm_confirm'] ?? '';
        unset($fields['sm_confirm']);
        if ($class === '' || !preg_match('/\bbtn\b/', $class)) {
            $class = trim($class . ' btn btn-link p-0 align-baseline');
        }

        $html = '<form method="post" class="sm-entry-action d-inline"'
            . ($confirm !== '' ? ' data-sm-confirm="' . esc_attr($confirm) . '" onsubmit="return confirm(this.dataset.smConfirm);"' : '')
            . '>';
        $html .= '<input type="hidden" name="sm_entry_action" value="' . esc_attr($action) . '" />';
        $html .= '<input type="hidden" name="sm_entry" value="' . (int) $entryId . '" />';
        foreach ($fields as $name => $value) {
            $html .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
        }
        // No id attribute: a view shows many of these forms on one page
        $html .= '<input type="hidden" name="_sm_entry_nonce" value="' . esc_attr(wp_create_nonce(self::nonceAction($action, $entryId, $fields))) . '" />';
        $html .= '<button type="submit" class="' . esc_attr($class) . '">' . esc_html($label) . '</button>';

        // wpautop treats <form> as a block and would break the table cell apart, so the view's
        // content filters see a placeholder instead
        $token = '<!--sm-entry-action-' . count(self::$pending) . '-' . wp_generate_password(6, false) . '-->';
        self::$pending[$token] = $html . '</form>';
        return $token;
    }

    /**
     * Put the buttons back once the view's content filters have run.
     */
    public static function restore(string $html): string {
        if (!self::$pending) {
            return $html;
        }
        $found = array_filter(self::$pending, static function ($token) use ($html) {
            return strpos($html, $token) !== false;
        }, ARRAY_FILTER_USE_KEY);
        foreach (array_keys($found) as $token) {
            unset(self::$pending[$token]);
        }
        return strtr($html, $found);
    }

    public static function handle(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['sm_entry_action'])) {
            return;
        }
        $action = sanitize_key(wp_unslash($_POST['sm_entry_action']));
        $entryId = absint($_POST['sm_entry'] ?? 0);
        $fields = [];
        if ($action === 'update_field') {
            $fields = [
                'sm_field' => (string) absint($_POST['sm_field'] ?? 0),
                'sm_value' => sanitize_text_field(wp_unslash($_POST['sm_value'] ?? '')),
            ];
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['_sm_entry_nonce'] ?? ''));
        if (!in_array($action, ['delete', 'update_field'], true) || !wp_verify_nonce($nonce, self::nonceAction($action, $entryId, $fields))) {
            wp_die(esc_html__('This link has expired. Please go back, refresh the page and try again.', 'scouting-forms'), '', ['response' => 403, 'back_link' => true]);
        }

        $entry = EntryRepository::find($entryId);
        if (!$entry) {
            self::back('missing');
        }

        if ($action === 'delete') {
            if (!Permissions::canDeleteEntry($entry)) {
                self::forbidden();
            }
            self::back(EntryRepository::delete($entryId) ? 'deleted' : 'failed');
        }

        $field = FormRepository::field((int) $fields['sm_field']);
        if (!$field || (int) $field['form_id'] !== (int) $entry['form_id']) {
            self::back('failed');
        }
        if (!Permissions::canEditEntry($entry)) {
            self::forbidden();
        }
        self::back(EntryRepository::updateField($entryId, (int) $field['id'], $fields['sm_value']) ? 'updated' : 'failed');
    }

    /**
     * Message for ?sm_entry_done=..., shown once above the view.
     */
    public static function notice(): string {
        $done = isset($_GET[self::DONE_PARAM]) ? sanitize_key(wp_unslash($_GET[self::DONE_PARAM])) : '';
        $messages = [
            'deleted' => ['success', __('Your entry was successfully deleted', 'scouting-forms')],
            'updated' => ['success', __('Entry updated.', 'scouting-forms')],
            'missing' => ['warning', __('That entry no longer exists.', 'scouting-forms')],
            'failed' => ['warning', __('That change could not be saved. Please try again.', 'scouting-forms')],
        ];
        if (!isset($messages[$done])) {
            return '';
        }
        [$variant, $text] = $messages[$done];
        return '<div class="alert alert-' . $variant . ' alert-dismissible fade show" role="status">' . esc_html($text)
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="' . esc_attr__('Close', 'scouting-forms') . '"></button></div>';
    }

    /**
     * @param array<string, string> $fields
     */
    private static function nonceAction(string $action, int $entryId, array $fields): string {
        $suffix = $action === 'update_field' ? '_' . ($fields['sm_field'] ?? '') . '_' . md5($fields['sm_value'] ?? '') : '';
        return 'sm_entry_' . $action . '_' . $entryId . $suffix;
    }

    private static function back(string $done): void {
        $url = wp_get_referer() ?: home_url('/');
        wp_safe_redirect(add_query_arg(self::DONE_PARAM, $done, remove_query_arg(self::DONE_PARAM, $url)));
        exit;
    }

    private static function forbidden(): void {
        wp_die(esc_html__('You do not have permission to change this entry.', 'scouting-forms'), '', ['response' => 403, 'back_link' => true]);
    }
}
