<?php

namespace WPE\ContentPerformance;

/**
 * Content Performance Auth
 */
class Auth {

	protected $access_id;
	protected $secret_key;

	function __construct( $access_id, $secret_key ) {
		$this->access_id = $access_id;
		$this->secret_key = $secret_key;
	}

	/**
	 * Build a request object.
	 *
	 * @param  string $path    This is the URL path: "/test/"
	 * @param  array  $headers An array of the headers.
	 * @param  string $method  The HTTP method.
	 * @param  body   $body    The body of the request.
	 * @return object          An object representing the request.
	 */
	public static function build_request_object( $path, $headers = array(), $method = 'GET', $body = null ) {
		$request = new \StdClass();
		$request->url = self::parse_uri( $path );
		$request->headers = $headers;
		$request->method = $method;
		$request->body = $body;
		return $request;
	}

	/**
	 * Sign a request.
	 *
	 * @param  string $url     The requested path.
	 * @param  array  $headers The headers.
	 * @param  string $method  The HTTP method.
	 * @param  string $body    The body of the request.
	 * @return array           The new headers array containing the Authorization header.
	 */
	public function sign( $path, $headers = array(), $method = 'GET', $body = null ) {
		$request = self::build_request_object( $path, $headers, $method, $body );
		$this->set_date( $request );
		$this->set_body( $request );
		$this->set_auth_header( $request );
		return $request->headers;
	}

	/**
	 * Check to see if a request is signed and if that signature is valid.
	 *
	 * @param  object $request The request object.
	 * @return boolean          True if the request is valid, false if not.
	 */
	public function is_authentic( $request ) {
		if ( $this->request_too_old( $request ) ) {
			Debug::log( 'Request too old.' );
			return false;
		}

		if ( $this->md5_mismatch( $request ) ) {
			Debug::log( 'MD5 mismatch.' );
			return false;
		}
		// Build our expected signature.
		$expected_signature = $this->get_hmac_signature( $request );

		$auth_header = self::parse_auth_header( $request );

		if ( false === $auth_header ) {
			Debug::log( 'Failed parsing auth header.' );
			return false;
		}

		if ( $auth_header['access_key'] !== $this->access_id ) {
			Debug::log( 'Access key mismatch.' );
			return false;
		}

		return hash_equals( $expected_signature, $auth_header['signature'] );
	}

	/**
	 * Take an api_auth Authorization header and extract the access_key/secret_key.
	 *
	 * @param  object $request The request object.
	 * @return array|boolean   An associative array containing 'access_key' and 'secret_key',
	 *                         or false if the header coulnd't be parsed.
	 */
	protected static function parse_auth_header( $request ) {

		// Extract the authorization header.
		$auth_header = self::find_header( 'authorization', $request->headers );

		// Extract the access key and signature;
		preg_match( '/APIAuth-HMAC-SHA256 (.*):(.*)/', $auth_header, $matches );

		if ( ! isset( $matches[1] ) || ! isset( $matches[2] ) ) {
			return false;
		}

		return array( 'access_key' => $matches[1], 'signature' => $matches[2] );
	}

	/**
	 * Builds canonical string from request.
	 *
	 * @param class $request Request used to build the canonical string.
	 * @return string
	 */
	protected function get_canonical_string( $request ) {
		$parts = array(
			$request->method,
			self::find_header( 'content_type', $request->headers ),
			self::find_header( 'content_md5', $request->headers ),
			$request->url,
			self::find_header( 'date', $request->headers ),
		);
		return join( ',', $parts );
	}

	/**
	 * Set a date header if it doesn't exist.
	 *
	 * @param object $request The request object.
	 */
	protected function set_date( $request ) {
		if ( ! self::find_header( 'date', $request->headers ) ) {
			$request->headers['Date'] = gmdate( 'D, d M Y H:i:s T' );
		}
	}

	protected function set_body( $request ) {
		if ( ! self::find_header( 'content_md5', $request->headers ) && $this->needs_content_md5( $request ) ) {
			$request->headers['Content-MD5'] = $this->calculate_md5( $request->body );
		}
	}

	/**
	 * Check to see if a request is older than 15 minutes.
	 *
	 * @param  object $request The request object.
	 * @return boolean         True if the request is too old.
	 */
	protected function request_too_old( $request ) {
		$date = self::find_header( 'date', $request->headers );
		if ( '' === $date ) {
			return true;
		}

		return strtotime( $date ) < (microtime( true ) - 900);
	}

	/**
	 * Finds and returns a header, case-insensitively.
	 *
	 * @param  string $header  The header to search for.
	 * @param  array  $headers The headers.
	 * @return string          The value of the header if it was found, or an empty string.
	 */
	protected static function find_header( $header, $headers ) {
		foreach ( $headers as $key => $value ) {
			// Canonicalize the header, see: https://developer.wordpress.org/reference/classes/wp_rest_request/canonicalize_header_name/
			if ( str_replace( '-', '_', strtolower( $key ) ) === $header ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Takes a URL and returns the path.
	 *
	 * Translated from:
	 * https://github.com/mgomes/api_auth/blob/5a1bafe7581b29188cc3487325bcbbdd0237764d/lib/api_auth/headers.rb#L96
	 *
	 * @param  string $url The URL.
	 * @return string      The path from the URL.
	 */
	protected static function parse_uri( $url ) {
		// Strip the domain!
		$uri_without_host = preg_replace( '/https?:\/\/[^,?\/]*/', '', $url );

		// If we're left with nothing then the path is "/".
		if ( empty( $uri_without_host ) ) {
			return '/';
		}

		return $uri_without_host;
	}

	/**
	 * MD5 hash and base64 encode a string.
	 *
	 * @param  string $body The string to hash.
	 * @return string       The hashed sting.
	 */
	protected function calculate_md5( $body ) {
		return base64_encode(
			md5( $body, true )
		);
	}

	/**
	 * Detect if the body MD5 matches the Content-MD5 header.
	 * @param  [type] $request [description]
	 * @return [type]          [description]
	 */
	protected function md5_mismatch( $request ) {
		if ( $this->needs_content_md5( $request ) ) {
			return self::find_header( 'content_md5', $request->headers ) !== $this->calculate_md5( $request->body );
		} else {
			return false;
		}
	}

	protected function needs_content_md5( $request ) {
		return in_array( strtolower( $request->method ), array( 'put', 'post' ) ) && ! empty( $request->body );
	}

	/**
	 * Generates an HMAC signature from the request and the secret key.
	 *
	 * @param object $request Request to generate signature from.
	 * @return string
	 */
	protected function get_hmac_signature( $request ) {
		$canonical_string = $this->get_canonical_string( $request );
		Debug::log( $canonical_string );
		$s = hash_hmac( 'sha256', $canonical_string, $this->secret_key, true );
		$s = base64_encode( $s );
		$s = trim( $s );
		return $s;
	}

	/**
	 * Adds an Authorization header to the request.
	 *
	 * @param object $request Request to sign.
	 * @return null
	 */
	protected function set_auth_header( $request ) {
		$signature = $this->get_hmac_signature( $request );
		$authorized_header = "APIAuth-HMAC-SHA256 {$this->access_id}:{$signature}";
		$request->headers['Authorization'] = $authorized_header;
	}
}
