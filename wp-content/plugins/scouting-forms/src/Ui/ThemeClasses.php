<?php

namespace ScoutingMemories\Forms\Ui;

/**
 * ThemeClasses
 *
 * The one place the plugin's public markup gets its look: every value is a plain string of
 * Tailwind utility classes (Tailwind 4, built by the site theme, which reads this folder for class
 * names). No Bootstrap names, no stylesheet of our own. The sizes, spacing and colours are the
 * old Bootstrap 5.3.8 site's, and the strings match the theme's own class lists
 * (Scouting_Memories_2026_UI) so forms, lists and pages look alike.
 *
 * Tailwind only builds classes it can read as whole strings: never build a class name from pieces.
 * Which Formidable or Bootstrap name gets which of these strings is decided elsewhere
 * (Forms\Rendering\FormidableClasses, Views\LegacyClassMap); which colour submit buttons get is
 * decided by ButtonStyle.
 */
class ThemeClasses {

    /** Bare hook around everything the plugin prints on the public site (scripts and tests find it; no style hangs on it) */
    public const SCOPE = 'sm-forms';

    // Headings (the theme's: Roboto Slab, medium weight)
    public const H3 = 'mt-0 mb-2 font-slab text-[calc(1.3rem+0.6vw)] leading-tight font-medium xl:text-[1.75rem]';
    public const H4 = 'mt-0 mb-2 font-slab text-[calc(1.275rem+0.3vw)] leading-tight font-medium xl:text-2xl';
    public const H5 = 'mt-0 mb-2 font-slab text-xl leading-tight font-medium';

    // Links in running text
    public const LINK = 'text-blue underline hover:text-blue-link-hover';

    // Buttons: the shape first, then one colour
    public const BTN    = 'inline-block cursor-pointer rounded-md border px-3 py-1.5 text-center align-middle text-base leading-normal font-normal no-underline select-none transition-colors duration-150 focus-visible:ring-4 focus-visible:outline-0 disabled:pointer-events-none disabled:opacity-65 aria-disabled:pointer-events-none aria-disabled:opacity-65';
    public const BTN_SM = 'inline-block cursor-pointer rounded border px-2 py-1 text-center align-middle text-sm leading-normal font-normal no-underline select-none transition-colors duration-150 focus-visible:ring-4 focus-visible:outline-0 disabled:pointer-events-none disabled:opacity-65 aria-disabled:pointer-events-none aria-disabled:opacity-65';
    public const BTN_LG = 'inline-block cursor-pointer rounded-lg border px-4 py-2 text-center align-middle text-xl leading-normal font-normal no-underline select-none transition-colors duration-150 focus-visible:ring-4 focus-visible:outline-0 disabled:pointer-events-none disabled:opacity-65 aria-disabled:pointer-events-none aria-disabled:opacity-65';

    public const BTN_PRIMARY           = 'border-blue bg-blue text-white hover:border-blue-link-hover hover:bg-blue-dark hover:text-white focus-visible:ring-blue/50';
    public const BTN_SECONDARY         = 'border-grey bg-grey text-white hover:border-grey-dark hover:bg-grey-dark hover:text-white focus-visible:ring-grey/50';
    public const BTN_SUCCESS           = 'border-green bg-green text-white hover:border-green-dark hover:bg-green-dark hover:text-white focus-visible:ring-green/50';
    public const BTN_DANGER            = 'border-red bg-red text-white hover:border-red-dark hover:bg-red-dark hover:text-white focus-visible:ring-red/50';
    public const BTN_INFO              = 'border-cyan bg-cyan text-black hover:border-cyan-dark hover:bg-cyan-dark hover:text-black focus-visible:ring-cyan/50';
    public const BTN_LIGHT             = 'border-light bg-light text-black hover:border-line hover:bg-line hover:text-black focus-visible:ring-line/50';
    public const BTN_SCOUT             = 'border-brand bg-brand text-white hover:border-brand-dark hover:bg-brand-dark hover:text-white focus-visible:ring-brand/50';
    public const BTN_OUTLINE_PRIMARY   = 'border-blue bg-transparent text-blue hover:bg-blue hover:text-white focus-visible:ring-blue/50';
    public const BTN_OUTLINE_SECONDARY = 'border-grey bg-transparent text-grey hover:bg-grey hover:text-white focus-visible:ring-grey/50';
    public const BTN_LINK              = 'border-transparent bg-transparent text-blue underline hover:text-blue-link-hover focus-visible:ring-blue/50';

    /** A button that reads as a link in a sentence (opens a dialog, posts a small form) */
    public const LINK_BUTTON = 'inline cursor-pointer border-0 bg-transparent p-0 align-baseline text-blue underline hover:text-blue-link-hover';

    /** The add/remove row buttons of a repeating section: site-green icons */
    public const ICON_BUTTON = 'inline-block cursor-pointer border-0 bg-transparent p-1 text-xl leading-none text-brand no-underline hover:text-brand-dark focus-visible:ring-4 focus-visible:ring-brand/25 focus-visible:outline-0';

    // Form fields and their labels. A field with a mistake carries aria-invalid="true": red line.
    public const LABEL    = 'mb-2 inline-block';
    public const FIELD    = 'block w-full appearance-none rounded-md border border-field-line bg-white px-3 py-1.5 text-base leading-normal font-normal text-ink focus:border-[#86b7fe] focus:ring-4 focus:ring-blue/25 focus:outline-0 aria-invalid:border-red aria-invalid:focus:ring-red/25';
    public const FIELD_SM = 'block w-full appearance-none rounded border border-field-line bg-white px-2 py-1 text-sm leading-normal font-normal text-ink focus:border-[#86b7fe] focus:ring-4 focus:ring-blue/25 focus:outline-0';
    public const SELECT   = 'block w-full rounded-md border border-field-line bg-white py-1.5 pr-9 pl-3 text-base leading-normal font-normal text-ink focus:border-[#86b7fe] focus:ring-4 focus:ring-blue/25 focus:outline-0 aria-invalid:border-red aria-invalid:focus:ring-red/25 data-[sm-combo]:sr-only';
    public const FILE     = 'block w-full rounded-md border border-field-line bg-white px-3 py-1.5 text-base leading-normal font-normal text-ink file:mr-3 file:cursor-pointer file:border-0 file:bg-light file:px-3 file:py-1.5 focus:border-[#86b7fe] focus:ring-4 focus:ring-blue/25 focus:outline-0 aria-invalid:border-red';
    public const HELP     = 'mt-1 text-sm text-grey';
    public const ERROR    = 'mt-1 text-sm text-red';
    public const REQUIRED = 'font-bold text-red';

    // Checkboxes and radio buttons: one choice per line, or side by side
    public const CHOICE        = 'flex items-baseline gap-2';
    public const CHOICE_INLINE = 'mr-4 inline-flex items-baseline gap-2';
    public const CHECK         = 'size-4 shrink-0 translate-y-0.5 accent-blue focus-visible:ring-4 focus-visible:ring-blue/25 focus-visible:outline-0 aria-invalid:outline aria-invalid:outline-red';
    public const SWITCH        = "relative h-4 w-8 shrink-0 translate-y-0.5 cursor-pointer appearance-none rounded-full border border-field-line bg-white transition-colors before:absolute before:top-px before:left-px before:size-3 before:rounded-full before:bg-grey before:transition-transform before:content-[''] checked:border-blue checked:bg-blue checked:before:translate-x-4 checked:before:bg-white focus-visible:ring-4 focus-visible:ring-blue/25 focus-visible:outline-0";

    // The form's own heading text
    public const FORM_INTRO = 'mb-4 text-base text-grey';

    // Formidable's 12-column field grid (2% between columns); fields fill the row unless their
    // layout name says otherwise (FormidableClasses)
    public const FIELD_GRID = 'grid auto-rows-max grid-cols-12 gap-x-[2%] *:col-span-12';
    public const FIELD_BOX  = 'mb-4';
    public const SUBMIT_ROW = 'mt-4';
    public const PAGE_NAV   = 'mt-4 flex gap-2';

    // Section headings: an h3 with a line under it; a collapsible one is a button with an arrow
    public const SECTION_TITLE        = 'mt-6 mb-3 border-b border-line pb-1 font-slab text-xl leading-tight font-medium';
    public const SECTION_TITLE_TOGGLE = 'group mt-6 mb-3 flex cursor-pointer items-center gap-2 border-b border-line pb-1 font-slab text-xl leading-tight font-medium focus-visible:ring-4 focus-visible:ring-blue/25 focus-visible:outline-0';
    public const SECTION_ARROW        = 'transition-transform duration-200 group-aria-expanded:rotate-180';
    /** A collapsible section's fields: closed while it carries data-sm-closed (open without scripts) */
    public const SECTION_BODY         = 'data-[sm-closed]:hidden noscript:data-[sm-closed]:grid';

    // Repeating sections: one row per entry, add/remove at the end of each row
    public const REPEAT_ROW     = 'border-b border-dashed border-line py-3';
    public const REPEAT_BUTTONS = 'flex items-end gap-1';
    public const SHOWN_VALUE    = 'm-0 min-h-6';

    // Searchable dropdowns (forms-front.js builds them from these)
    public const COMBO             = 'relative';
    public const COMBO_CHIPS       = 'mb-1 flex flex-wrap gap-1';
    public const COMBO_CHIP        = 'inline-flex items-center rounded-md border border-line bg-light px-2 py-1 text-sm leading-none font-medium text-ink';
    public const COMBO_CHIP_REMOVE = 'ml-1 cursor-pointer border-0 bg-transparent p-0 text-base leading-none text-ink opacity-50 hover:opacity-75';
    public const COMBO_LIST        = 'absolute top-full left-0 z-[1060] mt-0.5 max-h-64 w-full list-none overflow-y-auto rounded-md border border-line bg-white p-0 shadow-sm';
    public const COMBO_OPTION      = 'cursor-pointer border-b border-line px-3 py-1.5 last:border-b-0 hover:bg-light data-[sm-focus]:bg-light aria-selected:bg-brand aria-selected:text-white';
    public const COMBO_EMPTY       = 'px-3 py-1.5 text-grey';

    // Rich text fields (Forms\Rendering\RichTextField; the theme's "richText" Alpine component
    // drives them): a grey bar of buttons like the old editor's, the pressed one white with a line
    public const RICH              = 'block w-full';
    public const RICH_TOOLBAR      = 'flex flex-wrap items-center gap-1 rounded-t-md border border-b-0 border-field-line bg-light p-1';
    public const RICH_BUTTON       = 'inline-flex min-h-7 min-w-7 cursor-pointer items-center justify-center gap-1 rounded border border-transparent bg-transparent px-1.5 py-0.5 text-sm leading-normal font-normal text-ink hover:border-field-line hover:bg-white focus-visible:ring-4 focus-visible:ring-blue/25 focus-visible:outline-0 aria-pressed:border-field-line aria-pressed:bg-white';
    public const RICH_BUTTON_BOXED = 'inline-flex min-h-7 min-w-7 cursor-pointer items-center justify-center gap-1 rounded border border-field-line bg-white px-2 py-0.5 text-sm leading-normal font-normal text-ink hover:bg-light focus-visible:ring-4 focus-visible:ring-blue/25 focus-visible:outline-0';
    public const RICH_DIVIDER      = 'mx-1 h-5 w-px bg-field-line';
    public const RICH_ICON         = 'size-4 fill-none stroke-current stroke-2';
    public const RICH_BOLD         = 'font-bold';
    public const RICH_ITALIC       = 'font-serif italic';
    public const RICH_LINK_ROW     = 'flex flex-wrap items-center gap-2 border border-b-0 border-field-line bg-light p-2';
    public const RICH_LINK_INPUT   = 'min-w-0 flex-1';
    /** The box people type in: the theme's script puts these on the editor it builds */
    public const RICH_EDITOR       = 'prose max-w-none min-h-[300px] rounded-b-md border border-field-line bg-white px-3 py-2 text-ink prose-a:text-blue prose-a:underline focus:border-[#86b7fe] focus:ring-4 focus:ring-blue/25 focus:outline-0';
    /** The plain box with the HTML in it (the "Code" button, or no scripts), added to FIELD */
    public const RICH_CODE         = 'min-h-[300px] font-mono';
    /** That box while the editor is showing: off the page, still sent with the form */
    public const RICH_CODE_AWAY    = 'sr-only';

    // Honeypot: off the page for people, still in it for bots
    public const HONEYPOT = 'absolute -left-[9999px] size-px overflow-hidden';

    // Messages
    public const ALERT         = 'relative mb-4 rounded-md border p-4 text-ink';
    public const ALERT_SUCCESS = 'border-green/40 bg-green/20';
    public const ALERT_DANGER  = 'border-red/40 bg-red/20';
    public const ALERT_WARNING = 'border-yellow-400/40 bg-yellow-400/20';
    public const ALERT_INFO    = 'border-cyan/40 bg-cyan/20';
    public const ALERT_SMALL   = 'text-sm';
    public const ALERT_LINK    = 'font-bold text-ink underline';
    /** A message with a close box: ticking the box hides it */
    public const ALERT_CLOSABLE = 'pr-12 has-[:checked]:hidden';
    public const ALERT_CLOSE    = 'absolute top-0 right-0 cursor-pointer px-4 py-3 text-2xl leading-none text-ink opacity-50 hover:opacity-75 has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-blue/25';

    // Badges
    public const BADGE           = 'inline-block rounded-md px-2 py-1 align-baseline text-xs leading-none font-bold whitespace-nowrap';
    public const BADGE_SUCCESS   = 'bg-green text-white';
    public const BADGE_SECONDARY = 'bg-grey text-white';
    public const BADGE_WARNING   = 'bg-yellow-400 text-ink';
    public const BADGE_DANGER    = 'bg-red text-white';
    public const BADGE_INFO      = 'bg-cyan text-black';
    public const BADGE_LIGHT     = 'border border-line bg-light text-ink';

    // Tables
    public const TABLE         = 'mb-4 w-full border-collapse align-top text-ink';
    public const TABLE_BOX     = 'border border-line';
    public const TABLE_HEAD    = 'bg-light';
    public const TH            = 'border-b border-line p-2 text-left font-bold';
    public const TD            = 'border-b border-line p-2';
    public const TABLE_ROW     = 'hover:bg-black/[.075]';
    public const TABLE_ROW_ALT = 'odd:bg-black/5 hover:bg-black/[.075]';
    public const TABLE_SCROLL  = 'overflow-x-auto';
    /** A table whose cells carry no classes (a stored list): the cell look, set from the table */
    public const TABLE_CELLS    = '[&_td]:border-b [&_td]:border-line [&_td]:p-2 [&_th]:border-b [&_th]:border-line [&_th]:p-2 [&_th]:text-left [&_th]:font-bold';
    public const TABLE_CELLS_SM = '[&_td]:border-b [&_td]:border-line [&_td]:p-1 [&_th]:border-b [&_th]:border-line [&_th]:p-1 [&_th]:text-left [&_th]:font-bold';

    // Small images in lists
    public const THUMB       = 'size-12 rounded-md object-cover shadow-sm';
    public const THUMB_SMALL = 'size-[50px] rounded-md border border-line';
    public const THUMB_EMPTY = 'flex size-[50px] items-center justify-center rounded-md border border-line bg-light text-grey';

    // Paging: a joined row of boxes, blue numbers, the current page solid blue
    public const PAGING         = 'flex flex-wrap list-none pl-0';
    public const PAGE_LINK      = '-ml-px block border border-line bg-white px-3 py-1.5 text-blue no-underline first:ml-0 hover:bg-light hover:text-blue-link-hover';
    public const PAGE_LINK_HERE = '-ml-px block border border-blue bg-blue px-3 py-1.5 text-white no-underline first:ml-0';
    public const PAGE_LINK_OFF  = '-ml-px block border border-line bg-white px-3 py-1.5 text-grey no-underline first:ml-0';

    // Cards
    public const CARD        = 'relative flex min-w-0 flex-col rounded-md border border-black/[.175] bg-white break-words';
    public const CARD_HEADER = 'rounded-t-md border-b border-black/[.175] bg-light px-4 py-2';
    public const CARD_BODY   = 'flex-auto p-6';

    // Tabs drawn as pills
    public const PILLS     = 'mb-6 flex list-none flex-wrap pl-0';
    public const PILL      = 'block rounded-md px-4 py-2 text-blue no-underline hover:text-blue-link-hover';
    public const PILL_HERE = 'block rounded-md bg-blue px-4 py-2 text-white no-underline';

    // Dialogs (the browser's own popover: opened by a button, no script)
    public const DIALOG       = 'mx-auto mt-7 mb-auto max-h-[calc(100%-3.5rem)] w-[calc(100%-1rem)] overflow-y-auto rounded-lg border border-black/[.175] bg-white p-0 text-ink shadow-lg backdrop:bg-black/50';
    public const DIALOG_HEAD  = 'flex items-center justify-between border-b border-line p-4';
    public const DIALOG_TITLE = 'm-0 font-slab text-xl leading-normal font-medium';
    public const DIALOG_CLOSE = 'ml-auto cursor-pointer border-0 bg-transparent p-2 text-2xl leading-none text-ink opacity-50 hover:opacity-75 focus-visible:ring-4 focus-visible:ring-blue/25 focus-visible:outline-0';
    public const DIALOG_BODY  = 'p-4';

    // Text and layout pieces used in more than one place
    public const MUTED       = 'text-grey';
    public const MUTED_SMALL = 'text-sm text-grey';
    public const PANEL       = 'p-4';
    public const BAR         = 'flex items-center justify-between';
    public const NOWRAP      = 'whitespace-nowrap';
    public const INLINE      = 'inline';
    /** Hidden whatever else the element carries (plain `hidden` loses to a button's inline-block) */
    public const HIDDEN      = 'hidden!';

    private const BUTTON_SIZES = [
        'sm' => self::BTN_SM,
        'md' => self::BTN,
        'lg' => self::BTN_LG,
    ];

    private const BUTTON_COLOURS = [
        'primary' => self::BTN_PRIMARY,
        'secondary' => self::BTN_SECONDARY,
        'success' => self::BTN_SUCCESS,
        'danger' => self::BTN_DANGER,
        'info' => self::BTN_INFO,
        'light' => self::BTN_LIGHT,
        'scout' => self::BTN_SCOUT,
        'outline' => self::BTN_OUTLINE_SECONDARY,
        'outline-primary' => self::BTN_OUTLINE_PRIMARY,
        'link' => self::BTN_LINK,
    ];

    private const ALERTS = [
        'success' => self::ALERT_SUCCESS,
        'danger' => self::ALERT_DANGER,
        'warning' => self::ALERT_WARNING,
        'info' => self::ALERT_INFO,
    ];

    private const BADGES = [
        'success' => self::BADGE_SUCCESS,
        'secondary' => self::BADGE_SECONDARY,
        'warning' => self::BADGE_WARNING,
        'danger' => self::BADGE_DANGER,
        'info' => self::BADGE_INFO,
        'light' => self::BADGE_LIGHT,
    ];

    /**
     * Several class lists as one string, for a class attribute.
     */
    public static function classes(string ...$lists): string {
        return implode(' ', array_filter(array_map('trim', $lists), 'strlen'));
    }

    /**
     * @param string $colour 'primary' | 'secondary' | 'success' | 'danger' | 'info' | 'light' | 'scout' | 'outline' | 'outline-primary' | 'link'
     * @param string $size   'sm' | 'md' | 'lg'
     */
    public static function button(string $colour = 'primary', string $size = 'md', string $extra = ''): string {
        return self::classes(
            self::BUTTON_SIZES[$size] ?? self::BTN,
            self::BUTTON_COLOURS[$colour] ?? self::BTN_PRIMARY,
            $extra
        );
    }

    /**
     * @param string $variant 'success' | 'danger' | 'warning' | 'info'
     */
    public static function alert(string $variant = 'info', string $extra = ''): string {
        return self::classes(self::ALERT, self::ALERTS[$variant] ?? self::ALERT_INFO, $extra);
    }

    /**
     * A message that can be closed without a script: the close box is a checkbox, and the
     * message hides itself while the box is ticked.
     *
     * @param string $variant See alert()
     * @param string $html    The message, already escaped
     */
    public static function closableAlert(string $variant, string $html, string $role = 'status'): string {
        return '<div class="' . esc_attr(self::alert($variant, self::ALERT_CLOSABLE)) . '" role="' . esc_attr($role) . '">' . $html
            . '<label class="' . esc_attr(self::ALERT_CLOSE) . '"><input type="checkbox" class="sr-only" aria-label="' . esc_attr__('Close', 'scouting-forms') . '" />'
            . '<span aria-hidden="true">&times;</span></label></div>';
    }

    /**
     * @param string $variant 'success' | 'secondary' | 'warning' | 'danger' | 'info' | 'light'
     */
    public static function badge(string $variant = 'secondary'): string {
        return self::classes(self::BADGE, self::BADGES[$variant] ?? self::BADGE_SECONDARY);
    }

    /**
     * The class lists forms-front.js gives the elements it builds (it has none of its own).
     *
     * @return array<string, string>
     */
    public static function forBrowser(): array {
        return [
            'error' => self::ERROR,
            'combo' => self::COMBO,
            'comboChips' => self::COMBO_CHIPS,
            'comboChip' => self::COMBO_CHIP,
            'comboChipRemove' => self::COMBO_CHIP_REMOVE,
            'comboInput' => self::FIELD,
            'comboList' => self::COMBO_LIST,
            'comboOption' => self::COMBO_OPTION,
            'comboEmpty' => self::COMBO_EMPTY,
        ];
    }
}
