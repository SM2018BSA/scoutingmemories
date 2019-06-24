<?php

namespace WPE\ContentPerformance;

/**
 * Content Performance Service
 *
 * All logic related to making requests to the Content Performance service.
 */
class Service {
	// The default URL for the service. Can be filtered by cperf_service_url.
	const service_url = 'https://cperf.wpengine.io';
	const api_base = '/v1';

	/**
	 * WordPress HTTP transport used for communication.
	 *
	 * @var WP_Http
	 */
	private $http;

	private $site_id;

	public function __construct( $http, $site_id ) {
		$this->http = $http;
		$this->site_id = $site_id;
	}

	/**
	 * Get the request URL for a particular endpoint, with given parameters and
	 * authentication parameters given as query parameters.
	 *
	 * @param string $endpoint
	 * @param array $params
	 */
	public function request_url( $endpoint, $params = array() ) {
		return self::service_url( $endpoint ) . ( ! empty( $params ) ? '?' . $this->build_query_string( $params ) : '' );
	}

	/**
	 * Get the service URL, possibly for a particular endpoint.
	 *
	 * @param string $endpoint
	 * @return string
	 */
	public static function service_url( $endpoint = '' ) {
		return apply_filters( 'wpecp_service_url', self::service_url ) . apply_filters( 'wpecp_api_base', self::api_base ) . ( $endpoint ? '/' . $endpoint : '' );
	}

	/**
	 * Add the site id to the endpoint and make the request.
	 *
	 * @param  string $endpoint The endpoint to hit.
	 * @param  array  $params   The query string parameters to pass to the URL.
	 * @param  array  $args     The args for WP_Http request.
	 * @return array            Response from the service.
	 */
	public function dispatch( $endpoint = '', $params = array(), $args = array() ) {
		$request_url = $this->request_url( $this->add_site( $endpoint ), $params );
		return $this->request( $request_url, $args );
	}

	/**
	 * Add the site id to the endpoint and make a POST/PUT/PATCH/DELETE request.
	 *
	 * @param  string $endpoint The endpoint to hit.
	 * @param  array  $body     The body for the request.
	 * @param  string $method   The HTTP request method.
	 * @return array            Response from the service.
	 */
	public function dispatch_json( $endpoint = '', $body = array(), $method = 'POST' ) {
		$request_url = $this->request_url( $this->add_site( $endpoint ), array() );
		$args = array( 'method' => $method, 'body' => json_encode( $body ), 'headers' => array( 'Content-Type' => 'application/json' ) );
		return $this->request( $request_url, $args );
	}

	/**
	 * Signs a URL using query strings.
	 *
	 * @param  string $endpoint The endpoint to hit.
	 * @param  array  $params   The query strings.
	 * @return string           The signed URL.
	 */
	public function sign_url( $endpoint, $params = array() ) {
		$url = $this->request_url( $this->add_site( $endpoint ) );
		$options = Core::get();
		$auth = new Auth( $options['access_key'], $options['secret_key'] );
		// Sign the request to get the headers.
		$headers = $auth->sign( $url . ( $params ? '?' . http_build_query( $params ) : '' ) );
		// Pull the headers out and add them as query strings.
		$query_strings = array(
			'x-auth' => $headers['Authorization'],
			'x-date' => $headers['Date'],
		) + $params;

		$url .= '?' . http_build_query( $query_strings );
		return  $url;
	}

	/**
	 * Adds the site ID to a sites endpoint.
	 *
	 * @param  string $endpoint The sites endpoint.
	 * @return string           The full sites endpoint.
	 */
	private function add_site( $endpoint ) {
		$url = "sites/{$this->site_id}";
		if ( ! empty( $endpoint ) ) {
			$url .= '/' . $endpoint;
		}
		return $url;
	}

	/**
	 * Make a request and process the response.
	 *
	 * @param  string $url    The URL to hit.
	 * @return array|WP_Error Response from the service.
	 */
	public function request( $url, $args ) {
		// Set some default args.
		$defaults = array( 'method' => 'GET', 'headers' => array(), 'body' => null );
		$r = wp_parse_args( $args, $defaults );

		$options = Core::get();
		if ( empty( $options['access_key'] ) || empty( $options['secret_key'] ) ) {
			return new \WP_Error( 'missing_key', 'Access or secret key missing.' );
		}
		$auth = new Auth( $options['access_key'], $options['secret_key'] );
		// Sign the request.
		$r['headers'] = $auth->sign( $url, $r['headers'], $r['method'], $r['body'] );

		$response = $this->http->request( $url, $r );
		Debug::log( $r );
		Debug::log( $url );

		if ( is_array( $response ) && $this->is_successful( $response ) ) {
			$response = $this->decode_response( $response );
		} else if ( is_array( $response ) && ! empty( $response ) ) {
			// Build our WP_Error object.
			$error = new \WP_Error();
			// Add the code, the message, and the body if they exist.
			$error->add(
				isset( $response['response']['code'] ) ? $response['response']['code'] : '',
				isset( $response['response']['message'] ) ? $response['response']['message'] : '',
				isset( $response['body'] ) ? $response['body'] : ''
			);

			$response = $error;
		} else if ( ! is_wp_error( $response ) ) {
			$response = new \WP_Error( 'unknown_error', 'An unknown error occurred.', $response );
		}

		 return $response;
	}

	/**
	 * Convert an associative array to query strings.
	 *
	 * @param  array $params  The query string params.
	 * @return string         url encoded query strings.
	 */
	private function build_query_string( $params ) {
		$params = http_build_query( $params );
		/**
		 * By default, http_build_query converts arrays to this format:
		 *     files[0]=1&files[1]=2&...
		 *
		 * Which Rails converts to a hash, instead we need this format:
		 *     files[]=1&files[]=2&...
		 *
		 * Source: http://php.net/manual/en/function.http-build-query.php#111819
		 */
		$params = preg_replace( '/%5B[0-9]+%5D/simU', '%5B%5D', $params );
		return $params;
	}

	/**
	 * Extract the status code from the response.
	 * @param  array $response  The response from the service.
	 * @return int              The status code.
	 */
	private function get_status_code( $response ) {

		if ( ! isset( $response['response'] ) || ! isset( $response['response']['code'] ) ) {
			return null;
		}
		return $response['response']['code'];
	}

	/**
	 * Looks at a response and determines if it was successful.
	 *
	 * @param  array   $response The response from the service.
	 * @return boolean 			 True if the status code was 2**.
	 */
	private function is_successful( $response ) {

		$status_code = $this->get_status_code( $response );
		if ( null === $status_code ) {
			return false;
		}
		return $status_code >= 200 && $status_code < 300;
	}

	/**
	 * Decodes the body of a response.
	 *
	 * @param  array  $response The response.
	 * @return array            The decoded response.
	 */
	private function decode_response( $response ) {

		$decoded = array();

		if ( isset( $response['body'] ) ) {
			$decoded = json_decode( $response['body'], true );
		}
		if ( null === $decoded ) {
			return new \WP_Error( 'invalid_json', "The JSON response couldn't be decoded." );
		}

		return $decoded;
	}
}
