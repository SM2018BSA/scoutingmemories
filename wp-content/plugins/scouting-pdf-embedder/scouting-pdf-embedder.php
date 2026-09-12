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

define('SCOUTING_PDF_VERSION', '1.0.0');
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

        // REST API stream endpoint (preferred on WP Engine over admin-ajax.php)
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Ajax proxy fallback for environments with cross-origin CORS constraints (e.g. localhost)
        add_action('wp_ajax_scouting_pdf_proxy', array($this, 'proxy_pdf_stream'));
        add_action('wp_ajax_nopriv_scouting_pdf_proxy', array($this, 'proxy_pdf_stream'));
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
        wp_register_style(
            'scouting-pdf-viewer-css',
            SCOUTING_PDF_PLUGIN_URL . 'assets/css/scouting-pdf-viewer.css',
            array(),
            SCOUTING_PDF_VERSION
        );

        wp_register_script(
            'scouting-pdfjs-lib',
            SCOUTING_PDF_PLUGIN_URL . 'assets/vendor/pdf.min.js',
            array(),
            '2.16.105',
            true
        );

        wp_register_script(
            'scouting-pdf-viewer-js',
            SCOUTING_PDF_PLUGIN_URL . 'assets/js/scouting-pdf-viewer.js',
            array('scouting-pdfjs-lib'),
            SCOUTING_PDF_VERSION,
            true
        );

        wp_localize_script('scouting-pdf-viewer-js', 'ScoutingPdfConfig', array(
            'workerUrl' => SCOUTING_PDF_PLUGIN_URL . 'assets/vendor/pdf.worker.min.js',
            'cMapUrl'   => SCOUTING_PDF_PLUGIN_URL . 'assets/vendor/cmaps/',
            'restUrl'   => rest_url('scouting-pdf/v1/stream'),
            'ajaxUrl'   => admin_url('admin-ajax.php')
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

        // Validate PDF extension
        if (!preg_match('/\.pdf(\?.*)?$/i', $url)) {
            wp_die('Only PDF files are supported', 400);
        }

        // On WP Engine production, conserve PHP-FPM workers by redirecting to Google Cloud Storage CDN directly
        $is_wpe_production = (!empty($_SERVER['IS_WPE']) || (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'scoutingmemories.org') !== false || strpos($_SERVER['HTTP_HOST'], 'wpengine.com') !== false)));
        if ($is_wpe_production && stripos($url, 'storage.scoutingmemories.org') !== false) {
            $https_url = preg_replace('/^http:\/\//i', 'https://', $url);
            wp_redirect($https_url, 302);
            exit;
        }

        // For local development or non-production cross-origin testing, stream via cURL safely
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        if (isset($_SERVER['HTTP_RANGE'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Range: ' . $_SERVER['HTTP_RANGE']));
        }

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) {
            $len = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $name = strtolower(trim($parts[0]));
                if (in_array($name, array('content-type', 'content-length', 'accept-ranges', 'content-range', 'last-modified', 'etag'), true)) {
                    header(trim($parts[0]) . ': ' . trim($parts[1]));
                }
            } elseif (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d+)/', $header, $matches)) {
                http_response_code(intval($matches[1]));
            }
            return $len;
        });

        // Set caching and CORS headers for EverCache and client caching
        header('Cache-Control: public, max-age=86400, s-maxage=604800');
        header('Access-Control-Allow-Origin: *');
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
        $clean_title = !empty($title) ? esc_html($title) : esc_html(basename(parse_url($url, PHP_URL_PATH)));

        ob_start();
        ?>
        <div class="scouting-pdf-container" data-pdf-url="<?php echo $esc_url; ?>" tabindex="0">
            <div class="scouting-pdf-toolbar">
                <div class="scouting-pdf-group">
                    <button type="button" class="scouting-pdf-btn scouting-pdf-prev" title="Previous Page" aria-label="Previous Page">
                        <svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                    </button>
                    <span class="scouting-pdf-page-display">
                        <input type="number" class="scouting-pdf-page-input" value="1" min="1" aria-label="Current Page">
                        <span>/</span>
                        <span class="scouting-pdf-total-pages">--</span>
                    </span>
                    <button type="button" class="scouting-pdf-btn scouting-pdf-next" title="Next Page" aria-label="Next Page">
                        <svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                    </button>
                </div>

                <div class="scouting-pdf-group">
                    <button type="button" class="scouting-pdf-btn scouting-pdf-zoom-out" title="Zoom Out" aria-label="Zoom Out">
                        <svg viewBox="0 0 24 24"><path d="M19 13H5v-2h14v2z"/></svg>
                    </button>
                    <span class="scouting-pdf-zoom-level">100%</span>
                    <button type="button" class="scouting-pdf-btn scouting-pdf-zoom-in" title="Zoom In" aria-label="Zoom In">
                        <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    </button>
                    <button type="button" class="scouting-pdf-btn scouting-pdf-zoom-fit" title="Fit to Width">
                        Fit
                    </button>
                </div>

                <div class="scouting-pdf-group">
                    <a href="<?php echo $esc_url; ?>" download="<?php echo esc_attr($clean_title); ?>" class="scouting-pdf-btn scouting-pdf-download" title="Download PDF" target="_blank">
                        <svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                    </a>
                    <button type="button" class="scouting-pdf-btn scouting-pdf-fullscreen" title="Toggle Fullscreen" aria-label="Toggle Fullscreen">
                        <svg viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
                    </button>
                </div>
            </div>

            <div class="scouting-pdf-viewport">
                <div class="scouting-pdf-loading">
                    <div class="scouting-pdf-spinner"></div>
                    <div>Loading document...</div>
                </div>
                <div class="scouting-pdf-canvas-wrapper">
                    <canvas class="scouting-pdf-canvas"></canvas>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize plugin
Scouting_PDF_Embedder::get_instance();
