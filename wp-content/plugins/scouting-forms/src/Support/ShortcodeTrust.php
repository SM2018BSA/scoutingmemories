<?php

namespace ScoutingMemories\Forms\Support;

/**
 * ShortcodeTrust
 *
 * [sm_field_value] / [frm-field-value] and [sm_show_entry] / [frm-show-entry] can reveal any entry's
 * data (a member's email, for example). The site only uses them in settings written by
 * administrators: form actions, default values and view templates. Those places run shortcodes
 * inside trusted(); anywhere else (a post or page any author can write) the shortcodes only show
 * what the viewer may see.
 */
class ShortcodeTrust {

    private static int $depth = 0;

    /**
     * Run shortcodes written by administrators (form/view settings).
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function trusted(callable $fn) {
        self::$depth++;
        try {
            return $fn();
        } finally {
            self::$depth--;
        }
    }

    public static function isTrusted(): bool {
        return self::$depth > 0;
    }

    /**
     * Brackets in text that came from a form (entry values) are encoded, so text people type can
     * never be read as a shortcode when a trusted template runs.
     */
    public static function inert(string $value): string {
        return str_replace(['[', ']'], ['&#91;', '&#93;'], $value);
    }
}
