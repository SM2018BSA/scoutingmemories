<?php
/**
 * Background reading: a queue of whole documents for the page OCR service.
 *
 * WordPress owns the queue (one row per document in {prefix}scouting_pdf_ocr_docs); the OCR
 * service on the development server stays the dumb one-page worker it already is. A tick runs
 * every minute (WP-Cron; the dashboard also ticks when cron is late) and, for ONE document at
 * a time, polls the pages sent earlier and sends the next ones — at most 3 in flight, page 1
 * first, because page 1's answer names the document's page count. One document at a time keeps
 * the service's PDF cache effective (each PDF is downloaded once); 3 in flight keeps readers'
 * own Text-button requests fast.
 *
 * Nothing runs unless the OCR feature AND the "Background reading" setting are on, and the
 * queue additionally starts paused: nothing is sent until Resume is pressed on the dashboard.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Scouting_PDF_OCR_Queue {

    private static $instance = null;

    const STATE_OPTION     = 'scouting_pdf_ocr_queue_state'; // 'paused' (the default) or 'running'
    const LAST_TICK_OPTION = 'scouting_pdf_ocr_last_tick';
    const LOCK_OPTION      = 'scouting_pdf_ocr_tick_lock';
    const BACKOFF_TRANSIENT = 'scouting_pdf_ocr_backoff';
    const CRON_HOOK        = 'scouting_pdf_ocr_tick';
    const CRON_SCHEDULE    = 'scouting_pdf_ocr_minute';

    // At most 3 queue pages at the service at once, so a reader's own page never waits
    // behind more than a few ~10-second jobs
    const MAX_IN_FLIGHT = 3;
    // Bounds per tick: well under the minute between ticks, and far under the 120-second
    // point where a waiting tick would take the lock over
    const MAX_POLLS      = 10;
    const MAX_DISPATCHES = 3;
    const TICK_BUDGET    = 20;  // seconds of service calls
    // When the service refuses (full, hourly cap, paused) or does not answer, the queue
    // yields for ten minutes instead of hammering it
    const BACKOFF_SECONDS = 600;
    const LOCK_STALE_SECONDS = 120;
    const OVERDUE_SECONDS    = 90;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('cron_schedules', array($this, 'add_cron_schedule'));
        // After Scouting_PDF_OCR::maybe_create_table(), which also runs on init
        add_action('init', array($this, 'maybe_schedule'), 20);
        add_action(self::CRON_HOOK, array($this, 'tick'));
        add_action('wp_after_insert_post', array($this, 'auto_enqueue_post'), 10, 2);
    }

    // ---- Filling the queue ------------------------------------------------------------

    /**
     * Put one document in the queue. $canonical must already be canonical_url()'s spelling,
     * so http and https spellings of one file make one document. Returns 'added' or 'exists'.
     */
    public static function enqueue($canonical, $source, $post_id = 0, $label = '', $priority = 0) {
        global $wpdb;
        if ((int) get_option(Scouting_PDF_OCR::DB_OPTION) !== Scouting_PDF_OCR::TABLE_VERSION) {
            return 'exists'; // the tables are not ready yet; the site keeps trying on load
        }
        if ($label === '') {
            $label = rawurldecode(basename((string) wp_parse_url($canonical, PHP_URL_PATH)));
        }
        $inserted = $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO ' . Scouting_PDF_OCR::docs_table() .
            ' (doc_key, pdf_url, post_id, label, status, priority, source, enqueued_by, enqueued_at)' .
            " VALUES (%s, %s, %d, %s, 'queued', %d, %s, %d, %s)",
            Scouting_PDF_OCR::doc_key($canonical), $canonical, $post_id,
            mb_substr($label, 0, 200), $priority, $source,
            get_current_user_id(), current_time('mysql')
        ));
        return $inserted ? 'added' : 'exists';
    }

    /**
     * Documents in new and submitted posts are queued as the posts are saved. A submission
     * from the Add a Post form arrives as a PENDING post, so its document goes to the front
     * of the queue (priority 1) and its text is usually ready when a reviewer opens the post.
     */
    public function auto_enqueue_post($post_id, $post) {
        if (!Scouting_PDF_OCR::queue_enabled() || !Scouting_PDF_OCR::auto_enqueue()) {
            return;
        }
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!$post || !in_array($post->post_status, array('pending', 'publish'), true)) {
            return;
        }
        $type = get_post_type_object($post->post_type);
        if (!$type || empty($type->public)) {
            return;
        }
        self::enqueue_post_documents($post_id, $post->post_content, 'auto', 1);
    }

    /**
     * Queue every readable document in one post's content. Returns array(added, existing);
     * links the archive storage cannot serve are left out.
     */
    public static function enqueue_post_documents($post_id, $content, $source, $priority) {
        $added = 0;
        $existing = 0;
        $label = mb_substr(wp_strip_all_tags((string) get_the_title($post_id)), 0, 200);
        foreach (Scouting_PDF_Embedder::all_pdfs_in_content($content) as $url) {
            $canonical = Scouting_PDF_OCR::canonical_url($url);
            if (is_wp_error($canonical)) {
                continue; // not on the archive storage, so the service could not read it
            }
            if (self::enqueue($canonical, $source, $post_id, $label, $priority) === 'added') {
                $added++;
            } else {
                $existing++;
            }
        }
        return array($added, $existing);
    }

    const SCAN_BATCH = 200;

    /**
     * One batch of the whole-site scan: published and pending posts of public types after
     * $after_id, in ID order, straight from the posts table — each batch is one bounded
     * request, so the scan works behind the development server's gate too. The dashboard
     * calls this until 'done'.
     */
    public static function scan_batch($after_id, $batch = self::SCAN_BATCH) {
        global $wpdb;
        $types = array_values(array_diff(get_post_types(array('public' => true)), array('attachment')));
        if (!$types) {
            return array('last_id' => (int) $after_id, 'done' => true, 'posts_scanned' => 0, 'docs_added' => 0);
        }
        $type_list = implode(',', array_fill(0, count($types), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_content FROM {$wpdb->posts}
             WHERE ID > %d AND post_status IN ('publish', 'pending') AND post_type IN ($type_list)
             ORDER BY ID LIMIT %d",
            array_merge(array((int) $after_id), $types, array((int) $batch))
        ));
        $added = 0;
        $last = (int) $after_id;
        foreach ($rows as $row) {
            $last = (int) $row->ID;
            $label = mb_substr(wp_strip_all_tags((string) $row->post_title), 0, 200);
            foreach (Scouting_PDF_Embedder::all_pdfs_in_content($row->post_content) as $url) {
                $canonical = Scouting_PDF_OCR::canonical_url($url);
                if (is_wp_error($canonical)) {
                    continue;
                }
                if (self::enqueue($canonical, 'bulk', (int) $row->ID, $label, 0) === 'added') {
                    $added++;
                }
            }
        }
        return array(
            'last_id'       => $last,
            'done'          => count($rows) < (int) $batch,
            'posts_scanned' => count($rows),
            'docs_added'    => $added,
        );
    }

    // ---- Cron ---------------------------------------------------------------------

    public function add_cron_schedule($schedules) {
        $schedules[self::CRON_SCHEDULE] = array(
            'interval' => MINUTE_IN_SECONDS,
            'display'  => 'Once a minute, while the PDF OCR queue is on',
        );
        return $schedules;
    }

    /**
     * The cron event exists exactly while the feature and Background reading are on
     */
    public function maybe_schedule() {
        $on = Scouting_PDF_OCR::queue_enabled();
        $scheduled = wp_next_scheduled(self::CRON_HOOK);
        if ($on && !$scheduled) {
            wp_schedule_event(time(), self::CRON_SCHEDULE, self::CRON_HOOK);
        } elseif (!$on && $scheduled) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }

    // ---- Running or paused ----------------------------------------------------------

    public static function state() {
        return get_option(self::STATE_OPTION) === 'running' ? 'running' : 'paused';
    }

    public static function set_state($state) {
        update_option(self::STATE_OPTION, $state === 'running' ? 'running' : 'paused', false);
        if ($state === 'running') {
            // Resume means now, not when the backoff runs out
            delete_transient(self::BACKOFF_TRANSIENT);
        }
    }

    /**
     * Unix time the current backoff ends, or 0 when the queue is not backing off
     */
    public static function backoff_until() {
        $until = (int) get_transient(self::BACKOFF_TRANSIENT);
        return $until > time() ? $until : 0;
    }

    private static function start_backoff() {
        set_transient(self::BACKOFF_TRANSIENT, time() + self::BACKOFF_SECONDS, self::BACKOFF_SECONDS);
    }

    public static function last_tick() {
        return (string) get_option(self::LAST_TICK_OPTION, '');
    }

    // ---- The lock -------------------------------------------------------------------

    /**
     * One tick at a time, however it was started (cron, the dashboard's poll, Run once now).
     * An option row, not MySQL's GET_LOCK, because the test WordPress runs on SQLite and the
     * same code must run everywhere. The name is never read with get_option, so the options
     * cache cannot serve a stale copy.
     */
    private static function acquire_lock() {
        global $wpdb;
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            self::LOCK_OPTION, (string) time()
        ));
        if ($inserted === 1) {
            return true;
        }
        $held = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPTION
        ));
        if ($held === null || (int) $held >= time() - self::LOCK_STALE_SECONDS) {
            return false;
        }
        // The holder died mid-run (a tick never takes this long, see TICK_BUDGET). Delete
        // exactly the value seen, so a lock a THIRD run just took stays untouched.
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
            self::LOCK_OPTION, $held
        ));
        return $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            self::LOCK_OPTION, (string) time()
        )) === 1;
    }

    private static function release_lock() {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPTION
        ));
    }

    // ---- The tick ---------------------------------------------------------------------

    /**
     * One run of the dispatcher. Returns true when it ran, false when it stood back
     * (feature off, backing off, or another tick holds the lock).
     */
    public function tick() {
        if (!Scouting_PDF_OCR::queue_enabled() || self::backoff_until()) {
            return false;
        }
        if (!self::acquire_lock()) {
            return false;
        }
        try {
            update_option(self::LAST_TICK_OPTION, current_time('mysql'), false);
            $this->run_tick();
        } finally {
            self::release_lock();
        }
        return true;
    }

    /**
     * The dashboard calls this on every status poll: when cron has not run for a while
     * (no timer on the server, say), browsing the dashboard keeps the queue moving.
     */
    public function tick_if_overdue() {
        if (!Scouting_PDF_OCR::queue_enabled() || self::state() !== 'running' || self::backoff_until()) {
            return;
        }
        $last = self::last_tick();
        if ($last !== '' && strtotime($last) >= strtotime(current_time('mysql')) - self::OVERDUE_SECONDS) {
            return;
        }
        $this->tick();
    }

    private function run_tick() {
        global $wpdb;
        $docs = Scouting_PDF_OCR::docs_table();
        $pages = Scouting_PDF_OCR::table();
        $deadline = microtime(true) + self::TICK_BUDGET;
        $running = self::state() === 'running';

        $doc = $wpdb->get_row("SELECT * FROM $docs WHERE status = 'working' LIMIT 1");

        if ($doc) {
            // Poll the pages sent earlier, oldest first — readers' pages of this document too
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, job_id, requested_at FROM $pages WHERE doc_key = %s AND status = 'queued' ORDER BY requested_at, id LIMIT %d",
                $doc->doc_key, self::MAX_POLLS
            ));
            $gone_stale = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - 10 * MINUTE_IN_SECONDS);
            foreach ($rows as $row) {
                if ($row->job_id === '') {
                    // Died between its INSERT and the send; closed once it is old enough
                    if ($row->requested_at < $gone_stale) {
                        $wpdb->update($pages, array('status' => 'failed', 'finished_at' => current_time('mysql')),
                            array('id' => $row->id, 'status' => 'queued'));
                    }
                    continue;
                }
                if (microtime(true) >= $deadline) {
                    break;
                }
                $answer = Scouting_PDF_OCR::call_service('GET', '/v1/jobs/' . $row->job_id);
                if ($answer['status'] === 404) {
                    // The service forgot the job (its 7-day clean-up, or a reset)
                    $wpdb->update($pages, array('status' => 'failed', 'finished_at' => current_time('mysql')),
                        array('id' => $row->id));
                } elseif ($answer['json']) {
                    Scouting_PDF_OCR::save_job_answer($row->id, $answer['json']);
                }
            }
        } elseif ($running) {
            // Take the next waiting document: hand-added and submissions first (priority 1)
            $doc = $wpdb->get_row("SELECT * FROM $docs WHERE status = 'queued' ORDER BY priority DESC, id LIMIT 1");
            if ($doc) {
                $wpdb->update($docs, array('status' => 'working', 'started_at' => current_time('mysql')),
                    array('id' => $doc->id));
            }
        }
        if (!$doc) {
            return;
        }
        // save_job_answer() may have filled total_pages in the meantime
        $doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $docs WHERE id = %d", $doc->id));
        if (!$doc) {
            return;
        }
        if ($running && $doc->status === 'working') {
            $this->dispatch_pages($doc, $deadline);
            $doc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $docs WHERE id = %d", $doc->id));
        }
        if ($doc) {
            $this->settle($doc);
        }
    }

    /**
     * Send the working document's next pages: learn the page count from page 1 first,
     * then fill the in-flight slots lowest page first.
     */
    private function dispatch_pages($doc, $deadline) {
        global $wpdb;
        $pages = Scouting_PDF_OCR::table();
        $docs = Scouting_PDF_OCR::docs_table();

        if ((int) $doc->total_pages === 0) {
            $first = $wpdb->get_row($wpdb->prepare(
                "SELECT id, status, source FROM $pages WHERE doc_key = %s AND page = 1", $doc->doc_key
            ));
            if (!$first) {
                // Page 1's answer names the page count; the rest of the document waits for it
                $this->dispatch($doc, 1, null);
                return;
            }
            if ($first->status === 'queued') {
                return; // in flight (a reader's request, or our own); the poll step handles it
            }
            if ($first->status === 'failed') {
                if ($first->source !== 'reader') {
                    return; // our own send failed; settle() marks the document failed
                }
                // A reader's old failure: the queue takes it over for one retry
                $this->dispatch($doc, 1, $first);
                return;
            }
            // done or empty: a reader read page 1 before the document was queued. Ask the
            // service about that page again only to learn the count — it answers with the
            // finished job if it still has it, or reads the page once more. No new row.
            if (microtime(true) >= $deadline) {
                return;
            }
            $answer = Scouting_PDF_OCR::call_service('POST', '/v1/jobs',
                wp_json_encode(array('url' => $doc->pdf_url, 'page' => 1)));
            $job = $answer['json'];
            if (!$job || empty($job['status']) || !empty($job['error'])) {
                self::start_backoff();
                return;
            }
            if ($job['status'] !== 'done' || (int) ($job['pages'] ?? 0) < 1) {
                return; // the service is re-reading it; the count arrives on a later tick
            }
            $total = min(9999, (int) $job['pages']);
            $wpdb->query($wpdb->prepare(
                "UPDATE $docs SET total_pages = %d WHERE id = %d AND total_pages = 0", $total, $doc->id
            ));
            $doc->total_pages = $total;
        }

        $dispatched = 0;
        while ($dispatched < self::MAX_DISPATCHES && microtime(true) < $deadline) {
            $in_flight = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $pages WHERE doc_key = %s AND status = 'queued' AND job_id <> '' AND source IN ('bulk', 'auto')",
                $doc->doc_key
            ));
            if ($in_flight >= self::MAX_IN_FLIGHT) {
                return;
            }
            $next = $this->next_page($doc);
            if (!$next) {
                return;
            }
            if (!$this->dispatch($doc, $next[0], $next[1])) {
                return; // refused: the backoff is set, this tick is over
            }
            $dispatched++;
        }
    }

    /**
     * The lowest page of 1..total_pages that has no row yet, or a reader's failed row the
     * queue may take over. The queue's OWN failed pages stay failed (they are counted and
     * the document completes around them; the dashboard's Try again is their retry).
     * Returns array(page number, existing row or null), or null when nothing is left.
     */
    private function next_page($doc) {
        global $wpdb;
        $pages = Scouting_PDF_OCR::table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT page, id, status, source FROM $pages WHERE doc_key = %s ORDER BY page", $doc->doc_key
        ), OBJECT_K);
        for ($p = 1; $p <= (int) $doc->total_pages; $p++) {
            if (!isset($rows[$p])) {
                return array($p, null);
            }
            if ($rows[$p]->status === 'failed' && $rows[$p]->source === 'reader') {
                return array($p, $rows[$p]);
            }
        }
        return null;
    }

    /**
     * One page to the service. Returns false only on a refusal (the backoff is then set);
     * losing a race to a reader is fine and returns true.
     */
    private function dispatch($doc, $page_no, $existing) {
        global $wpdb;
        $pages = Scouting_PDF_OCR::table();
        $now = current_time('mysql');
        if ($existing) {
            // The reader path's conditional retry, but on the queue's account: the document's
            // source, no ask spent, nobody's name on it
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE $pages SET status = 'queued', source = %s, job_id = '', requested_by = 0, requested_at = %s, finished_at = NULL
                 WHERE id = %d AND status = 'failed'", $doc->source, $now, $existing->id
            ));
            if (!$updated) {
                return true; // someone else took it in between; not a refusal
            }
            $row_id = (int) $existing->id;
            $was_insert = false;
        } else {
            $inserted = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO $pages (doc_key, page, pdf_url, status, source, asks, requested_by, requested_at)
                 VALUES (%s, %d, %s, 'queued', %s, 0, 0, %s)",
                $doc->doc_key, $page_no, $doc->pdf_url, $doc->source, $now
            ));
            if (!$inserted) {
                return true; // a reader owns this page; their request is under way
            }
            $row_id = (int) $wpdb->insert_id;
            $was_insert = true;
        }
        $answer = Scouting_PDF_OCR::call_service('POST', '/v1/jobs',
            wp_json_encode(array('url' => $doc->pdf_url, 'page' => $page_no)));
        $job = $answer['json'];
        if (!$job || empty($job['status']) || !empty($job['error'])) {
            // The service took nothing on (full, capped, paused, down). Give the page back
            // so it is sent again after the backoff, and stop bothering the service.
            if ($was_insert) {
                $wpdb->delete($pages, array('id' => $row_id), array('%d'));
            } else {
                $wpdb->update($pages, array('status' => 'failed', 'finished_at' => $now), array('id' => $row_id));
            }
            self::start_backoff();
            return false;
        }
        $job_id = isset($job['id']) && preg_match('/^[a-f0-9]{32}$/', (string) $job['id']) ? (string) $job['id'] : '';
        $wpdb->update($pages, array('job_id' => $job_id), array('id' => $row_id));
        if ($job['status'] === 'done' || $job['status'] === 'failed') {
            // The service already had this page (read once before, then our table changed)
            Scouting_PDF_OCR::save_job_answer($row_id, $job);
        }
        return true;
    }

    /**
     * Recount the working document from its page rows (never trust counters) and close it
     * when every page is accounted for — or when page 1 is beyond reach.
     */
    private function settle($doc) {
        global $wpdb;
        if (!$doc || $doc->status !== 'working') {
            return;
        }
        $pages = Scouting_PDF_OCR::table();
        $docs = Scouting_PDF_OCR::docs_table();
        $total = (int) $doc->total_pages;
        $bound = $total > 0 ? $wpdb->prepare(' AND page <= %d', $total) : '';
        $done = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $pages WHERE doc_key = %s AND status IN ('done', 'empty')", $doc->doc_key
        ) . $bound);
        $failed = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $pages WHERE doc_key = %s AND status = 'failed'", $doc->doc_key
        ) . $bound);
        $update = array('pages_done' => $done, 'pages_failed' => $failed);
        $first = $wpdb->get_row($wpdb->prepare(
            "SELECT status, source FROM $pages WHERE doc_key = %s AND page = 1", $doc->doc_key
        ));
        if ($first && $first->status === 'failed' && $first->source !== 'reader') {
            // Our own page 1 failed: without it the page count can never be learned, so the
            // document has failed; the dashboard's Try again is its retry
            $update['status'] = 'failed';
            $update['finished_at'] = current_time('mysql');
        } elseif ($total > 0 && $done + $failed >= $total) {
            // Done, with its failures counted (the dashboard shows them)
            $update['status'] = 'done';
            $update['finished_at'] = current_time('mysql');
        }
        $wpdb->update($docs, $update, array('id' => $doc->id));
    }
}
