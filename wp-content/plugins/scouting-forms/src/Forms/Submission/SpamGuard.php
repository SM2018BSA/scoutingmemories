<?php

namespace ScoutingMemories\Forms\Forms\Submission;

use ScoutingMemories\Forms\Support\Environment;
use ScoutingMemories\Forms\Support\FormidableSettings;

/**
 * SpamGuard
 *
 * Spam protection for plugin-rendered forms:
 *  - honeypot: a text field hidden from people; bots that fill it are rejected (Formidable's
 *    "basic" honeypot does the same, with the same label),
 *  - timing: a signed timestamp; submissions faster than MIN_SECONDS or older than a day fail,
 *  - reCAPTCHA: when the form has a captcha field and Formidable has keys, the token is verified
 *    with Google. Local copies skip the Google call (Google rejects localhost keys),
 *  - rate limit: at most RATE_LIMIT submissions of one form per RATE_WINDOW from one person
 *    (their user account, or a hash of their IP address when logged out), so a public form such
 *    as a search that emails the admins cannot be used to flood anyone.
 */
class SpamGuard {

    private const MIN_SECONDS = 3;
    private const MAX_AGE = DAY_IN_SECONDS;
    private const RATE_LIMIT = 30;
    private const RATE_WINDOW = 10 * MINUTE_IN_SECONDS;

    /**
     * Hidden inputs to print inside the form.
     */
    public static function fields(int $formId): string {
        $time = time();
        return sprintf(
            '<div class="sm-hp" aria-hidden="true"><label for="sm_hp_%1$d">%2$s</label><input type="text" id="sm_hp_%1$d" name="sm_hp" value="" tabindex="-1" autocomplete="off" /></div>'
            . '<input type="hidden" name="sm_ts" value="%3$d" /><input type="hidden" name="sm_ts_sig" value="%4$s" />',
            $formId,
            esc_html__('If you are human, leave this field blank.', 'scouting-forms'),
            $time,
            esc_attr(self::sign($formId, $time))
        );
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return string Error message, or '' when the submission passes
     */
    public static function check(int $formId, array $fields): string {
        $honeypot = isset($_POST['sm_hp']) ? trim((string) wp_unslash($_POST['sm_hp'])) : '';
        if ($honeypot !== '') {
            return __('Your submission could not be accepted.', 'scouting-forms');
        }

        $time = isset($_POST['sm_ts']) ? (int) $_POST['sm_ts'] : 0;
        $sig = isset($_POST['sm_ts_sig']) ? sanitize_text_field(wp_unslash($_POST['sm_ts_sig'])) : '';
        if (!$time || !hash_equals(self::sign($formId, $time), $sig)) {
            return __('The form expired. Please reload the page and try again.', 'scouting-forms');
        }
        $age = time() - $time;
        if ($age < self::MIN_SECONDS) {
            return __('That was too fast. Please wait a moment and submit again.', 'scouting-forms');
        }
        if ($age > self::MAX_AGE) {
            return __('The form expired. Please reload the page and try again.', 'scouting-forms');
        }

        foreach ($fields as $field) {
            if ($field['type'] === 'captcha') {
                $error = self::verifyRecaptcha($field);
                if ($error !== '') {
                    return $error;
                }
            }
        }

        if (!self::withinRateLimit($formId)) {
            return __('Too many submissions in a short time. Please wait a few minutes and try again.', 'scouting-forms');
        }
        return '';
    }

    /**
     * Count this submission; false once the person has sent RATE_LIMIT in the window. Only the
     * connection's own address is used (forwarding headers can be forged), and only as a hash.
     */
    private static function withinRateLimit(int $formId): bool {
        $who = is_user_logged_in()
            ? 'u' . get_current_user_id()
            : 'ip' . (isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '');
        $key = 'sm_rl_' . substr(hash_hmac('sha256', $formId . '|' . $who, wp_salt('nonce')), 0, 32);
        $count = (int) get_transient($key);
        if ($count >= self::RATE_LIMIT) {
            return false;
        }
        set_transient($key, $count + 1, self::RATE_WINDOW);
        return true;
    }

    private static function verifyRecaptcha(array $field): string {
        $keys = FormidableSettings::recaptcha();
        if ($keys['secret'] === '' || Environment::isLocal()) {
            return '';
        }

        $message = (string) ($field['field_options']['invalid'] ?? '');
        if ($message === '') {
            $message = __('The reCAPTCHA was not entered correctly', 'scouting-forms');
        }

        $token = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';
        if ($token === '') {
            return $message;
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 10,
            'body' => [
                'secret' => $keys['secret'],
                'response' => $token,
                'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            ],
        ]);
        if (is_wp_error($response)) {
            return __('The spam check could not be completed. Please try again.', 'scouting-forms');
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return !empty($body['success']) ? '' : $message;
    }

    private static function sign(int $formId, int $time): string {
        return hash_hmac('sha256', "sm_form|{$formId}|{$time}", wp_salt('nonce'));
    }
}
