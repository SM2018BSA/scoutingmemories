<?php

namespace ScoutingMemories\Forms\Support;

use ScoutingMemories\Forms\Models\FormRepository;

/**
 * Permissions
 *
 * Who may do what with entries, following Formidable's rules so the same people see the same
 * Edit/Delete/Publish buttons:
 *   - Staff capabilities: frm_* (roles on the site already hold them) or the plugin's sm_* ones.
 *   - Editing an entry is decided per form: "editable" plus editable_role (edit your own entries)
 *     and open_editable_role (edit everyone's). Your own drafts are always editable.
 *   - Deleting needs frm_delete_entries, or the right to edit the entry.
 *
 * Role checks follow Formidable's settings with one deliberate difference: Formidable lets anyone
 * holding a built-in WordPress role (even a subscriber) pass a check for a custom role such as
 * "index_contributor". Here a custom role must actually be held (administrators always pass).
 */
class Permissions {

    private const ROLE_LADDER = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];

    /**
     * @param string $cap Without prefix: view_entries, edit_entries, delete_entries, create_entries, ...
     */
    public static function can(string $cap): bool {
        return current_user_can('frm_' . $cap) || current_user_can('sm_' . $cap) || current_user_can('manage_options');
    }

    /**
     * @param array<string, mixed> $entry Row with id, form_id, user_id, is_draft, parent_item_id
     * @param array<string, mixed>|null $form From FormRepository::find()
     */
    public static function canEditEntry(array $entry, ?array $form = null): bool {
        $userId = get_current_user_id();
        $form = $form ?? self::form((int) ($entry['form_id'] ?? 0));
        if (!$userId || !$form || (int) $entry['form_id'] !== (int) $form['id']) {
            return false;
        }

        // A repeater/child form follows its parent form and parent entry
        if ($form['parent_form_id'] > 0) {
            $form = self::form($form['parent_form_id']);
            if (!$form) {
                return false;
            }
            if (!empty($entry['parent_item_id'])) {
                $parent = \ScoutingMemories\Forms\Models\EntryRepository::find((int) $entry['parent_item_id']);
                $entry = $parent ?: $entry;
            }
        }

        $isOwn = (int) ($entry['user_id'] ?? 0) === $userId;
        $isDraft = !empty($entry['is_draft']);
        if ($isDraft && $isOwn) {
            return true;
        }

        $options = $form['options'];
        $canEditOthers = $form['editable'] && isset($options['open_editable_role']) && self::hasRole($options['open_editable_role']);
        if ($canEditOthers) {
            return true;
        }
        if (!$isOwn) {
            return false;
        }

        // Own entry: the form must allow editing, for this user's role
        if (!$form['editable'] || !self::hasRole($options['editable_role'] ?? '')) {
            return $isDraft;
        }
        return true;
    }

    /**
     * @param array<string, mixed> $entry
     */
    public static function canDeleteEntry(array $entry): bool {
        if (current_user_can('frm_delete_entries') || current_user_can('sm_delete_entries')) {
            return true;
        }
        return self::canEditEntry($entry);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function form(int $formId): ?array {
        static $forms = [];
        if (!array_key_exists($formId, $forms)) {
            $forms[$formId] = FormRepository::find($formId);
        }
        return $forms[$formId];
    }

    /**
     * Formidable role setting: a role name, a list of them, '' (any logged-in user), 'loggedout',
     * '-1' (nobody). A built-in role also admits the roles above it (an editor passes "author").
     *
     * @param mixed $needed
     */
    public static function hasRole($needed): bool {
        if (is_array($needed)) {
            foreach ($needed as $role) {
                if ($role === '' || self::hasRole((string) $role)) {
                    return true;
                }
            }
            return false;
        }

        $needed = (string) $needed;
        if ($needed === '-1') {
            return false;
        }
        if ($needed === 'loggedout') {
            return !is_user_logged_in();
        }
        if ($needed === '' || $needed === 'loggedin') {
            return is_user_logged_in();
        }
        if ($needed === '1') {
            $needed = 'administrator';
        }
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        if (in_array($needed, (array) $user->roles, true) || current_user_can($needed)) {
            return true;
        }

        // A built-in role admits the roles above it. A custom role must be held (Formidable would
        // let any built-in role, even subscriber, through); administrators always pass.
        $rank = array_search($needed, self::ROLE_LADDER, true);
        $ladder = $rank === false ? ['administrator'] : array_slice(self::ROLE_LADDER, 0, $rank);
        foreach ($ladder as $higher) {
            if (in_array($higher, (array) $user->roles, true)) {
                return true;
            }
        }
        return false;
    }
}
