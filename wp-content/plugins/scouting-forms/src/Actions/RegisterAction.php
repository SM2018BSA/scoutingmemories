<?php

namespace ScoutingMemories\Forms\Actions;

use ScoutingMemories\Forms\Models\EntryRepository;
use ScoutingMemories\Forms\Support\Permissions;
use ScoutingMemories\Forms\Support\TestData;

/**
 * RegisterAction
 *
 * Formidable Registration's "Register User" form action (register), following the add-on
 * (FrmRegEntry, FrmRegUser, FrmRegUserController):
 *   - checks: on sign-up the password, email (not already registered) and username; on a profile
 *     update the password is optional, a changed email must be free and the username cannot change;
 *   - creates the WordPress user (role from the action, never from the form; username from the email
 *     unless a field is mapped; display name = username), or updates the entry's user;
 *   - saves the mapped user meta and the avatar (frm_avatar_id), moves the entry to the new user,
 *     runs the form's "user_registration" actions and logs the new person in when the action says so.
 * People who may create accounts for others (the action's roles, e.g. administrators) register a new
 * user instead of editing themselves. The password is never stored with the entry.
 */
class RegisterAction {

    private const DEFAULT_MESSAGES = [
        'existing_email' => 'This email address is already registered.',
        'existing_username' => 'This username is already registered.',
        'blank_password' => 'Please enter a valid password.',
        'blank_email' => 'Please enter a valid email address.',
        'blank_username' => 'Please enter a valid username.',
        'illegal_username' => 'This username is invalid because it uses illegal characters. Please enter a valid username.',
        'illegal_password' => 'Passwords may not contain the character "\\".',
        'update_username' => 'Your username cannot be changed at this time.',
    ];

    /**
     * The form's published register action, if its conditions apply to this submission.
     *
     * @param array<int, mixed> $values
     * @return array<string, mixed>|null Action settings
     */
    public static function forForm(int $formId, string $event, array $values = []): ?array {
        foreach (ActionRunner::actionsFor($formId) as $action) {
            if ($action['type'] !== 'register') {
                continue;
            }
            $settings = $action['settings'];
            if (!in_array($event, (array) ($settings['event'] ?? ['create']), true)) {
                return null;
            }
            if (!\ScoutingMemories\Forms\Forms\Logic\Conditions::actionShouldRun((array) ($settings['conditions'] ?? []), $values)) {
                return null;
            }
            return $settings;
        }
        return null;
    }

    /**
     * May the current user create accounts for other people with this action?
     *
     * @param array<string, mixed> $settings
     */
    public static function canCreateUsers(array $settings): bool {
        if (!is_user_logged_in() || ($settings['reg_create_users'] ?? '') !== 'allow') {
            return false;
        }
        foreach ((array) ($settings['reg_create_role'] ?? []) as $role) {
            if ($role !== '' && current_user_can($role)) {
                return true;
            }
        }
        return false;
    }

    /**
     * May the current user update this person's profile through the form?
     *
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $form
     */
    public static function canUpdateProfile(int $profileUserId, array $settings, array $form): bool {
        $current = get_current_user_id();
        if (!$current) {
            return false;
        }
        if (current_user_can('administrator') || ($profileUserId && $profileUserId === $current) || self::canCreateUsers($settings)) {
            return true;
        }
        return $profileUserId > 0 && !empty($form['options']['open_editable_role']) && Permissions::hasRole($form['options']['open_editable_role']);
    }

    /**
     * Before saving: the user ID field names the account the entry is for. Someone creating
     * accounts for others starts from "no user", as the add-on does.
     *
     * @param array<string, mixed> $settings
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed> $values
     * @return array<int, mixed>
     */
    public static function prepareValues(array $settings, array $fields, array $values, bool $editing): array {
        if (!$editing && self::canCreateUsers($settings)) {
            foreach ($fields as $field) {
                if ($field['type'] === 'user_id') {
                    $values[(int) $field['id']] = 0;
                }
            }
        }
        return $values;
    }

    /**
     * The account the entry belongs to (its user ID field), or 0 when a new account is wanted.
     *
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed> $values
     */
    public static function selectedUser(array $fields, array $values): int {
        foreach ($fields as $field) {
            if ($field['type'] === 'user_id') {
                $id = (int) ($values[(int) $field['id']] ?? 0);
                return $id > 0 && get_userdata($id) ? $id : 0;
            }
        }
        return 0;
    }

    /**
     * Registration checks, on top of the form's own (FrmRegEntry::validate_field).
     *
     * @param array<string, mixed> $settings
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed> $values Validated values (hidden fields already removed)
     * @return array<int, string> field ID => message
     */
    public static function validate(array $settings, array $fields, array $values): array {
        $messages = self::messages();
        $errors = [];
        $selected = self::selectedUser($fields, $values);
        $user = $selected ? get_userdata($selected) : null;
        // An entry that names an account updates it; otherwise a new account is created
        $updating = $user !== null;
        $byId = [];
        foreach ($fields as $field) {
            $byId[(int) $field['id']] = $field;
        }

        $mapped = static function (string $setting) use ($settings, $byId, $values): ?int {
            $id = (int) ($settings['reg_' . $setting] ?? 0);
            if ($id <= 0 || !isset($byId[$id]) || !array_key_exists($id, $values)) {
                return null; // not mapped, or hidden by logic
            }
            return $id;
        };

        if (($id = $mapped('password')) !== null) {
            $password = (string) $values[$id];
            if ($password === '' && !$updating) {
                $errors[$id] = $messages['blank_password'];
            } elseif ($password !== '' && strpos($password, '\\') !== false) {
                $errors[$id] = $messages['illegal_password'];
            }
        }

        if (($id = $mapped('email')) !== null) {
            $email = trim((string) $values[$id]);
            if ($email === '' || !is_email($email)) {
                $errors[$id] = $messages['blank_email'];
            } elseif (!$updating && email_exists($email)) {
                $errors[$id] = $messages['existing_email'];
            } elseif ($updating && strtolower($email) !== strtolower((string) $user->user_email) && email_exists($email)) {
                $errors[$id] = $messages['existing_email'];
            }
        }

        if (($id = $mapped('username')) !== null) {
            $username = trim((string) $values[$id]);
            if ($username === '') {
                $errors[$id] = $messages['blank_username'];
            } elseif ($updating) {
                if (strtolower($username) !== strtolower((string) $user->user_login)) {
                    $errors[$id] = $messages['update_username'];
                }
            } elseif (username_exists($username)) {
                $errors[$id] = $messages['existing_username'];
            } elseif (!validate_username($username)) {
                $errors[$id] = $messages['illegal_username'];
            }
        }
        return $errors;
    }

    /**
     * Create or update the user after the entry is saved.
     *
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $context form, fields, values (incl. the password), entry
     * @return int The user ID created or updated, or 0
     */
    public static function run(array $settings, array $context): int {
        $form = $context['form'];
        $fields = $context['fields'];
        $values = $context['values'];
        $entry = $context['entry'];
        $selected = self::selectedUser($fields, $values);

        if ($selected) {
            if (!self::canUpdateProfile($selected, $settings, $form)) {
                return 0;
            }
            return self::updateUser($selected, $settings, $values);
        }
        if (is_user_logged_in() && !self::canCreateUsers($settings)) {
            return 0;
        }

        $userId = self::createUser($settings, $values, $context);
        if (!$userId) {
            return 0;
        }
        self::moveEntryToUser((int) $entry['id'], $fields, $userId);

        $context['entry'] = EntryRepository::find((int) $entry['id']);
        foreach ($fields as $field) {
            if ($field['type'] === 'user_id') {
                $context['values'][(int) $field['id']] = $userId;
            }
        }
        ActionRunner::run('user_registration', $context);

        if (!empty($settings['login']) && !is_user_logged_in()) {
            wp_set_current_user($userId);
            wp_set_auth_cookie($userId, false);
            do_action('wp_login', (string) get_userdata($userId)->user_login, get_userdata($userId));
        }
        return $userId;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<int, mixed> $values
     * @param array<string, mixed> $context
     */
    private static function createUser(array $settings, array $values, array $context): int {
        $email = trim((string) self::mappedValue($settings, 'email', $values));
        if ($email === '' || !is_email($email)) {
            return 0;
        }
        $password = self::password($settings, $values);
        if ($password === '') {
            $password = wp_generate_password(12, false);
        }

        $setting = (string) ($settings['reg_username'] ?? '');
        if ($setting === '') {
            $username = strstr($email, '@', true) ?: $email;
        } elseif ($setting === '-1') {
            $username = $email;
        } else {
            $username = (string) self::mappedValue($settings, 'username', $values);
        }
        $username = self::uniqueUsername(sanitize_user($username, true));

        $first = (string) self::mappedValue($settings, 'first_name', $values);
        $last = (string) self::mappedValue($settings, 'last_name', $values);
        $role = (string) ($settings['reg_role'] ?? 'subscriber');
        if ($role === '' || !get_role($role)) {
            $role = 'subscriber';
        }

        $userId = wp_insert_user([
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'first_name' => $first,
            'last_name' => $last,
            'display_name' => self::displayName($settings, $values, $username, $first, $last),
            'user_url' => (string) self::mappedValue($settings, 'user_url', $values),
            'role' => $role,
        ]);
        if (is_wp_error($userId) || !$userId) {
            return 0;
        }
        TestData::markUser((int) $userId);
        self::saveMeta((int) $userId, $settings, $values);
        return (int) $userId;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<int, mixed> $values
     */
    private static function updateUser(int $userId, array $settings, array $values): int {
        $user = get_userdata($userId);
        if (!$user) {
            return 0;
        }
        $data = ['ID' => $userId];
        $email = trim((string) self::mappedValue($settings, 'email', $values));
        if ($email !== '' && is_email($email)) {
            $data['user_email'] = $email;
        }
        $password = self::password($settings, $values);
        if ($password !== '') {
            $data['user_pass'] = $password;
        }
        foreach (['first_name', 'last_name', 'user_url'] as $key) {
            $value = self::mappedValue($settings, $key, $values);
            if ($value !== null && $value !== '') {
                $data[$key] = (string) $value;
            }
        }
        if (isset($settings['reg_display_name']) && $settings['reg_display_name'] !== '') {
            $data['display_name'] = self::displayName($settings, $values, (string) $user->user_login, (string) ($data['first_name'] ?? $user->first_name), (string) ($data['last_name'] ?? $user->last_name));
        }
        $result = wp_update_user($data);
        if (is_wp_error($result)) {
            return 0;
        }
        self::saveMeta($userId, $settings, $values);
        return $userId;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<int, mixed> $values
     */
    private static function saveMeta(int $userId, array $settings, array $values): void {
        $avatar = $settings['reg_avatar'] ?? '';
        if (is_numeric($avatar) && !empty($values[(int) $avatar])) {
            update_user_meta($userId, 'frm_avatar_id', (int) (is_array($values[(int) $avatar]) ? reset($values[(int) $avatar]) : $values[(int) $avatar]));
        }
        foreach ((array) ($settings['reg_usermeta'] ?? []) as $row) {
            $key = (string) ($row['meta_name'] ?? '');
            $fieldId = (int) ($row['field_id'] ?? 0);
            if ($key === '' || !$fieldId || !array_key_exists($fieldId, $values)) {
                continue;
            }
            update_user_meta($userId, $key, $values[$fieldId]);
        }
    }

    /**
     * The entry (and its child entries) now belong to the new user (FrmRegEntry::update_user_id_for_entry).
     *
     * @param array<int, array<string, mixed>> $fields
     */
    private static function moveEntryToUser(int $entryId, array $fields, int $userId): void {
        global $wpdb;
        $userFields = [];
        foreach ($fields as $field) {
            if ($field['type'] === 'user_id') {
                $userFields[(int) $field['form_id']] = (int) $field['id'];
            }
        }
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT id, form_id FROM {$wpdb->prefix}frm_items WHERE id = %d OR parent_item_id = %d",
            $entryId,
            $entryId
        ));
        foreach ($entries as $row) {
            $wpdb->update($wpdb->prefix . 'frm_items', ['user_id' => $userId, 'updated_by' => $userId], ['id' => (int) $row->id], ['%d', '%d'], ['%d']);
            if (isset($userFields[(int) $row->form_id])) {
                EntryRepository::updateField((int) $row->id, $userFields[(int) $row->form_id], (string) $userId);
            }
        }
    }

    /**
     * The field mapped to a registration setting (reg_email ...), as submitted.
     *
     * @param array<string, mixed> $settings
     * @param array<int, mixed> $values
     * @return mixed|null null when the setting has no field
     */
    private static function mappedValue(array $settings, string $name, array $values) {
        $id = $settings['reg_' . $name] ?? '';
        if (!is_numeric($id) || (int) $id <= 0) {
            return null;
        }
        $value = $values[(int) $id] ?? '';
        if (is_array($value)) {
            $value = implode(' ', array_map('strval', $value));
        }
        return html_entity_decode((string) $value, ENT_QUOTES);
    }

    /**
     * The password as WordPress's login screen will check it: WordPress compares the slashed
     * form of what is typed (wp_signon uses $_POST['pwd'] as posted), so accounts are created
     * from the slashed password, exactly as the add-on does.
     *
     * @param array<string, mixed> $settings
     * @param array<int, mixed> $values
     */
    private static function password(array $settings, array $values): string {
        $id = $settings['reg_password'] ?? '';
        if (!is_numeric($id) || !isset($values[(int) $id]) || is_array($values[(int) $id])) {
            return '';
        }
        $plain = (string) $values[(int) $id];
        return $plain === '' ? '' : addslashes($plain);
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<int, mixed> $values
     */
    private static function displayName(array $settings, array $values, string $username, string $first, string $last): string {
        $setting = (string) ($settings['reg_display_name'] ?? '');
        if ($setting === 'display_firstlast') {
            return trim($first . ' ' . $last);
        }
        if ($setting === 'display_lastfirst') {
            return trim($last . ' ' . $first);
        }
        if (is_numeric($setting)) {
            return (string) self::mappedValue($settings, 'display_name', $values);
        }
        return $username;
    }

    private static function uniqueUsername(string $base): string {
        $base = $base !== '' ? $base : 'user';
        $name = $base;
        for ($i = 1; username_exists($name); $i++) {
            $name = $base . $i;
        }
        return $name;
    }

    /**
     * The add-on's messages (Global settings > Registration), with its defaults.
     *
     * @return array<string, string>
     */
    public static function messages(): array {
        $saved = get_option('frm_reg_global_messages');
        $saved = is_object($saved) ? (array) $saved : (is_array($saved) ? $saved : []);
        $out = self::DEFAULT_MESSAGES;
        foreach ($out as $key => $default) {
            if (!empty($saved[$key]) && is_string($saved[$key])) {
                $out[$key] = $saved[$key];
            }
        }
        return $out;
    }

    /**
     * Field IDs of the action's password field (never stored with the entry).
     *
     * @param array<string, mixed> $settings
     */
    public static function passwordFieldId(array $settings): int {
        $id = $settings['reg_password'] ?? '';
        return is_numeric($id) ? (int) $id : 0;
    }

    /**
     * On an edit form, fields mapped to the account show the account's current values
     * (FrmRegEntry::check_updated_user_meta; not Dynamic fields or checkboxes).
     *
     * @param array<string, mixed> $settings
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed> $values
     * @return array<int, mixed>
     */
    public static function prefillFromUser(array $settings, array $fields, array $values): array {
        $userId = self::selectedUser($fields, $values);
        $user = $userId ? get_userdata($userId) : null;
        if (!$user) {
            return $values;
        }
        $map = ['reg_email' => 'user_email', 'reg_username' => 'user_login', 'reg_first_name' => 'first_name', 'reg_last_name' => 'last_name', 'reg_display_name' => 'display_name', 'reg_user_url' => 'user_url'];
        $byId = [];
        foreach ($fields as $field) {
            $byId[(int) $field['id']] = $field;
        }
        foreach ($map as $setting => $property) {
            $id = $settings[$setting] ?? '';
            if (is_numeric($id) && isset($byId[(int) $id]) && !in_array($byId[(int) $id]['type'], ['data', 'checkbox'], true)) {
                $values[(int) $id] = (string) $user->{$property};
            }
        }
        foreach ((array) ($settings['reg_usermeta'] ?? []) as $row) {
            $id = (int) ($row['field_id'] ?? 0);
            $key = (string) ($row['meta_name'] ?? '');
            if ($id && $key !== '' && isset($byId[$id]) && !in_array($byId[$id]['type'], ['data', 'checkbox'], true)) {
                $values[$id] = get_user_meta($userId, $key, true);
            }
        }
        $password = self::passwordFieldId($settings);
        if ($password) {
            $values[$password] = '';
        }
        return $values;
    }
}
