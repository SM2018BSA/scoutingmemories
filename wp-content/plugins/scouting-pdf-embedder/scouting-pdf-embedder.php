<?php
/**
 * Plugin Name: Scouting PDF Embedder
 * Plugin URI: https://scoutingmemories.org/
 * Description: High-performance, modern PDF viewer for Scouting Memories. Seamlessly replaces commercial PDF Embedder, supporting legacy shortcodes, Gutenberg blocks, and frontend form submissions.
 * Version: 1.4.0
 * Author: Roger Ellis / Scouting Memories
 * License: GPL-2.0+
 * Text Domain: scouting-pdf-embedder
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SCOUTING_PDF_VERSION', '1.4.0');
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

        // The worker stays registered for back-compat but is no longer printed as a page script:
        // PDF.js loads it from workerSrc as a real Web Worker (off the main thread) and falls
        // back to an in-page worker on its own if the browser blocks that.
        wp_register_script(
            'scouting-pdf-viewer-js',
            $plugin_url . 'assets/js/scouting-pdf-viewer.js',
            array('scouting-pdfjs-lib'),
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

        return $content;
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
        $raw_title = !empty($title) ? $title : basename((string) parse_url($url, PHP_URL_PATH));
        $clean_title = esc_html($raw_title);

        // Bootstrap 5 classes from the site theme; green highlight comes from scouting-pdf-viewer.css.
        // Each control has a visible label on wide screens and a Bootstrap tooltip (initialized
        // in scouting-pdf-viewer.js) explaining what it does, with its keyboard shortcut.
        $btn = 'btn btn-sm btn-outline-secondary';
        $label = 'd-none d-xl-inline ms-1';

        ob_start();
        ?>
        <div class="card my-4 shadow-sm overflow-hidden scouting-pdf-container" data-pdf-viewer data-pdf-state="loading" data-pdf-url="<?php echo $esc_url; ?>" tabindex="0" role="region" aria-label="<?php echo esc_attr(sprintf(__('PDF viewer: %s', 'scouting-pdf-embedder'), $raw_title)); ?>">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 user-select-none" data-pdf-role="toolbar">
                <div class="d-flex align-items-center gap-1" data-pdf-role="group">
                    <button type="button" class="<?php echo $btn; ?>" data-pdf-control="prev" data-bs-toggle="tooltip" data-bs-title="Go to the previous page (Left arrow)" aria-label="Previous page">
                        <i class="bi bi-chevron-left" aria-hidden="true"></i><span class="<?php echo $label; ?>">Previous</span>
                    </button>
                    <span class="d-inline-flex align-items-center gap-1 small text-muted" data-pdf-role="page-display">
                        <input type="number" class="form-control form-control-sm text-center" data-pdf-control="page-input" value="1" min="1" aria-label="Current page" data-bs-toggle="tooltip" data-bs-title="Type a page number and press Enter to jump to it">
                        <span>of</span>
                        <span data-pdf-control="total-pages">--</span>
                    </span>
                    <button type="button" class="<?php echo $btn; ?>" data-pdf-control="next" data-bs-toggle="tooltip" data-bs-title="Go to the next page (Right arrow)" aria-label="Next page">
                        <span class="d-none d-xl-inline me-1">Next</span><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="d-flex align-items-center gap-1" data-pdf-role="group">
                    <button type="button" class="<?php echo $btn; ?>" data-pdf-control="zoom-out" data-bs-toggle="tooltip" data-bs-title="Make the pages smaller ( - )" aria-label="Zoom out">
                        <i class="bi bi-zoom-out" aria-hidden="true"></i><span class="<?php echo $label; ?>">Zoom out</span>
                    </button>
                    <span class="small text-muted text-center" data-pdf-control="zoom-level" tabindex="0" data-bs-toggle="tooltip" data-bs-title="Current zoom. Hold Ctrl and scroll the mouse wheel to zoom in on a spot">100%</span>
                    <button type="button" class="<?php echo $btn; ?>" data-pdf-control="zoom-in" data-bs-toggle="tooltip" data-bs-title="Make the pages larger to read small print ( + )" aria-label="Zoom in">
                        <i class="bi bi-zoom-in" aria-hidden="true"></i><span class="<?php echo $label; ?>">Zoom in</span>
                    </button>
                    <button type="button" class="<?php echo $btn; ?> active" data-pdf-control="zoom-fit" data-bs-toggle="tooltip" data-bs-title="Fit the page width to the viewer ( 0 )" aria-label="Fit width" aria-pressed="true">
                        <i class="bi bi-arrow-left-right" aria-hidden="true"></i><span class="ms-1">Fit width</span>
                    </button>
                    <button type="button" class="<?php echo $btn; ?>" data-pdf-control="zoom-page" data-bs-toggle="tooltip" data-bs-title="Show one whole page at a time" aria-label="Fit page" aria-pressed="false">
                        <i class="bi bi-file-earmark" aria-hidden="true"></i><span class="ms-1">Fit page</span>
                    </button>
                </div>

                <div class="d-flex align-items-center gap-1" data-pdf-role="group">
                    <button type="button" class="<?php echo $btn; ?>" data-pdf-control="rotate" data-bs-toggle="tooltip" data-bs-title="Rotate the pages 90° clockwise, for sideways scans ( R )" aria-label="Rotate">
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i><span class="<?php echo $label; ?>">Rotate</span>
                    </button>
                    <button type="button" class="<?php echo $btn; ?>" data-pdf-control="fullscreen" data-bs-toggle="tooltip" data-bs-title="Fill the whole screen with the document (Esc to exit)" aria-label="Fullscreen">
                        <i class="bi bi-arrows-fullscreen" aria-hidden="true"></i><span class="<?php echo $label; ?>">Fullscreen</span>
                    </button>
                </div>
            </div>

            <div class="position-relative overflow-auto d-flex align-items-start bg-light" data-pdf-role="viewport">
                <div class="position-absolute top-50 start-50 translate-middle d-flex flex-column align-items-center gap-2 text-muted" data-pdf-role="loading" role="status" aria-live="polite">
                    <div class="spinner-border sm_green_color" aria-hidden="true"></div>
                    <div class="small">Loading document... <span data-pdf-role="progress"></span></div>
                </div>
                <div data-pdf-role="pages"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize plugin
Scouting_PDF_Embedder::get_instance();
