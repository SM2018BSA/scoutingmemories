<?php
/**
 * A button on the PDF Text screens that goes through the whole site a batch of posts at a
 * time, while the screen is open: the button, its progress bar, and the request behind it.
 * No cron job and no background worker; closing the screen stops it, and pressing again
 * starts over (every batch is safe to repeat).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_Batch_Button {

    /**
     * Print the button with its status line and bar. $action is the admin-ajax action.
     */
    public static function render($action, $label) {
        wp_enqueue_script('scouting-pdf-batch',
            plugins_url('assets/js/scouting-pdf-batch.js', SCOUTING_PDF_PLUGIN_DIR . 'scouting-pdf-embedder.php'),
            array(), SCOUTING_PDF_VERSION, true);
        ?>
        <p>
            <button type="button" class="button" data-sm-batch
                data-action="<?php echo esc_attr($action); ?>"
                data-nonce="<?php echo esc_attr(wp_create_nonce($action)); ?>"
                data-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                data-status="<?php echo esc_attr($action . '-status'); ?>"
                data-bar="<?php echo esc_attr($action . '-bar'); ?>"><?php echo esc_html($label); ?></button>
            <span id="<?php echo esc_attr($action . '-status'); ?>" role="status"></span>
        </p>
        <div class="sm-ocr-bar" id="<?php echo esc_attr($action . '-bar'); ?>" role="progressbar" aria-label="Posts checked"
            aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="display:none;max-width:480px"><span></span></div>
        <?php
    }

    /**
     * The request behind a button: check the nonce and the capability, run $each_post on
     * the next batch of posts matching $query_args, and answer with the progress.
     * $each_post returns a number that is added up over the whole run (posts with text,
     * new suggestions …); $done_message turns the final sum and the total into the last line.
     */
    public static function handle($action, $capability, $query_args, $batch, $each_post, $done_message) {
        check_ajax_referer($action);
        if (!current_user_can($capability)) {
            wp_send_json_error(array('message' => 'You are not allowed to do this.'), 403);
        }
        $page = isset($_POST['page']) ? max(1, absint(wp_unslash($_POST['page']))) : 1;
        $count = isset($_POST['count']) ? absint(wp_unslash($_POST['count'])) : 0;
        $query = new WP_Query(array_merge($query_args, array(
            'fields'                 => 'ids',
            'posts_per_page'         => $batch,
            'paged'                  => $page,
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'ignore_sticky_posts'    => true,
            'update_post_term_cache' => false,
        )));
        foreach ($query->posts as $post_id) {
            $count += (int) call_user_func($each_post, (int) $post_id);
        }
        $total = (int) $query->found_posts;
        $finished = $page >= (int) $query->max_num_pages;
        wp_send_json_success(array(
            'checked'  => min($total, $page * $batch),
            'total'    => $total,
            'count'    => $count,
            'finished' => $finished,
            'message'  => $finished ? call_user_func($done_message, $count, $total) : '',
        ));
    }
}
