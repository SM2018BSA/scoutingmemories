<?php
namespace WPE\ContentPerformance;

/**
 * Content Performance Abstract Status Class
 */
abstract class Status {
	protected $service;
	protected $response;
	// The endpoint to hit on init.
	protected $endpoint = '';
	const ENOUGH_DATA_IN_SECONDS = DAY_IN_SECONDS * 30;
	abstract public function is_enabled();

	function __construct() {
		$this->service = Core::get_service();
	}

	/**
	 * Make a call to the endpoint and store the response.
	 */
	function init() {
		if ( empty( Core::get_site_id() ) ) {
			return;
		}

		$this->response = $this->service->dispatch( $this->endpoint );
		Debug::log( $this->response );
	}

	/**
	 * Checks to see if a response is valid.
	 *
	 * @return boolean
	 */
	function is_response_valid() {
		return ! is_null( $this->response ) && ! is_wp_error( $this->response );
	}

	/**
	 * Checks to see if there is enough data available.
	 *
	 * @return boolean
	 */
	function enough_data() {
		if ( $this->is_response_valid() && isset( $this->response['start_date'] ) && isset( $this->response['end_date'] ) ) {
			return ( strtotime( $this->response['end_date'] ) - strtotime( $this->response['start_date'] ) )
				>= self::ENOUGH_DATA_IN_SECONDS;
		}
	}
}
