<?php
/**
 * A narrow, centred page (Login, Reset Password, Contact Us).
 *
 * Template Name: Short Centered Content
 *
 * @package Scouting_Memories_2026
 */

get_header();
?>

	<div class="flex w-full justify-center">
		<div id="primary" class="w-full self-center md:w-9/12 lg:w-7/12 xl:w-6/12 2xl:w-5/12">
			<main id="main" class="p-4" role="main">
				<?php
				while ( have_posts() ) :
					the_post();

					get_template_part( 'template-parts/content/content', 'page' );

					if ( comments_open() || get_comments_number() ) {
						comments_template();
					}

				endwhile;
				?>
			</main><!-- #main -->
		</div><!-- #primary -->
	</div>

<?php
get_footer();
