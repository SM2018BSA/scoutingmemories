<?php

namespace ScoutingMemories\Forms\Compat;

/**
 * EditUsers
 *
 * The Edit Users form (#33, administrators only, see Support\FormAccess) changes a member's roles
 * and assignments. The theme does this in NewUserEntry::frm_edit_users on frm_pre_create_entry,
 * but with a single role it removes every role and then stops with a fatal error (set_role()
 * given a list), leaving the member with no role at all, and an empty choice removes every role.
 * Without Formidable the plugin does the same work here instead: the member (found by the
 * email of the registration entry in ?entry=) gets the chosen roles and assignments, which are
 * also copied to that registration entry.
 */
class EditUsers {

    /** Edit Users field => registration field (and the user meta key) */
    private const COPY = [
        ['EU_ASSIGNED_STATE_FID', 'NUR_ASSIGNED_STATE_FID', 'assigned_state'],
        ['EU_ASSIGNED_COUNCIL_FID', 'NUR_ASSIGNED_COUNCIL_FID', 'assigned_council'],
        ['EU_ASSIGNED_ACTIVE_COUNCIL_FID', 'NUR_ASSIGNED_COUNCIL_ACTIVE_FID', 'active_assigned_council'],
        ['EU_ASSIGNED_REGION_SLUG_FID', 'NUR_ASSIGNED_REGION_SLUG_FID', 'assigned_region_slug'],
        ['EU_ASSIGNED_ACTIVE_REGION_FID', 'NUR_ASSIGNED_REGION_ACTIVE_FID', 'active_assigned_region'],
    ];

    public static function register(): void {
        // After the theme has added its hooks (functions.php runs before after_setup_theme)
        add_action('after_setup_theme', [self::class, 'replaceThemeHandler'], 99);
    }

    public static function replaceThemeHandler(): void {
        global $wp_filter;
        if (!self::constantsDefined() || empty($wp_filter['frm_pre_create_entry'])) {
            return;
        }
        foreach ($wp_filter['frm_pre_create_entry']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $fn = $callback['function'];
                if (is_array($fn) && is_object($fn[0]) && get_class($fn[0]) === 'NewUserEntry' && $fn[1] === 'frm_edit_users') {
                    remove_filter('frm_pre_create_entry', $fn, $priority);
                    add_filter('frm_pre_create_entry', [self::class, 'apply'], $priority, 1);
                }
            }
        }
    }

    /**
     * @param mixed $values Formidable's values for a new entry
     * @return mixed
     */
    public static function apply($values) {
        if (!is_array($values) || (int) ($values['form_id'] ?? 0) !== (int) constant('EDIT_USERS_FORMID')) {
            return $values;
        }
        $user = self::member();
        if (!$user || !current_user_can('promote_users') || !current_user_can('edit_user', $user->ID)) {
            return $values;
        }
        $meta = (array) ($values['item_meta'] ?? []);
        $entryId = self::registrationEntryId();

        foreach (self::COPY as [$from, $to, $userKey]) {
            $value = $meta[(int) constant($from)] ?? '';
            Data::updateMeta($entryId, (int) constant($to), $value);
            update_user_meta($user->ID, $userKey, $value);
        }

        $roles = array_values(array_filter(array_map('strval', (array) ($meta[(int) constant('EU_SET_ROLE_FID')] ?? [])), 'strlen'));
        $roles = array_values(array_intersect(array_unique($roles), array_keys(wp_roles()->get_names())));
        if ($roles) {
            // Nobody removes their own administrator role here (they would lock themselves out)
            if ($user->ID === get_current_user_id() && in_array('administrator', $user->roles, true) && !in_array('administrator', $roles, true)) {
                $roles[] = 'administrator';
            }
            $user->set_role(array_shift($roles));
            foreach ($roles as $role) {
                $user->add_role($role);
            }
        }
        return $values;
    }

    private static function member(): ?\WP_User {
        global $wpdb;
        $entryId = self::registrationEntryId();
        if (!$entryId) {
            return null;
        }
        $email = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}frm_item_metas WHERE item_id = %d AND field_id = %d",
            $entryId,
            (int) constant('NUR_EMAIL_FID')
        ));
        $user = is_email($email) ? get_user_by('email', $email) : false;
        return $user ?: null;
    }

    private static function registrationEntryId(): int {
        $entry = isset($_REQUEST['entry']) && is_scalar($_REQUEST['entry']) ? absint(wp_unslash($_REQUEST['entry'])) : 0;
        return $entry;
    }

    private static function constantsDefined(): bool {
        foreach (array_merge(['EDIT_USERS_FORMID', 'EU_SET_ROLE_FID', 'NUR_EMAIL_FID'], array_merge(...array_map(static fn($c) => [$c[0], $c[1]], self::COPY))) as $name) {
            if (!defined($name)) {
                return false;
            }
        }
        return true;
    }
}
