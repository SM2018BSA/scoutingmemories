<?php

namespace ScoutingMemories\Forms\Views;

use ScoutingMemories\Forms\Ui\ClassAttributes;
use ScoutingMemories\Forms\Ui\ThemeClasses;

/**
 * LegacyClassMap
 *
 * One job: the swap table from Bootstrap class names to Tailwind class lists.
 *
 * Lists and form texts stored in the database (view templates, field descriptions, the classes
 * typed into a shortcode) were written for the old Bootstrap site: `table`, `btn btn-secondary
 * btn-sm`, `text-nowrap`... The stored text is never edited. As it is drawn, each known Bootstrap
 * name is swapped for the Tailwind classes that give the same look; names this table does not
 * know are left alone. Run it on the stored text only, before its shortcodes are run: markup the
 * plugin prints itself is Tailwind already, where `mb-3` means something else.
 *
 * A key with several names ("btn btn-sm") is swapped as one when all of them are on the element,
 * and is tried before the single names. A bare stored table gets its cell look from the table
 * element (ThemeClasses::TABLE_CELLS), so stored rows need no classes.
 */
class LegacyClassMap {

    private const MAP = [
        // Buttons: shape (with its size), then colour
        'btn btn-sm' => ThemeClasses::BTN_SM,
        'btn btn-lg' => ThemeClasses::BTN_LG,
        'btn' => ThemeClasses::BTN,
        'btn-primary' => ThemeClasses::BTN_PRIMARY,
        'btn-secondary' => ThemeClasses::BTN_SECONDARY,
        'btn-success' => ThemeClasses::BTN_SUCCESS,
        'btn-danger' => ThemeClasses::BTN_DANGER,
        'btn-info' => ThemeClasses::BTN_INFO,
        'btn-light' => ThemeClasses::BTN_LIGHT,
        'btn-link' => ThemeClasses::BTN_LINK,
        'btn-outline-primary' => ThemeClasses::BTN_OUTLINE_PRIMARY,
        'btn-outline-secondary' => ThemeClasses::BTN_OUTLINE_SECONDARY,

        // Tables
        'table table-sm' => ThemeClasses::TABLE . ' ' . ThemeClasses::TABLE_CELLS_SM,
        'table' => ThemeClasses::TABLE . ' ' . ThemeClasses::TABLE_CELLS,
        'table-hover' => '[&_tbody_tr:hover]:bg-black/[.075]',
        'table-striped' => '[&_tbody_tr:nth-child(odd)]:bg-black/5',
        'table-bordered' => 'border border-line [&_td]:border [&_th]:border',
        'table-light' => ThemeClasses::TABLE_HEAD,
        'table-responsive' => ThemeClasses::TABLE_SCROLL,

        // Paging ("active" and "disabled" stay on the item as hooks)
        'pagination' => ThemeClasses::PAGING,
        'page-item' => '[&.active>*]:border-blue [&.active>*]:bg-blue [&.active>*]:text-white [&.disabled>*]:pointer-events-none [&.disabled>*]:text-grey',
        'page-link' => ThemeClasses::PAGE_LINK,

        // Form fields
        'form-control' => ThemeClasses::FIELD,
        'form-select' => ThemeClasses::SELECT,
        'form-label' => ThemeClasses::LABEL,
        'form-text' => ThemeClasses::HELP,
        'form-check' => ThemeClasses::CHOICE,
        'form-check-input' => ThemeClasses::CHECK,
        'form-check-label' => '',

        // Messages, badges, cards
        'alert alert-success' => ThemeClasses::ALERT . ' ' . ThemeClasses::ALERT_SUCCESS,
        'alert alert-danger' => ThemeClasses::ALERT . ' ' . ThemeClasses::ALERT_DANGER,
        'alert alert-warning' => ThemeClasses::ALERT . ' ' . ThemeClasses::ALERT_WARNING,
        'alert alert-info' => ThemeClasses::ALERT . ' ' . ThemeClasses::ALERT_INFO,
        'alert' => ThemeClasses::ALERT . ' ' . ThemeClasses::ALERT_INFO,
        'alert-link' => ThemeClasses::ALERT_LINK,
        'badge' => ThemeClasses::BADGE,
        'bg-success' => 'bg-green text-white',
        'bg-secondary' => 'bg-grey text-white',
        'bg-danger' => 'bg-red text-white',
        'bg-warning' => 'bg-yellow-400 text-ink',
        'bg-info' => 'bg-cyan text-black',
        'bg-primary' => 'bg-blue text-white',
        'card' => ThemeClasses::CARD,
        'card-header' => ThemeClasses::CARD_HEADER,
        'card-body' => 'flex-auto p-4',
        'card-title' => ThemeClasses::H5,
        'card-text' => '',
        'blockquote' => 'mb-4 text-xl',

        // Text
        'text-nowrap' => ThemeClasses::NOWRAP,
        'text-muted' => ThemeClasses::MUTED,
        'text-end' => 'text-right',
        'text-start' => 'text-left',
        'text-danger' => 'text-red',
        'text-success' => 'text-green',
        'text-primary' => 'text-blue',
        'text-decoration-none' => 'no-underline',
        'small' => 'text-sm',
        'fw-bold' => 'font-bold',
        'h3' => ThemeClasses::H3,
        'h4' => ThemeClasses::H4,
        'h5' => ThemeClasses::H5,

        // Display and layout (the old theme's own "hidden" is Tailwind's name too: left alone)
        'd-none' => 'hidden',
        'd-block' => 'block',
        'display-block' => 'block',
        'd-inline' => 'inline',
        'd-inline-block' => 'inline-block',
        'd-flex' => 'flex',
        'd-grid' => 'grid',
        'flex-grow-1' => 'grow',
        'justify-content-between' => 'justify-between',
        'justify-content-center' => 'justify-center',
        'justify-content-end' => 'justify-end',
        'align-items-center' => 'items-center',
        'float-end' => 'float-right',
        'float-start' => 'float-left',
        'w-100' => 'w-full',
        'h-100' => 'h-full',
        'img-fluid' => 'h-auto max-w-full',
        'row' => '-mx-3 flex flex-wrap',
        'col' => 'min-w-0 flex-1 px-3',
        'col-auto' => 'w-auto flex-none px-3',
        'col-12' => 'w-full px-3',
        'col-6' => 'w-1/2 px-3',
        'col-sm-6' => 'w-full px-3 sm:w-1/2',
        'col-md-3' => 'w-full px-3 md:w-1/4',
        'col-md-4' => 'w-full px-3 md:w-1/3',
        'col-md-6' => 'w-full px-3 md:w-1/2',
        'col-md-8' => 'w-full px-3 md:w-2/3',
        'col-md-9' => 'w-full px-3 md:w-3/4',
        'col-md-12' => 'w-full px-3',
        'col-lg-3' => 'w-full px-3 lg:w-1/4',
        'col-lg-4' => 'w-full px-3 lg:w-1/3',
        'col-lg-6' => 'w-full px-3 lg:w-1/2',
        'col-lg-8' => 'w-full px-3 lg:w-2/3',

        // Spacing: Bootstrap's steps 3, 4 and 5 are 1rem, 1.5rem and 3rem (1 and 2 are Tailwind's own)
        'm-3' => 'm-4', 'm-4' => 'm-6', 'm-5' => 'm-12',
        'mt-3' => 'mt-4', 'mt-4' => 'mt-6', 'mt-5' => 'mt-12',
        'mb-3' => 'mb-4', 'mb-4' => 'mb-6', 'mb-5' => 'mb-12',
        'mx-3' => 'mx-4', 'mx-4' => 'mx-6', 'mx-5' => 'mx-12',
        'my-3' => 'my-4', 'my-4' => 'my-6', 'my-5' => 'my-12',
        'ms-1' => 'ml-1', 'ms-2' => 'ml-2', 'ms-3' => 'ml-4', 'ms-4' => 'ml-6', 'ms-5' => 'ml-12', 'ms-auto' => 'ml-auto',
        'me-1' => 'mr-1', 'me-2' => 'mr-2', 'me-3' => 'mr-4', 'me-4' => 'mr-6', 'me-5' => 'mr-12', 'me-auto' => 'mr-auto',
        'p-3' => 'p-4', 'p-4' => 'p-6', 'p-5' => 'p-12',
        'pt-3' => 'pt-4', 'pt-4' => 'pt-6', 'pt-5' => 'pt-12',
        'pb-3' => 'pb-4', 'pb-4' => 'pb-6', 'pb-5' => 'pb-12',
        'px-3' => 'px-4', 'px-4' => 'px-6', 'px-5' => 'px-12',
        'py-3' => 'py-4', 'py-4' => 'py-6', 'py-5' => 'py-12',
        'ps-1' => 'pl-1', 'ps-2' => 'pl-2', 'ps-3' => 'pl-4', 'ps-4' => 'pl-6', 'ps-5' => 'pl-12',
        'pe-1' => 'pr-1', 'pe-2' => 'pr-2', 'pe-3' => 'pr-4', 'pe-4' => 'pr-6', 'pe-5' => 'pr-12',
        'gap-3' => 'gap-4', 'gap-4' => 'gap-6', 'gap-5' => 'gap-12',
    ];

    /**
     * Stored HTML with every known Bootstrap name in its class attributes swapped.
     */
    public static function html(string $html): string {
        return ClassAttributes::rewrite($html, [__CLASS__, 'swap']);
    }

    /**
     * A stored list of class names ("btn btn-danger btn-sm") swapped.
     */
    public static function names(string $classes): string {
        return ClassAttributes::rewriteList($classes, [__CLASS__, 'swap']);
    }

    /**
     * Whether a stored list of class names asks for a Bootstrap button.
     */
    public static function isButton(string $classes): bool {
        return (bool) preg_match('/(?<![\w-])btn(?![\w-])/', $classes);
    }

    /**
     * @param string[] $names
     * @return string[]
     */
    public static function swap(array $names): array {
        $swapped = [];
        foreach (self::MAP as $key => $classes) {
            $wanted = explode(' ', $key);
            if (array_diff($wanted, $names)) {
                continue;
            }
            // The Tailwind classes take the place of the first of the names they stand for
            $at = min(array_keys(array_intersect($names, $wanted)));
            $swapped[$at] = $classes;
            foreach (array_keys(array_intersect($names, $wanted)) as $index) {
                if ($index !== $at) {
                    $swapped[$index] = '';
                }
                // Out of the running for the single-name keys that follow
                $names[$index] = '';
            }
        }
        $out = [];
        foreach ($names as $index => $name) {
            $value = array_key_exists($index, $swapped) ? $swapped[$index] : $name;
            foreach (preg_split('/\s+/', $value) ?: [] as $class) {
                if ($class !== '') {
                    $out[] = $class;
                }
            }
        }
        return $out;
    }
}
