<?php
// Global helper functions the legacy classes and templates call. Copied from the old
// theme's functions.php; every one is function_exists-guarded there and here, so the
// copies never clash whichever side loads first.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'debug_to_console' ) ) :
	function debug_to_console( $data ) {
		$output = $data;
		if ( is_array( $output ) )
			$output = implode( ',', $output );
		echo "<script>console.log( 'Debug Objects: " . $output . "' );</script>";
	}
endif;

if ( ! function_exists( 'get_request_parameter' ) ) :
	/**
	 * Gets the request parameter.
	 *
	 * @param      string  $key      The query parameter
	 * @param      string  $default  The default value to return if not found
	 *
	 * @return     string  The request parameter.
	 */
	function get_request_parameter( $key, $default = null ) {
		// If not request set
		if ( ! isset( $_REQUEST[ $key ] ) || empty( $_REQUEST[ $key ] ) ) {
			return $default;
		}

		// Set so process it
		return strip_tags( (string) wp_unslash( $_REQUEST[ $key ] ) );
	}
endif;

if ( ! function_exists( 'clean' ) ) :
	function clean( $string ) {
		$string = str_replace( ' ', '_', $string ); // Replaces all spaces with underscores.
		$string = str_replace( '(', '_', $string ); // Replaces all spaces with underscores.
		$string = str_replace( ')', '_', $string ); // Replaces all spaces with underscores.
		$string = str_replace( '&amp;', '_and_', $string ); // Replaces all spaces with underscores.
		return preg_replace( '/[^A-Za-z0-9\-]/', '_', $string ); // Removes special chars.
	}
endif;
