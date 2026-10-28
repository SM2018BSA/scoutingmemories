<?php

namespace ScoutingMemories\Forms\Compat;

use ScoutingMemories\Forms\Forms\DynamicFormRenderer;
use ScoutingMemories\Forms\Support\ShortcodeTrust;
use ScoutingMemories\Forms\Views\FieldValue;
use ScoutingMemories\Forms\Views\ViewRenderer;

/**
 * Api
 *
 * What the Formidable controller methods the theme calls return, built on the plugin. Calls from
 * theme code are trusted (the theme is the site's own code, not post content).
 */
class Api {

    /**
     * FrmProEntriesController::get_field_value_shortcode
     *
     * @param array<string, mixed> $atts
     */
    public static function fieldValue(array $atts): string {
        $atts = array_map(static fn($v) => is_scalar($v) ? (string) $v : '', $atts);
        return ShortcodeTrust::trusted(static fn() => FieldValue::render($atts));
    }

    /**
     * FrmProEntriesController::show_entry_shortcode
     *
     * @param array<string, mixed> $atts
     * @return array<string, mixed>|string
     */
    public static function showEntry(array $atts) {
        if (($atts['format'] ?? '') === 'array') {
            return EntryArray::build($atts['id'] ?? ($atts['entry'] ?? ''));
        }
        if (($atts['format'] ?? '') === 'json') {
            $array = EntryArray::build($atts['id'] ?? ($atts['entry'] ?? ''));
            return $array === '' ? '' : (string) wp_json_encode($array);
        }
        return ShortcodeTrust::trusted(static fn() => ViewRenderer::showEntry($atts));
    }

    /**
     * FrmFormsController::show_form / get_form_shortcode
     *
     * @param array<string, mixed> $atts
     */
    public static function form(array $atts): string {
        return DynamicFormRenderer::renderShortcode($atts);
    }

    /**
     * FrmProDisplaysController::get_shortcode
     *
     * @param array<string, mixed> $atts
     */
    public static function view(array $atts): string {
        return ViewRenderer::render($atts);
    }
}
