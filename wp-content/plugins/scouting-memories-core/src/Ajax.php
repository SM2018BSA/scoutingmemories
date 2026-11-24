<?php
namespace ScoutingMemories\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin's own request handlers. (The public search AJAX endpoints still live in
 * the legacy view classes, registered the same way the old theme registered them.)
 */
final class Ajax {

	public const END_DATES_ACTION = 'sm_update_end_dates';
	public const END_DATES_NONCE  = 'sm_update_end_dates';

	public static function register(): void {
		// Logged-in users only on purpose: no admin_post_nopriv_ registration.
		add_action( 'admin_post_' . self::END_DATES_ACTION, [ self::class, 'update_end_dates' ] );
	}

	/**
	 * The "Update Active End Dates" tool (AUDIT.md row G). The old theme ran this for
	 * ANYONE on a plain GET of ?smp_action=update_end_dates; here it is a POST to
	 * admin-post.php that requires an administrator and a nonce. The new theme's Tools
	 * card submits a small form:
	 *
	 *   <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	 *     <input type="hidden" name="action" value="<?php echo Ajax::END_DATES_ACTION; ?>">
	 *     <?php wp_nonce_field( Ajax::END_DATES_NONCE ); ?>
	 *     <button>Update Active End Dates</button>
	 *   </form>
	 */
	public static function update_end_dates(): void {
		if ( ! Access::can_run_end_dates() ) {
			wp_die( 'You do not have permission to run this tool.', 403 );
		}
		check_admin_referer( self::END_DATES_NONCE );

		\CouncilEntry::update_active();
		\CampEntry::update_active();
		\LodgeEntry::update_active();

		$back = wp_get_referer();
		$back = $back ? add_query_arg( 'enddates', 'done', $back ) : home_url( '/my-account/?enddates=done' );

		// On the local sites display_errors prints notices before headers, so a
		// Location header may be impossible; fall back to a meta refresh.
		if ( headers_sent() ) {
			echo '<meta http-equiv="refresh" content="0;url=' . esc_url( $back ) . '">';
			echo '<p><a href="' . esc_url( $back ) . '">Done - back to My Account</a></p>';
		} else {
			wp_safe_redirect( $back );
		}
		exit;
	}
}
