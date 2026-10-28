<?php

namespace ScoutingMemories\Forms\Compat;

/**
 * Compat
 *
 * When Formidable is not active, the theme still calls Formidable's classes (FrmEntry,
 * FrmEntryMeta, FrmProEntriesController, FrmFormsController, FrmProDisplaysController, ...) and
 * listens to its hooks. This provides those classes from compat/ (loaded only when first used) and
 * the plugin fires the hooks (see Hooks). With Formidable active nothing here is loaded.
 */
class Compat {

    /** Classes the theme uses, by lower-case name (PHP class names ignore case) */
    private const CLASSES = [
        'frmapphelper' => 'FrmAppHelper',
        'frmdb' => 'FrmDb',
        'frmentry' => 'FrmEntry',
        'frmentrymeta' => 'FrmEntryMeta',
        'frmproentriescontroller' => 'FrmProEntriesController',
        'frmformscontroller' => 'FrmFormsController',
        'frmprodisplayscontroller' => 'FrmProDisplaysController',
    ];

    private static ?bool $formidable = null;

    public static function register(): void {
        if (self::formidableActive()) {
            return;
        }
        spl_autoload_register(static function (string $class): void {
            $name = self::CLASSES[strtolower($class)] ?? null;
            if ($name !== null) {
                require_once SM_FORMS_PLUGIN_DIR . 'compat/' . $name . '.php';
            }
        });
        Hooks::register();
        EditUsers::register();
    }

    /**
     * Is Formidable itself loaded? Decided once, before our stand-ins can answer class_exists().
     */
    public static function formidableActive(): bool {
        if (self::$formidable === null) {
            self::$formidable = class_exists('FrmAppHelper');
        }
        return self::$formidable;
    }
}
