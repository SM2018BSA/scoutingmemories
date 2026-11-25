<?php
/**
 * The category result pages (History, Photographs, Movies, Oral History,
 * Memorabilia, Museums). Each page carries ?cat= and the search filters in its
 * address; the core plugin's Indexing class turns them into the query.
 * Ported from the old theme: a card whose grey header holds the heading and a
 * plus; the header opens the search form under it (a native <details>), and
 * the plus turns into a dash while it is open.
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

$smem_museum_button = Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::BTN, Scouting_Memories_2026_UI::BTN_SECONDARY, 'max-w-full break-words' );

get_header();
?>

	<main id="primary" class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CONTAINER, 'mt-12 mb-12' ) ); ?>">

		<div class="mb-12 border-b border-line">

			<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::CARD ); ?>">
			<details class="group">
				<summary class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CARD_HEADER, 'block cursor-pointer list-none focus-visible:outline-2 focus-visible:outline-blue [&::-webkit-details-marker]:hidden' ) ); ?>">
					<h1 class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::H1, 'flex items-start justify-between gap-4 break-words' ) ); ?>">
						<span class="min-w-0">Category: <strong><?php echo esc_html( $smem_category_name ); ?></strong></span>
						<svg class="size-[1em] shrink-0 fill-current group-open:hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2"/></svg>
						<svg class="hidden size-[1em] shrink-0 fill-current group-open:block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M2 8a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11A.5.5 0 0 1 2 8"/></svg>
					</h1>
				</summary>
				<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::CARD_BODY ); ?>">
					<?php
					if ( class_exists( 'SearchForm' ) ) {
						echo SearchForm::show_category_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</div>
			</details>
			</div>

			<?php if ( 'Museums' === $smem_category_name ) : ?>
				<div class="mx-auto w-full p-4 sm:max-w-page-sm md:max-w-page-md lg:max-w-page-lg xl:max-w-page-xl 2xl:max-w-page-2xl">
					<div class="-mx-3 flex flex-wrap">
						<div class="max-w-full min-w-0 flex-1 px-3"><h5 class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::H5, 'break-words' ) ); ?>">National List of Scout Museums: </h5></div>
						<div class="max-w-full min-w-0 flex-1 px-3"><a class="<?php echo esc_attr( $smem_museum_button ); ?>" href="https://storage.scoutingmemories.org/2022/01/aa48b284-smp-museum-list-2022.pdf" target="_blank" rel="noopener"><?php esc_html_e( 'Searchable PDF', 'scouting-memories-2026' ); ?></a></div>
						<div class="max-w-full min-w-0 flex-1 px-3"><a class="<?php echo esc_attr( $smem_museum_button ); ?>" href="<?php echo esc_url( home_url( '/scouting-memories-museum-list' ) ); ?>"><?php esc_html_e( 'View Online', 'scouting-memories-2026' ); ?></a></div>
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
