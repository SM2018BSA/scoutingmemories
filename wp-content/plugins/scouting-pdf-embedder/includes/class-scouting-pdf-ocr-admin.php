<?php
/**
 * The PDF Text admin page, the "PDF text" box on the post edit screen, and the REST
 * endpoints behind both.
 *
 * Reviewers (editors and administrators: whoever can edit other people's posts) run the
 * review where they already review pending submissions — the post's edit screen. The queue
 * itself (pause, resume, scan, retry) is administrators only; the endpoints enforce it even
 * where a button slipped through.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_OCR_Admin {

    private static $instance = null;

    const MAX_TEXT = 200000;      // the service's own cap on a page's text
    const DOCS_PER_PAGE = 50;
    const TEXT_ROWS_PER_PAGE = 20;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Who may check and approve text: whoever reviews pending posts. The filter lets the
     * owner hand it to another capability later without a code change.
     */
    public static function can_review() {
        return current_user_can(apply_filters('scouting_pdf_ocr_review_cap', 'edit_others_posts'));
    }

    // ---- Permissions ---------------------------------------------------------------

    public function permission_review() {
        if (!Scouting_PDF_OCR::enabled()) {
            return new WP_Error('ocr_off', 'The reading service is switched off.', array('status' => 403));
        }
        if (!self::can_review()) {
            return new WP_Error('rest_forbidden', 'Sorry, you are not allowed to do that.', array('status' => rest_authorization_required_code()));
        }
        return true;
    }

    public function permission_admin() {
        if (!Scouting_PDF_OCR::enabled()) {
            return new WP_Error('ocr_off', 'The reading service is switched off.', array('status' => 403));
        }
        if (!current_user_can('manage_options')) {
            return new WP_Error('rest_forbidden', 'Sorry, you are not allowed to do that.', array('status' => rest_authorization_required_code()));
        }
        return true;
    }

    /**
     * The queue-touching endpoints refuse while Background reading is off; checking and
     * approving text keeps working then, because reader-read pages deserve review too.
     */
    private static function queue_off() {
        if (Scouting_PDF_OCR::queue_enabled()) {
            return null;
        }
        return new WP_Error('queue_off', 'Background reading is switched off.', array('status' => 403));
    }

    // ---- Routes ---------------------------------------------------------------------

    public function register_rest_routes() {
        $ns = 'scouting-pdf/v1';
        register_rest_route($ns, '/ocr/admin/status', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_status'),
            'permission_callback' => array($this, 'permission_review'),
        ));
        register_rest_route($ns, '/ocr/admin/queue', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_queue'),
            'permission_callback' => array($this, 'permission_admin'),
            'args' => array(
                'action' => array('type' => 'string', 'required' => true, 'enum' => array('pause', 'resume', 'tick')),
            ),
        ));
        register_rest_route($ns, '/ocr/admin/docs', array(
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'rest_docs'),
                'permission_callback' => array($this, 'permission_review'),
                'args' => array(
                    'status' => array('type' => 'string', 'default' => 'all', 'enum' => array('all', 'queued', 'working', 'done', 'failed')),
                    'page'   => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
                ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'rest_add_doc'),
                'permission_callback' => array($this, 'permission_admin'),
            ),
        ));
        register_rest_route($ns, '/ocr/admin/docs/(?P<id>[0-9]+)/retry', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_retry_doc'),
            'permission_callback' => array($this, 'permission_admin'),
        ));
        register_rest_route($ns, '/ocr/admin/docs/(?P<id>[0-9]+)/remove', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_remove_doc'),
            'permission_callback' => array($this, 'permission_admin'),
        ));
        register_rest_route($ns, '/ocr/admin/scan', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_scan'),
            'permission_callback' => array($this, 'permission_admin'),
            'args' => array(
                'after_id' => array('type' => 'integer', 'default' => 0, 'minimum' => 0),
            ),
        ));
        register_rest_route($ns, '/ocr/admin/doc-text', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_doc_text'),
            'permission_callback' => array($this, 'permission_review'),
            'args' => array(
                'doc_key' => array('type' => 'string', 'required' => true, 'pattern' => '^[a-f0-9]{32}$'),
                'page'    => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
            ),
        ));
        register_rest_route($ns, '/ocr/admin/approve/(?P<id>[0-9]+)', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_approve'),
            'permission_callback' => array($this, 'permission_review'),
        ));
        register_rest_route($ns, '/ocr/admin/approve-doc', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_approve_doc'),
            'permission_callback' => array($this, 'permission_review'),
        ));
        register_rest_route($ns, '/ocr/admin/enqueue-post', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_enqueue_post'),
            'permission_callback' => array($this, 'permission_review'),
        ));
    }

    // ---- The queue ---------------------------------------------------------------------

    /**
     * GET /ocr/admin/status: the dashboard's one poll. Its side effect keeps the queue
     * moving when cron is late: browsing the dashboard is enough to drive it.
     */
    public function rest_status() {
        Scouting_PDF_OCR_Queue::get_instance()->tick_if_overdue();
        global $wpdb;
        $docs = Scouting_PDF_OCR::docs_table();
        $pages = Scouting_PDF_OCR::table();
        $counts = array('queued' => 0, 'working' => 0, 'done' => 0, 'failed' => 0);
        foreach ($wpdb->get_results("SELECT status, COUNT(*) AS n FROM $docs GROUP BY status") as $r) {
            if (isset($counts[$r->status])) {
                $counts[$r->status] = (int) $r->n;
            }
        }
        if (!Scouting_PDF_OCR::queue_enabled()) {
            $queue = 'off';
        } elseif (Scouting_PDF_OCR_Queue::state() !== 'running') {
            $queue = 'paused';
        } elseif (Scouting_PDF_OCR_Queue::backoff_until()) {
            $queue = 'backoff';
        } else {
            $queue = 'running';
        }
        $backoff = Scouting_PDF_OCR_Queue::backoff_until();
        $current = $wpdb->get_row("SELECT doc_key, label, pages_done, total_pages FROM $docs WHERE status = 'working' LIMIT 1");
        $today = current_time('Y-m-d') . ' 00:00:00';
        $response = rest_ensure_response(array(
            'queue'         => $queue,
            'backoff_until' => $backoff ? wp_date('Y-m-d H:i:s', $backoff) : null,
            'last_tick'     => Scouting_PDF_OCR_Queue::last_tick() !== '' ? Scouting_PDF_OCR_Queue::last_tick() : null,
            // Seconds, counted here: the browser's clock may sit in another timezone
            'last_tick_age' => Scouting_PDF_OCR_Queue::last_tick() !== ''
                ? max(0, strtotime(current_time('mysql')) - strtotime(Scouting_PDF_OCR_Queue::last_tick())) : null,
            'docs'          => $counts,
            'current'       => $current ? array(
                'doc_key'     => $current->doc_key,
                'label'       => $current->label,
                'pages_done'  => (int) $current->pages_done,
                'total_pages' => (int) $current->total_pages,
            ) : null,
            'pages_unchecked' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM $pages WHERE status = 'done' AND review_status = 'pending'"
            ),
            'today' => array(
                'reader_pages' => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $pages WHERE requested_at >= %s AND source = 'reader'", $today
                )),
                'queue_pages' => (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $pages WHERE requested_at >= %s AND source IN ('bulk', 'auto')", $today
                )),
            ),
        ));
        $response->header('Cache-Control', 'no-store');
        return $response;
    }

    /**
     * POST /ocr/admin/queue with {"action": "pause" | "resume" | "tick"}
     */
    public function rest_queue($request) {
        $off = self::queue_off();
        if ($off) {
            return $off;
        }
        $action = (string) $request['action'];
        if ($action === 'tick') {
            $ticked = Scouting_PDF_OCR_Queue::get_instance()->tick();
            return rest_ensure_response(array('queue' => Scouting_PDF_OCR_Queue::state(), 'ticked' => $ticked));
        }
        Scouting_PDF_OCR_Queue::set_state($action === 'resume' ? 'running' : 'paused');
        return rest_ensure_response(array('queue' => Scouting_PDF_OCR_Queue::state()));
    }

    /**
     * GET /ocr/admin/docs: the Queue tab's table, 50 documents a page
     */
    public function rest_docs($request) {
        global $wpdb;
        $docs = Scouting_PDF_OCR::docs_table();
        $pages = Scouting_PDF_OCR::table();
        $status = (string) $request['status'];
        $page = max(1, (int) $request['page']);
        $where = $status === 'all' ? '1 = 1' : $wpdb->prepare('status = %s', $status);
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $docs WHERE $where");
        // Working first, then waiting (front of the queue first), then finished, newest first
        $rows = $wpdb->get_results(
            "SELECT * FROM $docs WHERE $where
             ORDER BY CASE status WHEN 'working' THEN 0 WHEN 'queued' THEN 1 ELSE 2 END,
                      CASE WHEN status = 'queued' THEN priority ELSE 0 END DESC,
                      CASE WHEN status IN ('working', 'queued') THEN id ELSE 0 END,
                      finished_at DESC, id
             LIMIT " . (int) self::DOCS_PER_PAGE . ' OFFSET ' . (int) (($page - 1) * self::DOCS_PER_PAGE)
        );
        $checked = array();
        if ($rows) {
            $keys = wp_list_pluck($rows, 'doc_key');
            $marks = implode(',', array_fill(0, count($keys), '%s'));
            foreach ($wpdb->get_results($wpdb->prepare(
                "SELECT doc_key, COUNT(*) AS n FROM $pages WHERE review_status = 'approved' AND doc_key IN ($marks) GROUP BY doc_key",
                $keys
            )) as $r) {
                $checked[$r->doc_key] = (int) $r->n;
            }
        }
        $out = array();
        foreach ($rows as $r) {
            $user = $r->enqueued_by ? get_userdata((int) $r->enqueued_by) : null;
            $out[] = array(
                'id'               => (int) $r->id,
                'doc_key'          => $r->doc_key,
                'pdf_url'          => $r->pdf_url,
                'post_id'          => (int) $r->post_id,
                'permalink'        => $r->post_id ? (get_permalink((int) $r->post_id) ?: null) : null,
                'edit_link'        => $r->post_id ? get_edit_post_link((int) $r->post_id, 'raw') : null,
                'label'            => $r->label,
                'total_pages'      => (int) $r->total_pages,
                'pages_done'       => (int) $r->pages_done,
                'pages_failed'     => (int) $r->pages_failed,
                'pages_checked'    => isset($checked[$r->doc_key]) ? $checked[$r->doc_key] : 0,
                'status'           => $r->status,
                'source'           => $r->source,
                'priority'         => (int) $r->priority,
                'enqueued_by_name' => $user ? $user->display_name : '',
                'enqueued_at'      => $r->enqueued_at,
                'finished_at'      => $r->finished_at,
            );
        }
        $response = rest_ensure_response(array('total' => $total, 'per_page' => self::DOCS_PER_PAGE, 'docs' => $out));
        $response->header('Cache-Control', 'no-store');
        return $response;
    }

    /**
     * POST /ocr/admin/docs with {"url": "..."}: add one document by its link, front of the queue
     */
    public function rest_add_doc($request) {
        $off = self::queue_off();
        if ($off) {
            return $off;
        }
        global $wpdb;
        $params = $request->get_json_params();
        $canonical = Scouting_PDF_OCR::canonical_url(isset($params['url']) ? (string) $params['url'] : '');
        if (is_wp_error($canonical)) {
            return $canonical;
        }
        $key = Scouting_PDF_OCR::doc_key($canonical);
        $added = Scouting_PDF_OCR_Queue::enqueue($canonical, 'bulk', 0, '', 1);
        if ($added === 'added') {
            return new WP_REST_Response(array('status' => 'added', 'doc_key' => $key), 201);
        }
        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT status FROM ' . Scouting_PDF_OCR::docs_table() . ' WHERE doc_key = %s', $key
        ));
        return rest_ensure_response(array(
            'status'  => ($existing && $existing->status === 'done') ? 'already_read' : 'already_queued',
            'doc_key' => $key,
        ));
    }

    /**
     * POST /ocr/admin/docs/<id>/retry: a failed document, or a done one with failed pages.
     * The failed page rows are deleted (they hold no text), so the dispatcher sends those
     * pages again as if new.
     */
    public function rest_retry_doc($request) {
        $off = self::queue_off();
        if ($off) {
            return $off;
        }
        global $wpdb;
        $docs = Scouting_PDF_OCR::docs_table();
        $pages = Scouting_PDF_OCR::table();
        $doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $docs WHERE id = %d", (int) $request['id']));
        if (!$doc) {
            return new WP_Error('no_such_doc', 'There is no such document.', array('status' => 404));
        }
        if (!($doc->status === 'failed' || ($doc->status === 'done' && (int) $doc->pages_failed > 0))) {
            return new WP_Error('not_retryable', 'This document has nothing to try again.', array('status' => 409));
        }
        $first_failed = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $pages WHERE doc_key = %s AND page = 1", $doc->doc_key
        )) === 'failed';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $pages WHERE doc_key = %s AND status = 'failed'", $doc->doc_key
        ));
        $done = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $pages WHERE doc_key = %s AND status IN ('done', 'empty')", $doc->doc_key
        ));
        $wpdb->update($docs, array(
            'status'       => 'queued',
            'pages_done'   => $done,
            'pages_failed' => 0,
            // Page 1 was the failure: its answer never named the page count, so learn it afresh
            'total_pages'  => $first_failed ? 0 : (int) $doc->total_pages,
            'started_at'   => null,
            'finished_at'  => null,
        ), array('id' => $doc->id));
        return rest_ensure_response(array('status' => 'queued'));
    }

    /**
     * POST /ocr/admin/docs/<id>/remove: only a document whose reading never started.
     * Page rows and text are never deleted from the dashboard.
     */
    public function rest_remove_doc($request) {
        $off = self::queue_off();
        if ($off) {
            return $off;
        }
        global $wpdb;
        $docs = Scouting_PDF_OCR::docs_table();
        $doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $docs WHERE id = %d", (int) $request['id']));
        if (!$doc) {
            return new WP_Error('no_such_doc', 'There is no such document.', array('status' => 404));
        }
        if ($doc->status !== 'queued' || $doc->started_at !== null) {
            return new WP_Error('already_started', 'This document has already been read, or is being read.', array('status' => 409));
        }
        $wpdb->delete($docs, array('id' => $doc->id), array('%d'));
        return rest_ensure_response(array('removed' => true));
    }

    /**
     * POST /ocr/admin/scan with {"after_id": n}: one bounded batch of the whole-site scan
     */
    public function rest_scan($request) {
        $off = self::queue_off();
        if ($off) {
            return $off;
        }
        return rest_ensure_response(Scouting_PDF_OCR_Queue::scan_batch((int) $request['after_id']));
    }

    // ---- Checking text --------------------------------------------------------------------

    /**
     * GET /ocr/admin/doc-text?doc_key=...&page=1: one document's pages with their text,
     * 20 rows a call. Feeds the PDF text box on the post edit screen.
     */
    public function rest_doc_text($request) {
        global $wpdb;
        $pages = Scouting_PDF_OCR::table();
        $docs = Scouting_PDF_OCR::docs_table();
        $key = (string) $request['doc_key'];
        $page = max(1, (int) $request['page']);
        $doc = $wpdb->get_row($wpdb->prepare(
            "SELECT doc_key, label, status, total_pages, pages_done, pages_failed FROM $docs WHERE doc_key = %s", $key
        ));
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $pages WHERE doc_key = %s", $key));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, page, status, review_status, text, engine, finished_at, reviewed_by, reviewed_at,
                    (text_original IS NOT NULL) AS edited
             FROM $pages WHERE doc_key = %s ORDER BY page LIMIT %d OFFSET %d",
            $key, self::TEXT_ROWS_PER_PAGE, ($page - 1) * self::TEXT_ROWS_PER_PAGE
        ));
        $out = array();
        foreach ($rows as $r) {
            $reviewer = $r->reviewed_by ? get_userdata((int) $r->reviewed_by) : null;
            $out[] = array(
                'id'               => (int) $r->id,
                'page'             => (int) $r->page,
                'status'           => $r->status,
                'review_status'    => $r->review_status,
                'text'             => $r->status === 'done' ? (string) $r->text : '',
                'engine'           => $r->engine,
                'finished_at'      => $r->finished_at,
                'reviewed_by_name' => $reviewer ? $reviewer->display_name : '',
                'reviewed_at'      => $r->reviewed_at,
                'edited'           => (bool) $r->edited,
            );
        }
        $response = rest_ensure_response(array(
            'doc' => $doc ? array(
                'doc_key'      => $doc->doc_key,
                'label'        => $doc->label,
                'status'       => $doc->status,
                'total_pages'  => (int) $doc->total_pages,
                'pages_done'   => (int) $doc->pages_done,
                'pages_failed' => (int) $doc->pages_failed,
            ) : null,
            'total_rows' => $total,
            'per_page'   => self::TEXT_ROWS_PER_PAGE,
            'pages'      => $out,
        ));
        $response->header('Cache-Control', 'no-store');
        return $response;
    }

    /**
     * POST /ocr/admin/approve/<id>, optionally with {"text": "..."}: approve one page, with
     * the reviewer's correction. The raw machine text is kept exactly once (text_original),
     * and corrections stay possible after approval.
     */
    public function rest_approve($request) {
        global $wpdb;
        $pages = Scouting_PDF_OCR::table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, status, text FROM $pages WHERE id = %d", (int) $request['id']));
        if (!$row || $row->status !== 'done') {
            return new WP_Error('no_such_page', 'There is no read page to approve here.', array('status' => 404));
        }
        $params = $request->get_json_params();
        if (is_array($params) && array_key_exists('text', $params)) {
            $text = (string) $params['text'];
            if (strlen($text) > self::MAX_TEXT) {
                return new WP_Error('text_too_long', 'The text is longer than a page can hold.', array('status' => 400));
            }
            if ($text !== (string) $row->text) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $pages SET text_original = COALESCE(text_original, text), text = %s WHERE id = %d",
                    $text, $row->id
                ));
            }
        }
        $wpdb->update($pages, array(
            'review_status' => 'approved',
            'reviewed_by'   => get_current_user_id(),
            'reviewed_at'   => current_time('mysql'),
        ), array('id' => $row->id));
        return rest_ensure_response(array('id' => (int) $row->id, 'review_status' => 'approved'));
    }

    /**
     * POST /ocr/admin/approve-doc with {"doc_key": "..."}: approve every page of one
     * document that is still waiting, unedited
     */
    public function rest_approve_doc($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $key = isset($params['doc_key']) ? (string) $params['doc_key'] : '';
        if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
            return new WP_Error('bad_doc_key', 'Invalid document code.', array('status' => 400));
        }
        $approved = (int) $wpdb->query($wpdb->prepare(
            'UPDATE ' . Scouting_PDF_OCR::table() .
            " SET review_status = 'approved', reviewed_by = %d, reviewed_at = %s
             WHERE doc_key = %s AND status = 'done' AND review_status = 'pending'",
            get_current_user_id(), current_time('mysql'), $key
        ));
        return rest_ensure_response(array('approved' => $approved));
    }

    // ---- The PDF Text page --------------------------------------------------------------

    /**
     * Top level on purpose: reviewers are editors, and editors never see the Settings menu,
     * so a Settings submenu would hide this from the people who use it daily. The page only
     * appears while the feature is on.
     */
    public function add_admin_menu() {
        if (!Scouting_PDF_OCR::enabled()) {
            return;
        }
        add_menu_page('PDF Text', 'PDF Text', apply_filters('scouting_pdf_ocr_review_cap', 'edit_others_posts'),
            'scouting-pdf-text', array($this, 'render_page'), 'dashicons-media-text', 26);
    }

    public function enqueue_assets($hook) {
        if ($hook === 'toplevel_page_scouting-pdf-text') {
            self::enqueue_admin_script();
        }
    }

    public static function enqueue_admin_script() {
        wp_enqueue_script('scouting-pdf-admin',
            SCOUTING_PDF_PLUGIN_URL . 'assets/js/scouting-pdf-admin.js', array(), SCOUTING_PDF_VERSION, true);
        wp_add_inline_script('scouting-pdf-admin', 'window.ScoutingPdfAdminConfig = ' . wp_json_encode(array(
            'restRoot' => esc_url_raw(rest_url('scouting-pdf/v1/ocr/admin/')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'isAdmin'  => current_user_can('manage_options'),
            'queueOn'  => Scouting_PDF_OCR::queue_enabled(),
        )) . ';', 'before');
    }

    public function render_page() {
        if (!self::can_review()) {
            return;
        }
        $tab = isset($_GET['tab']) && $_GET['tab'] === 'queue' ? 'queue' : 'overview';
        $base = admin_url('admin.php?page=scouting-pdf-text');
        $is_admin = current_user_can('manage_options');
        $test_url = wp_nonce_url(admin_url('admin-post.php?action=scouting_pdf_ocr_test'), 'scouting_pdf_ocr_test');
        ?>
        <div class="wrap" id="sm-ocr-dashboard" data-tab="<?php echo esc_attr($tab); ?>">
            <h1>PDF Text</h1>
            <nav class="nav-tab-wrapper">
                <a class="nav-tab <?php echo $tab === 'overview' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($base); ?>">Overview</a>
                <a class="nav-tab <?php echo $tab === 'queue' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($base . '&tab=queue'); ?>">Queue</a>
            </nav>
            <?php if ($tab === 'overview') : ?>
            <div id="sm-ocr-overview">
                <p id="sm-ocr-state">Loading…</p>
                <p id="sm-ocr-docs-line"></p>
                <p id="sm-ocr-today-line"></p>
                <p id="sm-ocr-unchecked-line"></p>
                <p id="sm-ocr-current" hidden></p>
                <p id="sm-ocr-lastrun"></p>
                <p>
                    <?php if ($is_admin) : ?>
                        <button type="button" class="button button-primary" id="sm-ocr-resume" hidden>Resume the queue</button>
                        <button type="button" class="button" id="sm-ocr-pause" hidden>Pause the queue</button>
                        <button type="button" class="button" id="sm-ocr-tick">Run once now</button>
                        <?php if (Scouting_PDF_OCR::enabled()) : ?>
                            <a href="<?php echo esc_url($test_url); ?>" class="button">Test connection</a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=scouting-pdf-ocr')); ?>">Settings</a>
                    <?php endif; ?>
                </p>
                <p id="sm-ocr-action-result"></p>
            </div>
            <?php else : ?>
            <div id="sm-ocr-queue">
                <?php if ($is_admin) : ?>
                <p>
                    <label for="sm-ocr-add-url"><strong>Add one document by its link</strong></label><br>
                    <input type="url" id="sm-ocr-add-url" class="regular-text" placeholder="https://storage.scoutingmemories.org/…/file.pdf">
                    <button type="button" class="button" id="sm-ocr-add">Add document</button>
                    <span class="description">It goes to the front of the queue.</span>
                </p>
                <p id="sm-ocr-add-result"></p>
                <p>
                    <button type="button" class="button" id="sm-ocr-scan">Find every document on the site</button>
                    <span id="sm-ocr-scan-progress"></span>
                </p>
                <?php endif; ?>
                <table class="widefat striped">
                    <thead><tr><th>Document</th><th>Pages</th><th>Checked</th><th>Status</th><th>Added</th><th></th></tr></thead>
                    <tbody id="sm-ocr-docs"><tr><td colspan="6">Loading…</td></tr></tbody>
                </table>
                <div class="tablenav"><div class="tablenav-pages" id="sm-ocr-pager"></div></div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * POST /ocr/admin/enqueue-post with {"post_id": n}: the PDF text box's "Read this
     * document" — queue the documents of one post, front of the queue
     */
    public function rest_enqueue_post($request) {
        $off = self::queue_off();
        if ($off) {
            return $off;
        }
        $params = $request->get_json_params();
        $post_id = isset($params['post_id']) ? (int) $params['post_id'] : 0;
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('no_such_post', 'There is no such post.', array('status' => 404));
        }
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('rest_forbidden', 'Sorry, you are not allowed to do that.', array('status' => 403));
        }
        list($added, $existing) = Scouting_PDF_OCR_Queue::enqueue_post_documents($post_id, $post->post_content, 'auto', 1);
        return rest_ensure_response(array('added' => $added, 'existing' => $existing));
    }
}
