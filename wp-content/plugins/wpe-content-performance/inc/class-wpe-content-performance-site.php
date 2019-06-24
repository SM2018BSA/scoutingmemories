<?php
namespace WPE\ContentPerformance;
require_once( __DIR__ . '/abstract-class-wpe-content-performance-status.php' );

/**
 * Content Performance Site
 */
class Site extends Status {

	protected $endpoint = '';
	const DAYS_BACK = 365;

	/**
	 * Checks the global enabled flag.
	 *
	 * @return boolean The global enabled state.
	 */
	function is_enabled() {
		return $this->is_response_valid() && 'enabled' === $this->response['status'] && $this->enough_data();
	}

	function get( $item ) {
		if ( $this->is_response_valid() && isset( $this->response[ $item ] ) ) {
			return $this->response[ $item ];
		}
	}

	function get_fetch_progress() {
		$current_days_back = ( strtotime( $this->response['end_date'] ) - strtotime( $this->response['start_date'] ) ) / DAY_IN_SECONDS;
		return round( 100 * ( $current_days_back / self::DAYS_BACK ) );
	}
}
