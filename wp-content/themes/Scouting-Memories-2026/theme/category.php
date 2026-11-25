<?php
/**
 * Category archives (/category/.../): the filtered memory list, ported from the
 * old theme. The Indexing class (core plugin) turns the URL's search filters
 * into the query. The card's grey header holds the heading (at the size of
 * ordinary text, as the old page showed it) and opens the wide search form
 * under it: a native <details>, so it opens again (the old Bootstrap 4
 * collapse was broken).
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

	<main id="primary" class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CONTAINER, 'mt-12 mb-12' ) ); ?>">

		<div class="mb-12 border-b border-line">
			<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::CARD ); ?>">
			<details class="group">
				<summary class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CARD_HEADER, 'block cursor-pointer list-none focus-visible:outline-2 focus-visible:outline-blue [&::-webkit-details-marker]:hidden' ) ); ?>">
					<h1 class="mt-0 mb-2 flex w-full items-center justify-between gap-4 text-left font-slab text-base leading-tight font-medium break-words">
						<span class="min-w-0">Category: <?php echo esc_html( $smem_category_name ); ?></span>
						<svg class="size-6 shrink-0 fill-current group-open:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/></svg>
						<svg class="hidden size-6 shrink-0 fill-current group-open:block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M2 8a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11A.5.5 0 0 1 2 8"/></svg>
					</h1>
				</summary>
				<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::CARD_BODY ); ?>">
					<?php
					if ( class_exists( 'SearchForm' ) ) {
						echo SearchForm::show_form2(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</div>
			</details>
			</div>
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
