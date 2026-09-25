<?php

namespace ScoutingMemories\Forms\Forms;

use ScoutingMemories\Forms\Support\ShortcodeTrust;

/**
 * Modal
 *
 * [frmmodal-content label="clicking here"]...[/frmmodal-content] and [frmmodal id=26 label=".."]
 * from the Formidable Modal add-on (used in the Add a Post form's help text), when the add-on is
 * not active: a link that opens a Bootstrap 5 modal. The modals are printed in the footer, so a
 * form inside one is never nested in the form that holds the link.
 */
class Modal {

    private const SIZES = ['modal-sm', 'modal-lg', 'modal-xl', 'modal-fullscreen'];

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
            'size' => in_array($atts['size'], self::SIZES, true) ? $atts['size'] : '',
            'content' => $content,
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

        return '<a href="#" class="' . esc_attr(trim('frmmodal-link ' . self::classes((string) $atts['class']))) . '" data-bs-toggle="modal" data-bs-target="#sm-modal-' . $index . '">'
            . esc_html($label) . '</a>';
    }

    public static function printModals(): void {
        foreach (self::$modals as $index => $modal) {
            $run = static fn() => do_shortcode($modal['content']);
            $body = $modal['trusted'] ? ShortcodeTrust::trusted($run) : $run();
            $labelId = 'sm-modal-' . $index . '-title';
            echo '<div class="modal fade sm-forms ' . esc_attr($modal['class']) . '" id="sm-modal-' . (int) $index . '" tabindex="-1"'
                . ($modal['title'] !== '' ? ' aria-labelledby="' . esc_attr($labelId) . '"' : ' aria-label="' . esc_attr__('Dialog', 'scouting-forms') . '"')
                . ' aria-hidden="true">'
                . '<div class="modal-dialog ' . esc_attr($modal['size']) . '"><div class="modal-content">'
                . '<div class="modal-header">'
                . ($modal['title'] !== '' ? '<h2 class="modal-title fs-5" id="' . esc_attr($labelId) . '">' . esc_html($modal['title']) . '</h2>' : '')
                . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . esc_attr__('Close', 'scouting-forms') . '"></button>'
                . '</div><div class="modal-body">' . $body . '</div></div></div></div>';
        }
        self::$modals = [];
    }

    private static function classes(string $classes): string {
        return implode(' ', array_filter(array_map('sanitize_html_class', preg_split('/\s+/', $classes) ?: [])));
    }
}
