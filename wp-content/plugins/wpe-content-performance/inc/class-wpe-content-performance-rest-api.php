<?php

namespace WPE\ContentPerformance;

/**
 * Content Performance REST API extensions
 * http://v2.wp-api.org/extending/adding/
 */
class REST_API {

	// Define and register singleton
	private static $instance = false;

	/**
	 * @codeCoverageIgnore
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Clone
	 */
	private function __clone() { }

	/**
	 * API Route namespace.
	 *
	 * @var string
	 */
	public $namespace = 'cpp';

	/**
	 * Constructor
	 *
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		$this->fix_plugin_conflicts();
	}

	function fix_plugin_conflicts() {
		// Make sure this is a request to our routes.
		if ( strpos( $_SERVER['REQUEST_URI'], 'wp-json/' . $this->namespace . '/v1' ) === false ) {
			return;
		}

		// Fix for WP SpamShield (PI-359).
		add_filter( 'wpss_misc_form_spam_check_bypass', '__return_true' );

		// Fix for Co-Authors Plus (PI-622).
		add_filter( 'coauthors_plus_should_query_post_author', '__return_false' );

		/**
		 * Allow adding fixes for other plugins.
		 */
		do_action( 'wpecp_fix_plugin_conflicts' );
	}

	/**
	 * Register REST API routes used by Content Performance
	 *
	 * @action rest_api_init
	 */
	function register_routes() {
		$this->register_route( 'v1', 'posts', 'get_posts_info_callback', array( 'GET', 'POST' ) );
		$this->register_route( 'v1', 'status', 'get_status_callback' );
		$this->register_route( 'v1', 'validate', 'get_posts_valid_callback', array( 'GET', 'POST' ) );
	}

	/**
	 * Helper for registering routes.
	 */
	function register_route( $version, $endpoint, $callback, $methods = array( 'GET' ) ) {
		register_rest_route( "{$this->namespace}/{$version}", "/{$endpoint}/", array(
				'methods' => $methods,
				'callback' => array( get_called_class(), $callback ),
				'permission_callback' => array( get_called_class(), 'permission' ),
		) );
	}

	/**
	 * Decides if a rest request is authenticated.
	 *
	 * @param  WP_REST_Request $request The incoming request.
	 * @return boolean                  True if the request was successfully authenticated.
	 */
	static function permission( $request ) {
		$auth_header = array( 'authorization' => self::get_authorization_header() );

		// Flatten array.
		$headers = array_map( function( $header ) {
			return $header[0];
		}, $request->get_headers() );

		// Add our authorization header to the headers array.
		$headers = array_merge( $auth_header, $headers );

		// Build the request object needed by the Auth class.
		$request = Auth::build_request_object( $_SERVER['REQUEST_URI'], $headers, $request->get_method(), $request->get_body() );

		// Init the class, only the secret key is needed for authentication.
		$auth = new Auth( Core::get_access_key(), Core::get_secret_key() );

		/**
		 * Decide if a rest request is authenticated.
		 *
		 * @param boolean $is_authentic True if the request is authenticated, false if not.
		 * @param WP_REST_Request       The incoming request object.
		 */
		return apply_filters( 'wpecp_rest_authenticated', $auth->is_authentic( $request ), $request );
	}

	/**
	 * Get the authorization header
	 *
	 * On certain systems and configurations, the Authorization header will be
	 * stripped out by the server or PHP. Typically this is then used to
	 * generate `PHP_AUTH_USER`/`PHP_AUTH_PASS` but not passed on. We use
	 * `getallheaders` here to try and grab it out instead.
	 *
	 * https://github.com/WP-API/OAuth1/blob/cc9f7f962efe6ee25fdd62b3ac72eae60aff921d/lib/class-wp-rest-oauth1.php#L61
	 *
	 * @return string|null Authorization header if set, null otherwise
	 */
	static function get_authorization_header() {
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
		}
		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			// Check for the authoization header case-insensitively.
			foreach ( $headers as $key => $value ) {
				if ( strtolower( $key ) === 'authorization' ) {
					return $value;
				}
			}
		}
		return null;
	}

	/**
	 * Return the post data for posts by paths.
	 */
	static function get_posts_info_callback( $data ) {
		// Get the query parameters.
		$parameters = $data->get_params();

		if ( true !== $valid = self::is_path_parameter_valid( $parameters ) ) {
			return $valid;
		}

		// Initialize our posts array.
		$posts = array();

		// If paths are set, loop through them.
		foreach ( $parameters['path'] as $path ) {
			// Attempt to get the ID for the post.
			$id = url_to_postid( apply_filters( 'wpecp_post_url', $path ) );

			// Get the actual WP_Post object, set to null if $id is 0.
			$_post = ( 0 !== $id ) ? get_post( $id ) : null;

			// Make sure we're working with a post.
			if ( 'WP_Post' === get_class( $_post ) ) {
				$post_info = Core::get_post_data( $_post );
				// $post_info['valid'] = true;
			} else {
				// If post wasn't found return an empty array.
				$post_info = array();
				// $post_info['valid'] = Core::is_path_valid( $path );
			}
			// Make sure we return the same path we were sent.
			$post_info['query'] = $path;
			array_push( $posts, $post_info );
		}

		$response = new \WP_REST_Response( $posts );
		// Set the plugin build string as a header.
		$response->header( 'X-CPP-BUILD', Core::get_build_string() );
		return $response;
	}

	static function is_path_parameter_valid( $parameters ) {
		if ( isset( $parameters['path'] ) && is_array( $parameters['path'] ) ) {
			return true;
		} else if ( isset( $parameters['path'] ) && ! is_array( $parameters['path'] ) ) {
			return new \WP_Error( 'parameter_wrong_format', 'Path needs to be an array', array( 'status' => 400 ) );
		} else {
			return new \WP_Error( 'missing_required_parameter', 'Missing required parameter: path', array( 'status' => 400, 'parameter' => 'path' ) );
		}
	}

	static function get_status_callback() {
		return array(
			'site_id' => Core::get_site_id(),
			'install_name' => Core::get_install_name(),
			'build' => Core::get_build_string(),
			'has_access_key' => ! empty( Core::get( 'access_key' ) ),
			'has_secret_key' => ! empty( Core::get( 'secret_key' ) ),
			'dbversion' => Core::get( 'dbversion' ),
			'default_date_range' => Core::get( 'default_date_range' ),
			'base_url' => site_url(),
			'wp_version' => get_bloginfo( 'version' ),
			'php_version' => phpversion(),
		);
	}

	/**
	 * Return the validity of paths.
	 */
	static function get_posts_valid_callback( $data ) {
		// Get the query parameters.
		$parameters = $data->get_params();
		// Ensure path paremter is valid.
		if ( true !== $valid = self::is_path_parameter_valid( $parameters ) ) {
			return $valid;
		}

		$posts = array();
		foreach ( $parameters['path'] as $path ) {
			$post_info = array();
			$post_info['query'] = $path;
			$post_info['valid'] = Core::is_path_valid( apply_filters( 'wpecp_post_url', $path ) );
			array_push( $posts, $post_info );
		}

		return new \WP_REST_Response( $posts );
	}
} // Class
REST_API::instance();
