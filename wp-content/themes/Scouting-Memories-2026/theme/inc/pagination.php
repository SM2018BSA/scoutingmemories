<?php
/**
 * Numbered pagination as a daisyUI join, ported from the old theme's
 * Theme::bootstrap_pagination(). Keeps the search filters (state, council,
 * lodge, camp, start_date, end_date) on every page link, exactly as before.
 *
 * @package Scouting_Memories_2026
 */

if ( ! function_exists( 'scouting_memories_2026_pagination' ) ) :
	/**
	 * Prints or returns the numbered page links for a query.
	 *
	 * @param WP_Query|null $the_query A custom query, or null for the global one.
	 * @param bool          $display   Print (default) or return the markup.
	 * @return string|void The markup when $display is false.
	 */
	function scouting_memories_2026_pagination( $the_query = null, $display = true ) {
		if ( null === $the_query ) {
			global $wp_query;
			$the_query = $wp_query;
		}

		// Carry the search filters into every page link, like the old theme did.
		$add_args = array();
		foreach ( array( 'state', 'council', 'lodge', 'camp', 'start_date', 'end_date' ) as $param ) {
			if ( isset( $_GET[ $param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$add_args[ $param ] = function_exists( 'get_request_parameter' )
					? get_request_parameter( $param )
					: sanitize_text_field( wp_unslash( $_GET[ $param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		$format = get_option( 'permalink_structure' ) ? 'page/%#%/' : '&paged=%#%';

		$pages = paginate_links(
			array(
				'base'         => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
				'format'       => $format,
				'current'      => max( 1, get_query_var( 'paged' ) ),
				'total'        => $the_query->max_num_pages,
				'type'         => 'array',
				'show_all'     => false,
				'end_size'     => 3,
				'mid_size'     => 1,
				'prev_next'    => true,
				'prev_text'    => __( '&laquo; Prev', 'scouting-memories-2026' ),
				'next_text'    => __( 'Next &raquo;', 'scouting-memories-2026' ),
				'add_args'     => $add_args,
				'add_fragment' => '',
			)
		);

		if ( ! is_array( $pages ) ) {
			return null;
		}

		$pagination = '<nav class="pt-8" aria-label="' . esc_attr__( 'Page navigation', 'scouting-memories-2026' ) . '"><div class="join flex-wrap">';
		foreach ( $pages as $page ) {
			$classes = 'join-item btn btn-sm';
			if ( false !== strpos( $page, 'current' ) ) {
				$classes .= ' btn-active';
			}
			if ( false !== strpos( $page, 'dots' ) ) {
				$classes .= ' btn-disabled';
			}
			$pagination .= str_replace( 'page-numbers', esc_attr( $classes ), $page );
		}
		$pagination .= '</div></nav>';

		if ( $display ) {
			echo $pagination; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return null;
		}
		return $pagination;
	}
endif;
