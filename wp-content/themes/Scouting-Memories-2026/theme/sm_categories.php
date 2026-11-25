<?php
/**
 * The category result pages (History, Photographs, Movies, Oral History,
 * Memorabilia, Museums). Each page carries ?cat= and the search filters in its
 * address; the core plugin's Indexing class turns them into the query.
 * Ported from the old theme; the fold-out search form is a native <details>
 * daisy collapse, whose plus (a dash when open) replaces the old icon script.
 *
 * Template Name: SM Categories
 *
 * @package Scouting_Memories_2026
 */

$smem_current_category = get_category( get_query_var( 'cat' ) );
$smem_is_real_category = $smem_current_category instanceof WP_Term;
$smem_category_slug    = $smem_is_real_category ? $smem_current_category->slug : '';
$smem_category_name    = $smem_is_real_category ? $smem_current_category->name : get_the_title();

$smem_paged = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;

$smem_indexing  = class_exists( 'Indexing' ) ? new Indexing( $smem_category_slug, $smem_paged ) : null;
$smem_the_query = $smem_indexing ? new WP_Query( $smem_indexing->query, array( 'paged' => $smem_paged ) ) : $GLOBALS['wp_query'];

get_header();
?>

	<main id="primary" class="site-main sm-container sm-edge mt-12 mb-12">

		<div class="page-header sm-page-header">

			<details class="collapse collapse-plus sm-fold">
				<summary class="collapse-title">
					<h1>Category: <strong><?php echo esc_html( $smem_category_name ); ?></strong></h1>
				</summary>
				<div class="collapse-content">
					<?php
					if ( class_exists( 'SearchForm' ) ) {
						echo SearchForm::show_category_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</div>
			</details>

			<?php if ( 'Museums' === $smem_category_name ) : ?>
				<div class="sm-container sm-museums">
					<div class="sm-museums-row">
						<div><h5>National List of Scout Museums: </h5></div>
						<div><a class="btn btn-secondary" href="https://storage.scoutingmemories.org/2022/01/aa48b284-smp-museum-list-2022.pdf" target="_blank" rel="noopener"><?php esc_html_e( 'Searchable PDF', 'scouting-memories-2026' ); ?></a></div>
						<div><a class="btn btn-secondary" href="<?php echo esc_url( home_url( '/scouting-memories-museum-list' ) ); ?>"><?php esc_html_e( 'View Online', 'scouting-memories-2026' ); ?></a></div>
					</div>
				</div>
			<?php endif; ?>

		</div>

		<?php
		if ( $smem_the_query->have_posts() ) :
			while ( $smem_the_query->have_posts() ) :
				$smem_the_query->the_post();
				get_template_part( 'template-parts/content/content', 'search' );
			endwhile;

			scouting_memories_2026_pagination( $smem_the_query );

			wp_reset_postdata();
		else :
			get_template_part( 'template-parts/content/content', 'none' );
		endif;
		?>

	</main><!-- #main -->

<?php
get_footer();
