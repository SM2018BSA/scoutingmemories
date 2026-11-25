<?php
/**
 * A narrow page, centred between the masthead and the footer (Login, Reset Password,
 * Contact Us), at the old site's column widths. The data-centered attribute is what the
 * #content box in header.php looks for to become a column this block can sit in the middle of.
 *
 * Template Name: Short Centered Content
 *
 * @package Scouting_Memories_2026
 */

get_header();
?>

	<div data-centered class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CONTAINER, 'flex flex-auto' ) ); ?>">
		<div id="primary" class="mx-auto flex w-full justify-center self-center">
			<main id="main" class="w-full min-w-0 flex-none px-3 md:w-9/12 lg:w-7/12 xl:w-6/12 2xl:w-5/12">
				<div class="mx-auto flex-auto p-4">
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
