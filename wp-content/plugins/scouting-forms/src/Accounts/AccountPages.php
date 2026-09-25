<?php

namespace ScoutingMemories\Forms\Accounts;

use ScoutingMemories\Forms\Ui\ThemeClasses;

/**
 * AccountPages
 *
 * The site's Login (page 229) and Reset Password (page 842) pages, like Formidable Registration:
 *   [sm_login show_lost_password="1" redirect="..."]  and  [sm_reset_password]
 * ([frm-login] / [frm-reset-password] once the add-on is gone). Both forms post to WordPress's own
 * wp-login.php, so WordPress checks passwords and sends reset links; this class never sees a
 * password it keeps. When the add-on is not active it also sends WordPress's login, lost-password
 * and reset screens to these pages and brings errors back to them as messages.
 *
 * Pages come from the add-on's settings (frm_reg_global_pages: login, resetpass, register).
 */
class AccountPages {

    public static function registerHooks(): void {
        add_shortcode('sm_login', [__CLASS__, 'loginForm']);
        add_shortcode('sm_reset_password', [__CLASS__, 'resetForm']);

        // Take over only when Formidable Registration is not doing this already
        add_action('init', static function () {
            if (class_exists('FrmRegAppController')) {
                return;
            }
            if (!shortcode_exists('frm-login')) {
                add_shortcode('frm-login', [__CLASS__, 'loginForm']);
            }
            if (!shortcode_exists('frm-reset-password')) {
                add_shortcode('frm-reset-password', [__CLASS__, 'resetForm']);
            }
            add_action('login_form_login', [__CLASS__, 'toLoginPage']);
            add_action('login_form_lostpassword', [__CLASS__, 'lostPassword']);
            add_action('login_form_rp', [__CLASS__, 'resetPassword']);
            add_action('login_form_resetpass', [__CLASS__, 'resetPassword']);
            add_filter('authenticate', [__CLASS__, 'backToLoginPageOnError'], 999, 1);
        }, 999);
    }

    /**
     * @return int Page ID from the add-on's settings, or 0
     */
    public static function page(string $name): int {
        $pages = get_option('frm_reg_global_pages');
        $pages = is_object($pages) ? (array) $pages : (is_array($pages) ? $pages : []);
        $id = (int) ($pages[$name . '_page'] ?? 0);
        return $id > 0 && get_post_status($id) === 'publish' ? $id : 0;
    }

    /**
     * [sm_login show_lost_password="1" redirect="..." logout_redirect="..."]
     *
     * @param array<string, string>|string $atts
     */
    public static function loginForm($atts = []): string {
        $atts = shortcode_atts([
            'redirect' => '',
            'logout_redirect' => '',
            'show_lost_password' => '0',
            'show_remember' => '1',
            'class' => '',
            'label_username' => __('Username', 'scouting-forms'),
            'label_password' => __('Password', 'scouting-forms'),
            'label_remember' => __('Remember Me', 'scouting-forms'),
            'label_log_in' => __('Login', 'scouting-forms'),
            'label_log_out' => __('Logout', 'scouting-forms'),
        ], is_array($atts) ? $atts : [], 'sm_login');
        wp_enqueue_style('sm-forms-front');

        if (is_user_logged_in()) {
            $back = $atts['logout_redirect'] !== '' ? $atts['logout_redirect'] : (string) get_permalink();
            return '<a href="' . esc_url(wp_logout_url($back)) . '" class="frm_logout_link">' . esc_html($atts['label_log_out']) . '</a>';
        }

        static $count = 0;
        $n = $count++;
        $redirect = $atts['redirect'] !== '' ? $atts['redirect'] : (isset($_GET['redirect_to']) ? wp_unslash((string) $_GET['redirect_to']) : home_url('/'));
        $html = '<div id="loginform-' . $n . '" class="' . esc_attr(trim(ThemeClasses::SCOPE . ' frm_forms frm_login_form ' . $atts['class'])) . '">';
        $html .= self::messages();
        $html .= '<form method="post" action="' . esc_url(site_url('wp-login.php', 'login_post')) . '"><div class="frm_form_fields"><fieldset><div class="frm_fields_container">';
        $html .= sprintf(
            '<div class="frm_form_field form-field login-username frm_top_container"><label for="user_login%1$d" class="frm_primary_label">%2$s</label><input id="user_login%1$d" name="log" value="" type="text" class="%3$s" autocomplete="username" required /></div>',
            $n,
            esc_html($atts['label_username']),
            esc_attr(ThemeClasses::input())
        );
        $html .= sprintf(
            '<div class="frm_form_field form-field login-password frm_top_container"><label for="user_pass%1$d" class="frm_primary_label">%2$s</label><input id="user_pass%1$d" name="pwd" value="" type="password" class="%3$s" autocomplete="current-password" required /></div>',
            $n,
            esc_html($atts['label_password']),
            esc_attr(ThemeClasses::input())
        );
        $html .= '<input type="hidden" name="redirect_to" value="' . esc_url($redirect) . '" />';
        $html .= '<input type="hidden" name="sm_login_page" value="' . (int) get_the_ID() . '" />';
        $html .= '<div class="frm_submit"><button type="submit" name="wp-submit" id="wp-submit' . $n . '" class="' . esc_attr(ThemeClasses::button('scout')) . '">' . esc_html($atts['label_log_in']) . '</button></div>';
        if ($atts['show_remember'] !== '0') {
            $html .= sprintf(
                '<div class="frm_form_field form-field frm_none_container login-remember"><div class="form-check"><input name="rememberme" id="rememberme%1$d" value="forever" type="checkbox" class="form-check-input" /><label class="form-check-label" for="rememberme%1$d">%2$s</label></div></div>',
                $n,
                esc_html($atts['label_remember'])
            );
        }
        if ($atts['show_lost_password'] !== '0') {
            $reset = self::page('resetpass');
            $url = $reset ? get_permalink($reset) : wp_lostpassword_url();
            $html .= '<div class="frm_form_field frm_html_container form-field login_lost_pw"><a class="forgot-password" href="' . esc_url($url) . '">' . esc_html__('Forgot your password?', 'scouting-forms') . '</a></div>';
        }
        return $html . '</div></fieldset></div></form></div>';
    }

    /**
     * [sm_reset_password]: ask for a reset link, or (from the emailed link) choose a new password.
     *
     * @param array<string, string>|string $atts
     */
    public static function resetForm($atts = []): string {
        $atts = shortcode_atts(['class' => ''], is_array($atts) ? $atts : [], 'sm_reset_password');
        wp_enqueue_style('sm-forms-front');
        $messages = get_option('frm_reg_global_messages');
        $messages = is_object($messages) ? (array) $messages : (is_array($messages) ? $messages : []);
        $html = '<div class="' . esc_attr(trim(ThemeClasses::SCOPE . ' frm_forms ' . $atts['class'])) . '">' . self::messages();

        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
        $login = isset($_GET['login']) ? sanitize_user(wp_unslash($_GET['login'])) : '';
        if ($key !== '' && $login !== '') {
            $html .= '<form method="post" action="' . esc_url(site_url('wp-login.php?action=resetpass', 'login_post')) . '" class="frm-show-form"><div class="frm_form_fields"><fieldset>';
            $html .= '<div class="frm_description"><p>' . esc_html($messages['reset_password'] ?? __('Enter your new password below.', 'scouting-forms')) . '</p></div>';
            $html .= '<input type="hidden" name="rp_login" value="' . esc_attr($login) . '" autocomplete="off" />';
            $html .= '<input type="hidden" name="rp_key" value="' . esc_attr($key) . '" />';
            foreach (['pass1' => __('New password', 'scouting-forms'), 'pass2' => __('Confirm new password', 'scouting-forms')] as $name => $label) {
                $html .= sprintf(
                    '<div class="frm_form_field form-field frm_top_container"><label for="sm_%1$s" class="frm_primary_label">%2$s <span class="frm_required">*</span></label><input type="password" id="sm_%1$s" name="%1$s" class="%3$s" autocomplete="new-password" required /></div>',
                    $name,
                    esc_html($label),
                    esc_attr(ThemeClasses::input())
                );
            }
            $html .= '<div class="frm_submit"><button type="submit" class="' . esc_attr(ThemeClasses::button('scout')) . '">' . esc_html__('Reset Password', 'scouting-forms') . '</button></div>';
            return $html . '</fieldset></div></form></div>';
        }

        $html .= '<form method="post" action="' . esc_url(site_url('wp-login.php?action=lostpassword', 'login_post')) . '" class="frm-show-form"><div class="frm_form_fields"><fieldset>';
        $html .= '<div class="frm_description"><p>' . esc_html($messages['lost_password'] ?? __('Please enter your username or email address. You will receive a link to create a new password via email.', 'scouting-forms')) . '</p></div>';
        $html .= '<div class="frm_form_field form-field frm_top_container"><label for="sm_user_login" class="frm_primary_label">' . esc_html__('Username or Email Address', 'scouting-forms') . ' <span class="frm_required">*</span></label><input type="text" id="sm_user_login" name="user_login" class="' . esc_attr(ThemeClasses::input()) . '" autocomplete="username" required /></div>';
        $html .= '<div class="frm_submit"><button type="submit" class="' . esc_attr(ThemeClasses::button('scout')) . '">' . esc_html__('Get New Password', 'scouting-forms') . '</button></div>';
        return $html . '</fieldset></div></form></div>';
    }

    /**
     * Messages for ?login=..., ?password=changed, ?checkemail=confirm, ?errors=... (fixed texts
     * only; nothing from the URL is shown as is).
     */
    private static function messages(): string {
        $known = [
            'empty_username' => ['danger', __('Please enter your username or email address.', 'scouting-forms')],
            'empty_password' => ['danger', __('Please enter your password.', 'scouting-forms')],
            'invalid_username' => ['danger', __('Unknown username or email address. Please check it and try again.', 'scouting-forms')],
            'invalid_email' => ['danger', __('Unknown username or email address. Please check it and try again.', 'scouting-forms')],
            'incorrect_password' => ['danger', __('The password you entered is incorrect.', 'scouting-forms')],
            'invalidkey' => ['danger', __('Your password reset link is not valid. Please request a new one.', 'scouting-forms')],
            'expiredkey' => ['danger', __('Your password reset link has expired. Please request a new one.', 'scouting-forms')],
            'password_reset_mismatch' => ['danger', __('The two passwords you entered do not match.', 'scouting-forms')],
            'password_reset_empty' => ['danger', __('Please enter a new password.', 'scouting-forms')],
            'retrieve_password_email_failure' => ['danger', __('The email could not be sent. Please try again later.', 'scouting-forms')],
            'confirm' => ['success', __('Check your email for a link to reset your password.', 'scouting-forms')],
            'changed' => ['success', __('Your password has been changed. You can log in now.', 'scouting-forms')],
            'loggedout' => ['info', __('You are now logged out.', 'scouting-forms')],
        ];
        $codes = [];
        foreach (['login', 'errors'] as $param) {
            if (isset($_GET[$param]) && is_string($_GET[$param])) {
                $codes = array_merge($codes, explode(',', sanitize_text_field(wp_unslash($_GET[$param]))));
            }
        }
        if (isset($_GET['checkemail']) && $_GET['checkemail'] === 'confirm') {
            $codes[] = 'confirm';
        }
        if (isset($_GET['password']) && $_GET['password'] === 'changed') {
            $codes[] = 'changed';
        }
        if (isset($_GET['loggedout']) && $_GET['loggedout'] === 'true') {
            $codes[] = 'loggedout';
        }
        $html = '';
        foreach (array_unique($codes) as $code) {
            if (isset($known[$code])) {
                [$variant, $text] = $known[$code];
                $html .= '<div class="' . esc_attr(ThemeClasses::alert($variant)) . '" role="' . ($variant === 'danger' ? 'alert' : 'status') . '">' . esc_html($text) . '</div>';
            }
        }
        return $html;
    }

    /**
     * wp-login.php (not a sign-in attempt) shows the site's Login page instead.
     */
    public static function toLoginPage(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            return;
        }
        $page = self::page('login');
        if (!$page || (isset($_GET['interim-login']))) {
            return;
        }
        $args = [];
        foreach (['redirect_to', 'loggedout', 'checkemail', 'password'] as $key) {
            if (isset($_GET[$key]) && is_string($_GET[$key])) {
                $args[$key] = rawurlencode(wp_unslash($_GET[$key]));
            }
        }
        wp_safe_redirect(add_query_arg($args, get_permalink($page)));
        exit;
    }

    /**
     * Lost password: the form is on the Reset Password page; a request sends WordPress's reset
     * email and comes back to the Login page (or to the form with the problem).
     */
    public static function lostPassword(): void {
        $page = self::page('resetpass');
        if (!$page) {
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_safe_redirect(get_permalink($page));
            exit;
        }
        $result = retrieve_password();
        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg('errors', implode(',', $result->get_error_codes()), get_permalink($page)));
            exit;
        }
        $login = self::page('login');
        wp_safe_redirect(add_query_arg('checkemail', 'confirm', $login ? get_permalink($login) : wp_login_url()));
        exit;
    }

    /**
     * The emailed reset link opens the Reset Password page; the new password is checked and set
     * with WordPress's own functions.
     */
    public static function resetPassword(): void {
        $page = self::page('resetpass');
        if (!$page) {
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $key = isset($_GET['key']) ? wp_unslash((string) $_GET['key']) : '';
            $login = isset($_GET['login']) ? wp_unslash((string) $_GET['login']) : '';
            $user = check_password_reset_key($key, $login);
            if (is_wp_error($user)) {
                wp_safe_redirect(add_query_arg('errors', $user->get_error_code() === 'expired_key' ? 'expiredkey' : 'invalidkey', get_permalink($page)));
                exit;
            }
            wp_safe_redirect(add_query_arg(['login' => rawurlencode($login), 'key' => rawurlencode($key)], get_permalink($page)));
            exit;
        }

        $key = isset($_POST['rp_key']) ? wp_unslash((string) $_POST['rp_key']) : '';
        $login = isset($_POST['rp_login']) ? wp_unslash((string) $_POST['rp_login']) : '';
        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            wp_safe_redirect(add_query_arg('errors', $user->get_error_code() === 'expired_key' ? 'expiredkey' : 'invalidkey', get_permalink($page)));
            exit;
        }
        $back = add_query_arg(['login' => rawurlencode($login), 'key' => rawurlencode($key)], get_permalink($page));
        $pass1 = isset($_POST['pass1']) ? (string) $_POST['pass1'] : '';
        $pass2 = isset($_POST['pass2']) ? (string) $_POST['pass2'] : '';
        if ($pass1 === '') {
            wp_safe_redirect(add_query_arg('errors', 'password_reset_empty', $back));
            exit;
        }
        if ($pass1 !== $pass2) {
            wp_safe_redirect(add_query_arg('errors', 'password_reset_mismatch', $back));
            exit;
        }
        // Like wp-login.php: the slashed password as posted (WordPress compares that form at login)
        reset_password($user, $pass1);
        $login = self::page('login');
        wp_safe_redirect(add_query_arg('password', 'changed', $login ? get_permalink($login) : wp_login_url()));
        exit;
    }

    /**
     * A failed sign-in from the Login page goes back to it with the reason.
     *
     * @param \WP_User|\WP_Error|null $user
     * @return \WP_User|\WP_Error|null
     */
    public static function backToLoginPageOnError($user) {
        if (!is_wp_error($user) || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['sm_login_page'])) {
            return $user;
        }
        $page = self::page('login');
        if (!$page) {
            return $user;
        }
        $args = ['login' => implode(',', $user->get_error_codes())];
        if (!empty($_POST['redirect_to'])) {
            $args['redirect_to'] = rawurlencode(wp_unslash((string) $_POST['redirect_to']));
        }
        wp_safe_redirect(add_query_arg($args, get_permalink($page)));
        exit;
    }
}
