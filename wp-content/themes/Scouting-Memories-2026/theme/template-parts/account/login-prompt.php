<?php
/**
 * Shown on a members-only page to a visitor who is not logged in.
 *
 * @package Scouting_Memories_2026
 */

?>

	<section id="primary" class="w-full">
		<main id="main" class="mx-auto mt-8 mb-8 max-w-xl px-3">
			<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::CARD ); ?>">
				<div class="flex flex-auto flex-col items-center gap-2 p-6 text-center">
					<h1 class="font-slab text-2xl leading-tight font-medium"><?php the_title(); ?></h1>
					<p><?php esc_html_e( 'Please log in to see this page.', 'scouting-memories-2026' ); ?></p>
					<a class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::BTN, Scouting_Memories_2026_UI::BTN_PRIMARY ) ); ?>" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Log in', 'scouting-memories-2026' ); ?></a>
				</div>
			</div>
		</main><!-- #main -->
	</section><!-- #primary -->
