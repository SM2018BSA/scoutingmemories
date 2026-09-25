<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Actions\ActionRunner;
use ScoutingMemories\Forms\Actions\RegisterAction;
use ScoutingMemories\Forms\Forms\Logic\FieldLogic;
use ScoutingMemories\Forms\Forms\Submission\SpamGuard;
use ScoutingMemories\Forms\Forms\Submission\Validator;
use ScoutingMemories\Forms\Models\EntryRepository;
use ScoutingMemories\Forms\Models\FormRepository;
use ScoutingMemories\Forms\Models\PostFields;
use ScoutingMemories\Forms\Support\Permissions;
use ScoutingMemories\Forms\Support\TestData;

/**
 * EntryService
 *
 * The one way an entry is created or changed, whoever does it: the front-end form, the admin
 * entries screen (REST), and (Phase 7) theme code calling Formidable's functions. Steps:
 * permission -> validation (+ Register User checks) -> spam check (front end) -> uploads ->
 * save (repeating sections as child entries; passwords never stored) -> Register User -> the
 * form's other actions ("create" or "update").
 */
class EntryService {

    /**
     * @param array<string, mixed> $form From FormRepository::find()
     * @param array<int|string, mixed> $posted item_meta as submitted (unslashed)
     * @param array<string, mixed> $opts entry_id (0 = new entry), admin (true = capability checks
     *                                   instead of the form's edit rules), spam_check, uploads
     * @return array<string, mixed> ok, errors, form_error, values (safe to show again), entry_id,
     *                              context (for confirmations), on_submit, updated
     */
    public static function submit(array $form, array $posted, array $opts = []): array {
        $opts += ['entry_id' => 0, 'admin' => false, 'spam_check' => true, 'uploads' => true];
        $fields = FormRepository::fields((int) $form['id']);
        $editId = (int) $opts['entry_id'];
        $fail = static function (string $message, array $values = [], array $errors = []) use ($editId): array {
            return ['ok' => false, 'form_error' => $message, 'errors' => $errors, 'values' => $values, 'entry_id' => $editId];
        };

        $entry = [];
        if ($editId) {
            // Checked again on every save: the entry must belong to this form and be editable by this user
            $entry = EntryRepository::find($editId);
            $allowed = $entry && (int) $entry['form_id'] === (int) $form['id'] && ($opts['admin']
                ? Permissions::can('edit_entries')
                : ($form['editable'] && Permissions::canEditEntry($entry, $form)));
            if (!$allowed) {
                return $fail(__('You do not have permission to edit this entry.', 'scouting-forms'));
            }
        } elseif ($opts['admin'] && !Permissions::can('create_entries')) {
            return $fail(__('You do not have permission to add entries.', 'scouting-forms'));
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

        if ($opts['spam_check']) {
            $spamError = SpamGuard::check((int) $form['id'], $fields);
            if ($spamError !== '') {
                return $fail($spamError, self::withoutPasswords($fields, $validated['values']));
            }
        }

        $errors = $validated['errors'] + ($editId || !$opts['uploads'] ? [] : self::checkRequiredFiles($fields));
        if ($errors) {
            return ['ok' => false, 'form_error' => '', 'errors' => $errors, 'values' => self::withoutPasswords($fields, $validated['values']), 'entry_id' => $editId];
        }

        $uploads = $opts['uploads'] ? self::saveUploads($fields) : [];
        $values = $validated['values'] + $uploads;

        // Passwords are handed to the Register User action only, never stored with the entry
        $secret = $values;
        $values = self::withoutPasswords($fields, $values);

        if ($editId) {
            // Only sections that were submitted change their rows; others keep their child entries
            $rows = array_intersect_key($validated['rows'], array_filter($posted, 'is_array'));
            return self::update($form, $fields, $entry, $values, $rows, $uploads, $register ? $secret : null, $register);
        }

        // Repeating sections are saved as child entries once the parent entry exists
        foreach (array_keys($validated['rows']) as $sectionId) {
            unset($values[$sectionId]);
        }

        $name = EntryRepository::nameFromValues($fields, $values, $form['name']);
        $entryId = EntryRepository::create((int) $form['id'], $values, ['name' => $name]);
        if (!$entryId) {
            return $fail(__('Your submission could not be saved. Please try again.', 'scouting-forms'), self::withoutPasswords($fields, $validated['values']));
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

        return ['ok' => true, 'errors' => [], 'form_error' => '', 'entry_id' => $entryId, 'context' => $context, 'on_submit' => $actions['on_submit'], 'updated' => false];
    }

    /**
     * Save an edited entry: its values (Formidable's update rules, see EntryRepository::update),
     * its repeating-section rows (existing rows updated, new rows added, removed rows deleted),
     * then Register User and the form's "update" actions.
     *
     * @param array<string, mixed> $form
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $entry
     * @param array<int, mixed> $values
     * @param array<int, array<string, array<int, mixed>>> $rows
     * @param array<int, int> $uploads Newly uploaded files (field ID => attachment)
     * @param array<int, mixed>|null $secret Values including passwords (for Register User)
     * @param array<string, mixed>|null $register
     * @return array<string, mixed>
     */
    private static function update(array $form, array $fields, array $entry, array $values, array $rows, array $uploads, ?array $secret, ?array $register): array {
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
            // A file stays unless a new one was uploaded
            if ($field['type'] === 'file' && !isset($uploads[$id])) {
                $keep[] = $id;
            }
            // Post-mapped values live on the post (the Add a Post action updates it)
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
            if (!array_key_exists($sectionId, $rows)) {
                continue; // not submitted: rows stay as they are
            }
            $childIds = self::updateRows($entryId, (string) $entry['name'], $field, $rows[$sectionId]);
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
        return ['ok' => true, 'errors' => [], 'form_error' => '', 'entry_id' => $entryId, 'context' => $context, 'on_submit' => $actions['on_submit'], 'updated' => true];
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
    public static function withoutPasswords(array $fields, array $values): array {
        foreach ($fields as $field) {
            if ($field['type'] === 'password') {
                unset($values[(int) $field['id']]);
            }
        }
        return $values;
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
