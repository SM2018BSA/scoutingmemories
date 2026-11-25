<?php
/**
 * A narrow page, centred between the masthead and the footer (Login, Reset Password,
 * Contact Us), at the old site's column widths.
 *
 * Template Name: Short Centered Content
 *
 * @package Scouting_Memories_2026
 */

get_header();
?>

	<div class="sm-container sm-short-centered">
		<div id="primary" class="sm-short-centered-row">
			<main id="main" class="sm-short-centered-col">
				<div class="p-4">
					<?php
					while ( have_posts() ) :
						the_post();

						get_template_part( 'template-parts/content/content', 'page' );

						if ( comments_open() || get_comments_number() ) {
							comments_template();
						}

					endwhile;
					?>
				</div>
			</main><!-- #main -->
		</div><!-- #primary -->
	</div>

<?php
get_footer();
