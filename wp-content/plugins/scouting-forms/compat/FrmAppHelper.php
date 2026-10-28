<?php
// Stand-in for Formidable's FrmAppHelper, loaded only when Formidable is not active.
// The theme only creates one (functions.php); nothing else of it is used.

if (!defined('ABSPATH')) {
    exit;
}

class FrmAppHelper {

    public static function plugin_version(): string {
        return SM_FORMS_VERSION;
    }

    public static function is_empty_value($value, $empty = ''): bool {
        return \ScoutingMemories\Forms\Compat\Data::isEmpty($value) || $value === $empty;
    }

    public static function is_not_empty_value($value): bool {
        return !self::is_empty_value($value);
    }
}
