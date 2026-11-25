<?php

namespace ScoutingMemories\Forms\Forms\Rendering;

use ScoutingMemories\Forms\Ui\ThemeClasses;

/**
 * RichTextField
 *
 * One job: the markup of a rich text field.
 *
 * It prints plain HTML and no script: a bar of buttons, an empty box for the editor and the
 * field's own <textarea>, which holds the HTML and is what the form sends (same name and id the
 * field always had). The site theme's "richText" Alpine.js component brings it to life (it is told
 * a field is on the page by the `sm_forms_rich_text_field` action and loads its editor then).
 * Without scripts the textarea is simply there to type in.
 *
 * What the buttons may call on the component: run(name), active(name), showCode, linkOpen,
 * linkUrl, applyLink(), removeLink(). The look is Ui\ThemeClasses' RICH_* lists.
 */
class RichTextField {

    /** Shapes drawn inside a 20 x 20 icon, by the name the button runs */
    private const ICONS = [
        'image' => '<rect x="2.5" y="4" width="15" height="12" rx="1.5"/><circle cx="7" cy="8" r="1.2"/><path d="M3 14l4-3.5 3 2.5 3-4 4 5"/>',
        'bulletList' => '<circle cx="3.5" cy="5" r="1"/><circle cx="3.5" cy="10" r="1"/><circle cx="3.5" cy="15" r="1"/><path d="M8 5h9M8 10h9M8 15h9"/>',
        'orderedList' => '<path d="M8 5h9M8 10h9M8 15h9M2.5 4l1-1v4M2.5 12.5c0-1.5 2-1.5 2 0 0 1-2 1.5-2 3h2"/>',
        'blockquote' => '<path d="M4 5v10M8 6.5h9M8 10h9M8 13.5h6"/>',
        'alignLeft' => '<path d="M3 4h14M3 8h9M3 12h14M3 16h9"/>',
        'alignCenter' => '<path d="M3 4h14M5.5 8h9M3 12h14M5.5 16h9"/>',
        'alignRight' => '<path d="M3 4h14M8 8h9M3 12h14M8 16h9"/>',
        'link' => '<path d="M8.5 11.5l3-3M9.5 6l1.3-1.3a3 3 0 0 1 4.2 4.2l-1.3 1.3M10.5 14l-1.3 1.3a3 3 0 0 1-4.2-4.2l1.3-1.3"/>',
        'undo' => '<path d="M7 4L3 8l4 4M3 8h8a5 5 0 0 1 0 10H8"/>',
        'redo' => '<path d="M13 4l4 4-4 4M17 8H9a5 5 0 0 0 0 10h3"/>',
    ];

    /**
     * @param string $id         The textarea's id
     * @param string $name       The textarea's name
     * @param string $label      The field's label (names the editor for screen readers)
     * @param string $value      The stored HTML
     * @param int    $rows       Rows of the textarea
     * @param bool   $media      Whether the "Add Media" button is offered
     * @param string $attributes More attributes for the textarea (aria-required ...), already escaped
     */
    public static function render(string $id, string $name, string $label, string $value, int $rows, bool $media, string $attributes = ''): string {
        // The theme loads its editor on pages where this has fired
        do_action('sm_forms_rich_text_field');

        return '<div x-data="richText" data-rich-text class="' . esc_attr(ThemeClasses::RICH) . '">'
            . self::toolbar($media)
            . self::linkRow()
            . sprintf(
                '<div x-ref="editor" x-show="!showCode" data-editor-class="%s" aria-label="%s"></div>',
                esc_attr(ThemeClasses::RICH_EDITOR),
                esc_attr($label)
            )
            . sprintf(
                // The box that puts the textarea out of sight wraps it: on the textarea itself its
                // full-width class would win over the "out of sight" one and push the page wide
                '<div :class="showCode ? \'\' : \'%6$s\'"><textarea x-ref="field" id="%1$s" name="%2$s" rows="%3$d"%4$s class="%5$s">%7$s</textarea></div>',
                esc_attr($id),
                esc_attr($name),
                $rows,
                $attributes,
                esc_attr(ThemeClasses::classes(ThemeClasses::FIELD, ThemeClasses::RICH_CODE)),
                esc_attr(ThemeClasses::RICH_CODE_AWAY),
                // Paragraphs as tags (older posts were saved with blank lines between them), as
                // WordPress's own editor was given them
                esc_textarea(wpautop($value))
            )
            . '</div>';
    }

    private static function toolbar(bool $media): string {
        // Not shown until the component is running: without scripts there is only the textarea
        $html = '<div data-rich-toolbar hidden :hidden="false" role="toolbar" aria-label="' . esc_attr__('Formatting', 'scouting-forms') . '" class="' . esc_attr(ThemeClasses::RICH_TOOLBAR) . '">';
        if ($media) {
            $html .= self::button('image', __('Add Media', 'scouting-forms'), self::icon('image') . ' ' . esc_html__('Add Media', 'scouting-forms'), false, ThemeClasses::RICH_BUTTON_BOXED)
                . self::divider();
        }
        $html .= self::button('paragraph', __('Paragraph', 'scouting-forms'), esc_html__('Paragraph', 'scouting-forms'))
            . self::button('heading2', __('Heading 2', 'scouting-forms'), esc_html__('Heading 2', 'scouting-forms'))
            . self::button('heading3', __('Heading 3', 'scouting-forms'), esc_html__('Heading 3', 'scouting-forms'))
            . self::divider()
            . self::button('bold', __('Bold', 'scouting-forms'), '<span aria-hidden="true" class="' . esc_attr(ThemeClasses::RICH_BOLD) . '">B</span>')
            . self::button('italic', __('Italic', 'scouting-forms'), '<span aria-hidden="true" class="' . esc_attr(ThemeClasses::RICH_ITALIC) . '">I</span>')
            . self::divider()
            . self::button('bulletList', __('Bulleted list', 'scouting-forms'), self::icon('bulletList'))
            . self::button('orderedList', __('Numbered list', 'scouting-forms'), self::icon('orderedList'))
            . self::button('blockquote', __('Quote', 'scouting-forms'), self::icon('blockquote'))
            . self::divider()
            . self::button('alignLeft', __('Align left', 'scouting-forms'), self::icon('alignLeft'))
            . self::button('alignCenter', __('Align centre', 'scouting-forms'), self::icon('alignCenter'))
            . self::button('alignRight', __('Align right', 'scouting-forms'), self::icon('alignRight'))
            . self::divider()
            . self::button('link', __('Insert or edit link', 'scouting-forms'), self::icon('link'))
            . self::divider()
            . self::button('undo', __('Undo', 'scouting-forms'), self::icon('undo'), false)
            . self::button('redo', __('Redo', 'scouting-forms'), self::icon('redo'), false)
            . self::divider()
            . sprintf(
                '<button type="button" class="%s" x-on:click="run(\'code\')" :aria-pressed="showCode" aria-pressed="false">%s</button>',
                esc_attr(ThemeClasses::RICH_BUTTON),
                esc_html__('Code', 'scouting-forms')
            );
        return $html . '</div>';
    }

    /**
     * One button of the bar.
     *
     * @param string $name    What it runs: run(name); also what active(name) is asked about
     * @param string $label   Its name for screen readers and its tooltip
     * @param string $content Its face, already escaped (text or an icon)
     * @param bool   $toggle  Whether it shows as pressed while its format is on
     */
    private static function button(string $name, string $label, string $content, bool $toggle = true, string $classes = ThemeClasses::RICH_BUTTON): string {
        return sprintf(
            '<button type="button" class="%1$s" x-on:click="run(\'%2$s\')"%3$s aria-label="%4$s" title="%4$s">%5$s</button>',
            esc_attr($classes),
            esc_attr($name),
            $toggle ? sprintf(' :aria-pressed="active(\'%s\')" aria-pressed="false"', esc_attr($name)) : '',
            esc_attr($label),
            $content
        );
    }

    private static function icon(string $name): string {
        return '<svg viewBox="0 0 20 20" aria-hidden="true" stroke-linecap="round" stroke-linejoin="round" class="' . esc_attr(ThemeClasses::RICH_ICON) . '">' . self::ICONS[$name] . '</svg>';
    }

    private static function divider(): string {
        return '<span aria-hidden="true" class="' . esc_attr(ThemeClasses::RICH_DIVIDER) . '"></span>';
    }

    /**
     * The small row for a link's address, shown while linkOpen is true.
     */
    private static function linkRow(): string {
        return sprintf(
            '<div x-show="linkOpen" hidden :hidden="!linkOpen" data-rich-link class="%1$s">'
            . '<input type="url" x-model="linkUrl" x-on:keydown.enter.prevent="applyLink()" aria-label="%2$s" placeholder="https://" class="%3$s" />'
            . '<button type="button" class="%4$s" x-on:click="applyLink()">%5$s</button>'
            . '<button type="button" class="%4$s" x-on:click="removeLink()">%6$s</button>'
            . '</div>',
            esc_attr(ThemeClasses::RICH_LINK_ROW),
            esc_attr__('Link address', 'scouting-forms'),
            esc_attr(ThemeClasses::classes(ThemeClasses::FIELD_SM, ThemeClasses::RICH_LINK_INPUT)),
            esc_attr(ThemeClasses::RICH_BUTTON_BOXED),
            esc_html__('Apply', 'scouting-forms'),
            esc_html__('Remove link', 'scouting-forms')
        );
    }
}
