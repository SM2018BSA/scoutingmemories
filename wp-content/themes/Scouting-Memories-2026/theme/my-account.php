<?php
/**
 * The My Account dashboard: the profile column and the tabbed panels (Home, Posts,
 * Media, Defaults, Indexing, Admin, Admin 2).
 *
 * Presentation only. Who sees which tab comes from the core plugin's Access class;
 * which tab is open comes from its Tabs class (worked out on the server, from
 * whitelists); every form and view comes from its forms gateway, so the same page
 * runs on Scouting Forms or on Formidable.
 *
 * The tabs are radio buttons, each inside its own label: the label of the checked radio
 * is drawn as the open tab (has-[:checked]:) and only the pane of the checked radio shows
 * (group-has-[#id:checked]:block). They work without JavaScript and every id on the page
 * is unique. Every style is a Tailwind utility class on the element (docs/TAILWIND-ONLY.md);
 * the data-ma attributes are only there for the tests to find things.
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

// A main tab: a blue link on the grey line; the open one is a white box with rounded top corners sitting on that line.
$smem_tab_class = '-mb-px block cursor-pointer rounded-t-md border border-transparent px-4 py-2 text-blue no-underline transition-colors duration-150 hover:border-gray-200 hover:border-b-line hover:text-blue-link-hover has-[:checked]:border-line has-[:checked]:border-b-white has-[:checked]:bg-white has-[:checked]:text-ink has-[:checked]:hover:border-line has-[:checked]:hover:border-b-white has-[:checked]:hover:text-ink has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand';

// A pill under Indexing and Admin: a blue word; the open one is a blue pill.
$smem_pill_class = 'block cursor-pointer rounded-md px-4 py-2 text-blue no-underline transition-colors duration-150 hover:text-blue-link-hover has-[:checked]:bg-blue has-[:checked]:text-white has-[:checked]:hover:text-white has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand';

// A list that is wider than a small screen scrolls sideways inside its own box.
$smem_scroll_label = __( 'Table: scrolls sideways', 'scouting-memories-2026' );

$smem_part_args = array(
	'forms'        => $smem_forms,
	'sub'          => $smem_tab2,
	'pill_class'   => $smem_pill_class,
	'scroll_label' => $smem_scroll_label,
);

// Carried over from the old theme: what a form marks show_only_for_admin is hidden from administrators (the name reads inverted on purpose).
$smem_main_class = Scouting_Memories_2026_UI::classes(
	'mt-12 pt-12',
	Scouting_Memories_2026_UI::CONTAINER,
	Access::can_see_admin_tabs() ? '[&_.show_only_for_admin]:hidden!' : ''
);
?>

	<section id="primary" class="w-full">
		<main id="main" class="<?php echo esc_attr( $smem_main_class ); ?>">

			<h1 class="sr-only"><?php the_title(); ?></h1>

			<?php if ( $smem_dates_done ) : ?>
				<div class="relative mb-4 rounded-md border border-green/40 bg-green/20 p-4 text-ink" role="status"><?php esc_html_e( 'Active end dates were updated for councils, camps and lodges.', 'scouting-memories-2026' ); ?></div>
			<?php endif; ?>

			<div class="-mx-3 flex flex-wrap">

				<aside class="w-full max-w-full shrink-0 border border-line px-3 sm:w-3/12" aria-label="<?php esc_attr_e( 'Your profile', 'scouting-memories-2026' ); ?>">
					<div class="flex justify-center">
						<div class="flex flex-col">
							<div><?php echo $smem_user->show_avatar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<div class="mt-4">
								<p class="mb-4"><?php echo $smem_user->show_first_name(); ?> <?php echo $smem_user->show_last_name(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
								<p class="mb-4"><?php esc_html_e( 'User Roles:', 'scouting-memories-2026' ); ?></p>
								<?php // One role prints as small text, several as a small capitalised bulleted list (the markup comes from CurrentUser). ?>
								<div class="mb-4 [&_span]:text-[0.875em] [&_ul]:list-disc [&_ul]:pl-8 [&_ul]:text-[0.875em] [&_ul]:capitalize"><?php echo $smem_user->show_user_roles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							</div>
							<div><a class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::BTN_SM, Scouting_Memories_2026_UI::BTN_SECONDARY ) ); ?>" href="<?php echo esc_url( wp_logout_url() ); ?>"><?php esc_html_e( 'Logout', 'scouting-memories-2026' ); ?></a></div>
						</div>
					</div>
				</aside>

				<div class="group w-full max-w-full min-w-0 shrink-0 px-3 sm:w-9/12" data-ma="tabs">

					<div class="flex flex-wrap border-b border-line" data-ma="tablist" role="group" aria-label="<?php esc_attr_e( 'My Account sections', 'scouting-memories-2026' ); ?>">
						<label class="<?php echo esc_attr( $smem_tab_class ); ?>" for="tab-myHome"><input class="sr-only" type="radio" name="ma-tab" id="tab-myHome"<?php checked( 'myHome', $smem_tab ); ?>><?php esc_html_e( 'Home', 'scouting-memories-2026' ); ?></label>
						<?php if ( Access::can_see_posts_tab() ) : ?>
							<label class="<?php echo esc_attr( $smem_tab_class ); ?>" for="tab-myPosts"><input class="sr-only" type="radio" name="ma-tab" id="tab-myPosts"<?php checked( 'myPosts', $smem_tab ); ?>><?php esc_html_e( 'Posts', 'scouting-memories-2026' ); ?></label>
						<?php endif; ?>
						<label class="<?php echo esc_attr( $smem_tab_class ); ?>" for="tab-myMedia"><input class="sr-only" type="radio" name="ma-tab" id="tab-myMedia"<?php checked( 'myMedia', $smem_tab ); ?>><?php esc_html_e( 'Media', 'scouting-memories-2026' ); ?></label>
						<label class="<?php echo esc_attr( $smem_tab_class ); ?>" for="tab-myDefaults"><input class="sr-only" type="radio" name="ma-tab" id="tab-myDefaults"<?php checked( 'myDefaults', $smem_tab ); ?>><?php esc_html_e( 'Defaults', 'scouting-memories-2026' ); ?></label>
						<?php if ( Access::can_see_indexing_tab() ) : ?>
							<label class="<?php echo esc_attr( $smem_tab_class ); ?>" for="tab-myIndexing"><input class="sr-only" type="radio" name="ma-tab" id="tab-myIndexing"<?php checked( 'myIndexing', $smem_tab ); ?>><?php esc_html_e( 'Indexing', 'scouting-memories-2026' ); ?></label>
						<?php endif; ?>
						<?php if ( Access::can_see_admin_tabs() ) : ?>
							<label class="<?php echo esc_attr( $smem_tab_class ); ?>" for="tab-myAdmin"><input class="sr-only" type="radio" name="ma-tab" id="tab-myAdmin"<?php checked( 'myAdmin', $smem_tab ); ?>><?php esc_html_e( 'Admin', 'scouting-memories-2026' ); ?></label>
							<?php // Admin 2 has no tab to click: it opens from the Users list's Edit links, so its label stays hidden and its radio is switched off unless it is the open one. ?>
							<label class="hidden" for="tab-myAdmin2"><input type="radio" name="ma-tab" id="tab-myAdmin2"<?php checked( 'myAdmin2', $smem_tab ); ?><?php disabled( 'myAdmin2' !== $smem_tab ); ?>><?php esc_html_e( 'Admin 2', 'scouting-memories-2026' ); ?></label>
						<?php endif; ?>
					</div>

					<div data-ma="panes">

						<div class="hidden px-3 group-has-[#tab-myHome:checked]:block" data-ma="pane" id="pane-myHome">
							<?php echo $smem_forms->form( FormsGateway::FORM_ACCOUNT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>

						<?php if ( Access::can_see_posts_tab() ) : ?>
							<div class="hidden p-4 group-has-[#tab-myPosts:checked]:block" data-ma="pane" id="pane-myPosts">
								<?php get_template_part( 'template-parts/account/posts', $smem_forms->key(), $smem_part_args ); ?>
							</div>
						<?php endif; ?>

						<div class="hidden group-has-[#tab-myMedia:checked]:block" data-ma="pane" id="pane-myMedia">
							<div class="p-12"><div class="mb-4"><?php echo $smem_forms->media_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
						</div>

						<div class="hidden group-has-[#tab-myDefaults:checked]:block" data-ma="pane" id="pane-myDefaults">
							<?php if ( Access::can_see_defaults_tab() ) : ?>
								<div class="p-4"><?php echo $smem_forms->form( FormsGateway::FORM_DEFAULTS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<?php else : ?>
								<p class="mb-4"><?php esc_html_e( 'You do not have permission to upload content.', 'scouting-memories-2026' ); ?></p>
							<?php endif; ?>
						</div>

						<?php if ( Access::can_see_indexing_tab() ) : ?>
							<div class="hidden p-4 group-has-[#tab-myIndexing:checked]:block" data-ma="pane" id="pane-myIndexing">
								<?php get_template_part( 'template-parts/account/indexing', null, $smem_part_args ); ?>
							</div>
						<?php endif; ?>

						<?php if ( Access::can_see_admin_tabs() ) : ?>
							<div class="hidden pt-12 group-has-[#tab-myAdmin:checked]:block" data-ma="pane" id="pane-myAdmin">
							<div class="p-4">

								<div class="flex flex-wrap" data-ma="sublist" role="group" aria-label="<?php esc_attr_e( 'Class Object Tools', 'scouting-memories-2026' ); ?>">
									<span class="mr-4 py-[0.3125rem] text-xl leading-normal whitespace-nowrap text-ink"><?php esc_html_e( 'Class Object Tools', 'scouting-memories-2026' ); ?></span>
									<label class="<?php echo esc_attr( $smem_pill_class ); ?>" for="adm-users"><input class="sr-only" type="radio" name="ma-adm" id="adm-users" checked><?php esc_html_e( 'Users', 'scouting-memories-2026' ); ?></label>
									<label class="<?php echo esc_attr( $smem_pill_class ); ?>" for="adm-posts"><input class="sr-only" type="radio" name="ma-adm" id="adm-posts"><?php esc_html_e( 'Posts', 'scouting-memories-2026' ); ?></label>
									<label class="<?php echo esc_attr( $smem_pill_class ); ?>" for="adm-states"><input class="sr-only" type="radio" name="ma-adm" id="adm-states"><?php esc_html_e( 'States', 'scouting-memories-2026' ); ?></label>
									<label class="<?php echo esc_attr( $smem_pill_class ); ?>" for="adm-councils"><input class="sr-only" type="radio" name="ma-adm" id="adm-councils"><?php esc_html_e( 'Councils', 'scouting-memories-2026' ); ?></label>
									<label class="<?php echo esc_attr( $smem_pill_class ); ?>" for="adm-camps"><input class="sr-only" type="radio" name="ma-adm" id="adm-camps"><?php esc_html_e( 'Camps', 'scouting-memories-2026' ); ?></label>
									<label class="<?php echo esc_attr( $smem_pill_class ); ?>" for="adm-lodges"><input class="sr-only" type="radio" name="ma-adm" id="adm-lodges"><?php esc_html_e( 'Lodges', 'scouting-memories-2026' ); ?></label>
									<label class="<?php echo esc_attr( $smem_pill_class ); ?>" for="adm-tools"><input class="sr-only" type="radio" name="ma-adm" id="adm-tools"><?php esc_html_e( 'Tools', 'scouting-memories-2026' ); ?></label>
								</div>

								<?php // The Users list's Edit links open the Admin 2 tab (javascript/account.js). ?>
								<div class="hidden pt-4 group-has-[#adm-users:checked]:block" data-ma="subpane" id="adm-pane-users" x-data="editLinksOpenAdmin2">
									<h2 class="<?php echo esc_attr( Scouting_Memories_2026_UI::H2 ); ?>"><?php esc_html_e( 'Users', 'scouting-memories-2026' ); ?></h2>
									<div class="overflow-x-auto" x-data="scrollRegion" x-on:resize.window="check()" x-on:change.window="check()" data-label="<?php echo esc_attr( $smem_scroll_label ); ?>"><?php echo $smem_forms->view( FormsGateway::VIEW_USERS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
								</div>
								<div class="hidden pt-4 group-has-[#adm-posts:checked]:block" data-ma="subpane" id="adm-pane-posts"><h2 class="<?php echo esc_attr( Scouting_Memories_2026_UI::H2 ); ?>"><?php esc_html_e( 'Posts', 'scouting-memories-2026' ); ?></h2></div>
								<div class="hidden pt-4 group-has-[#adm-states:checked]:block" data-ma="subpane" id="adm-pane-states"><h2 class="<?php echo esc_attr( Scouting_Memories_2026_UI::H2 ); ?>"><?php esc_html_e( 'States', 'scouting-memories-2026' ); ?></h2></div>
								<div class="hidden pt-4 group-has-[#adm-councils:checked]:block" data-ma="subpane" id="adm-pane-councils"></div><?php // The old page showed nothing here. ?>
								<div class="hidden pt-4 group-has-[#adm-camps:checked]:block" data-ma="subpane" id="adm-pane-camps"><?php esc_html_e( 'Camps', 'scouting-memories-2026' ); ?></div>
								<div class="hidden pt-4 group-has-[#adm-lodges:checked]:block" data-ma="subpane" id="adm-pane-lodges"><?php esc_html_e( 'Lodges', 'scouting-memories-2026' ); ?></div>
								<div class="hidden pt-4 group-has-[#adm-tools:checked]:block" data-ma="subpane" id="adm-pane-tools">
									<div class="px-3"><?php get_template_part( 'template-parts/account/end-dates-card' ); ?></div>
								</div>

							</div>
							</div>

							<div class="hidden px-3 pt-12 group-has-[#tab-myAdmin2:checked]:block" data-ma="pane" id="pane-myAdmin2">
								<?php echo $smem_forms->form( FormsGateway::FORM_EDIT_USER ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endif; ?>

					</div>

				</div>

			</div>

		</main><!-- #main -->
	</section><!-- #primary -->

<?php
get_footer();
