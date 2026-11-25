<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package Scouting_Memories_2026
 */

get_header();
?>

	<div id="primary" class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CONTAINER, 'mt-12 h-full' ) ); ?>">
		<main id="main" class="m-0 w-full p-0">

			<?php
			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/content/content', get_post_type() );

				// If comments are open or we have at least one comment, load up the comment template.
				if ( comments_open() || get_comments_number() ) :
					comments_template();
				endif;

			endwhile; // End of the loop.
			?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_footer();
