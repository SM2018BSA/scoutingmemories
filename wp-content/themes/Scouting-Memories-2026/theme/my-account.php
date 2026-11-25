<?php
/**
 * The My Account dashboard: the profile card and the tabbed panels (Home, Posts,
 * Media, Defaults, Indexing, Admin, Admin 2).
 *
 * Presentation only. Who sees which tab comes from the core plugin's Access class;
 * which tab is open comes from its Tabs class (worked out on the server, from
 * whitelists); every form and view comes from its forms gateway, so the same page
 * runs on Scouting Forms or on Formidable. The tabs are radio-driven daisyUI tabs:
 * they work without JavaScript and every id on the page is unique.
 *
 * Template Name: My Account Page
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Access;
use ScoutingMemories\Core\Dashboard\Tabs;
use ScoutingMemories\Core\Forms\FormsGateway;
use ScoutingMemories\Core\Forms\GatewayFactory;

get_header();

if ( ! is_user_logged_in() ) {
	get_template_part( 'template-parts/account/login-prompt' );
	get_footer();
	return;
}

$smem_user  = isset( $GLOBALS['the_user'] ) && $GLOBALS['the_user'] instanceof CurrentUser ? $GLOBALS['the_user'] : new CurrentUser();
$smem_forms = GatewayFactory::current();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only: these only choose which tab is open.
$smem_tab        = Tabs::current( $_GET );
$smem_tab2       = Tabs::current_sub( $_GET );
$smem_dates_done = isset( $_GET['enddates'] ) && 'done' === $_GET['enddates'];
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$smem_part_args = array(
	'forms' => $smem_forms,
	'sub'   => $smem_tab2,
);
?>

	<section id="primary" class="content-area w-full">
		<main id="main" class="site-main mx-auto mt-4 mb-8 max-w-6xl">

			<h1 class="sr-only"><?php the_title(); ?></h1>

			<?php if ( $smem_dates_done ) : ?>
				<div class="alert alert-success alert-soft mb-4" role="status"><?php esc_html_e( 'Active end dates were updated for councils, camps and lodges.', 'scouting-memories-2026' ); ?></div>
			<?php endif; ?>

			<div class="grid grid-cols-1 gap-4 sm:grid-cols-4">

				<aside class="ma-profile p-4" aria-label="<?php esc_attr_e( 'Your profile', 'scouting-memories-2026' ); ?>">
					<div class="flex flex-wrap justify-center gap-x-6 gap-y-2 sm:flex-col sm:items-center">
						<div><?php echo $smem_user->show_avatar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<div>
							<p><?php echo $smem_user->show_first_name(); ?> <?php echo $smem_user->show_last_name(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
							<p class="mt-1"><?php esc_html_e( 'User Roles:', 'scouting-memories-2026' ); ?></p>
							<p><?php echo $smem_user->show_user_roles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						</div>
						<div><a class="btn btn-sm ma-logout" href="<?php echo esc_url( wp_logout_url() ); ?>"><?php esc_html_e( 'Logout', 'scouting-memories-2026' ); ?></a></div>
					</div>
				</aside>

				<div class="ma-tabs min-w-0 sm:col-span-3">

					<input type="radio" name="ma-tab" id="tab-myHome"<?php checked( 'myHome', $smem_tab ); ?>>
					<?php if ( Access::can_see_posts_tab() ) : ?>
						<input type="radio" name="ma-tab" id="tab-myPosts"<?php checked( 'myPosts', $smem_tab ); ?>>
					<?php endif; ?>
					<input type="radio" name="ma-tab" id="tab-myMedia"<?php checked( 'myMedia', $smem_tab ); ?>>
					<input type="radio" name="ma-tab" id="tab-myDefaults"<?php checked( 'myDefaults', $smem_tab ); ?>>
					<?php if ( Access::can_see_indexing_tab() ) : ?>
						<input type="radio" name="ma-tab" id="tab-myIndexing"<?php checked( 'myIndexing', $smem_tab ); ?>>
					<?php endif; ?>
					<?php if ( Access::can_see_admin_tabs() ) : ?>
						<input type="radio" name="ma-tab" id="tab-myAdmin"<?php checked( 'myAdmin', $smem_tab ); ?>>
						<?php // Admin 2 has no tab of its own: it opens from the Users list's Edit links, so its radio is switched off unless it is the open one. ?>
						<input type="radio" name="ma-tab" id="tab-myAdmin2" aria-label="<?php esc_attr_e( 'Admin 2: edit a user', 'scouting-memories-2026' ); ?>"<?php checked( 'myAdmin2', $smem_tab ); ?><?php disabled( 'myAdmin2' !== $smem_tab ); ?>>
					<?php endif; ?>

					<div class="ma-tablist tabs" role="group" aria-label="<?php esc_attr_e( 'My Account sections', 'scouting-memories-2026' ); ?>">
						<label class="tab" for="tab-myHome"><?php esc_html_e( 'Home', 'scouting-memories-2026' ); ?></label>
						<?php if ( Access::can_see_posts_tab() ) : ?>
							<label class="tab" for="tab-myPosts"><?php esc_html_e( 'Posts', 'scouting-memories-2026' ); ?></label>
						<?php endif; ?>
						<label class="tab" for="tab-myMedia"><?php esc_html_e( 'Media', 'scouting-memories-2026' ); ?></label>
						<label class="tab" for="tab-myDefaults"><?php esc_html_e( 'Defaults', 'scouting-memories-2026' ); ?></label>
						<?php if ( Access::can_see_indexing_tab() ) : ?>
							<label class="tab" for="tab-myIndexing"><?php esc_html_e( 'Indexing', 'scouting-memories-2026' ); ?></label>
						<?php endif; ?>
						<?php if ( Access::can_see_admin_tabs() ) : ?>
							<label class="tab" for="tab-myAdmin"><?php esc_html_e( 'Admin', 'scouting-memories-2026' ); ?></label>
							<label class="tab hidden" for="tab-myAdmin2"><?php esc_html_e( 'Admin 2', 'scouting-memories-2026' ); ?></label><?php // Reached through the Users list's Edit links. ?>
						<?php endif; ?>
					</div>

					<div class="ma-panes mt-2 p-3 sm:p-4">

						<div class="ma-pane" id="pane-myHome">
							<?php echo $smem_forms->form( FormsGateway::FORM_ACCOUNT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>

						<?php if ( Access::can_see_posts_tab() ) : ?>
							<div class="ma-pane" id="pane-myPosts">
								<?php get_template_part( 'template-parts/account/posts', $smem_forms->key(), $smem_part_args ); ?>
							</div>
						<?php endif; ?>

						<div class="ma-pane" id="pane-myMedia">
							<div class="p-4 sm:p-12"><?php echo $smem_forms->media_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						</div>

						<div class="ma-pane" id="pane-myDefaults">
							<?php if ( Access::can_see_defaults_tab() ) : ?>
								<?php echo $smem_forms->form( FormsGateway::FORM_DEFAULTS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php else : ?>
								<p><?php esc_html_e( 'You do not have permission to upload content.', 'scouting-memories-2026' ); ?></p>
							<?php endif; ?>
						</div>

						<?php if ( Access::can_see_indexing_tab() ) : ?>
							<div class="ma-pane" id="pane-myIndexing">
								<?php get_template_part( 'template-parts/account/indexing', null, $smem_part_args ); ?>
							</div>
						<?php endif; ?>

						<?php if ( Access::can_see_admin_tabs() ) : ?>
							<div class="ma-pane pt-12" id="pane-myAdmin">

								<input type="radio" name="ma-adm" id="adm-users" checked>
								<input type="radio" name="ma-adm" id="adm-posts">
								<input type="radio" name="ma-adm" id="adm-states">
								<input type="radio" name="ma-adm" id="adm-councils">
								<input type="radio" name="ma-adm" id="adm-camps">
								<input type="radio" name="ma-adm" id="adm-lodges">
								<input type="radio" name="ma-adm" id="adm-tools">

								<div class="ma-sublist tabs mb-4 items-center" role="group" aria-label="<?php esc_attr_e( 'Class Object Tools', 'scouting-memories-2026' ); ?>">
									<span class="ma-brand mr-4 text-xl"><?php esc_html_e( 'Class Object Tools', 'scouting-memories-2026' ); ?></span>
									<label class="tab" for="adm-users"><?php esc_html_e( 'Users', 'scouting-memories-2026' ); ?></label>
									<label class="tab" for="adm-posts"><?php esc_html_e( 'Posts', 'scouting-memories-2026' ); ?></label>
									<label class="tab" for="adm-states"><?php esc_html_e( 'States', 'scouting-memories-2026' ); ?></label>
									<label class="tab" for="adm-councils"><?php esc_html_e( 'Councils', 'scouting-memories-2026' ); ?></label>
									<label class="tab" for="adm-camps"><?php esc_html_e( 'Camps', 'scouting-memories-2026' ); ?></label>
									<label class="tab" for="adm-lodges"><?php esc_html_e( 'Lodges', 'scouting-memories-2026' ); ?></label>
									<label class="tab" for="adm-tools"><?php esc_html_e( 'Tools', 'scouting-memories-2026' ); ?></label>
								</div>

								<div class="ma-subpane" id="adm-pane-users">
									<h2 class="ma-h2"><?php esc_html_e( 'Users', 'scouting-memories-2026' ); ?></h2>
									<div class="sm-scroll-x"><?php echo $smem_forms->view( FormsGateway::VIEW_USERS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
								</div>
								<div class="ma-subpane" id="adm-pane-posts"><h2 class="ma-h2"><?php esc_html_e( 'Posts', 'scouting-memories-2026' ); ?></h2></div>
								<div class="ma-subpane" id="adm-pane-states"><h2 class="ma-h2"><?php esc_html_e( 'States', 'scouting-memories-2026' ); ?></h2></div>
								<div class="ma-subpane" id="adm-pane-councils"></div><?php // The old page showed nothing here. ?>
								<div class="ma-subpane" id="adm-pane-camps"><?php esc_html_e( 'Camps', 'scouting-memories-2026' ); ?></div>
								<div class="ma-subpane" id="adm-pane-lodges"><?php esc_html_e( 'Lodges', 'scouting-memories-2026' ); ?></div>
								<div class="ma-subpane" id="adm-pane-tools">
									<?php get_template_part( 'template-parts/account/end-dates-card' ); ?>
								</div>

							</div>

							<div class="ma-pane pt-12" id="pane-myAdmin2">
								<?php echo $smem_forms->form( FormsGateway::FORM_EDIT_USER ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>

					</div><!-- .ma-panes -->

				</div>

			</div>

			<?php if ( Access::can_see_admin_tabs() ) : ?>
				<style>
					/* Carried over from the old theme: hidden from administrators (the class name reads inverted on purpose). */
					.show_only_for_admin {
						display: none !important;
					}
				</style>
			<?php endif; ?>

		</main><!-- #main -->
	</section><!-- #primary -->

	<script>
		// The Users list's Edit links open the Admin 2 tab - only those links, not
		// every link in the Admin pane.
		(function () {
			document.querySelectorAll('#adm-pane-users a[href*="entry="]').forEach(function (a) {
				var url = new URL(a.href, location.origin);
				url.searchParams.set('tab', 'myAdmin2');
				a.href = url.toString();
			});
		})();
	</script>

<?php
get_footer();
