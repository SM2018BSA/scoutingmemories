<?php
/**
 * The template for displaying archive pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

get_header();
?>

	<main id="primary">

		<?php if ( have_posts() ) : ?>

			<header>
				<?php
				the_archive_title( '<h1 class="' . esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::H1, 'break-words' ) ) . '">', '</h1>' );
				the_archive_description( '<div class="[&_a]:text-blue [&_a]:underline [&_p]:mb-4">', '</div>' );
				?>
			</header>

			<?php
			/* Start the Loop */
			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/content/content', get_post_type() );

			endwhile;

			the_posts_navigation();

		else :

			get_template_part( 'template-parts/content/content', 'none' );

		endif;
		?>

	</main><!-- #primary -->

<?php
get_sidebar();
get_footer();
