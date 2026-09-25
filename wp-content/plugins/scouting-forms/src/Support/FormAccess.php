<?php

namespace ScoutingMemories\Forms\Support;

use ScoutingMemories\Forms\Models\FormRepository;

/**
 * FormAccess
 *
 * Who may see and submit each form. Formidable leaves every form on this site open (none uses its
 * "logged in / roles" setting) and relies on the theme hiding the pages; but a form can be posted to
 * any page, and the theme's Edit Users hook changes anyone's roles (Administrator included) with no
 * check of its own. So the plugin enforces, on display and on every submission:
 *   - Formidable's own settings when a form has them (logged_in, logged_in_role);
 *   - the rules below, which match how the theme shows each form (keyed by form key, the same on
 *     every copy of the site). Child forms (repeating sections) follow their parent form.
 * Forms not listed are open to everyone (contact, registration, searches, ...).
 */
class FormAccess {

    /**
     * form key => rule: 'login' (any logged-in user), a capability, or a list of roles/capabilities
     * any one of which is enough (logged in is always required).
     */
    private const RULES = [
        'editusers' => ['manage_options'],                                  // Edit Users: administrators (My Account > Admin)
        'state' => ['manage_options'],                                      // States: reference data
        'region_key' => ['manage_options'],                                 // Regions: reference data
        'council_key' => ['index_contributor', 'edit_others_posts'],       // Add a Council (theme: add-council.php)
        'vvdlj' => ['index_contributor', 'edit_others_posts'],             // Add a Lodge
        'camp_key' => ['index_contributor', 'edit_others_posts'],          // Add a Camp
        'n146q' => ['create_posts', 'edit_posts'],                          // Add a Post (theme: add-post.php)
        'sm-user-registration2' => 'login',                                 // Edit Account Info
        'sm-user-defaults' => 'login',                                      // Edit Account Defaults
    ];

    /**
     * @param array<string, mixed> $form From FormRepository::find()
     */
    public static function allowed(array $form): bool {
        return self::reason($form) === '';
    }

    /**
     * Why the current visitor may not use the form ('' when they may).
     *
     * @param array<string, mixed> $form
     */
    public static function reason(array $form): string {
        // A child form (repeating section) is only ever used inside its parent
        if (!empty($form['parent_form_id'])) {
            $parent = FormRepository::find((int) $form['parent_form_id']);
            return $parent ? self::reason($parent) : 'denied';
        }

        $opts = $form['options'];
        $rule = self::RULES[$form['key']] ?? null;
        $needsLogin = !empty($opts['logged_in']) || $rule !== null;
        if ($needsLogin && !is_user_logged_in()) {
            return 'login';
        }

        // Formidable's own role setting, when a form has one
        if (!empty($opts['logged_in'])) {
            $roles = array_filter((array) ($opts['logged_in_role'] ?? []), static fn($r) => $r !== '' && $r !== null);
            if ($roles && !Permissions::hasRole(array_values($roles))) {
                return 'denied';
            }
        }

        if (is_array($rule)) {
            foreach ($rule as $capOrRole) {
                if (current_user_can($capOrRole)) {
                    return '';
                }
            }
            return 'denied';
        }
        return '';
    }

    /**
     * What a visitor sees instead of a form they may not use.
     *
     * @param array<string, mixed> $form
     */
    public static function message(array $form): string {
        if (self::reason($form) === 'login') {
            return sprintf(
                /* translators: %s: login link */
                __('Please %s to use this form.', 'scouting-forms'),
                '<a href="' . esc_url(wp_login_url((string) get_permalink())) . '">' . esc_html__('log in', 'scouting-forms') . '</a>'
            );
        }
        return esc_html__('You do not have permission to use this form.', 'scouting-forms');
    }
}
