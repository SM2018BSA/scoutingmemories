<?php

namespace ScoutingMemories\Forms\Actions;

use ScoutingMemories\Forms\Support\Mailer;

/**
 * EmailAction
 *
 * Sends a Formidable "email" form action: to, cc, bcc, from, reply-to, subject and message, with
 * entry shortcodes replaced. HTML unless the action is set to plain text. Goes through Mailer,
 * so local copies log the message instead of sending it.
 */
class EmailAction {

    /**
     * @param array<string, mixed> $settings Action settings (decoded post_content)
     * @param array<string, mixed> $context form, fields, values, entry
     */
    public static function send(array $settings, array $context): bool {
        $plain = !empty($settings['plain_text']);
        $replace = static function (string $text, bool $html = false) use ($context): string {
            return EntryShortcodes::replace($text, $context['form'], $context['fields'], $context['values'], $context['entry'], $html);
        };

        $to = self::addresses($replace((string) ($settings['email_to'] ?? '')));
        if (!$to) {
            return false;
        }

        $headers = [];
        $from = self::headerValue($replace((string) ($settings['from'] ?? '')));
        if ($from !== '') {
            $headers[] = 'From: ' . $from;
        }
        $replyTo = self::headerValue($replace((string) ($settings['reply_to'] ?? '')));
        if ($replyTo !== '' && self::addresses($replyTo)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        foreach (['cc' => 'Cc', 'bcc' => 'Bcc'] as $key => $header) {
            $list = self::addresses($replace((string) ($settings[$key] ?? '')));
            if ($list) {
                $headers[] = $header . ': ' . implode(', ', $list);
            }
        }

        $subject = self::headerValue($replace((string) ($settings['email_subject'] ?? '')));
        if ($subject === '') {
            $subject = sprintf('%s: %s', get_bloginfo('name'), $context['form']['name']);
        }

        $message = (string) ($settings['email_message'] ?? '[default-message]');
        if ($plain) {
            $body = wp_strip_all_tags($replace($message));
        } else {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $body = nl2br($replace($message, true));
        }

        return Mailer::send($to, $subject, $body, $headers);
    }

    /**
     * Valid email addresses from a comma/semicolon separated list (display names allowed).
     *
     * @return string[]
     */
    private static function addresses(string $list): array {
        $valid = [];
        foreach (preg_split('/[,;]+/', $list) as $part) {
            $part = trim($part);
            $email = preg_match('/<([^>]+)>/', $part, $m) ? trim($m[1]) : $part;
            if ($email !== '' && is_email($email)) {
                $valid[] = $part;
            }
        }
        return array_values(array_unique($valid));
    }

    /**
     * Header-safe text: no line breaks (blocks header injection). Angle brackets are kept, since
     * "Name <address>" is the normal From/Reply-To format.
     */
    private static function headerValue(string $value): string {
        return trim(preg_replace('/[\r\n\t]+/', ' ', $value));
    }
}
