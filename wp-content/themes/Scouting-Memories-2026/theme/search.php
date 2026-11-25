<?php
/**
 * The template for displaying search results pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 *
 * @package Scouting_Memories_2026
 */

get_header();
?>

	<div id="primary" class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CONTAINER, 'mt-12 h-full' ) ); ?>">
		<main id="main" class="m-0 w-full p-0">

		<?php if ( have_posts() ) : ?>

			<header>
				<h1 class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::H1, 'break-words' ) ); ?>">
					<?php
					/* translators: %s: search query. */
					printf( esc_html__( 'Search Results for: %s', 'scouting-memories-2026' ), '<span>' . get_search_query() . '</span>' );
					?>
				</h1>
			</header>

			<?php
			/* Start the Loop */
			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/content/content', 'search' );

			endwhile;

			the_posts_navigation();

		else :

			get_template_part( 'template-parts/content/content', 'none' );

		endif;
		?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php
get_footer();
