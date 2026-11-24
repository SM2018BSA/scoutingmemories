<?php
/**
 * Shown on a members-only page to a visitor who is not logged in.
 *
 * @package Scouting_Memories_2026
 */

?>

	<section id="primary" class="content-area w-full">
		<main id="main" class="site-main mx-auto mt-8 mb-8 max-w-xl">
			<div class="card card-border bg-base-100">
				<div class="card-body items-center text-center">
					<h1 class="card-title text-2xl"><?php the_title(); ?></h1>
					<p><?php esc_html_e( 'Please log in to see this page.', 'scouting-memories-2026' ); ?></p>
					<a class="btn btn-primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Log in', 'scouting-memories-2026' ); ?></a>
				</div>
			</div>
		</main><!-- #main -->
	</section><!-- #primary -->
