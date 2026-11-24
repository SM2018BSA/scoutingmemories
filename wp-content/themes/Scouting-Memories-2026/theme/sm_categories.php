<?php
/**
 * The category result pages (History, Photographs, Movies, Oral History,
 * Memorabilia, Museums). Each page carries ?cat= and the search filters in its
 * address; the core plugin's Indexing class turns them into the query.
 * Ported from the old theme; the fold-out search form is a native <details>
 * daisy collapse, whose arrow replaces the old plus/dash icon script.
 *
 * Template Name: SM Categories
 *
 * @package Scouting_Memories_2026
 */

$sm_current_category = get_category( get_query_var( 'cat' ) );
$sm_is_real_category = $sm_current_category instanceof WP_Term;
$sm_category_slug    = $sm_is_real_category ? $sm_current_category->slug : '';
$sm_category_name    = $sm_is_real_category ? $sm_current_category->name : get_the_title();

$sm_paged = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;

$sm_indexing  = class_exists( 'Indexing' ) ? new Indexing( $sm_category_slug, $sm_paged ) : null;
$sm_the_query = $sm_indexing ? new WP_Query( $sm_indexing->query, array( 'paged' => $sm_paged ) ) : $GLOBALS['wp_query'];

get_header();
?>

	<main id="primary" class="site-main max-w-5xl mx-auto mt-8 mb-8">

		<div class="page-header mb-8 border-b border-base-300 pb-4">

			<details class="collapse collapse-arrow card-border bg-base-100">
				<summary class="collapse-title">
					<h1 class="text-2xl">Category: <strong><?php echo esc_html( $sm_category_name ); ?></strong></h1>
				</summary>
				<div class="collapse-content">
					<?php
					if ( class_exists( 'SearchForm' ) ) {
						echo SearchForm::show_category_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</div>
			</details>

			<?php if ( 'Museums' === $sm_category_name ) : ?>
				<div class="flex flex-wrap items-center gap-4 p-4">
					<h5 class="text-lg">National List of Scout Museums:</h5>
					<a class="btn btn-neutral" href="http://storage.scoutingmemories.org/2022/01/aa48b284-smp-museum-list-2022.pdf" target="_blank" rel="noopener">Searchable PDF</a>
					<a class="btn btn-neutral" href="<?php echo esc_url( home_url( '/scouting-memories-museum-list' ) ); ?>">View Online</a>
				</div>
			<?php endif; ?>

		</div>

		<?php
		if ( $sm_the_query->have_posts() ) :
			while ( $sm_the_query->have_posts() ) :
				$sm_the_query->the_post();
				get_template_part( 'template-parts/content/content', 'search' );
			endwhile;

			scouting_memories_2026_pagination( $sm_the_query );

			wp_reset_postdata();
		else :
			get_template_part( 'template-parts/content/content', 'none' );
		endif;
		?>

	</main><!-- #main -->

<?php
get_footer();
