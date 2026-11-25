<?php
/**
 * Where the AI Tagging Assistant lives and the secret both sides share. Constants win over
 * the saved settings, the same way the OCR feature works:
 *
 *   define( 'SCOUTING_AI_TAGGER_APP_URL', 'https://tagger.68-183-140-47.sslip.io' );
 *   define( 'SCOUTING_AI_TAGGER_SECRET', '...' );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scouting_AI_Tagger {

	const OPTION = 'scouting_ai_tagger';

	private static ?Scouting_AI_Tagger $instance = null;

	public static function get_instance(): Scouting_AI_Tagger {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		Scouting_AI_Tagger_Sync::register();
		Scouting_AI_Tagger_Settings::register();
		Scouting_AI_Tagger_Post_Suggestions::register();
	}

	/** @return array{url:string,secret:string} */
	public static function settings(): array {
		$saved = get_option( self::OPTION );
		return [
			'url'    => is_array( $saved ) && isset( $saved['url'] ) ? (string) $saved['url'] : '',
			'secret' => is_array( $saved ) && isset( $saved['secret'] ) ? (string) $saved['secret'] : '',
		];
	}

	/** The app's address without a trailing slash; the constant wins over the saved setting. */
	public static function service_url(): string {
		if ( defined( 'SCOUTING_AI_TAGGER_APP_URL' ) && is_string( SCOUTING_AI_TAGGER_APP_URL ) ) {
			return untrailingslashit( SCOUTING_AI_TAGGER_APP_URL );
		}
		return untrailingslashit( self::settings()['url'] );
	}

	public static function secret(): string {
		if ( defined( 'SCOUTING_AI_TAGGER_SECRET' ) && is_string( SCOUTING_AI_TAGGER_SECRET ) ) {
			return SCOUTING_AI_TAGGER_SECRET;
		}
		return self::settings()['secret'];
	}

	/** True when an address and a secret are set and the address is https (or a local site). */
	public static function enabled(): bool {
		$url    = self::service_url();
		$secret = self::secret();
		if ( '' === $url || strlen( $secret ) < 32 ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			return false;
		}
		return 'https' === $parts['scheme'] || self::is_local_host( $parts['host'] );
	}

	/** Development hosts where plain http is fine (DDEV, this PC, the web container). */
	public static function is_local_host( string $host ): bool {
		$host = strtolower( $host );
		if ( in_array( $host, [ 'localhost', '127.0.0.1', '[::1]', 'host.docker.internal' ], true ) ) {
			return true;
		}
		foreach ( [ '.ddev.site', '.test', '.localhost' ] as $ending ) {
			if ( str_ends_with( $host, $ending ) ) {
				return true;
			}
		}
		return false;
	}
}
