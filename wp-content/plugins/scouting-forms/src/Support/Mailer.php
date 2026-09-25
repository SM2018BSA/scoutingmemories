<?php

namespace ScoutingMemories\Forms\Support;

/**
 * Mailer
 *
 * The one place the plugin sends email (form notifications, registration mail, ...).
 * On the live site it calls wp_mail(). On a local copy it never sends: the message is stored
 * in a log shown under Scouting Forms > Test Tools, so testing can't email real people.
 */
class Mailer {

    private const LOG_OPTION = 'sm_forms_test_mail_log';
    private const LOG_LIMIT = 50;

    /**
     * On a local copy, WordPress's own mail (password/email change notices, core emails) is also
     * logged instead of sent, so account tests cannot email real people. No effect on live.
     */
    public static function registerHooks(): void {
        if (!Environment::isLocal()) {
            return;
        }
        add_filter('pre_wp_mail', static function ($return, $atts) {
            self::log($atts['to'] ?? '', '[WordPress] ' . (string) ($atts['subject'] ?? ''), (string) ($atts['message'] ?? ''), $atts['headers'] ?? [], (array) ($atts['attachments'] ?? []));
            return true;
        }, 10, 2);
    }

    /**
     * @param string|string[] $to
     * @param string|string[] $headers
     * @param string[] $attachments
     */
    public static function send($to, string $subject, string $body, $headers = [], array $attachments = []): bool {
        if (Environment::isLocal()) {
            self::log($to, $subject, $body, $headers, $attachments);
            return true;
        }
        return wp_mail($to, $subject, $body, $headers, $attachments);
    }

    /**
     * @return array<int, array<string, mixed>> Newest first
     */
    public static function getLog(): array {
        $log = get_option(self::LOG_OPTION, []);
        return is_array($log) ? array_reverse($log) : [];
    }

    public static function clearLog(): void {
        delete_option(self::LOG_OPTION);
    }

    /**
     * @param string|string[] $to
     * @param string|string[] $headers
     * @param string[] $attachments
     */
    private static function log($to, string $subject, string $body, $headers, array $attachments): void {
        $log = get_option(self::LOG_OPTION, []);
        if (!is_array($log)) {
            $log = [];
        }
        $log[] = [
            'time' => current_time('mysql'),
            'to' => is_array($to) ? implode(', ', $to) : (string) $to,
            'subject' => $subject,
            'body' => $body,
            'headers' => is_array($headers) ? implode("\n", $headers) : (string) $headers,
            'attachments' => array_map('basename', $attachments),
        ];
        update_option(self::LOG_OPTION, array_slice($log, -self::LOG_LIMIT), false);
    }
}
