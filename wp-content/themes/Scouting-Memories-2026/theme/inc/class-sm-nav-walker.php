<?php
/**
 * Renders the primary menu as a daisyUI menu.
 *
 * Two levels, like the old Bootstrap walker: a top-level item with children
 * becomes a <details><summary> fold, which daisyUI's menu component opens and
 * closes without JavaScript. Hiding items by role stays with the Nav Menu
 * Roles plugin, which filters the items before any walker runs.
 *
 * @package Scouting_Memories_2026
 */

class SM_Nav_Walker extends Walker_Nav_Menu {

	public function start_lvl( &$output, $depth = 0, $args = null ) {
		// The fold's panel: the old site's green dropdown, scrollable when long.
		$output .= '<ul class="p-2 z-50 w-56 sm_green_bkg_color text-white shadow max-h-96 overflow-y-auto">';
	}

	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '</ul>';
	}

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes      = empty( $item->classes ) ? array() : (array) $item->classes;
		$has_children = in_array( 'menu-item-has-children', $classes, true );
		$is_current   = in_array( 'current-menu-item', $classes, true );

		$output .= '<li' . ( $is_current ? ' class="font-semibold"' : '' ) . '>';

		$title = apply_filters( 'the_title', $item->title, $item->ID );
		$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );

		if ( $has_children && 0 === $depth ) {
			$output .= '<details><summary>' . esc_html( $title ) . '</summary>';
			return;
		}

		$atts = array(
			'href'   => ! empty( $item->url ) ? $item->url : '#',
			'target' => $item->target,
			'rel'    => $item->xfn,
			'title'  => $item->attr_title,
		);
		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( ! empty( $value ) ) {
				$attributes .= ' ' . $attr . '="' . esc_attr( $value ) . '"';
			}
		}

		$output .= '<a' . $attributes . '>' . esc_html( $title ) . '</a>';
	}

	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		$classes      = empty( $item->classes ) ? array() : (array) $item->classes;
		$has_children = in_array( 'menu-item-has-children', $classes, true );

		if ( $has_children && 0 === $depth ) {
			$output .= '</details>';
		}
		$output .= '</li>';
	}
}
