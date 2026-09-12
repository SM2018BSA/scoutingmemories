<?php
/**
 * Plugin Name: Scouting PDF Embedder
 * Plugin URI: https://scoutingmemories.org/
 * Description: High-performance, modern PDF viewer for Scouting Memories. Seamlessly replaces commercial PDF Embedder, supporting legacy shortcodes, Gutenberg blocks, and frontend form submissions.
 * Version: 1.0.0
 * Author: Roger Ellis / Scouting Memories
 * License: GPL-2.0+
 * Text Domain: scouting-pdf-embedder
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SCOUTING_PDF_VERSION', '1.0.4');
define('SCOUTING_PDF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCOUTING_PDF_PLUGIN_URL', plugin_dir_url(__FILE__));

class Scouting_PDF_Embedder {

    private static $instance = null;
    private $scripts_enqueued = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Un-escape entity-encoded shortcodes before WordPress do_shortcode runs
        add_filter('the_content', array($this, 'clean_shortcodes_in_content'), 1);

        // Register shortcodes
        add_shortcode('pdf-embedder', array($this, 'render_shortcode'));
        add_shortcode('pdf_embedder', array($this, 'render_shortcode'));

        // Handle legacy Gutenberg blocks, viewer markup, or standalone PDF links
        add_filter('the_content', array($this, 'filter_legacy_blocks'), 99);
        add_filter('render_block', array($this, 'render_legacy_block'), 10, 2);

        // Filter media sent to editor in frontend/backend TinyMCE
        add_filter('media_send_to_editor', array($this, 'media_send_to_editor'), 20, 3);

        // Enqueue scripts & styles
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('wp_enqueue_scripts', array($this, 'dequeue_legacy_scripts'), 9999);
        add_action('wp_print_scripts', array($this, 'dequeue_legacy_scripts'), 1);
        add_action('wp_print_footer_scripts', array($this, 'dequeue_legacy_scripts'), 1);

        add_action('init', array($this, 'takeover_shortcodes'), 99999);

        // REST API stream endpoint (preferred on WP Engine over admin-ajax.php)
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Ajax proxy fallback for environments with cross-origin CORS constraints (e.g. localhost)
        add_action('wp_ajax_scouting_pdf_proxy', array($this, 'proxy_pdf_stream'));
        add_action('wp_ajax_nopriv_scouting_pdf_proxy', array($this, 'proxy_pdf_stream'));
    }

    /**
     * Re-register shortcodes on late init to guarantee precedence over any legacy plugins
     */
    public function takeover_shortcodes() {
        remove_shortcode('pdf-embedder');
        remove_shortcode('pdf_embedder');
        add_shortcode('pdf-embedder', array($this, 'render_shortcode'));
        add_shortcode('pdf_embedder', array($this, 'render_shortcode'));
    }

    /**
     * Dequeue conflicting legacy PDF Embedder scripts to prevent "domainerror" / "content blocked" messages
     */
    public function dequeue_legacy_scripts() {
        wp_dequeue_script('pdfemb_embed_pdf');
        wp_dequeue_script('pdfemb_pdfjs');
        wp_dequeue_style('pdfemb_embed_pdf_css');
        wp_deregister_script('pdfemb_embed_pdf');
        wp_deregister_script('pdfemb_pdfjs');
        wp_deregister_style('pdfemb_embed_pdf_css');
    }

    /**
     * Clean entity-escaped shortcodes from frontend form submissions or wptexturize
     */
    public function clean_shortcodes_in_content($content) {
        if (empty($content)) {
            return $content;
        }

        // Replace opening brackets for pdf-embedder
        $content = str_replace(
            array('&#91;pdf-embedder', '&lsqb;pdf-embedder', '&#91;pdf_embedder', '&lsqb;pdf_embedder', '&#091;pdf-embedder', '&#x5b;pdf-embedder'),
            '[pdf-embedder',
            $content
        );

        // Replace closing brackets after pdf-embedder
        $content = preg_replace('/(\[pdf[-_]embedder[^\]]*?)(&#93;|&rsqb;|&#093;|&#x5d;)/i', '$1]', $content);

        // Fix smart quotes inside [pdf-embedder ...]
        $content = preg_replace_callback('/\[pdf[-_]embedder([^\]]*)\]/i', function($matches) {
            $cleaned = str_replace(
                array('&#8220;', '&#8221;', '&#8243;', '&quot;', '&apos;', '&#039;', '‘', '’', '“', '”'),
                '"',
                $matches[1]
            );
            return '[pdf-embedder' . $cleaned . ']';
        }, $content);

        // Convert Gutenberg block comments for legacy pdfemb/pdf-embedder-viewer
        $content = preg_replace_callback('/<!--\s*wp:pdfemb\/pdf-embedder-viewer\s*(\{.*?\})\s*-->.*?<!--\s*\/wp:pdfemb\/pdf-embedder-viewer\s*-->/is', function($matches) {
            $data = json_decode($matches[1], true);
            if (!empty($data['url'])) {
                $title = !empty($data['title']) ? ' title="' . esc_attr($data['title']) . '"' : '';
                return '[pdf-embedder url="' . esc_url($data['url']) . '"' . $title . ']';
            }
            return '';
        }, $content);

        return $content;
    }

    /**
     * Register scripts and styles
     */
    public function register_assets() {
        $is_ssl = is_ssl() 
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') 
            || (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off');

        $plugin_url = SCOUTING_PDF_PLUGIN_URL;
        $rest_url = rest_url('scouting-pdf/v1/stream');
        $ajax_url = admin_url('admin-ajax.php');
        if ($is_ssl) {
            $plugin_url = set_url_scheme($plugin_url, 'https');
            $rest_url = set_url_scheme($rest_url, 'https');
            $ajax_url = set_url_scheme($ajax_url, 'https');
        }

        wp_register_style(
            'scouting-pdf-viewer-css',
            $plugin_url . 'assets/css/scouting-pdf-viewer.css',
            array(),
            SCOUTING_PDF_VERSION
        );

        wp_register_script(
            'scouting-pdfjs-lib',
            $plugin_url . 'assets/vendor/pdf.min.js',
            array(),
            '2.16.105',
            true
        );

        wp_register_script(
            'scouting-pdfjs-worker',
            $plugin_url . 'assets/vendor/pdf.worker.min.js',
            array('scouting-pdfjs-lib'),
            '2.16.105',
            true
        );

        wp_register_script(
            'scouting-pdf-viewer-js',
            $plugin_url . 'assets/js/scouting-pdf-viewer.js',
            array('scouting-pdfjs-lib', 'scouting-pdfjs-worker'),
            SCOUTING_PDF_VERSION,
            true
        );

        wp_localize_script('scouting-pdf-viewer-js', 'ScoutingPdfConfig', array(
            'workerUrl' => $plugin_url . 'assets/vendor/pdf.worker.min.js',
            'cMapUrl'   => $plugin_url . 'assets/vendor/cmaps/',
            'restUrl'   => $rest_url,
            'ajaxUrl'   => $ajax_url
        ));
    }

    /**
     * Register REST API routes (preferred on WP Engine for lighter footprint and caching)
     */
    public function register_rest_routes() {
        register_rest_route('scouting-pdf/v1', '/stream', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'rest_proxy_stream'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'pdf_url' => array(
                    'required'          => true,
                    'sanitize_callback' => 'esc_url_raw',
                ),
            ),
        ));
    }

    /**
     * WP REST API endpoint callback
     */
    public function rest_proxy_stream($request) {
        $url = $request->get_param('pdf_url');
        $this->handle_stream_request($url);
    }

    /**
     * Ajax fallback handler for backwards compatibility
     */
    public function proxy_pdf_stream() {
        if (empty($_GET['pdf_url'])) {
            wp_die('Missing URL', 400);
        }
        $url = esc_url_raw($_GET['pdf_url']);
        $this->handle_stream_request($url);
    }

    /**
     * Safe streaming handler with WP Engine worker optimization & SSRF prevention
     */
    private function handle_stream_request($url) {
        if (empty($url)) {
            wp_die('Missing URL', 400);
        }

        $url = esc_url_raw($url);
        $parsed = parse_url($url);
        $allowed_hosts = array('storage.scoutingmemories.org', 'scoutingmemories.org', 'localhost', '127.0.0.1');

        // Security check: restrict to trusted storage domains to eliminate SSRF
        if (empty($parsed['host']) || !in_array(strtolower($parsed['host']), $allowed_hosts, true)) {
            wp_die('Host not allowed', 403);
        }

        // Clear any active output buffers to allow streaming
        while (ob_get_level()) {
            ob_end_clean();
        }

        // CORS headers - crucial for PDF.js to inspect Content-Range, Content-Length, and Accept-Ranges
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Allow-Headers: Range, Content-Type, Authorization, X-Requested-With, Origin, Accept');
        header('Access-Control-Expose-Headers: Accept-Ranges, Content-Range, Content-Length, Content-Type, ETag, Last-Modified');

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit;
        }

        // Validate PDF extension
        if (!preg_match('/\.pdf(\?.*)?$/i', $url)) {
            wp_die('Only PDF files are supported', 400);
        }

        // For streaming via cURL safely
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }

        if (isset($_SERVER['HTTP_RANGE'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Range: ' . $_SERVER['HTTP_RANGE']));
        }

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) {
            $len = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $name = strtolower(trim($parts[0]));
                if (in_array($name, array('content-type', 'content-length', 'accept-ranges', 'content-range', 'last-modified', 'etag'), true)) {
                    header(trim($parts[0]) . ': ' . trim($parts[1]), true);
                }
            } elseif (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d+)/', $header, $matches)) {
                $status_code = intval($matches[1]);
                if ($status_code !== 301 && $status_code !== 302) {
                    http_response_code($status_code);
                }
            }
            return $len;
        });

        // Set caching and CORS headers for client caching
        header('Cache-Control: public, max-age=86400, s-maxage=604800');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Expose-Headers: Accept-Ranges, Content-Range, Content-Length, Content-Type, ETag, Last-Modified');

        curl_exec($ch);
        curl_close($ch);
        exit;
    }

    /**
     * Ensure scripts are enqueued when a viewer is rendered
     */
    private function enqueue_assets() {
        if (!$this->scripts_enqueued) {
            wp_enqueue_style('scouting-pdf-viewer-css');
            wp_enqueue_script('scouting-pdfjs-lib');
            wp_enqueue_script('scouting-pdfjs-worker');
            wp_enqueue_script('scouting-pdf-viewer-js');
            $this->scripts_enqueued = true;
        }
    }

    /**
     * Shortcode handler: [pdf-embedder url="..."]
     */
    public function render_shortcode($atts, $content = null) {
        $url = '';
        $title = '';

        if (is_array($atts)) {
            if (!empty($atts['url'])) {
                $url = trim($atts['url']);
            } elseif (!empty($atts[0]) && preg_match('/\.pdf(\?.*)?$/i', $atts[0])) {
                $url = trim($atts[0]);
            }

            if (!empty($atts['title'])) {
                $title = sanitize_text_field($atts['title']);
            }
        }

        if (empty($url) && !empty($content)) {
            $content_trimmed = trim(strip_tags($content));
            if (preg_match('/^https?:\/\/[^\s]+\.pdf(\?.*)?$/i', $content_trimmed)) {
                $url = $content_trimmed;
            }
        }

        if (empty($url)) {
            return '';
        }

        return $this->build_viewer_html($url, $title);
    }

    /**
     * Filter legacy Gutenberg blocks and markup from old PDF Embedder, and standalone PDF links
     */
    public function filter_legacy_blocks($content) {
        if (empty($content)) {
            return $content;
        }

        // Pattern 1: Gutenberg block from old commercial plugin
        // <div class="wp-block-pdfemb-pdf-embedder-viewer">...<a href="..." class="pdfemb-viewer"...>Title</a>...</div>
        if (strpos($content, 'wp-block-pdfemb') !== false || strpos($content, 'pdfemb-viewer') !== false) {
            $pattern = '/<div[^>]*class=["\'][^"\']*wp-block-pdfemb-pdf-embedder-viewer[^"\']*["\'][^>]*>\s*<a[^>]*href=["\']([^"\']+\.pdf[^"\']*)["\'][^>]*>(.*?)<\/a>\s*<\/div>/is';
            $content = preg_replace_callback($pattern, function($matches) {
                $url = $matches[1];
                $title = strip_tags($matches[2]);
                return $this->build_viewer_html($url, $title);
            }, $content);

            // Fallback for standalone <a class="pdfemb-viewer" href="...pdf">
            $fallback_pattern = '/<a[^>]*class=["\'][^"\']*pdfemb-viewer[^"\']*["\'][^>]*href=["\']([^"\']+\.pdf[^"\']*)["\'][^>]*>(.*?)<\/a>/is';
            $content = preg_replace_callback($fallback_pattern, function($matches) {
                $url = $matches[1];
                $title = strip_tags($matches[2]);
                return $this->build_viewer_html($url, $title);
            }, $content);
        }

        // Pattern 2: Standalone paragraph containing only a PDF link
        // e.g. <p><a href="https://.../something.pdf">Title</a></p>
        $standalone_pattern = '/<p>\s*<a[^>]*href=["\']([^"\']+\.pdf(\?[^"\']*)?)["\'][^>]*>([^<]+)<\/a>\s*<\/p>/i';
        $content = preg_replace_callback($standalone_pattern, function($matches) {
            $url = $matches[1];
            $title = strip_tags($matches[3]);
            return $this->build_viewer_html($url, $title);
        }, $content);

        // Pattern 3: Auto-detect associated PDF for posts without explicit shortcode (e.g. multi-step form submissions)
        if (is_singular() && in_the_loop() && is_main_query() && strpos($content, 'data-pdf-viewer') === false && strpos($content, 'scouting-pdf-container') === false && strpos($content, 'pdf-embedder') === false) {
            $post_id = get_the_ID();
            if ($post_id) {
                $associated = $this->find_associated_pdf($post_id);
                if ($associated && !empty($associated['url'])) {
                    $content .= "\n\n" . $this->build_viewer_html($associated['url'], $associated['title']);
                }
            }
        }

        return $content;
    }

    /**
     * Find associated PDF for posts where shortcode was not embedded in content
     * Handles: direct media attachments, duplicate pending submissions from form glitches, and author uploads
     */
    public function find_associated_pdf($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        // 1. Direct attached media
        $attachments = get_attached_media('application/pdf', $post_id);
        if (!empty($attachments)) {
            $att = reset($attachments);
            return array(
                'url'   => wp_get_attachment_url($att->ID),
                'title' => $att->post_title
            );
        }

        // 2. Check for related/duplicate submission with same title containing a PDF shortcode
        global $wpdb;
        $related = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_title = %s AND ID != %d AND (post_content LIKE '%pdf-embedder%' OR post_content LIKE '%.pdf%') ORDER BY ID DESC LIMIT 1",
            $post->post_title,
            $post_id
        ));
        if (!empty($related)) {
            $related_content = html_entity_decode($related[0]->post_content, ENT_QUOTES);
            if (preg_match('/url=["\']([^"\']+\.pdf[^"\']*)["\']/i', $related_content, $m)) {
                $title = '';
                if (preg_match('/title=["\']([^"\']*)["\']/i', $related_content, $tm)) {
                    $title = $tm[1];
                }
                return array('url' => $m[1], 'title' => $title);
            }
        }

        // 3. Check for PDF attachment uploaded by same author around the same time (+/- 60 mins)
        if (!empty($post->post_author)) {
            $recent_pdfs = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_title, guid FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type = 'application/pdf' AND post_author = %d AND ABS(TIMESTAMPDIFF(MINUTE, post_date, %s)) <= 60 ORDER BY ID DESC LIMIT 1",
                $post->post_author,
                $post->post_date
            ));
            if (!empty($recent_pdfs)) {
                return array('url' => $recent_pdfs[0]->guid, 'title' => $recent_pdfs[0]->post_title);
            }
        }

        return null;
    }

    /**
     * Handle Gutenberg blocks from legacy PDF Embedder or modern block registrations
     */
    public function render_legacy_block($block_content, $block) {
        if (!empty($block['blockName']) && ($block['blockName'] === 'pdfemb/pdf-embedder-viewer' || $block['blockName'] === 'scouting/pdf-viewer')) {
            $url = !empty($block['attrs']['url']) ? $block['attrs']['url'] : '';
            $title = !empty($block['attrs']['title']) ? $block['attrs']['title'] : '';
            if (!empty($url)) {
                return $this->build_viewer_html($url, $title);
            }
        }
        return $block_content;
    }

    /**
     * Format media insertion in TinyMCE
     */
    public function media_send_to_editor($html, $id, $attachment) {
        if (!empty($attachment['url']) && preg_match('/\.pdf$/i', $attachment['url'])) {
            $title = !empty($attachment['post_title']) ? ' title="' . esc_attr($attachment['post_title']) . '"' : '';
            return '[pdf-embedder url="' . esc_url($attachment['url']) . '"' . $title . ']';
        }
        return $html;
    }

    /**
     * Build the viewer container HTML with modern UI toolbar and SVG icons
     */
    public function build_viewer_html($url, $title = '') {
        $this->enqueue_assets();

        // Enforce HTTPS on SSL environments and upgrade legacy HTTP storage URLs to prevent Mixed Content blocking on WP Engine SSL
        if (is_ssl() || stripos($url, 'storage.scoutingmemories.org') !== false) {
            $url = preg_replace('/^http:\/\/storage\.scoutingmemories\.org/i', 'https://storage.scoutingmemories.org', $url);
            if (is_ssl()) {
                $url = set_url_scheme($url, 'https');
            }
        }

        $esc_url = esc_url($url);
        $clean_title = !empty($title) ? esc_html($title) : esc_html(basename(parse_url($url, PHP_URL_PATH)));

        ob_start();
        ?>
        <div class="relative w-full max-w-full my-6 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden text-slate-800 font-sans focus-within:border-slate-300 focus-within:shadow-md transition-all scouting-pdf-container" data-pdf-viewer data-pdf-url="<?php echo $esc_url; ?>" tabindex="0">
            <div class="flex flex-wrap items-center justify-between bg-slate-50 px-3.5 py-2.5 border-b border-slate-200 select-none gap-2" data-pdf-role="toolbar">
                <div class="flex items-center gap-1.5" data-pdf-role="group">
                    <button type="button" class="inline-flex items-center justify-center bg-white text-slate-600 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-100 hover:border-slate-400 hover:text-slate-900 active:bg-slate-200 active:translate-y-px disabled:opacity-40 disabled:cursor-not-allowed transition-all" data-pdf-control="prev" title="Previous Page" aria-label="Previous Page">
                        <svg class="w-4 h-4 fill-current block" viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                    </button>
                    <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 px-1" data-pdf-role="page-display">
                        <input type="number" class="w-11 py-1 px-1.5 bg-white border border-slate-300 rounded-md text-slate-900 text-xs font-semibold text-center focus:outline-none focus:border-[#025600] focus:ring-2 transition-all" data-pdf-control="page-input" value="1" min="1" aria-label="Current Page">
                        <span class="text-slate-400">/</span>
                        <span class="font-medium text-slate-600" data-pdf-control="total-pages">--</span>
                    </span>
                    <button type="button" class="inline-flex items-center justify-center bg-white text-slate-600 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-100 hover:border-slate-400 hover:text-slate-900 active:bg-slate-200 active:translate-y-px disabled:opacity-40 disabled:cursor-not-allowed transition-all" data-pdf-control="next" title="Next Page" aria-label="Next Page">
                        <svg class="w-4 h-4 fill-current block" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                    </button>
                </div>

                <div class="flex items-center gap-1.5" data-pdf-role="group">
                    <button type="button" class="inline-flex items-center justify-center bg-white text-slate-600 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-100 hover:border-slate-400 hover:text-slate-900 active:bg-slate-200 active:translate-y-px disabled:opacity-40 disabled:cursor-not-allowed transition-all" data-pdf-control="zoom-out" title="Zoom Out" aria-label="Zoom Out">
                        <svg class="w-4 h-4 fill-current block" viewBox="0 0 24 24"><path d="M19 13H5v-2h14v2z"/></svg>
                    </button>
                    <span class="text-xs font-semibold text-slate-600 bg-slate-100 border border-slate-200 rounded-md px-2 py-1 min-w-[3.25rem] text-center" data-pdf-control="zoom-level">100%</span>
                    <button type="button" class="inline-flex items-center justify-center bg-white text-slate-600 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-100 hover:border-slate-400 hover:text-slate-900 active:bg-slate-200 active:translate-y-px disabled:opacity-40 disabled:cursor-not-allowed transition-all" data-pdf-control="zoom-in" title="Zoom In" aria-label="Zoom In">
                        <svg class="w-4 h-4 fill-current block" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    </button>
                    <button type="button" class="inline-flex items-center justify-center bg-white text-slate-600 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold uppercase tracking-wider shadow-sm hover:bg-slate-100 hover:border-slate-400 hover:text-slate-900 active:bg-slate-200 active:translate-y-px transition-all" data-pdf-control="zoom-fit" title="Fit to Width">
                        Fit
                    </button>
                </div>

                <div class="flex items-center gap-1.5" data-pdf-role="group">
                    <a href="<?php echo $esc_url; ?>" download="<?php echo esc_attr($clean_title); ?>" class="inline-flex items-center justify-center bg-[#025600] text-white border border-[#025600] rounded-lg px-2.5 py-1.5 text-xs font-semibold shadow-sm hover:bg-[#013e00] hover:text-white active:translate-y-px transition-all" data-pdf-control="download" title="Download PDF" target="_blank">
                        <svg class="w-4 h-4 fill-white block" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                    </a>
                    <button type="button" class="inline-flex items-center justify-center bg-white text-slate-600 border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-100 hover:border-slate-400 hover:text-slate-900 active:bg-slate-200 active:translate-y-px transition-all" data-pdf-control="fullscreen" title="Toggle Fullscreen" aria-label="Toggle Fullscreen">
                        <svg class="w-4 h-4 fill-current block" viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
                    </button>
                </div>
            </div>

            <div class="relative w-full min-h-[480px] max-h-[80vh] overflow-auto flex justify-center items-start bg-slate-100 p-6 box-border" data-pdf-role="viewport">
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 flex flex-col items-center gap-3 text-slate-500 text-sm font-medium z-10" data-pdf-role="loading">
                    <div class="w-9 h-9 border-[3px] border-slate-200 border-t-[#025600] rounded-full animate-spin"></div>
                    <div class="text-slate-500 text-sm font-medium">Loading document...</div>
                </div>
                <div class="relative hidden bg-white rounded shadow-xl ring-1 ring-black/5 leading-none transition-opacity duration-200" data-pdf-role="canvas-wrapper">
                    <canvas class="block max-w-full h-auto rounded" data-pdf-role="canvas"></canvas>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize plugin
Scouting_PDF_Embedder::get_instance();
