<?php
/**
 * The My Account dashboard: profile sidebar plus the tabbed panels (Home,
 * Posts, Media, Defaults, Indexing, Admin, Admin 2), every panel the same
 * Formidable form or view as the old theme.
 *
 * Rebuilt as radio-driven daisyUI tabs: the ?tab= / ?tab2= deep links are
 * resolved SERVER-side against the Access whitelists (which kills the old
 * reflected-XSS echo), every id is unique (the old page repeated ids five
 * times), who-sees-what comes from the Access class, and the end-dates tool
 * is a nonce'd POST to the core plugin instead of an open GET.
 *
 * Template Name: My Account Page
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Access;
use ScoutingMemories\Core\Ajax;

get_header();

$sm_user = isset( $GLOBALS['the_user'] ) && $GLOBALS['the_user'] instanceof CurrentUser ? $GLOBALS['the_user'] : new CurrentUser();

$sm_account_form  = new Form( MY_ACCOUNT_FORMID );
$sm_users_form    = new Form( EDIT_USERS_FORMID );
$sm_defaults_form = new Form( EDIT_USER_DEFAULTS_FORMID );

$sm_posts_view    = new View( POSTS_VIEWID );
$sm_users_view    = new View( EDIT_USERS_VIEWID );
$sm_councils_view = new CouncilView( ALL_COUNCILS_VIEWID );
$sm_lodges_view   = new LodgeView( ALL_LODGES_VIEWID );
$sm_camps_view    = new CampView( ALL_CAMPS_VIEWID );

// Deep links, resolved server-side against the whitelists (AUDIT row I).
$sm_tab  = isset( $_GET['tab'] ) && in_array( $_GET['tab'], Access::allowed_tabs(), true ) ? $_GET['tab'] : 'myHome'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$sm_tab2 = isset( $_GET['tab2'] ) && in_array( $_GET['tab2'], Access::allowed_sub_tabs(), true ) ? $_GET['tab2'] : 'myCouncils'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

/** Prints checked when this tab is the open one. */
function sm_checked( $value, $current ) {
	echo $value === $current ? ' checked' : '';
}
?>

	<section id="primary" class="content-area w-full">
		<main id="main" class="site-main max-w-7xl mx-auto mt-8">

			<?php if ( isset( $_GET['enddates'] ) && 'done' === $_GET['enddates'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="alert alert-success alert-soft mb-4" role="status">Active end dates were updated for councils, camps and lodges.</div>
			<?php endif; ?>

			<div class="grid grid-cols-1 sm:grid-cols-4 gap-4">

				<div class="card card-border bg-base-100 p-4"><!-- profile sidebar -->
					<div class="flex flex-col items-center gap-2 text-center">
						<div><?php echo $sm_user->show_avatar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<p class="mt-2"><?php echo $sm_user->show_first_name(); ?> <?php echo $sm_user->show_last_name(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<p class="font-bold">User Roles:</p>
						<p><?php echo $sm_user->show_user_roles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						<div><a class="btn btn-secondary btn-sm" href="<?php echo esc_url( wp_logout_url() ); ?>">Logout</a></div>
					</div>
				</div>

				<div class="sm:col-span-3 min-w-0 ma-tabs">

					<input type="radio" name="ma-tab" id="tab-myHome"<?php sm_checked( 'myHome', $sm_tab ); ?>>
					<?php if ( Access::can_see_posts_tab() ) : ?>
						<input type="radio" name="ma-tab" id="tab-myPosts"<?php sm_checked( 'myPosts', $sm_tab ); ?>>
					<?php endif; ?>
					<input type="radio" name="ma-tab" id="tab-myMedia"<?php sm_checked( 'myMedia', $sm_tab ); ?>>
					<input type="radio" name="ma-tab" id="tab-myDefaults"<?php sm_checked( 'myDefaults', $sm_tab ); ?>>
					<?php if ( Access::can_see_indexing_tab() ) : ?>
						<input type="radio" name="ma-tab" id="tab-myIndexing"<?php sm_checked( 'myIndexing', $sm_tab ); ?>>
					<?php endif; ?>
					<?php if ( Access::can_see_admin_tabs() ) : ?>
						<input type="radio" name="ma-tab" id="tab-myAdmin"<?php sm_checked( 'myAdmin', $sm_tab ); ?>>
						<input type="radio" name="ma-tab" id="tab-myAdmin2"<?php sm_checked( 'myAdmin2', $sm_tab ); ?>>
					<?php endif; ?>

					<div class="ma-tablist tabs tabs-lift" role="tablist">
						<label class="tab" for="tab-myHome">Home</label>
						<?php if ( Access::can_see_posts_tab() ) : ?>
							<label class="tab" for="tab-myPosts">Posts</label>
						<?php endif; ?>
						<label class="tab" for="tab-myMedia">Media</label>
						<label class="tab" for="tab-myDefaults">Defaults</label>
						<?php if ( Access::can_see_indexing_tab() ) : ?>
							<label class="tab" for="tab-myIndexing">Indexing</label>
						<?php endif; ?>
						<?php if ( Access::can_see_admin_tabs() ) : ?>
							<label class="tab" for="tab-myAdmin">Admin</label>
							<label class="tab hidden" for="tab-myAdmin2">Admin 2</label><?php // Reached through the Users list's Edit links, like before. ?>
						<?php endif; ?>
					</div>

					<div class="ma-panes border border-base-300 border-t-0 bg-base-100 p-4">

						<div class="ma-pane" id="pane-myHome" role="tabpanel">
							<?php echo $sm_account_form->show_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>

						<?php if ( Access::can_see_posts_tab() ) : ?>
							<div class="ma-pane" id="pane-myPosts" role="tabpanel">
								<h2 class="text-xl mb-4">My Posts</h2>
								<?php echo $sm_posts_view->show_view(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>

						<div class="ma-pane" id="pane-myMedia" role="tabpanel">
							<div class="p-8"><?php echo do_shortcode( '[frontend-button]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						</div>

						<div class="ma-pane" id="pane-myDefaults" role="tabpanel">
							<?php if ( Access::can_see_defaults_tab() ) : ?>
								<?php echo $sm_defaults_form->show_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php else : ?>
								<p>You do not have permission to upload content.</p>
							<?php endif; ?>
						</div>

						<?php if ( Access::can_see_indexing_tab() ) : ?>
							<div class="ma-pane" id="pane-myIndexing" role="tabpanel">

								<input type="radio" name="ma-idx" id="idx-myCouncils"<?php sm_checked( 'myCouncils', $sm_tab2 ); ?>>
								<input type="radio" name="ma-idx" id="idx-myCamps"<?php sm_checked( 'myCamps', $sm_tab2 ); ?>>
								<input type="radio" name="ma-idx" id="idx-myLodges"<?php sm_checked( 'myLodges', $sm_tab2 ); ?>>
								<input type="radio" name="ma-idx" id="idx-myTools">

								<div class="ma-sublist tabs tabs-box mb-4" role="tablist">
									<label class="tab" for="idx-myCouncils">Councils</label>
									<label class="tab" for="idx-myCamps">Camps</label>
									<label class="tab" for="idx-myLodges">Lodges</label>
									<label class="tab" for="idx-myTools">Tools</label>
								</div>

								<div class="ma-subpane" id="idx-pane-councils"><?php echo $sm_councils_view->show_view(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
								<div class="ma-subpane" id="idx-pane-camps"><?php echo $sm_camps_view->show_view(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
								<div class="ma-subpane" id="idx-pane-lodges"><?php echo $sm_lodges_view->show_view(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
								<div class="ma-subpane" id="idx-pane-tools">
									<?php get_template_part( 'template-parts/account/end-dates-card' ); ?>
								</div>

							</div>
						<?php endif; ?>

						<?php if ( Access::can_see_admin_tabs() ) : ?>
							<div class="ma-pane" id="pane-myAdmin" role="tabpanel">

								<input type="radio" name="ma-adm" id="adm-users" checked>
								<input type="radio" name="ma-adm" id="adm-posts">
								<input type="radio" name="ma-adm" id="adm-states">
								<input type="radio" name="ma-adm" id="adm-councils">
								<input type="radio" name="ma-adm" id="adm-camps">
								<input type="radio" name="ma-adm" id="adm-lodges">
								<input type="radio" name="ma-adm" id="adm-tools">

								<div class="ma-sublist tabs tabs-box mb-4" role="tablist">
									<span class="px-3 self-center font-semibold">Class Object Tools</span>
									<label class="tab" for="adm-users">Users</label>
									<label class="tab" for="adm-posts">Posts</label>
									<label class="tab" for="adm-states">States</label>
									<label class="tab" for="adm-councils">Councils</label>
									<label class="tab" for="adm-camps">Camps</label>
									<label class="tab" for="adm-lodges">Lodges</label>
									<label class="tab" for="adm-tools">Tools</label>
								</div>

								<div class="ma-subpane" id="adm-pane-users">
									<h2 class="text-xl mb-4">Users</h2>
									<?php echo $sm_users_view->show_view(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
								<div class="ma-subpane" id="adm-pane-posts"><h2 class="text-xl">Posts</h2></div>
								<div class="ma-subpane" id="adm-pane-states"><h2 class="text-xl">States</h2></div>
								<div class="ma-subpane" id="adm-pane-councils"><h2 class="text-xl">Councils</h2></div>
								<div class="ma-subpane" id="adm-pane-camps"><h2 class="text-xl">Camps</h2></div>
								<div class="ma-subpane" id="adm-pane-lodges"><h2 class="text-xl">Lodges</h2></div>
								<div class="ma-subpane" id="adm-pane-tools">
									<?php get_template_part( 'template-parts/account/end-dates-card' ); ?>
								</div>

							</div>

							<div class="ma-pane" id="pane-myAdmin2" role="tabpanel">
								<?php echo $sm_users_form->show_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>

					</div><!-- .ma-panes -->

				</div>

			</div>

			<?php if ( Access::can_see_admin_tabs() ) : ?>
				<style>
					/* Carried over from the old theme: this section of the Defaults form is
					   hidden from administrators (the class name reads inverted on purpose). */
					.show_only_for_admin {
						display: none !important;
					}
				</style>
			<?php endif; ?>

		</main><!-- #main -->
	</section><!-- #primary -->

	<script>
		// The Users list's Edit links open the Admin 2 tab, as before - but only
		// those links, not every link in the Admin pane (the old page broke its
		// own sub-tabs doing that).
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
