<?php
/**
 * Page OCR for the Scouting PDF viewer.
 *
 * A logged-in reader presses "Text" in the viewer and asks for ONE page to be read. WordPress
 * sends the request to the OCR service on the development server (signed with a shared
 * secret), saves the text it gets back, and from then on shows that text to everyone.
 *
 * Switched off unless configured: the service address and secret come from wp-config.php
 * (SCOUTING_PDF_OCR_URL, SCOUTING_PDF_OCR_SECRET) or, when those constants are absent, from
 * the Settings -> PDF OCR page. With neither, no button is shown and the routes answer "off",
 * so the plugin is safe to publish before OCR is switched on.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_OCR {

    private static $instance = null;

    // Bump this when the table layout changes; the table is (re)built by dbDelta on load,
    // because the plugin is loaded from a must-use loader and has no activation hook
    const TABLE_VERSION = 2;

    // The one host the OCR service downloads from. Narrower on purpose than the viewer's
    // proxy_hosts(): the service refuses everything else, so the button is only offered
    // for documents it can actually read.
    const OCR_HOST = 'storage.scoutingmemories.org';

    const OPTION = 'scouting_pdf_ocr';          // settings array
    const DB_OPTION = 'scouting_pdf_ocr_db_version';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'maybe_create_table'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_scouting_pdf_ocr_test', array($this, 'handle_test_connection'));
        add_action('admin_notices', array($this, 'print_test_result'));
    }

    // ---- Configuration --------------------------------------------------------

    private static function settings() {
        $defaults = array(
            'enabled'    => 0,
            'url'        => '',
            'secret'     => '',
            'site_limit' => 100,
            'user_limit' => 20,
        );
        $saved = get_option(self::OPTION);
        return is_array($saved) ? array_merge($defaults, $saved) : $defaults;
    }

    /**
     * The service address without a trailing slash, e.g. https://ocr.example/
     * Constants in wp-config.php win over the settings page.
     */
    public static function service_url() {
        if (defined('SCOUTING_PDF_OCR_URL') && SCOUTING_PDF_OCR_URL) {
            return untrailingslashit((string) SCOUTING_PDF_OCR_URL);
        }
        $s = self::settings();
        if (!$s['enabled']) {
            return '';
        }
        return untrailingslashit((string) $s['url']);
    }

    private static function secret() {
        if (defined('SCOUTING_PDF_OCR_SECRET') && SCOUTING_PDF_OCR_SECRET) {
            return (string) SCOUTING_PDF_OCR_SECRET;
        }
        $s = self::settings();
        return $s['enabled'] ? (string) $s['secret'] : '';
    }

    public static function enabled() {
        $url = self::service_url();
        if ($url === '' || self::secret() === '') {
            return false;
        }
        // https only: the signature protects the requests, but nothing signs the ANSWERS,
        // so the text must not be tamperable in transit. Plain http is allowed only for
        // local development and test addresses.
        if (preg_match('#^https://#i', $url)) {
            return true;
        }
        if (!preg_match('#^http://#i', $url)) {
            return false;
        }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        return $host === 'localhost' || $host === '127.0.0.1' || $host === '[::1]'
            || preg_match('/\.(ddev\.site|test|localhost)$/', $host);
    }

    private static function limits() {
        $s = self::settings();
        return array(
            'site' => max(1, (int) $s['site_limit']),
            'user' => max(1, (int) $s['user_limit']),
        );
    }

    // ---- One link, one spelling ------------------------------------------------

    /**
     * The PDF link in the exact spelling the OCR service stores: https, the storage host,
     * no query string, the path percent-encoded one fixed way. WordPress and the service
     * must agree on what "the same document" is, so this mirrors the service's clean_url().
     * Returns the canonical link or a WP_Error.
     */
    public static function canonical_url($url) {
        $url = Scouting_PDF_Embedder::validate_proxy_url($url);
        if (is_wp_error($url)) {
            return $url;
        }
        if (strlen($url) > 1024 || strpos($url, '\\') !== false) {
            return new WP_Error('bad_url', 'Invalid URL', array('status' => 400));
        }
        $parts = wp_parse_url($url);
        if (strtolower($parts['host']) !== self::OCR_HOST) {
            // validate_proxy_url() also allows the site's own hosts; the service reads
            // only from storage, so for OCR this is final, not worth retrying
            return new WP_Error('ocr_host', 'This document is not on the archive storage', array('status' => 403));
        }
        // Decode once, refuse what the service refuses, then re-encode one fixed way
        $path = rawurldecode($parts['path']);
        if (!preg_match('//u', $path) || preg_match('/[\x00-\x1f\x7f\\\\]/', $path)) {
            return new WP_Error('bad_url', 'Invalid URL', array('status' => 400));
        }
        if (!preg_match('/\.pdf$/i', $path)) {
            return new WP_Error('not_pdf', 'Only PDF files are supported', array('status' => 400));
        }
        $segments = explode('/', $path);
        array_shift($segments); // the path starts with /, so the first piece is ''
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return new WP_Error('bad_url', 'Invalid URL', array('status' => 400));
            }
        }
        // rawurlencode() keeps A-Z a-z 0-9 _ . - ~ ; the service also keeps these:
        $keep = array(
            '%2F' => '/', '%21' => '!', '%24' => '$', '%26' => '&', '%27' => "'",
            '%28' => '(', '%29' => ')', '%2A' => '*', '%2B' => '+', '%2C' => ',',
            '%3B' => ';', '%3D' => '=', '%3A' => ':', '%40' => '@',
        );
        $encoded = strtr(rawurlencode($path), $keep);
        $canonical = 'https://' . self::OCR_HOST . $encoded;
        // Encoding can make a link longer; the service checks ITS 1024 limit on this form,
        // so refuse here exactly what the service would refuse
        if (strlen($canonical) > 1024) {
            return new WP_Error('bad_url', 'Invalid URL', array('status' => 400));
        }
        return $canonical;
    }

    /**
     * Short code for one document: the first 32 characters of the SHA-256 of its canonical link
     */
    public static function doc_key($canonical_url) {
        return substr(hash('sha256', $canonical_url), 0, 32);
    }

    /**
     * The attribute build_viewer_html() adds to the container when this document can be read
     */
    public static function viewer_attr($url) {
        if (!self::enabled()) {
            return '';
        }
        $canonical = self::canonical_url($url);
        if (is_wp_error($canonical)) {
            return '';
        }
        return sprintf(' data-pdf-ocr-key="%s"', esc_attr(self::doc_key($canonical)));
    }

    // ---- The table --------------------------------------------------------------

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'scouting_pdf_ocr';
    }

    public function maybe_create_table() {
        if ((int) get_option(self::DB_OPTION) === self::TABLE_VERSION) {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset_collate = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  doc_key char(32) NOT NULL,
  page smallint(5) unsigned NOT NULL,
  pdf_url varchar(1024) NOT NULL,
  status varchar(10) NOT NULL DEFAULT 'queued',
  asks smallint(5) unsigned NOT NULL DEFAULT 1,
  job_id char(32) NOT NULL DEFAULT '',
  text longtext NULL,
  words longtext NULL,
  engine varchar(60) NOT NULL DEFAULT '',
  requested_by bigint(20) unsigned NOT NULL DEFAULT 0,
  requested_at datetime NOT NULL,
  finished_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY doc_page (doc_key,page),
  KEY requested_at (requested_at)
) $charset_collate;");
        // Record success only when the table is really there, so a failed CREATE is retried
        // on the next load instead of being remembered as done
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            update_option(self::DB_OPTION, self::TABLE_VERSION);
        }
    }

    // ---- Talking to the service ---------------------------------------------------

    /**
     * A signed request to the OCR service: HMAC-SHA256 with the shared secret over
     * time, method, path and the body's SHA-256, as the service's README describes.
     * Returns array('status' => HTTP status, 'json' => decoded answer or null).
     */
    private static function call_service($method, $path, $body = '') {
        $stamp = (string) time();
        $to_sign = $stamp . "\n" . $method . "\n" . $path . "\n" . hash('sha256', $body);
        $signature = hash_hmac('sha256', $to_sign, self::secret());
        $response = wp_remote_request(self::service_url() . $path, array(
            'method'              => $method,
            'timeout'             => 5,
            'limit_response_size' => 4 * MB_IN_BYTES,
            'body'                => $body === '' ? null : $body,
            'headers' => array(
                'Content-Type'         => 'application/json',
                'X-Scouting-Time'      => $stamp,
                'X-Scouting-Signature' => $signature,
            ),
        ));
        if (is_wp_error($response)) {
            return array('status' => 0, 'json' => null);
        }
        $json = json_decode((string) wp_remote_retrieve_body($response), true);
        // Not JSON: the web server in front answered (the service is down or restarting)
        return array(
            'status' => (int) wp_remote_retrieve_response_code($response),
            'json'   => is_array($json) ? $json : null,
        );
    }

    /**
     * Copy a finished job from the service's answer into our row. A page the service read
     * but found no words on is stored as "empty", so the viewer can say so.
     */
    private static function save_job_answer($row_id, $job) {
        global $wpdb;
        if (!is_array($job) || empty($job['status'])) {
            return;
        }
        if ($job['status'] === 'done') {
            $text = isset($job['text']) ? (string) $job['text'] : '';
            $wpdb->update(self::table(), array(
                'status'      => trim($text) === '' ? 'empty' : 'done',
                'text'        => $text,
                'words'       => isset($job['words']) ? wp_json_encode($job['words']) : null,
                'engine'      => isset($job['engine']) ? substr((string) $job['engine'], 0, 60) : '',
                'finished_at' => current_time('mysql'),
            ), array('id' => $row_id), array('%s', '%s', '%s', '%s', '%s'), array('%d'));
        } elseif ($job['status'] === 'failed') {
            $wpdb->update(self::table(), array(
                'status'      => 'failed',
                'finished_at' => current_time('mysql'),
            ), array('id' => $row_id), array('%s', '%s'), array('%d'));
        }
        // queued and working: nothing to save yet
    }

    /**
     * A queued row this old with no answer is treated as failed, so the page can be asked for again
     */
    private static function is_stale($row) {
        return $row->status === 'queued'
            && (strtotime($row->requested_at) < strtotime(current_time('mysql')) - 10 * MINUTE_IN_SECONDS);
    }

    /**
     * Pages asked for today (site time): array(by this reader, by everyone)
     */
    private static function asks_today($user_id) {
        global $wpdb;
        $table = self::table();
        $today = current_time('Y-m-d') . ' 00:00:00';
        return array(
            (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(asks), 0) FROM $table WHERE requested_by = %d AND requested_at >= %s", $user_id, $today
            )),
            (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(asks), 0) FROM $table WHERE requested_at >= %s", $today
            )),
        );
    }

    private static function limit_reached() {
        return new WP_Error('ocr_limit', "Today's limit for reading pages has been reached.", array('status' => 429));
    }

    // ---- REST routes ---------------------------------------------------------------

    public function register_rest_routes() {
        register_rest_route('scouting-pdf/v1', '/ocr/(?P<doc_key>[a-f0-9]{32})/(?P<page>[0-9]{1,4})', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_get_text'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route('scouting-pdf/v1', '/ocr', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'rest_request_read'),
            // Logged-in readers only; WordPress's own cookie + X-WP-Nonce check runs first
            'permission_callback' => function() { return is_user_logged_in(); },
        ));
    }

    /**
     * GET /ocr/<doc_key>/<page>: what we have for this page. Open to everyone; saved text
     * is public. When the row is still queued and the asker is logged in, this also asks
     * the service whether the job finished, and saves the answer.
     */
    // Row columns the routes read ("words" stays in the database: it is large and the
    // viewer does not use it yet)
    const ROW_COLUMNS = 'id, doc_key, page, status, asks, job_id, text, engine, requested_by, requested_at, finished_at';

    public function rest_get_text($request) {
        if (!self::enabled()) {
            return rest_ensure_response(array('status' => 'off'));
        }
        global $wpdb;
        $doc_key = (string) $request['doc_key'];
        $page = (int) $request['page'];
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT ' . self::ROW_COLUMNS . ' FROM ' . self::table() . ' WHERE doc_key = %s AND page = %d', $doc_key, $page
        ));
        if (!$row) {
            return self::with_cache_headers(rest_ensure_response(array('status' => 'none')), false);
        }
        // Ask the service whether the job finished: logged-in callers only, never for a row
        // already given up on, and at most once every few seconds per row however many
        // readers are polling (the popup itself asks every 3 seconds)
        if ($row->status === 'queued' && is_user_logged_in() && $row->job_id
                && !self::is_stale($row) && false === get_transient('scouting_pdf_ocr_poll_' . $row->id)) {
            set_transient('scouting_pdf_ocr_poll_' . $row->id, 1, 3);
            $answer = self::call_service('GET', '/v1/jobs/' . $row->job_id);
            if ($answer['status'] === 404) {
                // The service no longer knows the job (its list was cleared); the staleness
                // rule below reports it, and an old row is closed for good here
                if (self::is_stale($row)) {
                    $wpdb->update(self::table(), array('status' => 'failed', 'finished_at' => current_time('mysql')), array('id' => $row->id));
                }
            } elseif ($answer['json']) {
                self::save_job_answer($row->id, $answer['json']);
            }
            $row = $wpdb->get_row($wpdb->prepare(
                'SELECT ' . self::ROW_COLUMNS . ' FROM ' . self::table() . ' WHERE id = %d', $row->id
            ));
        }
        $status = self::is_stale($row) ? 'failed' : $row->status;
        $out = array('status' => $status);
        if ($status === 'done') {
            $out['text'] = (string) $row->text;
            $out['engine'] = (string) $row->engine;
        }
        return self::with_cache_headers(rest_ensure_response($out), $status === 'done' || $status === 'empty');
    }

    /**
     * Deterministic caching on WP Engine: finished answers may be cached briefly,
     * anything still moving must not be
     */
    private static function with_cache_headers($response, $final) {
        $response->header('Cache-Control', $final ? 'public, max-age=300' : 'no-store');
        return $response;
    }

    /**
     * POST /ocr with {"url": "...", "page": 12}: a logged-in reader asks for a page to be
     * read. The link travels only in the request body, never in a web address.
     */
    public function rest_request_read($request) {
        if (!self::enabled()) {
            return new WP_Error('ocr_off', 'The reading service is switched off.', array('status' => 403));
        }
        $params = $request->get_json_params();
        $canonical = self::canonical_url(isset($params['url']) ? (string) $params['url'] : '');
        if (is_wp_error($canonical)) {
            return $canonical;
        }
        $page = isset($params['page']) ? (int) $params['page'] : 0;
        if ($page < 1 || $page > 9999) {
            return new WP_Error('bad_page', 'Page must be a whole number from 1 to 9999.', array('status' => 400));
        }

        global $wpdb;
        $this->maybe_create_table();
        $table = self::table();
        $doc_key = self::doc_key($canonical);

        // Already read: answer from what we saved, no limits spent
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT ' . self::ROW_COLUMNS . " FROM $table WHERE doc_key = %s AND page = %d", $doc_key, $page
        ));
        if ($row && ($row->status === 'done' || $row->status === 'empty')) {
            $out = array('status' => $row->status, 'doc_key' => $doc_key);
            if ($row->status === 'done') {
                $out['text'] = (string) $row->text;
                $out['engine'] = (string) $row->engine;
            }
            return rest_ensure_response($out);
        }
        if ($row && $row->status === 'queued' && !self::is_stale($row)) {
            return rest_ensure_response(array('status' => 'queued', 'doc_key' => $doc_key));
        }

        // Today's limits (site time): the user's, then the whole site's. Every page the service
        // takes on is counted ("asks"), so retrying a failed read spends the allowance like any
        // other request; a refusal (service stopped or busy) is given back further down.
        $limits = self::limits();
        $user_id = get_current_user_id();
        list($by_user, $by_site) = self::asks_today($user_id);
        if ($by_user >= $limits['user'] || $by_site >= $limits['site']) {
            return self::limit_reached();
        }

        // Save the queued row; the unique key stops two readers creating two jobs
        $now = current_time('mysql');
        if ($row) {
            // A failed or stale row is asked for again, and the ask is counted. Of two readers
            // retrying at the same moment only one gets it; the other finds it queued again.
            $stale_before = gmdate('Y-m-d H:i:s', strtotime($now) - 10 * MINUTE_IN_SECONDS);
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE $table SET status = 'queued', asks = asks + 1, job_id = '', requested_by = %d, requested_at = %s, finished_at = NULL
                 WHERE id = %d AND (status = 'failed' OR (status = 'queued' AND requested_at < %s))", $user_id, $now, $row->id, $stale_before
            ));
            if (!$updated) {
                return rest_ensure_response(array('status' => 'queued', 'doc_key' => $doc_key));
            }
            $row_id = (int) $row->id;
        } else {
            $inserted = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $table (doc_key, page, pdf_url, status, requested_by, requested_at)
                 VALUES (%s, %d, %s, 'queued', %d, %s)", $doc_key, $page, $canonical, $user_id, $now
            ));
            if (!$inserted) {
                // Another reader created the row in between: their job is under way
                return rest_ensure_response(array('status' => 'queued', 'doc_key' => $doc_key));
            }
            $row_id = (int) $wpdb->insert_id;
        }

        // Requests sent at the same moment all pass the check above, so look again now that this
        // ask is saved, and take it back if it went over
        list($by_user, $by_site) = self::asks_today($user_id);
        if ($by_user > $limits['user'] || $by_site > $limits['site']) {
            if ($row) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table SET status = 'failed', asks = asks - 1, finished_at = %s WHERE id = %d", current_time('mysql'), $row_id
                ));
            } else {
                $wpdb->delete($table, array('id' => $row_id), array('%d'));
            }
            return self::limit_reached();
        }

        // Hand the job to the service
        $answer = self::call_service('POST', '/v1/jobs', wp_json_encode(array('url' => $canonical, 'page' => $page)));
        $job = $answer['json'];
        if (!$job || empty($job['status']) || !empty($job['error'])) {
            // The service took nothing on, so the ask is given back: a stopped or busy service
            // must not use up readers' allowances. The row stays retryable either way, but a busy
            // service is not a broken one.
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET status = 'failed', asks = asks - 1, finished_at = %s WHERE id = %d", current_time('mysql'), $row_id
            ));
            $refusal = is_array($job) && isset($job['error']) ? (string) $job['error'] : '';
            if (in_array($refusal, array('queue_full', 'hourly_limit', 'paused'), true)) {
                return new WP_Error('ocr_busy', 'The reading service is busy right now.', array('status' => 503));
            }
            return new WP_Error('ocr_service_down', 'The reading service is not answering right now.', array('status' => 503));
        }
        $job_id = isset($job['id']) && preg_match('/^[a-f0-9]{32}$/', (string) $job['id']) ? (string) $job['id'] : '';
        $wpdb->update($table, array('job_id' => $job_id), array('id' => $row_id));
        if ($job['status'] === 'done' || $job['status'] === 'failed') {
            // The service already had this page (for example read once, then our table was emptied)
            self::save_job_answer($row_id, $job);
            $saved = $wpdb->get_row($wpdb->prepare('SELECT ' . self::ROW_COLUMNS . " FROM $table WHERE id = %d", $row_id));
            $out = array('status' => $saved->status, 'doc_key' => $doc_key);
            if ($saved->status === 'done') {
                $out['text'] = (string) $saved->text;
                $out['engine'] = (string) $saved->engine;
            }
            return rest_ensure_response($out);
        }
        return rest_ensure_response(array('status' => 'queued', 'doc_key' => $doc_key));
    }

    // ---- Settings page ---------------------------------------------------------------

    public function add_settings_page() {
        add_options_page('PDF OCR', 'PDF OCR', 'manage_options', 'scouting-pdf-ocr', array($this, 'render_settings_page'));
    }

    public function register_settings() {
        register_setting('scouting_pdf_ocr', self::OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings'),
        ));
    }

    public function sanitize_settings($input) {
        $old = self::settings();
        $input = is_array($input) ? $input : array();
        $url = isset($input['url']) ? esc_url_raw(trim((string) $input['url'])) : '';
        $secret = isset($input['secret']) ? trim((string) $input['secret']) : '';
        return array(
            'enabled'    => empty($input['enabled']) ? 0 : 1,
            'url'        => $url,
            // An empty password box keeps the saved secret
            'secret'     => $secret === '' ? $old['secret'] : $secret,
            'site_limit' => max(1, min(10000, (int) ($input['site_limit'] ?? $old['site_limit']))),
            'user_limit' => max(1, min(10000, (int) ($input['user_limit'] ?? $old['user_limit']))),
        );
    }

    private static function count_today() {
        global $wpdb;
        if ((int) get_option(self::DB_OPTION) !== self::TABLE_VERSION) {
            return 0;
        }
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE requested_at >= %s', current_time('Y-m-d') . ' 00:00:00'
        ));
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = self::settings();
        $constants = defined('SCOUTING_PDF_OCR_URL') && SCOUTING_PDF_OCR_URL && defined('SCOUTING_PDF_OCR_SECRET') && SCOUTING_PDF_OCR_SECRET;
        $test_url = wp_nonce_url(admin_url('admin-post.php?action=scouting_pdf_ocr_test'), 'scouting_pdf_ocr_test');
        ?>
        <div class="wrap">
            <h1>PDF OCR</h1>
            <p>Lets logged-in readers have the text of one PDF page read by the OCR service. Text already read is shown to everyone.</p>
            <?php if ($constants) : ?>
                <div class="notice notice-info"><p>The service address and secret are set in <code>wp-config.php</code>, so the feature is <strong><?php echo self::enabled() ? 'on' : 'off'; ?></strong> and the boxes below are ignored.</p></div>
            <?php endif; ?>
            <?php if ((int) get_option(self::DB_OPTION) !== self::TABLE_VERSION) : ?>
                <div class="notice notice-error"><p>The table that stores read pages could not be created. Nothing can be saved until it exists; the site will keep trying.</p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('scouting_pdf_ocr'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Reading pages</th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked($s['enabled']); ?>> Switched on</label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ocr-url">Service address</label></th>
                        <td><input type="url" class="regular-text" id="ocr-url" name="<?php echo esc_attr(self::OPTION); ?>[url]" value="<?php echo esc_attr($s['url']); ?>" placeholder="https://...">
                        <p class="description">The OCR service's address, without <code>/v1</code>. It must start with <code>https://</code>.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ocr-secret">Shared secret</label></th>
                        <td><input type="password" class="regular-text" id="ocr-secret" name="<?php echo esc_attr(self::OPTION); ?>[secret]" value="" autocomplete="new-password" placeholder="<?php echo $s['secret'] !== '' ? 'saved (leave empty to keep it)' : ''; ?>">
                        <p class="description">Leave empty to keep the saved secret.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ocr-site-limit">Pages per day, whole site</label></th>
                        <td><input type="number" min="1" max="10000" id="ocr-site-limit" name="<?php echo esc_attr(self::OPTION); ?>[site_limit]" value="<?php echo (int) $s['site_limit']; ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ocr-user-limit">Pages per day, per reader</label></th>
                        <td><input type="number" min="1" max="10000" id="ocr-user-limit" name="<?php echo esc_attr(self::OPTION); ?>[user_limit]" value="<?php echo (int) $s['user_limit']; ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row">Today</th>
                        <td><?php echo (int) self::count_today(); ?> page(s) requested.</td>
                    </tr>
                </table>
                <p class="submit">
                    <?php submit_button('Save Changes', 'primary', 'submit', false); ?>
                    <?php if (self::enabled()) : ?>
                        <a href="<?php echo esc_url($test_url); ?>" class="button">Test connection</a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * "Test connection": a signed question about a job that cannot exist. "No such job" back
     * means the service is reachable and accepted the secret.
     */
    public function handle_test_connection() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }
        check_admin_referer('scouting_pdf_ocr_test');
        $result = 'down';
        if (self::enabled()) {
            $answer = self::call_service('GET', '/v1/jobs/' . str_repeat('0', 32));
            if ($answer['status'] === 404 && $answer['json']) {
                $result = 'ok';
            } elseif ($answer['status'] === 401) {
                // The service tells a wrong secret and a wrong clock apart; so do we
                $result = (isset($answer['json']['error']) && $answer['json']['error'] === 'bad_time') ? 'clock' : 'secret';
            }
        }
        wp_safe_redirect(add_query_arg('ocr_test', $result, admin_url('options-general.php?page=scouting-pdf-ocr')));
        exit;
    }

    public function print_test_result() {
        if (!isset($_GET['ocr_test']) || !isset($_GET['page']) || $_GET['page'] !== 'scouting-pdf-ocr') {
            return;
        }
        $messages = array(
            'ok'     => array('notice-success', 'Connected: the service answered and accepted the secret.'),
            'secret' => array('notice-error', 'The service answered but refused the secret. Check the shared secret.'),
            'clock'  => array('notice-error', 'The clocks of this website and the reading service are more than 5 minutes apart. The secret itself is right.'),
            'down'   => array('notice-error', 'The service did not answer. Check the address, or try again shortly.'),
        );
        $key = (string) $_GET['ocr_test'];
        if (isset($messages[$key])) {
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($messages[$key][0]), esc_html($messages[$key][1]));
        }
    }
}
