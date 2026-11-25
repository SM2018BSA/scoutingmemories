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

$smem_current_category = get_category( get_query_var( 'cat' ) );
$smem_category_name    = ( $smem_current_category instanceof WP_Term ) ? $smem_current_category->name : single_cat_title( '', false );
$smem_category_slug    = ( $smem_current_category instanceof WP_Term ) ? $smem_current_category->slug : get_query_var( 'category_name' );

$smem_paged = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;

$smem_indexing  = class_exists( 'Indexing' ) ? new Indexing( $smem_category_slug, $smem_paged ) : null;
$smem_the_query = $smem_indexing ? new WP_Query( $smem_indexing->query ) : $GLOBALS['wp_query'];

get_header();
?>

	<main id="primary" class="site-main sm-container sm-edge mt-12 mb-12">

		<div class="page-header sm-page-header">
			<details class="collapse collapse-plus sm-fold sm-fold-plain">
				<summary class="collapse-title">
					<h1 class="sm-fold-h1">Category: <?php echo esc_html( $smem_category_name ); ?></h1>
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
		if ( $smem_the_query->have_posts() ) :
			while ( $smem_the_query->have_posts() ) :
				$smem_the_query->the_post();
				get_template_part( 'template-parts/content/content', 'search' );
			endwhile;
			wp_reset_postdata();

			scouting_memories_2026_pagination( $smem_the_query );
		else :
			get_template_part( 'template-parts/content/content', 'none' );
		endif;
		?>

	</main><!-- #main -->

<?php
get_footer();
