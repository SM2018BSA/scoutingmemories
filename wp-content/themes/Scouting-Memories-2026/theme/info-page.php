<?php
/**
 * The Info Page: six category buttons that carry the visitor's search filters
 * into each category page, a centre image, and the search form below —
 * ported from the old theme.
 *
 * Template Name: Info Page
 *
 * @package Scouting_Memories_2026
 */

/**
 * Builds one category link with the current search filters attached,
 * exactly like the old template's hand-written URLs.
 *
 * @param string $path   The category page's path, for example '/history/'.
 * @param int    $cat_id The category's id.
 * @return string The address, not yet escaped.
 */
function scouting_memories_2026_info_link( $path, $cat_id ) {
	$args = array( 'cat' => $cat_id );
	foreach ( array( 'state', 'council', 'lodge', 'camp', 'start_date', 'end_date' ) as $param ) {
		$args[ $param ] = function_exists( 'get_request_parameter' ) ? (string) get_request_parameter( $param ) : '';
	}
	return add_query_arg( $args, home_url( $path ) );
}

get_header();
?>

	<main id="main" class="site-main content-area sm-container">
		<div class="px-3">

			<div class="grid grid-cols-2 items-center gap-x-6 min-[576px]:grid-cols-1 md:grid-cols-3">

				<div class="frm_submit frm_style_divi with_frm_style flex flex-col items-center justify-center gap-12 py-8 md:py-2">
					<a class="btn btn-primary sm-btn-bs" href="<?php echo esc_url( scouting_memories_2026_info_link( '/history/', 3 ) ); ?>"><?php esc_html_e( 'History', 'scouting-memories-2026' ); ?></a>
					<a class="btn btn-primary sm-btn-bs" href="<?php echo esc_url( scouting_memories_2026_info_link( '/photographs/', 5 ) ); ?>"><?php esc_html_e( 'Photographs', 'scouting-memories-2026' ); ?></a>
					<a class="btn btn-primary sm-btn-bs" href="<?php echo esc_url( scouting_memories_2026_info_link( '/movies/', 8 ) ); ?>"><?php esc_html_e( 'Movies', 'scouting-memories-2026' ); ?></a>
				</div>

				<div class="hidden md:block">
					<img class="inline h-auto max-w-full align-baseline" src="https://storage.scoutingmemories.org/2019/02/904438c9-1911-boy-scount-original-cove-2r.jpg" alt="">
				</div>

				<div class="frm_submit frm_style_divi with_frm_style flex flex-col items-center justify-center gap-12 pt-2 pb-8 md:pb-2">
					<a class="btn btn-primary sm-btn-bs" href="<?php echo esc_url( scouting_memories_2026_info_link( '/oral-history/', 6 ) ); ?>"><?php esc_html_e( 'Oral History', 'scouting-memories-2026' ); ?></a>
					<a class="btn btn-primary sm-btn-bs" href="<?php echo esc_url( scouting_memories_2026_info_link( '/memorabilia/', 4 ) ); ?>"><?php esc_html_e( 'Memorabilia', 'scouting-memories-2026' ); ?></a>
					<a class="btn btn-primary sm-btn-bs" href="<?php echo esc_url( scouting_memories_2026_info_link( '/museums/', 7 ) ); ?>"><?php esc_html_e( 'Museums', 'scouting-memories-2026' ); ?></a>
				</div>

			</div>

			<div>
				<?php
				if ( class_exists( 'SearchForm' ) ) {
					echo SearchForm::show_form2(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>

		</div>
	</main><!-- #main -->

<?php
get_footer();
