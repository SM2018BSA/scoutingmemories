<?php
/**
 * PDF Text > Tag suggestions: the list a reviewer works through. Each row is one suggestion
 * with the line it came from; Add tag puts the tag on the post, Not right puts the
 * suggestion away for good. Nothing is tagged without one of those presses.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_Suggestions_Screen {

    const PAGE = 'scouting-pdf-tag-suggestions';
    const DECIDE_ACTION = 'scouting_pdf_suggestion';
    const FIND_ACTION = 'scouting_pdf_find_suggestions';
    const FIND_BATCH = 25;
    const POSTS_PER_SCREEN = 20;

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array('Scouting_PDF_Tag_Suggestions', 'register'));
        // After the PDF Text menu itself (priority 10)
        add_action('admin_menu', array($this, 'add_menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_post_' . self::DECIDE_ACTION, array($this, 'handle_decision'));
        add_action('wp_ajax_' . self::FIND_ACTION, array($this, 'ajax_find'));
    }

    private static function capability() {
        return apply_filters('scouting_pdf_ocr_review_cap', 'edit_others_posts');
    }

    public function add_menu() {
        if (!Scouting_PDF_OCR::enabled()) {
            return;
        }
        add_submenu_page('scouting-pdf-text', 'Tag suggestions', 'Tag suggestions', self::capability(), self::PAGE, array($this, 'render_page'));
    }

    public function enqueue_assets($hook) {
        if (strpos((string) $hook, self::PAGE) !== false) {
            // The progress bar's look
            wp_enqueue_style('scouting-pdf-admin', SCOUTING_PDF_PLUGIN_URL . 'assets/css/scouting-pdf-admin.css', array(), SCOUTING_PDF_VERSION);
        }
    }

    private static function page_url($args = array()) {
        return add_query_arg($args, admin_url('admin.php?page=' . self::PAGE));
    }

    // ---- The button: look through every document ---------------------------------------

    public function ajax_find() {
        Scouting_PDF_Batch_Button::handle(
            self::FIND_ACTION,
            'manage_options',
            array(
                'post_type'   => array_values(array_diff(get_post_types(array('public' => true)), array('attachment'))),
                'post_status' => array('publish', 'pending'),
                'meta_query'  => array(array('key' => Scouting_PDF_Document_Text::META_KEY, 'compare' => 'EXISTS')),
            ),
            self::FIND_BATCH,
            function ($post_id) {
                return Scouting_PDF_Tag_Suggestions::add($post_id, Scouting_PDF_Name_Matcher::suggestions_for($post_id));
            },
            function ($new) {
                return sprintf(
                    _n('Finished. %s new suggestion. Reload this page to see it.', 'Finished. %s new suggestions. Reload this page to see them.', $new, 'scouting-pdf-embedder'),
                    number_format_i18n($new)
                );
            }
        );
    }

    // ---- Add tag / Not right ------------------------------------------------------------

    public function handle_decision() {
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $id = isset($_POST['suggestion']) ? sanitize_key(wp_unslash($_POST['suggestion'])) : '';
        $decision = isset($_POST['decision']) ? sanitize_key(wp_unslash($_POST['decision'])) : '';
        $paged = isset($_POST['paged']) ? max(1, absint(wp_unslash($_POST['paged']))) : 1;
        check_admin_referer(self::DECIDE_ACTION . '_' . $post_id . '_' . $id);
        if (!current_user_can(self::capability()) || !current_user_can('edit_post', $post_id)) {
            wp_die(esc_html('You are not allowed to change this post.'), 403);
        }
        if (!in_array($decision, array('add', 'reject'), true)) {
            wp_die(esc_html('Unknown choice.'), 400);
        }
        $waiting = Scouting_PDF_Tag_Suggestions::waiting($post_id);
        $result = 'gone';
        if (isset($waiting[$id])) {
            $suggestion = $waiting[$id];
            if ($decision === 'reject') {
                Scouting_PDF_Tag_Suggestions::decide($post_id, $id, 'rejected');
                $result = 'rejected';
            } else {
                $result = self::add_tag($post_id, $suggestion) ? 'added' : 'failed';
                if ($result === 'added') {
                    Scouting_PDF_Tag_Suggestions::decide($post_id, $id, 'added');
                }
            }
        }
        wp_safe_redirect(self::page_url(array('paged' => $paged, 'result' => $result)));
        exit;
    }

    /**
     * Put the suggested tag on the post, keeping the tags it has
     */
    private static function add_tag($post_id, $suggestion) {
        $taxonomy = get_taxonomy($suggestion['field']);
        if (!$taxonomy || !current_user_can($taxonomy->cap->assign_terms)) {
            return false;
        }
        // A whole number is a term's ID; a year is a term's name (made if the site has none yet)
        $term = in_array($suggestion['field'], array('start_date', 'end_date'), true) ? (string) $suggestion['value'] : (int) $suggestion['value'];
        if (is_int($term) && !term_exists($term, $suggestion['field'])) {
            return false;
        }
        $set = wp_set_object_terms($post_id, $term, $suggestion['field'], true);
        return is_array($set);
    }

    // ---- The screen ---------------------------------------------------------------------

    public function render_page() {
        if (!current_user_can(self::capability())) {
            return;
        }
        $paged = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;
        $result = isset($_GET['result']) ? sanitize_key(wp_unslash($_GET['result'])) : '';
        $messages = array(
            'added'    => array('success', 'The tag was added to the post.'),
            'rejected' => array('success', 'The suggestion was put away. It will not be suggested again.'),
            'failed'   => array('error', 'The tag could not be added. Your account may not be allowed to set this kind of tag.'),
            'gone'     => array('warning', 'Someone has already decided that suggestion.'),
        );
        ?>
        <div class="wrap">
            <h1>Tag suggestions</h1>
            <?php if (isset($messages[$result])) : ?>
                <div class="notice notice-<?php echo esc_attr($messages[$result][0]); ?> is-dismissible"><p><?php echo esc_html($messages[$result][1]); ?></p></div>
            <?php endif; ?>
            <p>Names of councils, camps, lodges and states, and years, found in the text of each post's documents.
                Nothing is added to a post until you press <strong>Add tag</strong>. <strong>Not right</strong> puts a suggestion away for good.</p>
            <?php if (current_user_can('manage_options')) : ?>
                <?php Scouting_PDF_Batch_Button::render(self::FIND_ACTION, 'Find tag suggestions for all documents'); ?>
            <?php endif; ?>
            <?php
            /** Other plugins bring in suggestions of their own here (the AI tagger), before the list is read */
            do_action('scouting_pdf_tag_suggestions_screen_top');
            $query = new WP_Query(array(
                'post_type'           => array_values(array_diff(get_post_types(array('public' => true)), array('attachment'))),
                'post_status'         => array('publish', 'pending'),
                'posts_per_page'      => self::POSTS_PER_SCREEN,
                'paged'               => $paged,
                'orderby'             => 'ID',
                'order'               => 'ASC',
                'ignore_sticky_posts' => true,
                'meta_query'          => array(array('key' => Scouting_PDF_Tag_Suggestions::WAITING_KEY, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC')),
            ));
            ?>
            <table class="widefat striped">
                <thead><tr><th>Post</th><th>Field</th><th>Suggestion</th><th>Page</th><th>Found in this line</th><th></th></tr></thead>
                <tbody>
                <?php if (!$query->posts) : ?>
                    <tr><td colspan="6">No suggestions are waiting.</td></tr>
                <?php endif; ?>
                <?php foreach ($query->posts as $post) : ?>
                    <?php foreach (Scouting_PDF_Tag_Suggestions::waiting($post->ID) as $suggestion) : ?>
                        <?php $this->render_row($post, $suggestion, $paged); ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $links = paginate_links(array(
                'base'    => add_query_arg('paged', '%#%', self::page_url()),
                'format'  => '',
                'current' => $paged,
                'total'   => max(1, (int) $query->max_num_pages),
            ));
            if ($links) {
                echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post($links) . '</div></div>';
            }
            ?>
        </div>
        <?php
    }

    private function render_row($post, $suggestion, $paged) {
        $page_url = get_permalink($post) . Scouting_PDF_Transcript::page_hash($suggestion['viewer'], $suggestion['page'])
            . '&find=' . rawurlencode($suggestion['phrase']);
        $field = Scouting_PDF_Tag_Suggestions::FIELDS[$suggestion['field']];
        ?>
        <tr>
            <td>
                <a href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo esc_html(get_the_title($post)); ?></a>
                <?php if ($post->post_status !== 'publish') : ?>
                    <span class="post-state">(<?php echo esc_html($post->post_status); ?>)</span>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html($field); ?></td>
            <td><strong><?php echo esc_html($suggestion['label']); ?></strong><?php echo $suggestion['source'] === 'ai' ? ' <span class="description">(AI)</span>' : ''; ?></td>
            <td><a href="<?php echo esc_url($page_url); ?>"><?php echo esc_html(sprintf('Page %d', $suggestion['page'])); ?></a></td>
            <td><?php echo esc_html($suggestion['evidence']); ?></td>
            <td style="white-space:nowrap">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::DECIDE_ACTION); ?>">
                    <input type="hidden" name="post_id" value="<?php echo esc_attr($post->ID); ?>">
                    <input type="hidden" name="suggestion" value="<?php echo esc_attr($suggestion['id']); ?>">
                    <input type="hidden" name="paged" value="<?php echo esc_attr($paged); ?>">
                    <?php wp_nonce_field(self::DECIDE_ACTION . '_' . $post->ID . '_' . $suggestion['id']); ?>
                    <button type="submit" name="decision" value="add" class="button button-primary"
                        aria-label="<?php echo esc_attr(sprintf('Add tag: %s %s to %s', $field, $suggestion['label'], get_the_title($post))); ?>">Add tag</button>
                    <button type="submit" name="decision" value="reject" class="button"
                        aria-label="<?php echo esc_attr(sprintf('Not right: %s %s for %s', $field, $suggestion['label'], get_the_title($post))); ?>">Not right</button>
                </form>
            </td>
        </tr>
        <?php
    }
}
