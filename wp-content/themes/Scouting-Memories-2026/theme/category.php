<?php
/**
 * Category archives (/category/…/): the filtered memory list, ported from the
 * old theme. The Indexing class (core plugin) turns the URL's search filters
 * into the query; the fold-out search form replaces the old broken Bootstrap 4
 * collapse with a native <details> daisy collapse, so it opens again.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

$sm_current_category = get_category( get_query_var( 'cat' ) );
$sm_category_name    = ( $sm_current_category instanceof WP_Term ) ? $sm_current_category->name : single_cat_title( '', false );
$sm_category_slug    = ( $sm_current_category instanceof WP_Term ) ? $sm_current_category->slug : get_query_var( 'category_name' );

$sm_paged = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;

$sm_indexing  = class_exists( 'Indexing' ) ? new Indexing( $sm_category_slug, $sm_paged ) : null;
$sm_the_query = $sm_indexing ? new WP_Query( $sm_indexing->query ) : $GLOBALS['wp_query'];

get_header();
?>

	<main id="primary" class="site-main max-w-5xl mx-auto mt-8 mb-8">

		<div class="page-header mb-8 border-b border-base-300 pb-4">
			<details class="collapse collapse-arrow card-border bg-base-100">
				<summary class="collapse-title">
					<h1 class="text-2xl">Category: <?php echo esc_html( $sm_category_name ); ?></h1>
				</summary>
				<div class="collapse-content">
					<?php
					if ( class_exists( 'SearchForm' ) ) {
						echo SearchForm::show_form2(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</div>
			</details>
		</div>

		<?php
		if ( $sm_the_query->have_posts() ) :
			while ( $sm_the_query->have_posts() ) :
				$sm_the_query->the_post();
				get_template_part( 'template-parts/content/content', 'search' );
			endwhile;
			wp_reset_postdata();

			scouting_memories_2026_pagination( $sm_the_query );
		else :
			get_template_part( 'template-parts/content/content', 'none' );
		endif;
		?>

	</main><!-- #main -->

<?php
get_footer();
