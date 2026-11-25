<?php
/**
 * "Make document text available to the site": the one-time copy of text that was read
 * before the site kept it with the posts. No PDF is read again; each request goes through
 * the next 50 posts and refreshes their text, while the PDF Text screen is open.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_Text_Sync {

    const ACTION = 'scouting_pdf_text_sync';
    const BATCH = 50;

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('scouting_pdf_text_overview_tools', array($this, 'render_button'));
        add_action('wp_ajax_' . self::ACTION, array($this, 'ajax_batch'));
    }

    public function render_button() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <h2>Document text on the site</h2>
        <p>The text section under each document, the site search and the tag suggestions use the text kept with each post.
            New and corrected pages are added by themselves. Press this once to add the text that was read before.</p>
        <?php
        Scouting_PDF_Batch_Button::render(self::ACTION, 'Make document text available to the site');
    }

    public function ajax_batch() {
        Scouting_PDF_Batch_Button::handle(
            self::ACTION,
            'manage_options',
            array(
                'post_type'   => array_values(array_diff(get_post_types(array('public' => true)), array('attachment'))),
                'post_status' => array('publish', 'pending'),
            ),
            self::BATCH,
            function ($post_id) {
                return Scouting_PDF_Document_Text::refresh_post($post_id) > 0 ? 1 : 0;
            },
            function ($with_text, $total) {
                return sprintf('All document text is available to the site. %s of %s posts have text.',
                    number_format_i18n($with_text), number_format_i18n($total));
            }
        );
    }
}
