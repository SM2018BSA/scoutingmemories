<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Support\ShortcodeTrust;
use ScoutingMemories\Forms\Ui\ThemeClasses;
use ScoutingMemories\Forms\Views\LegacyClassMap;

/**
 * Modal
 *
 * [frmmodal-content label="clicking here"]...[/frmmodal-content] and [frmmodal id=26 label=".."]
 * from the Formidable Modal add-on (used in the Add a Post form's help text), when the add-on is
 * not active: a link-looking button that opens a dialog. The dialog is the browser's own popover
 * (popover / popovertarget), so it opens, closes with its X, Esc or a click outside, and sits on
 * top of the page with no script and no stylesheet: its look is Tailwind classes (ThemeClasses).
 * The dialogs are printed in the footer, so a form inside one is never nested in the form that
 * holds the link.
 */
class Modal {

    /** The add-on's size names (Bootstrap's) => the dialog's width */
    private const SIZES = [
        '' => 'max-w-[500px]',
        'modal-sm' => 'max-w-[300px]',
        'modal-lg' => 'max-w-[800px]',
        'modal-xl' => 'max-w-[1140px]',
        'modal-fullscreen' => 'max-w-none',
    ];

    /** @var array<int, array{title:string, class:string, size:string, content:string, trusted:bool}> */
    private static array $modals = [];

    public static function registerHooks(): void {
        add_action('init', static function () {
            if (class_exists('frmBtsModApp')) {
                return;
            }
            foreach (['frmmodal', 'frmmodal-content'] as $tag) {
                if (!shortcode_exists($tag)) {
                    add_shortcode($tag, [__CLASS__, 'link']);
                }
            }
        }, 999);
        add_action('wp_footer', [__CLASS__, 'printModals'], 5);
    }

    /**
     * @param array<string, string>|string $atts
     */
    public static function link($atts, ?string $content = ''): string {
        $atts = shortcode_atts([
            'id' => '',
            'label' => '',
            'modal_title' => '',
            'modal_class' => '',
            'type' => 'form',
            'class' => '',
            'size' => '',
        ], is_array($atts) ? $atts : []);

        $content = (string) $content;
        if ($content === '') {
            $id = absint($atts['id']);
            if (!$id) {
                return '';
            }
            $content = $atts['type'] === 'view' ? '[display-frm-data id=' . $id . ']' : '[formidable id=' . $id . ']';
        }

        $modal = [
            'title' => (string) $atts['modal_title'],
            'class' => self::classes((string) $atts['modal_class']),
            'size' => self::SIZES[$atts['size']] ?? self::SIZES[''],
            // Bootstrap names typed into the text are swapped before its shortcodes draw their own markup
            'content' => LegacyClassMap::html($content),
            // Shortcodes inside run later, in the footer, with the trust of the place they were written
            'trusted' => ShortcodeTrust::isTrusted(),
        ];
        // The same modal rendered again (content filtered twice, e.g. for an excerpt) is reused
        $index = array_search($modal, self::$modals, true);
        if ($index === false) {
            $index = count(self::$modals);
            self::$modals[] = $modal;
        }
        $label = $atts['label'] !== '' ? $atts['label'] : __('Click here', 'scouting-forms');

        return '<button type="button" class="' . esc_attr(ThemeClasses::classes('frmmodal-link', self::classes((string) $atts['class']), ThemeClasses::LINK_BUTTON)) . '" popovertarget="sm-modal-' . $index . '">'
            . esc_html($label) . '</button>';
    }

    public static function printModals(): void {
        foreach (self::$modals as $index => $modal) {
            $run = static fn() => do_shortcode($modal['content']);
            $body = $modal['trusted'] ? ShortcodeTrust::trusted($run) : $run();
            $labelId = 'sm-modal-' . $index . '-title';
            $id = 'sm-modal-' . (int) $index;
            echo '<div popover role="dialog" class="' . esc_attr(ThemeClasses::classes(ThemeClasses::SCOPE, $modal['class'], ThemeClasses::DIALOG, $modal['size'])) . '" id="' . $id . '"'
                . ($modal['title'] !== '' ? ' aria-labelledby="' . esc_attr($labelId) . '"' : ' aria-label="' . esc_attr__('Dialog', 'scouting-forms') . '"')
                . '>'
                . '<div class="' . esc_attr(ThemeClasses::DIALOG_HEAD) . '">'
                . ($modal['title'] !== '' ? '<h2 class="' . esc_attr(ThemeClasses::DIALOG_TITLE) . '" id="' . esc_attr($labelId) . '">' . esc_html($modal['title']) . '</h2>' : '')
                . '<button type="button" class="' . esc_attr(ThemeClasses::DIALOG_CLOSE) . '" popovertarget="' . $id . '" popovertargetaction="hide" aria-label="' . esc_attr__('Close', 'scouting-forms') . '"><span aria-hidden="true">&times;</span></button>'
                . '</div><div class="' . esc_attr(ThemeClasses::DIALOG_BODY) . '">' . $body . '</div></div>';
        }
        self::$modals = [];
    }

    private static function classes(string $classes): string {
        return LegacyClassMap::names(implode(' ', array_filter(array_map('sanitize_html_class', preg_split('/\s+/', $classes) ?: []))));
    }
}
