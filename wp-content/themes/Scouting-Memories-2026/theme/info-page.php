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
 */
function scouting_memories_2026_info_link( $path, $cat_id ) {
	$args = array( 'cat' => $cat_id );
	foreach ( array( 'state', 'council', 'lodge', 'camp', 'start_date', 'end_date' ) as $param ) {
		$args[ $param ] = function_exists( 'get_request_parameter' ) ? (string) get_request_parameter( $param ) : '';
	}
	return esc_url( add_query_arg( $args, home_url( $path ) ) );
}

get_header();
?>

	<div class="content-area max-w-6xl mx-auto mt-8">

		<div class="grid grid-cols-1 md:grid-cols-3 items-center gap-4">

			<div class="flex flex-col items-center justify-center gap-10 py-6">
				<a class="btn btn-primary" href="<?php echo scouting_memories_2026_info_link( '/history/', 3 ); ?>">History</a>
				<a class="btn btn-primary" href="<?php echo scouting_memories_2026_info_link( '/photographs/', 5 ); ?>">Photographs</a>
				<a class="btn btn-primary" href="<?php echo scouting_memories_2026_info_link( '/movies/', 8 ); ?>">Movies</a>
			</div>

			<div class="hidden md:block">
				<img class="max-w-full h-auto" src="https://storage.scoutingmemories.org/2019/02/904438c9-1911-boy-scount-original-cove-2r.jpg" alt="">
			</div>

			<div class="flex flex-col items-center justify-center gap-10 py-6">
				<a class="btn btn-primary" href="<?php echo scouting_memories_2026_info_link( '/oral-history/', 6 ); ?>">Oral History</a>
				<a class="btn btn-primary" href="<?php echo scouting_memories_2026_info_link( '/memorabilia/', 4 ); ?>">Memorabilia</a>
				<a class="btn btn-primary" href="<?php echo scouting_memories_2026_info_link( '/museums/', 7 ); ?>">Museums</a>
			</div>

		</div>

		<div class="mt-8">
			<?php
			if ( class_exists( 'SearchForm' ) ) {
				echo SearchForm::show_form2(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
		</div>

	</div>

<?php
get_footer();
