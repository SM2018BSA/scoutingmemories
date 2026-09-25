<?php
/**
 * Plugin Name: Scouting PDF Embedder
 * Plugin URI: https://scoutingmemories.org/
 * Description: PDF viewer for Scouting Memories, built for research: search inside documents, selectable text, printed page numbers, page links, citations, thumbnails, image adjustments for faded scans, print and download. Replaces the commercial PDF Embedder and keeps its shortcodes and blocks working.
 * Version: 2.0.0
 * Author: Roger Ellis / Scouting Memories
 * License: GPL-2.0+
 * Text Domain: scouting-pdf-embedder
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SCOUTING_PDF_VERSION', '2.0.0');
define('SCOUTING_PDFJS_VERSION', '6.3.289');
define('SCOUTING_PDF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCOUTING_PDF_PLUGIN_URL', plugin_dir_url(__FILE__));

class Scouting_PDF_Embedder {

    private static $instance = null;
    private $scripts_enqueued = false;
    private $viewer_count = 0;

    // Archive fields (ACF) that go into citations and the document info panel
    const CITATION_FIELDS = array(
        'identifier'           => 'identifier',
        'dateOriginal'         => 'date_of_original',
        'dateDigital'          => 'date_of_digital',
        'publisher'            => 'publisher_of_digital',
        'location'             => 'meta_location',
        'subject'              => 'meta_subject',
        'physicalDescription'  => 'meta_physical_description',
    );

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

        // Citation tags that Zotero, Mendeley and Google Scholar read from the page head
        add_action('wp_head', array($this, 'print_citation_meta_tags'), 5);

        // Same-origin stream endpoint, used when the storage server blocks a direct load
        add_action('rest_api_init', array($this, 'register_rest_routes'));
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
     * Clean entity-escaped shortcodes left in older posts by wptexturize or form plugins.
     * Safe to keep: the viewer only ever embeds PDFs from the allowed hosts.
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

    // ---- Allowed hosts ------------------------------------------------------

    /**
     * Hosts whose PDFs may be shown in the viewer. A PDF anywhere else is shown as a plain
     * link, so a post can't make visitors' browsers parse a file from an unknown server.
     * Add hosts with the `scouting_pdf_allowed_hosts` filter.
     */
    public static function allowed_embed_hosts() {
        $hosts = self::proxy_hosts();
        foreach (array(home_url(), site_url()) as $site) {
            $host = wp_parse_url($site, PHP_URL_HOST);
            if ($host) {
                $hosts[] = $host;
            }
        }
        $uploads = wp_get_upload_dir();
        if (!empty($uploads['baseurl'])) {
            $host = wp_parse_url($uploads['baseurl'], PHP_URL_HOST);
            if ($host) {
                $hosts[] = $host;
            }
        }
        $hosts = apply_filters('scouting_pdf_allowed_hosts', $hosts);
        return array_values(array_unique(array_map('strtolower', (array) $hosts)));
    }

    /**
     * Remote storage the stream endpoint may fetch from. Deliberately never includes
     * localhost or this server itself. Filter: `scouting_pdf_proxy_hosts`.
     */
    public static function proxy_hosts() {
        $hosts = apply_filters('scouting_pdf_proxy_hosts', array(
            'storage.scoutingmemories.org',
            'scoutingmemories.org',
            'www.scoutingmemories.org',
        ));
        return array_values(array_unique(array_map('strtolower', (array) $hosts)));
    }

    /**
     * True for an http(s) link to a .pdf on an allowed host
     */
    public static function is_embeddable_url($url) {
        $parts = wp_parse_url($url);
        if (empty($parts['host']) || empty($parts['scheme']) || empty($parts['path'])) {
            return false;
        }
        if (!in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        if (!preg_match('/\.pdf$/i', $parts['path'])) {
            return false;
        }
        return in_array(strtolower($parts['host']), self::allowed_embed_hosts(), true);
    }

    // ---- Assets -------------------------------------------------------------

    /**
     * Register scripts and styles. PDF.js itself is an ES module that the viewer script
     * imports on demand, so pages without a document never download it.
     */
    public function register_assets() {
        $is_ssl = is_ssl()
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off');

        $plugin_url = SCOUTING_PDF_PLUGIN_URL;
        $rest_url = rest_url('scouting-pdf/v1/stream');
        if ($is_ssl) {
            $plugin_url = set_url_scheme($plugin_url, 'https');
            $rest_url = set_url_scheme($rest_url, 'https');
        }
        $pdfjs = $plugin_url . 'assets/vendor/pdfjs/';
        $ver = '?ver=' . SCOUTING_PDFJS_VERSION;

        wp_register_style(
            'scouting-pdf-viewer-css',
            $plugin_url . 'assets/css/scouting-pdf-viewer.css',
            array(),
            SCOUTING_PDF_VERSION
        );

        wp_register_script(
            'scouting-pdf-viewer-js',
            $plugin_url . 'assets/js/scouting-pdf-viewer.js',
            array(),
            SCOUTING_PDF_VERSION,
            true
        );

        $config = array(
            'libUrl'              => $pdfjs . 'pdf.min.js' . $ver,
            'workerUrl'           => $pdfjs . 'pdf.worker.min.js' . $ver,
            'cMapUrl'             => $pdfjs . 'cmaps/',
            'standardFontDataUrl' => $pdfjs . 'standard_fonts/',
            'wasmUrl'             => $pdfjs . 'wasm/',
            'iccUrl'              => $pdfjs . 'iccs/',
            'restUrl'             => $rest_url,
            'siteName'            => wp_strip_all_tags(get_bloginfo('name')),
        );
        wp_add_inline_script('scouting-pdf-viewer-js', 'window.ScoutingPdfConfig = ' . wp_json_encode($config) . ';', 'before');
    }

    /**
     * Ensure scripts are enqueued when a viewer is rendered
     */
    private function enqueue_assets() {
        if (!$this->scripts_enqueued) {
            wp_enqueue_style('scouting-pdf-viewer-css');
            wp_enqueue_script('scouting-pdf-viewer-js');
            $this->scripts_enqueued = true;
        }
    }

    // ---- Stream endpoint ----------------------------------------------------

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
                    'required' => true,
                    'type'     => 'string',
                ),
            ),
        ));
    }

    /**
     * WP REST API endpoint callback
     */
    public function rest_proxy_stream($request) {
        $this->handle_stream_request((string) $request->get_param('pdf_url'));
    }

    /**
     * Check a URL the stream endpoint was asked to fetch. Returns the https URL to fetch,
     * or a WP_Error. Only .pdf files on the proxy hosts, on the default ports, qualify.
     */
    public static function validate_proxy_url($url) {
        $url = trim((string) $url);
        // parse_url() quietly rewrites control characters, so refuse them (and spaces) up front
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return new WP_Error('bad_url', 'Invalid URL', array('status' => 400));
        }
        $parts = wp_parse_url($url);
        if (!$parts || empty($parts['host']) || empty($parts['scheme']) || empty($parts['path'])) {
            return new WP_Error('bad_url', 'Invalid URL', array('status' => 400));
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        if (!in_array($scheme, array('http', 'https'), true)) {
            return new WP_Error('bad_scheme', 'Only http and https are supported', array('status' => 400));
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return new WP_Error('bad_url', 'Credentials are not allowed', array('status' => 400));
        }
        if (isset($parts['port']) && !in_array((int) $parts['port'], array(80, 443), true)) {
            return new WP_Error('bad_port', 'Port not allowed', array('status' => 403));
        }
        if (!in_array($host, self::proxy_hosts(), true)) {
            return new WP_Error('bad_host', 'Host not allowed', array('status' => 403));
        }
        if (!preg_match('/\.pdf$/i', $parts['path'])) {
            return new WP_Error('not_pdf', 'Only PDF files are supported', array('status' => 400));
        }
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        // Always fetch over TLS so the file can't be swapped in transit
        return 'https://' . $host . $parts['path'] . $query;
    }

    /**
     * Resolve a host and refuse private, loopback and reserved addresses, so a DNS change
     * can never point the endpoint at the server's own network.
     */
    public static function resolve_public_ip($host) {
        $ips = gethostbynamel($host);
        if (empty($ips)) {
            return false;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return $ips[0];
    }

    private function stream_error($status, $message) {
        status_header($status);
        header_remove('Access-Control-Allow-Origin');
        header_remove('Access-Control-Allow-Credentials');
        header('Content-Type: text/plain; charset=utf-8', true);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        echo $message;
        exit;
    }

    /**
     * Stream a PDF from storage through this site. Hardened against SSRF: fixed host list,
     * public IPs only (pinned for the request), TLS verified, redirects re-checked, and the
     * response is always served as an inert PDF that can't run script on this origin.
     */
    private function handle_stream_request($url) {
        $target = self::validate_proxy_url($url);
        if (is_wp_error($target)) {
            $data = $target->get_error_data();
            $this->stream_error(isset($data['status']) ? $data['status'] : 400, $target->get_error_message());
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        $range = '';
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=\d*-\d*(,\s*\d*-\d*){0,4}$/', $_SERVER['HTTP_RANGE'])) {
            $range = $_SERVER['HTTP_RANGE'];
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        for ($hop = 0; $hop <= 3; $hop++) {
            $host = wp_parse_url($target, PHP_URL_HOST);
            $ip = self::resolve_public_ip($host);
            if (!$ip) {
                $this->stream_error(502, 'Storage host could not be resolved');
            }

            $state = array('status' => 0, 'headers' => array(), 'location' => '', 'sent' => false);
            $filename = sanitize_file_name(basename((string) wp_parse_url($target, PHP_URL_PATH)));
            $send_headers = function() use (&$state, $filename) {
                $state['sent'] = true;
                status_header($state['status']);
                header_remove('Access-Control-Allow-Origin');
                header_remove('Access-Control-Allow-Credentials');
                header('Content-Type: application/pdf', true);
                header('X-Content-Type-Options: nosniff');
                header("Content-Security-Policy: default-src 'none'; sandbox");
                header('Content-Disposition: inline; filename="' . $filename . '"');
                header('Cross-Origin-Resource-Policy: same-origin');
                header('Referrer-Policy: no-referrer');
                header('Cache-Control: public, max-age=86400, s-maxage=604800');
                foreach ($state['headers'] as $name => $value) {
                    header($name . ': ' . $value, true);
                }
            };

            $ch = curl_init($target);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_RESOLVE        => array($host . ':443:' . $ip),
                CURLOPT_USERAGENT      => 'ScoutingPdfEmbedder/' . SCOUTING_PDF_VERSION,
                CURLOPT_NOBODY         => ($method === 'HEAD'),
                CURLOPT_HTTPHEADER     => $range ? array('Range: ' . $range) : array(),
            ));
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $line) use (&$state) {
                if (preg_match('/^HTTP\/[\d.]+\s+(\d{3})/', $line, $m)) {
                    // A new status line (e.g. after "100 Continue") starts a fresh header set
                    $state['status'] = (int) $m[1];
                    $state['headers'] = array();
                    $state['location'] = '';
                    return strlen($line);
                }
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $value = trim($parts[1]);
                    if ($name === 'location') {
                        $state['location'] = $value;
                    } elseif (in_array($name, array('content-length', 'content-range', 'accept-ranges', 'etag', 'last-modified'), true)
                        && !preg_match('/[\r\n]/', $value)) {
                        $state['headers'][ucwords($name, '-')] = $value;
                    }
                }
                return strlen($line);
            });
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$state, $send_headers) {
                // Redirect and error bodies are never passed on
                if ($state['status'] < 200 || $state['status'] >= 300) {
                    return strlen($chunk);
                }
                if (!$state['sent']) {
                    $send_headers();
                }
                echo $chunk;
                flush();
                return strlen($chunk);
            });

            $ok = curl_exec($ch);
            curl_close($ch);

            if ($state['sent']) {
                exit;
            }
            $status = $state['status'];
            if (in_array($status, array(301, 302, 303, 307, 308), true) && $state['location'] !== '') {
                $next = WP_Http::make_absolute_url($state['location'], $target);
                $checked = self::validate_proxy_url($next);
                if (is_wp_error($checked)) {
                    $this->stream_error(502, 'Storage redirected to a location that is not allowed');
                }
                $target = $checked;
                continue;
            }
            if (!$ok) {
                $this->stream_error(502, 'Could not reach storage');
            }
            if (in_array($status, array(200, 206, 304), true)) {
                // Headers only (HEAD request, empty file, or not modified)
                $send_headers();
                exit;
            }
            if ($status === 416) {
                $this->stream_error(416, 'Requested range not satisfiable');
            }
            $this->stream_error(($status === 403 || $status === 404) ? 404 : 502, 'Document not available');
        }
        $this->stream_error(502, 'Too many redirects');
    }

    // ---- Content ------------------------------------------------------------

    /**
     * Shortcode handler: [pdf-embedder url="..." title="..." page="3" download="no"]
     */
    public function render_shortcode($atts, $content = null) {
        $url = '';
        $title = '';
        $options = array();

        if (is_array($atts)) {
            if (!empty($atts['url'])) {
                $url = trim($atts['url']);
            } elseif (!empty($atts[0]) && preg_match('/\.pdf(\?.*)?$/i', $atts[0])) {
                $url = trim($atts[0]);
            }

            if (!empty($atts['title'])) {
                $title = sanitize_text_field($atts['title']);
            }
            if (!empty($atts['page'])) {
                $options['page'] = absint($atts['page']);
            }
            if (isset($atts['download'])) {
                $options['download'] = !in_array(strtolower((string) $atts['download']), array('no', 'off', 'false', '0'), true);
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

        return $this->build_viewer_html($url, $title, $options);
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
                return $this->build_viewer_html(html_entity_decode($matches[1]), strip_tags($matches[2]));
            }, $content);

            // Fallback for standalone <a class="pdfemb-viewer" href="...pdf">
            $fallback_pattern = '/<a[^>]*class=["\'][^"\']*pdfemb-viewer[^"\']*["\'][^>]*href=["\']([^"\']+\.pdf[^"\']*)["\'][^>]*>(.*?)<\/a>/is';
            $content = preg_replace_callback($fallback_pattern, function($matches) {
                return $this->build_viewer_html(html_entity_decode($matches[1]), strip_tags($matches[2]));
            }, $content);
        }

        // Pattern 2: Standalone paragraph containing only a PDF link
        // e.g. <p><a href="https://.../something.pdf">Title</a></p>
        $standalone_pattern = '/<p>\s*<a[^>]*href=["\']([^"\']+\.pdf(\?[^"\']*)?)["\'][^>]*>([^<]+)<\/a>\s*<\/p>/i';
        $content = preg_replace_callback($standalone_pattern, function($matches) {
            // Links to other sites stay links
            if (!self::is_embeddable_url(html_entity_decode($matches[1]))) {
                return $matches[0];
            }
            return $this->build_viewer_html(html_entity_decode($matches[1]), strip_tags($matches[3]));
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

    // ---- Citations ----------------------------------------------------------

    /**
     * Read an archive field: ACF when it's active, plain post meta otherwise
     */
    private static function archive_field($post_id, $key) {
        $value = function_exists('get_field') ? get_field($key, $post_id) : get_post_meta($post_id, $key, true);
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map(function($v) {
                return is_scalar($v) ? (string) $v : '';
            }, $value)));
        }
        return is_scalar($value) ? trim(sanitize_text_field(wp_strip_all_tags((string) $value))) : '';
    }

    private static function plain_title($post_id) {
        return trim(wp_strip_all_tags(html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8')));
    }

    /**
     * What the viewer needs to build citations for the current post
     */
    public static function citation_data($title = '') {
        $post_id = get_the_ID();
        $data = array(
            'title'     => $title,
            'postTitle' => $post_id ? self::plain_title($post_id) : '',
            'permalink' => $post_id ? get_permalink($post_id) : '',
            'site'      => wp_strip_all_tags(html_entity_decode(get_bloginfo('name'), ENT_QUOTES, 'UTF-8')),
        );
        if ($post_id) {
            foreach (self::CITATION_FIELDS as $name => $key) {
                $value = self::archive_field($post_id, $key);
                if ($value !== '') {
                    $data[$name] = $value;
                }
            }
        }
        return array_filter($data, 'strlen');
    }

    /**
     * First PDF shown in a post's content, if it's one the viewer would embed
     */
    public static function first_pdf_in_content($content) {
        if (preg_match_all('/(?:url=["\']|href=["\']|\[pdf[-_]embedder\s+)(https?:\/\/[^"\'\s\]]+?\.pdf)(?=["\'\s\]?])/i', (string) $content, $m)) {
            foreach ($m[1] as $url) {
                $url = html_entity_decode($url);
                if (self::is_embeddable_url($url)) {
                    return $url;
                }
            }
        }
        return '';
    }

    /**
     * Highwire Press tags, so reference managers (Zotero, Mendeley) save the post with
     * its title, date, publisher and PDF in one click
     */
    public function print_citation_meta_tags() {
        if (!is_singular()) {
            return;
        }
        $post = get_queried_object();
        if (!$post || empty($post->post_content)) {
            return;
        }
        $content = $this->clean_shortcodes_in_content($post->post_content);
        $pdf = self::first_pdf_in_content($content);
        if (!$pdf) {
            return;
        }
        $pdf = preg_replace('/^http:\/\/storage\.scoutingmemories\.org/i', 'https://storage.scoutingmemories.org', $pdf);

        $tags = array(
            'citation_title'            => self::plain_title($post->ID),
            'citation_publisher'        => self::archive_field($post->ID, 'publisher_of_digital'),
            'citation_date'             => self::archive_field($post->ID, 'date_of_original'),
            'citation_online_date'      => self::archive_field($post->ID, 'date_of_digital'),
            'citation_pdf_url'          => $pdf,
            'citation_abstract_html_url'=> get_permalink($post->ID),
            'DC.identifier'             => self::archive_field($post->ID, 'identifier'),
            'DC.coverage'               => self::archive_field($post->ID, 'meta_location'),
        );
        if ($tags['citation_publisher'] === '') {
            $tags['citation_publisher'] = wp_strip_all_tags(html_entity_decode(get_bloginfo('name'), ENT_QUOTES, 'UTF-8'));
        }
        echo "\n<!-- Scouting PDF Embedder: citation metadata -->\n";
        foreach ($tags as $name => $value) {
            if ($value !== '') {
                printf('<meta name="%s" content="%s">' . "\n", esc_attr($name), esc_attr($value));
            }
        }
    }

    // ---- Viewer markup ------------------------------------------------------

    private function button($control, $icon, $label, $tip, $attrs = '', $label_class = 'scouting-pdf-btn-label') {
        return sprintf(
            '<button type="button" class="btn btn-sm btn-outline-secondary" data-pdf-control="%s" data-bs-toggle="tooltip" data-bs-title="%s" aria-label="%s"%s><i class="bi %s" aria-hidden="true"></i>%s</button>',
            esc_attr($control),
            esc_attr($tip),
            esc_attr($label),
            $attrs,
            esc_attr($icon),
            $label_class ? '<span class="' . esc_attr($label_class) . '">' . esc_html($label) . '</span>' : ''
        );
    }

    private function menu_item($control, $icon, $label, $attrs = '') {
        return sprintf(
            '<button type="button" class="scouting-pdf-menu-item d-flex align-items-center gap-2 w-100" role="menuitem" data-pdf-control="%s"%s><i class="bi %s" aria-hidden="true"></i><span>%s</span></button>',
            esc_attr($control),
            $attrs,
            esc_attr($icon),
            esc_html($label)
        );
    }

    /**
     * Build the viewer container HTML
     */
    public function build_viewer_html($url, $title = '', $options = array()) {
        // Enforce HTTPS on SSL environments and upgrade legacy HTTP storage URLs to prevent Mixed Content blocking on WP Engine SSL
        if (is_ssl() || stripos($url, 'storage.scoutingmemories.org') !== false) {
            $url = preg_replace('/^http:\/\/storage\.scoutingmemories\.org/i', 'https://storage.scoutingmemories.org', $url);
            if (is_ssl()) {
                $url = set_url_scheme($url, 'https');
            }
        }

        $raw_title = !empty($title) ? $title : basename((string) wp_parse_url($url, PHP_URL_PATH));

        // PDFs from anywhere else are linked, not opened in visitors' browsers
        if (!self::is_embeddable_url($url)) {
            if (esc_url($url, array('http', 'https')) === '') {
                return '';
            }
            return sprintf(
                '<p class="scouting-pdf-link"><a href="%s" rel="noopener nofollow">%s</a></p>',
                esc_url($url, array('http', 'https')),
                esc_html($raw_title)
            );
        }

        $this->enqueue_assets();
        $this->viewer_count++;

        $allow_download = isset($options['download']) ? $options['download'] : true;
        $allow_download = (bool) apply_filters('scouting_pdf_allow_download', $allow_download, $url);
        $start_page = !empty($options['page']) ? (int) $options['page'] : 1;
        $cite = self::citation_data(!empty($title) ? $title : '');

        $hidden_if_no_download = $allow_download ? '' : ' hidden';

        ob_start();
        ?>
        <div class="card my-4 shadow-sm scouting-pdf-container" id="scouting-pdf-<?php echo (int) $this->viewer_count; ?>" data-pdf-viewer data-pdf-index="<?php echo (int) $this->viewer_count; ?>" data-pdf-state="loading" data-pdf-url="<?php echo esc_url($url); ?>" data-pdf-title="<?php echo esc_attr($raw_title); ?>" data-pdf-start-page="<?php echo (int) $start_page; ?>" data-pdf-download="<?php echo $allow_download ? '1' : '0'; ?>" data-pdf-cite="<?php echo esc_attr(wp_json_encode($cite)); ?>" tabindex="0" role="region" aria-label="<?php echo esc_attr(sprintf(__('PDF viewer: %s', 'scouting-pdf-embedder'), $raw_title)); ?>">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 user-select-none" data-pdf-role="toolbar">
                <div class="d-flex align-items-center gap-1" data-pdf-role="group">
                    <?php echo $this->button('sidebar', 'bi-layout-sidebar', 'Pages', 'Show page thumbnails, contents, search results and document details', ' aria-pressed="true"'); ?>
                </div>

                <div class="d-flex align-items-center gap-1" data-pdf-role="group">
                    <?php echo $this->button('zoom-out', 'bi-zoom-out', 'Zoom out', 'Make the pages smaller ( - )'); ?>
                    <span class="small text-muted text-center" data-pdf-control="zoom-level" tabindex="0" data-bs-toggle="tooltip" data-bs-title="Current zoom. Hold Ctrl and scroll the mouse wheel to zoom in on a spot">100%</span>
                    <?php echo $this->button('zoom-in', 'bi-zoom-in', 'Zoom in', 'Make the pages larger to read small print ( + )'); ?>
                    <?php echo $this->button('zoom-fit', 'bi-arrow-left-right', 'Fit width', 'Fit the page width to the viewer ( 0 )', ' aria-pressed="false"'); ?>
                    <?php echo $this->button('zoom-page', 'bi-file-earmark', 'Fit page', 'Show one whole page at a time', ' aria-pressed="true"'); ?>
                </div>

                <div class="d-flex align-items-center gap-1" data-pdf-role="group">
                    <?php echo $this->button('adjust', 'bi-sliders', 'Adjust', 'Brighten, darken or invert faded scans', ' aria-pressed="false"'); ?>
                    <?php echo $this->button('rotate', 'bi-arrow-clockwise', 'Rotate', 'Rotate pages clockwise ( R )'); ?>
                    <?php echo $this->button('cite', 'bi-quote', 'Cite', 'Get a citation for this page (Chicago, MLA, APA, or for Zotero)'); ?>
                    <?php echo $this->button('download', 'bi-download', 'Download', 'Save the original PDF file', $hidden_if_no_download); ?>
                    <div class="position-relative" data-pdf-role="menu-wrap">
                        <?php echo $this->button('more', 'bi-three-dots', 'More', 'Link to this page, print, save an area as a picture, two-page view and more', ' aria-haspopup="menu" aria-expanded="false"'); ?>
                        <div class="scouting-pdf-menu shadow" role="menu" data-pdf-role="menu" hidden>
                            <div class="d-flex align-items-center justify-content-between px-3 py-1 border-bottom mb-1 text-muted small">
                                <span class="fw-semibold">Menu</span>
                                <button type="button" class="btn btn-sm btn-link text-muted p-0 border-0" data-pdf-control="menu-close" aria-label="Close menu" title="Close menu"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                            </div>
                            <?php echo $this->menu_item('copy-link', 'bi-link-45deg', 'Copy link to this page'); ?>
                            <?php echo $this->menu_item('comment-page', 'bi-chat-left-text', 'Comment on this page', ' hidden'); ?>
                            <?php echo $this->menu_item('print', 'bi-printer', 'Print…', $hidden_if_no_download); ?>
                            <?php echo $this->menu_item('snapshot', 'bi-camera', 'Save an area as a picture'); ?>
                            <?php echo $this->menu_item('spread', 'bi-book', 'Two-page view', ' aria-checked="false" role="menuitemcheckbox"'); ?>
                            <?php echo $this->menu_item('info', 'bi-info-circle', 'Document details'); ?>
                        </div>
                    </div>
                    <?php echo $this->button('fullscreen', 'bi-arrows-fullscreen', 'Fullscreen', 'Fill the whole screen with the document (Esc to exit)'); ?>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-3 px-3 py-2 border-bottom bg-body-tertiary small" data-pdf-role="adjustbar" hidden>
                <label class="d-flex align-items-center gap-2 mb-0">Brightness <input type="range" class="form-range" min="50" max="200" step="5" value="100" data-pdf-control="brightness"></label>
                <label class="d-flex align-items-center gap-2 mb-0">Contrast <input type="range" class="form-range" min="50" max="300" step="5" value="100" data-pdf-control="contrast"></label>
                <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" id="scouting-pdf-<?php echo (int) $this->viewer_count; ?>-gray" data-pdf-control="grayscale"><label class="form-check-label" for="scouting-pdf-<?php echo (int) $this->viewer_count; ?>-gray">Black &amp; white</label></div>
                <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" id="scouting-pdf-<?php echo (int) $this->viewer_count; ?>-invert" data-pdf-control="invert"><label class="form-check-label" for="scouting-pdf-<?php echo (int) $this->viewer_count; ?>-invert">Invert (negatives, blueprints)</label></div>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-pdf-control="adjust-reset">Reset</button>
                <button type="button" class="btn-close ms-auto" data-pdf-control="adjust-close" aria-label="Close image adjustments"></button>
            </div>

            <div class="d-flex position-relative" data-pdf-role="body">
                <aside class="border-end bg-body" data-pdf-role="sidebar" aria-label="Document navigation">
                    <div class="nav nav-tabs nav-fill small px-1 pt-1" role="tablist" data-pdf-role="tabs">
                        <button type="button" class="nav-link active" role="tab" aria-selected="true" data-pdf-tab="thumbs">Pages</button>
                        <button type="button" class="nav-link" role="tab" aria-selected="false" data-pdf-tab="outline" hidden>Contents</button>
                        <button type="button" class="nav-link" role="tab" aria-selected="false" data-pdf-tab="info">Details</button>
                        <button type="button" class="btn btn-sm btn-link text-muted p-1 px-2 border-0 align-self-center ms-auto" data-pdf-control="sidebar-close" aria-label="Close sidebar" title="Close sidebar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                    </div>
                    <div data-pdf-panel="thumbs" role="tabpanel"></div>
                    <div data-pdf-panel="outline" role="tabpanel" hidden></div>
                    <div data-pdf-panel="info" role="tabpanel" hidden></div>
                </aside>

                <div class="position-relative d-flex align-items-start bg-light flex-grow-1" data-pdf-role="viewport">
                    <div class="position-absolute top-50 start-50 translate-middle d-flex flex-column align-items-center gap-2 text-muted" data-pdf-role="loading" role="status" aria-live="polite">
                        <div class="spinner-border sm_green_color" aria-hidden="true"></div>
                        <div class="small">Loading document... <span data-pdf-role="progress"></span></div>
                    </div>
                    <div data-pdf-role="pages"></div>
                </div>

                <div class="card shadow" data-pdf-role="dialog" role="dialog" aria-modal="true" hidden>
                    <div class="card-header d-flex align-items-center justify-content-between py-2">
                        <strong data-pdf-role="dialog-title"></strong>
                        <button type="button" class="btn-close" data-pdf-control="dialog-close" aria-label="Close"></button>
                    </div>
                    <div class="card-body small" data-pdf-role="dialog-body"></div>
                </div>

                <div class="toast-container position-absolute top-0 start-50 translate-middle-x p-3" data-pdf-role="toasts" aria-live="polite"></div>
            </div>

            <div class="card-footer d-flex align-items-center justify-content-center py-2 user-select-none bg-body" data-pdf-role="bottom-toolbar">
                <div class="d-flex align-items-center gap-2" data-pdf-role="group">
                    <?php echo $this->button('prev', 'bi-chevron-left', 'Previous', 'Go to the previous page (Left arrow)'); ?>
                    <span class="d-inline-flex align-items-center gap-1 small text-muted px-1" data-pdf-role="page-display">
                        <span class="fw-medium text-body-secondary me-1">Page:</span>
                        <input type="number" class="form-control form-control-sm text-center" data-pdf-control="page-input" value="1" min="1" aria-label="Current page" data-bs-toggle="tooltip" data-bs-title="Type a page number and press Enter to jump to it">
                        <span>of</span>
                        <span data-pdf-control="total-pages">--</span>
                        <span class="badge text-bg-light border" data-pdf-control="page-label" hidden data-bs-toggle="tooltip" data-bs-title="The page number printed on this page. Use it in citations"></span>
                    </span>
                    <?php echo $this->button('next', 'bi-chevron-right', 'Next', 'Go to the next page (Right arrow)'); ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize plugin
Scouting_PDF_Embedder::get_instance();
