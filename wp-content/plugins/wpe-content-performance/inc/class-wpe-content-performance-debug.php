<?php

namespace WPE\ContentPerformance;

/**
 * @codeCoverageIgnore
 */
class Debug
{
	public static $messages = array();

	/**
	 * Log a debug message.
	 *
	 * @param  string $message The message to log.
	 */
	public static function log( $message ) {
		$backtrace = debug_backtrace()[1];
		$package = array(
			'function' => $backtrace['function'],
			'class' => $backtrace['class'],
			'message' => $message,
		);

		array_push( self::$messages, $package );
	}

	/**
	 * Display debug messages.
	 *
	 * This will display a white box containing all debug messages.
	 */
	public static function display_debug_messages() {
		echo '<div class="clear"></div>';
		echo '<div style="width: 100%; padding: 10px; margin-left: 160px; background-color: white; z-index: 999; position: absolute;">';
		foreach ( self::$messages as $message ) {
			echo '<strong>' . $message['class'] . '->' . $message['function']. '</strong><pre>';
			var_dump( $message['message'] );
			echo '</pre>';
		}
		echo '</div>';
	}
}
