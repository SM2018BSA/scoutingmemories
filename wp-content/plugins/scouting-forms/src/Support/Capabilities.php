<?php

namespace ScoutingMemories\Forms\Support;

/**
 * Capabilities
 *
 * The plugin's own capabilities, one for each Formidable capability the site's roles hold:
 * sm_view_forms, sm_edit_forms, sm_view_entries, sm_edit_entries, sm_delete_entries, ... Each role
 * gets the sm_* versions of the frm_* capabilities it already has (a historian keeps entry access),
 * and administrators get all of them. Nothing is ever removed, so this can run again safely; it
 * runs once per version of the list, on the first wp-admin request.
 *
 * Checks use Permissions::can(), which accepts either the frm_* or the sm_* capability, so access
 * stays the same before and after Formidable is removed.
 */
class Capabilities {

    public const VERSION = 1;
    private const OPTION = 'sm_forms_caps_version';

    public const NAMES = [
        'view_forms', 'edit_forms', 'delete_forms', 'list_forms', 'change_settings',
        'view_entries', 'create_entries', 'edit_entries', 'delete_entries', 'view_reports',
        'list_displays', 'create_displays', 'edit_displays',
    ];

    public static function registerHooks(): void {
        // On init (wp-admin only): WordPress builds the admin menu before admin_init
        add_action('init', static function () {
            if (is_admin() && (int) get_option(self::OPTION, 0) < self::VERSION) {
                self::sync();
            }
        });
    }

    /**
     * Give each role the sm_* capabilities matching its frm_* ones.
     *
     * @return array<string, string[]> role => capabilities added
     */
    public static function sync(): array {
        $added = [];
        foreach (wp_roles()->role_objects as $slug => $role) {
            foreach (self::NAMES as $name) {
                $has = $slug === 'administrator' || $role->has_cap('frm_' . $name);
                if ($has && !$role->has_cap('sm_' . $name)) {
                    $role->add_cap('sm_' . $name);
                    $added[$slug][] = 'sm_' . $name;
                }
            }
        }
        update_option(self::OPTION, self::VERSION, false);
        return $added;
    }
}
