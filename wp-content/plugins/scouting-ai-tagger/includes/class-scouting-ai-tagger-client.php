<?php
/**
 * Signed calls to the AI Tagging Assistant. The same HMAC scheme as the OCR app:
 * X-Scouting-Time and X-Scouting-Signature over "time\nmethod\npath\nsha256(body)".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scouting_AI_Tagger_Client {

	/**
	 * @param string $method GET or POST
	 * @param string $path   e.g. /v1/index/status
	 * @param array|null $body JSON body for POST
	 * @return array{status:int,json:array|null}|WP_Error
	 */
	public static function call( string $method, string $path, ?array $body = null ) {
		if ( ! Scouting_AI_Tagger::enabled() ) {
			return new WP_Error( 'tagger_off', 'The tagging assistant\'s address and secret are not set up yet.' );
		}
		$raw  = null === $body ? '' : wp_json_encode( $body );
		$time = (string) time();
		$sign = hash_hmac( 'sha256', $time . "\n" . $method . "\n" . $path . "\n" . hash( 'sha256', $raw ), Scouting_AI_Tagger::secret() );

		$answer = wp_remote_request(
			Scouting_AI_Tagger::service_url() . $path,
			[
				'method'              => $method,
				'timeout'             => 20,
				'redirection'         => 0,
				'limit_response_size' => MB_IN_BYTES,
				'headers'             => [
					'Content-Type'         => 'application/json',
					'X-Scouting-Time'      => $time,
					'X-Scouting-Signature' => $sign,
				],
				'body'                => '' === $raw ? null : $raw,
			]
		);
		if ( is_wp_error( $answer ) ) {
			return $answer;
		}
		$parsed = json_decode( (string) wp_remote_retrieve_body( $answer ), true );
		return [
			'status' => (int) wp_remote_retrieve_response_code( $answer ),
			'json'   => is_array( $parsed ) ? $parsed : null,
		];
	}

	/** A short plain-English line for an answer that was not what we wanted. */
	public static function describe_problem( $answer ): string {
		if ( is_wp_error( $answer ) ) {
			return $answer->get_error_message();
		}
		$code = isset( $answer['json']['error'] ) ? (string) $answer['json']['error'] : '';
		if ( 'bad_time' === $code ) {
			return 'The app refused the request time: one of the two clocks is wrong.';
		}
		if ( 'bad_signature' === $code ) {
			return 'The app refused the signature: the secret here and on the app do not match.';
		}
		if ( '' !== $code ) {
			return sprintf( 'The app answered %d (%s).', $answer['status'], $code );
		}
		return sprintf( 'The server in front of the app answered %d.', $answer['status'] );
	}
}
