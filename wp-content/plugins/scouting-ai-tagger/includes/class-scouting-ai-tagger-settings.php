<?php
/**
 * Settings > AI Tagging Assistant: the app's address and shared secret, a connection test,
 * and the "Send index to the tagging assistant" button (a progress list drives the sync
 * steps over REST; see assets/admin.js).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scouting_AI_Tagger_Settings {

	const PAGE = 'scouting-ai-tagger';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_page' ] );
		add_action( 'admin_init', [ self::class, 'register_setting' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'add_script' ] );
	}

	public static function add_page(): void {
		add_options_page(
			'AI Tagging Assistant',
			'AI Tagging Assistant',
			'manage_options',
			self::PAGE,
			[ self::class, 'render' ]
		);
	}

	public static function register_setting(): void {
		register_setting(
			self::PAGE,
			Scouting_AI_Tagger::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ self::class, 'sanitize' ],
			]
		);
	}

	public static function sanitize( $given ): array {
		$saved = Scouting_AI_Tagger::settings();
		$given = is_array( $given ) ? $given : [];
		$url   = isset( $given['url'] ) ? trim( (string) $given['url'] ) : '';
		if ( '' !== $url ) {
			$parts = wp_parse_url( $url );
			$https = isset( $parts['scheme'], $parts['host'] )
				&& ( 'https' === $parts['scheme'] || ( 'http' === $parts['scheme'] && Scouting_AI_Tagger::is_local_host( $parts['host'] ) ) );
			if ( ! $https ) {
				add_settings_error( Scouting_AI_Tagger::OPTION, 'url', 'The app address must start with https:// (plain http is fine for local development sites only). The address was not saved.' );
				$url = $saved['url'];
			} else {
				$url = untrailingslashit( esc_url_raw( $url ) );
			}
		}
		// An empty secret box keeps the saved secret, so saving the page never wipes it.
		$secret = isset( $given['secret'] ) ? trim( (string) $given['secret'] ) : '';
		if ( '' === $secret ) {
			$secret = $saved['secret'];
		} elseif ( strlen( $secret ) < 32 ) {
			add_settings_error( Scouting_AI_Tagger::OPTION, 'secret', 'The shared secret must be at least 32 characters. The secret was not saved.' );
			$secret = $saved['secret'];
		}
		return [ 'url' => $url, 'secret' => $secret ];
	}

	public static function add_script( string $hook ): void {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_script(
			'scouting-ai-tagger-admin',
			SCOUTING_AI_TAGGER_URL_BASE . 'assets/admin.js',
			[],
			SCOUTING_AI_TAGGER_VERSION,
			true
		);
		wp_localize_script(
			'scouting-ai-tagger-admin',
			'scoutingAiTagger',
			[
				'endpoint' => esc_url_raw( rest_url( 'scouting-tagger/v1/sync-step' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			]
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings      = Scouting_AI_Tagger::settings();
		$url_fixed     = defined( 'SCOUTING_AI_TAGGER_APP_URL' );
		$secret_fixed  = defined( 'SCOUTING_AI_TAGGER_SECRET' );
		$enabled       = Scouting_AI_Tagger::enabled();
		?>
		<div class="wrap">
			<h1>AI Tagging Assistant</h1>
			<p>The assistant is a separate app on the development server. It suggests the archive's
				tags for documents historians upload. This page connects this site to it.</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sm-tagger-url">App address</label></th>
						<td>
							<?php if ( $url_fixed ) : ?>
								<code><?php echo esc_html( Scouting_AI_Tagger::service_url() ); ?></code>
								<p class="description">Set by the SCOUTING_AI_TAGGER_APP_URL constant in wp-config.php.</p>
							<?php else : ?>
								<input name="<?php echo esc_attr( Scouting_AI_Tagger::OPTION ); ?>[url]" type="url" id="sm-tagger-url"
									value="<?php echo esc_attr( $settings['url'] ); ?>" class="regular-text"
									placeholder="https://tagger.68-183-140-47.sslip.io">
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sm-tagger-secret">Shared secret</label></th>
						<td>
							<?php if ( $secret_fixed ) : ?>
								<p class="description">Set by the SCOUTING_AI_TAGGER_SECRET constant in wp-config.php.</p>
							<?php else : ?>
								<input name="<?php echo esc_attr( Scouting_AI_Tagger::OPTION ); ?>[secret]" type="password" id="sm-tagger-secret"
									value="" class="regular-text" autocomplete="new-password"
									placeholder="<?php echo '' !== $settings['secret'] ? 'saved — leave empty to keep it' : 'at least 32 characters'; ?>">
								<p class="description">The same value as TAGGER_SECRET on the app. Leaving the box empty keeps the saved secret.</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php if ( ! $url_fixed || ! $secret_fixed ) : ?>
					<?php submit_button( 'Save settings' ); ?>
				<?php endif; ?>
			</form>

			<hr>
			<h2>The index</h2>
			<?php if ( ! $enabled ) : ?>
				<p>Fill in the app address and the shared secret above first.</p>
			<?php else : ?>
				<p>Sending the index gives the assistant this site's councils, camps, lodges, states,
					the links between them, and the already-tagged documents it learns from. Nothing
					runs on its own: the index goes over only when the button is pressed.</p>
				<p>
					<button type="button" class="button" id="sm-tagger-test">Test the connection</button>
					<button type="button" class="button button-primary" id="sm-tagger-send">Send index to the tagging assistant</button>
				</p>
				<div id="sm-tagger-progress" aria-live="polite"></div>
			<?php endif; ?>
		</div>
		<?php
	}
}
