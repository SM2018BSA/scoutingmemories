<?php

namespace ScoutingMemories\Forms\Forms\Rendering;

use ScoutingMemories\Forms\Ui\ClassAttributes;
use ScoutingMemories\Forms\Ui\ThemeClasses;

/**
 * FormidableClasses
 *
 * One job: the table from Formidable's own class names to the Tailwind classes that give them
 * their look.
 *
 * Forms are drawn from the templates Formidable stored (field custom_html, the form's before_html
 * and submit_html) and from the layout names typed into each field's "CSS layout classes" box
 * (frm_first, frm_half, frm_third ...). Scripts, stored settings and tests find elements by those
 * names, so they stay in the markup as bare hooks; no CSS hangs on them. The look comes from the
 * Tailwind classes this table prints next to each one. Names it does not know are left alone.
 *
 * A key written "tag.name" only applies to that element (a legend marked frm_hidden is read out
 * but not shown; anything else marked frm_hidden is not ours to show or hide).
 */
class FormidableClasses {

    private const MAP = [
        // The form
        'frm_form_title' => ThemeClasses::H3,
        'frm_screen_reader' => 'sr-only',
        'legend.frm_hidden' => 'sr-only',
        'frm_fields_container' => ThemeClasses::FIELD_GRID,
        'frm_grid_container' => ThemeClasses::FIELD_GRID,
        'frm_submit' => ThemeClasses::SUBMIT_ROW,

        // One field
        'frm_form_field' => ThemeClasses::FIELD_BOX,
        'frm_primary_label' => ThemeClasses::LABEL,
        'frm_required' => ThemeClasses::REQUIRED,
        'frm_description' => ThemeClasses::HELP,
        'frm_error' => ThemeClasses::ERROR,
        'frm_show_it' => ThemeClasses::SHOWN_VALUE,

        // Label position "none" / "hidden": the label is for screen readers only
        'frm_none_container' => '[&>.frm_primary_label]:sr-only',
        'frm_hidden_container' => '[&>.frm_primary_label]:sr-only',

        // Sections and repeating rows
        'frm_section_heading' => ThemeClasses::FIELD_GRID,
        'frm_repeat_grid' => ThemeClasses::REPEAT_ROW,
        'frm_repeat_buttons' => ThemeClasses::REPEAT_BUTTONS,

        // Layout: Formidable's 12 columns, side by side from 600px up
        'frm_full' => 'col-span-12',
        'frm_first' => 'min-[600px]:col-start-1',
        'frm_half' => 'min-[600px]:col-span-6',
        'frm_third' => 'min-[600px]:col-span-4',
        'frm_two_thirds' => 'min-[600px]:col-span-8',
        'frm_fourth' => 'min-[600px]:col-span-3',
        'frm_three_fourths' => 'min-[600px]:col-span-9',
        'frm_fifth' => 'min-[600px]:col-span-2',
        'frm_sixth' => 'min-[600px]:col-span-2',
        'frm1' => 'min-[600px]:col-span-1',
        'frm2' => 'min-[600px]:col-span-2',
        'frm3' => 'min-[600px]:col-span-3',
        'frm4' => 'min-[600px]:col-span-4',
        'frm5' => 'min-[600px]:col-span-5',
        'frm6' => 'min-[600px]:col-span-6',
        'frm7' => 'min-[600px]:col-span-7',
        'frm8' => 'min-[600px]:col-span-8',
        'frm9' => 'min-[600px]:col-span-9',
        'frm10' => 'min-[600px]:col-span-10',
        'frm11' => 'min-[600px]:col-span-11',
        'frm12' => 'col-span-12',
    ];

    /** The text under the form's title is body-sized, unlike a field's help text */
    private const FORM_HEAD = [
        'frm_description' => ThemeClasses::FORM_INTRO,
    ];

    /**
     * HTML with the Tailwind classes added after each Formidable name in its class attributes.
     * Run it once on a template, before other markup is put into it.
     */
    public static function html(string $html): string {
        return ClassAttributes::rewrite($html, static fn(array $names, string $tag): array => self::add($names, $tag, self::MAP));
    }

    /**
     * The same for the top of a form (its title and the text under it).
     */
    public static function formHead(string $html): string {
        $map = self::FORM_HEAD + self::MAP;
        return ClassAttributes::rewrite($html, static fn(array $names, string $tag): array => self::add($names, $tag, $map));
    }

    /**
     * A list of names ("frm_first frm_half") with their Tailwind classes added.
     */
    public static function names(string $classes, string $tag = ''): string {
        return ClassAttributes::rewriteList($classes, static fn(array $names, string $tag): array => self::add($names, $tag, self::MAP), $tag);
    }

    /**
     * @param string[] $names
     * @param array<string, string> $map
     * @return string[]
     */
    private static function add(array $names, string $tag, array $map): array {
        $out = [];
        foreach ($names as $name) {
            $out[] = $name;
            $classes = $map[strtolower($tag) . '.' . $name] ?? ($map[$name] ?? '');
            foreach (explode(' ', $classes) as $class) {
                if ($class !== '') {
                    $out[] = $class;
                }
            }
        }
        return $out;
    }
}
