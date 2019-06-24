<?php
namespace WPE\ContentPerformance;
require_once( __DIR__ . '/abstract-class-wpe-content-performance-status.php' );
/**
 * Content Performance Connection
 */
class Connection extends Status {

	protected $endpoint = 'connection';

	/**
	 * Checks the global enabled flag.
	 *
	 * @return boolean The global enabled state.
	 */
	function is_enabled() {
		return $this->is_response_valid() && $this->response['enabled'] && $this->enough_data();
	}

	/**
	 * Check the ga_access enabled flag.
	 *
	 * @return boolean The ga_access enabled state.
	 */
	function is_ga_access_enabled() {
		return $this->is_response_valid() && $this->response['ga_access']['enabled'];
	}

	/**
	 * Check the ga_view enabled flag.
	 *
	 * @return boolean The ga_view enabled state.
	 */
	function is_ga_view_enabled() {
		return $this->is_response_valid() && $this->response['ga_view']['enabled'];
	}

	/**
	 * Checks to see if the key pair exists and if they're valid.
	 *
	 * @return boolean The key pair enabled state.
	 */
	function is_key_pair_enabled() {
		return $this->is_response_valid();
	}

	/**
	 * Returns a single error from the Service.
	 *
	 * @return array An array including an error code and text.
	 */
	function get_error() {
		if ( is_wp_error( $this->response ) ) {
			if ( $this->response->get_error_code() == 'missing_key' ) {
				return array( 'code' => 'missing_key', 'text' => 'Content Performance key pair is required before any other settings can be configured.' );
			} else if ( $this->response->get_error_code() == '403' ) {
				return array( 'code' => 'access_denied', 'text' => 'Content Performance key pair is invalid.' );
			} else { // Standard HTTP error.
				return array(
					'code' => 'http_request_failed',
					'text' => 'Could not connect to the Content Performance service.',
				);
			}
		}

		// Support site_fetching_historical_data which isn't an error.
		if (
			! empty( $this->response['ga_view']['messages'] ) &&
			'site_fetching_historical_data' === $this->response['ga_view']['messages'][0]['code']
		) {
			return $this->response['ga_view']['messages'][0];
		}

		foreach ( array( 'ga_access', 'ga_view', 'wp_access' ) as $service ) {
			if ( false === $this->response[ $service ]['enabled'] ) {
				if ( ! empty( $this->response[ $service ]['messages'] ) ) {
					return $this->response[ $service ]['messages'][0];
				} else {
					return array( 'code' => 'unknown', 'text' => 'An unknown error occurred.' );
				}
			}
		}
	}

	function get_view_id() {
		if ( $this->is_response_valid() && isset( $this->response['ga_view']['view_id'] ) ) {
			return $this->response['ga_view']['view_id'];
		}
	}
}
