<?php
/**
 * Background reading: a queue of whole documents for the page OCR service.
 *
 * WordPress owns the queue (one row per document in {prefix}scouting_pdf_ocr_docs); the OCR
 * service on the development server stays the dumb page worker it already is. A tick runs
 * every minute (WP-Cron; the dashboard also ticks when cron is late). It works on up to 4
 * documents at once, polls the pages sent earlier and sends the next ones — at most 4 in
 * flight over all documents, page 1 of each first, because page 1's answer names the
 * document's page count. 4 in flight keeps the service's readers (2 on the development
 * server) busy while keeping readers' own Text-button requests fast. A tick started by cron
 * stays for most of its minute, collecting answers every few seconds and sending the next
 * pages at once; a tick from the dashboard is one quick pass.
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

    // At most 4 queue pages at the service at once (over all documents), so a reader's own
    // page never waits behind more than a few ~10-second jobs
    const MAX_IN_FLIGHT = 4;
    // Documents read side by side; most of the archive's documents are a page or two
    const MAX_WORKING_DOCS = 4;
    // Bounds per tick: under the minute between ticks, and far under the 120-second point
    // where a waiting tick would take the lock over
    const MAX_POLLS   = 12;
    const TICK_BUDGET = 20;  // seconds: a dashboard tick (Run once now, or cron running late)
    const CRON_BUDGET = 45;  // seconds: a tick from cron, which waits for answers as it goes
    const POLL_PAUSE  = 2;   // seconds between looks at the service within a cron tick
    // When the service refuses (full, hourly cap, paused) or does not answer, the queue
    // yields for ten minutes instead of hammering it
    const BACKOFF_SECONDS = 600;
    const LOCK_STALE_SECONDS = 120;
    const OVERDUE_SECONDS    = 90;
    // One post can name at most this many documents; more is a mistake or mischief
    const MAX_DOCS_PER_POST = 20;
    // A working document whose page count cannot be learned (page 1 read long ago, the
    // service re-read keeps failing) is given up on after a day, so it cannot block the queue
    const LEARN_GIVE_UP_SECONDS = 86400;

    // The exact lock value this run inserted, so release touches no other run's lock
    private static $lock_value = '';

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
            return 'not_ready'; // the tables are missing; dbDelta retries on every load
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
        $taken = 0;
        foreach (Scouting_PDF_Embedder::all_pdfs_in_content($content) as $url) {
            $canonical = Scouting_PDF_OCR::canonical_url($url);
            if (is_wp_error($canonical)) {
                continue; // not on the archive storage, so the service could not read it
            }
            // A cap per post: the archive's posts hold a handful of documents each, so
            // hundreds of links in one post are a mistake or mischief, not work
            if (++$taken > self::MAX_DOCS_PER_POST) {
                break;
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
        // time plus a random tail: two runs in the same second must not share a value,
        // or release could free the other's lock
        self::$lock_value = time() . '.' . wp_rand(100000, 999999);
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            self::LOCK_OPTION, self::$lock_value
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
            self::LOCK_OPTION, self::$lock_value
        )) === 1;
    }

    /**
     * Releases only this run's own lock: a run that stalled past the takeover and lost its
     * lock must not free the lock of whoever took over.
     */
    private static function release_lock() {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
            self::LOCK_OPTION, self::$lock_value
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
        // Only cron waits for answers: a web request (the dashboard) must come back quickly
        $from_cron = wp_doing_cron();
        if ($from_cron && function_exists('set_time_limit')) {
            @set_time_limit(self::CRON_BUDGET + 30);
        }
        try {
            update_option(self::LAST_TICK_OPTION, current_time('mysql'), false);
            $this->run_tick($from_cron ? self::CRON_BUDGET : self::TICK_BUDGET, $from_cron);
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

    private function run_tick($budget, $wait) {
        $deadline = microtime(true) + $budget;
        while (true) {
            $running = self::state() === 'running';
            $this->poll_pages($deadline);
            $this->settle_working();
            if ($running && !$this->fill_slots($deadline)) {
                return; // refused: the backoff is set, this tick is over
            }
            // A run from cron stays and collects the answers as they come in, sending the
            // next pages straight away, instead of leaving the service idle until the next minute
            if (!$wait || $this->in_flight() === 0 || microtime(true) + self::POLL_PAUSE >= $deadline) {
                return;
            }
            sleep(self::POLL_PAUSE);
            // Pause, or Background reading switched off, takes effect within a few seconds
            wp_cache_delete(self::STATE_OPTION, 'options');
            wp_cache_delete(Scouting_PDF_OCR::OPTION, 'options');
            wp_cache_delete('alloptions', 'options');
            if (!Scouting_PDF_OCR::queue_enabled()) {
                return;
            }
        }
    }

    /**
     * Ask the service about the pages sent earlier, oldest first: the queue's own and
     * readers' pages of the documents being read
     */
    private function poll_pages($deadline) {
        global $wpdb;
        $docs = Scouting_PDF_OCR::docs_table();
        $pages = Scouting_PDF_OCR::table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.job_id, p.requested_at FROM $pages p JOIN $docs d ON d.doc_key = p.doc_key
             WHERE d.status = 'working' AND p.status = 'queued' ORDER BY p.requested_at, p.id LIMIT %d",
            self::MAX_POLLS
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
    }

    /**
     * The documents being read, front of the queue first
     */
    public static function working_docs() {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . Scouting_PDF_OCR::docs_table() .
            " WHERE status = 'working' ORDER BY priority DESC, id");
    }

    private function settle_working() {
        foreach (self::working_docs() as $doc) {
            $this->settle($doc);
        }
    }

    /**
     * The queue's pages at the service right now, over all documents
     */
    private function in_flight() {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Scouting_PDF_OCR::table() .
            " WHERE status = 'queued' AND job_id <> '' AND source IN ('bulk', 'auto')");
    }

    /**
     * Keep MAX_IN_FLIGHT pages at the service: the next pages of the documents being read
     * first (front of the queue first), then the next waiting document, up to
     * MAX_WORKING_DOCS at once. Several documents at once matters because most of the
     * archive's documents are short: one at a time would leave the service idle while each
     * one's page 1 is read. Returns false when the service refused.
     */
    private function fill_slots($deadline) {
        global $wpdb;
        $docs = Scouting_PDF_OCR::docs_table();
        $free = self::MAX_IN_FLIGHT - $this->in_flight();
        while ($free > 0 && microtime(true) < $deadline) {
            $working = self::working_docs();
            foreach ($working as $doc) {
                $sent = $this->dispatch_pages($doc, $deadline, $free);
                if ($sent === false) {
                    return false;
                }
                $free -= $sent;
                if ($free <= 0) {
                    return true;
                }
            }
            if (count($working) >= self::MAX_WORKING_DOCS) {
                return true;
            }
            // Take the next waiting document: hand-added and submissions first (priority 1)
            $next = $wpdb->get_row("SELECT id FROM $docs WHERE status = 'queued' ORDER BY priority DESC, id LIMIT 1");
            if (!$next) {
                return true;
            }
            $wpdb->query($wpdb->prepare(
                "UPDATE $docs SET status = 'working', started_at = %s WHERE id = %d AND status = 'queued'",
                current_time('mysql'), $next->id
            ));
        }
        return true;
    }

    /**
     * Send up to $limit of one document's next pages: learn the page count from page 1
     * first, then lowest page first. Returns how many were sent, or false on a refusal.
     */
    private function dispatch_pages($doc, $deadline, $limit) {
        global $wpdb;
        $pages = Scouting_PDF_OCR::table();
        $docs = Scouting_PDF_OCR::docs_table();

        if ((int) $doc->total_pages === 0) {
            $first = $wpdb->get_row($wpdb->prepare(
                "SELECT id, status, source FROM $pages WHERE doc_key = %s AND page = 1", $doc->doc_key
            ));
            if (!$first) {
                // Page 1's answer names the page count; the rest of the document waits for it
                return $this->dispatch($doc, 1, null) ? 1 : false;
            }
            if ($first->status === 'queued') {
                return 0; // in flight (a reader's request, or our own); the poll step handles it
            }
            if ($first->status === 'failed') {
                if ($first->source !== 'reader') {
                    return 0; // our own send failed; settle() marks the document failed
                }
                // A reader's old failure: the queue takes it over for one retry
                return $this->dispatch($doc, 1, $first) ? 1 : false;
            }
            // done or empty: a reader read page 1 before the document was queued. Ask the
            // service about that page again only to learn the count — it answers with the
            // finished job if it still has it, or reads the page once more. No new row.
            if (microtime(true) >= $deadline) {
                return 0;
            }
            $answer = Scouting_PDF_OCR::call_service('POST', '/v1/jobs',
                wp_json_encode(array('url' => $doc->pdf_url, 'page' => 1)));
            $job = $answer['json'];
            if (!$job || empty($job['status']) || !empty($job['error'])) {
                self::start_backoff();
                return false;
            }
            if ($job['status'] !== 'done' || (int) ($job['pages'] ?? 0) < 1) {
                return 0; // the service is re-reading it; the count arrives on a later tick
            }
            $total = min(9999, (int) $job['pages']);
            $wpdb->query($wpdb->prepare(
                "UPDATE $docs SET total_pages = %d WHERE id = %d AND total_pages = 0", $total, $doc->id
            ));
            $doc->total_pages = $total;
        }

        $sent = 0;
        while ($sent < $limit && microtime(true) < $deadline) {
            $next = $this->next_page($doc);
            if (!$next) {
                break;
            }
            if (!$this->dispatch($doc, $next[0], $next[1])) {
                return false;
            }
            $sent++;
        }
        return $sent;
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
                // A taken-over reader row goes back the way it was found — failed, the
                // reader's — so a busy hour cannot burn its one queue retry (or fail the
                // whole document when it was page 1)
                $wpdb->update($pages, array('status' => 'failed', 'source' => 'reader', 'finished_at' => $now), array('id' => $row_id));
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
        // A reader's failed page still has its one queue takeover coming, so the document
        // is not closed over it (it would silently skip that retry)
        $reader_failed = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $pages WHERE doc_key = %s AND status = 'failed' AND source = 'reader'", $doc->doc_key
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
        } elseif ($total === 0 && $first && ($first->status === 'done' || $first->status === 'empty')
                && $doc->started_at && strtotime($doc->started_at) < strtotime(current_time('mysql')) - self::LEARN_GIVE_UP_SECONDS) {
            // Page 1 was read long ago, the service has forgotten it, and re-reading it has
            // not produced a page count for a whole day (the file is gone from storage, or
            // that page no longer reads). Give up so the document cannot block the queue;
            // the dashboard's Try again starts it afresh.
            $update['status'] = 'failed';
            $update['finished_at'] = current_time('mysql');
        } elseif ($total > 0 && $done + $failed >= $total && $reader_failed === 0) {
            // Done, with its failures counted (the dashboard shows them)
            $update['status'] = 'done';
            $update['finished_at'] = current_time('mysql');
        }
        $wpdb->update($docs, $update, array('id' => $doc->id));
    }
}
