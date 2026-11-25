<?php
/**
 * The primary menu's walker.
 *
 * @package Scouting_Memories_2026
 */

/**
 * Renders the primary menu (styled as `.sm-nav` in scouting-memories.css).
 *
 * Two levels, like the old Bootstrap walker: a top-level item with children
 *  becomes a <details><summary> fold, which the browser opens and
 * closes without JavaScript. Hiding items by role stays with the Nav Menu
 * Roles plugin, which filters the items before any walker runs.
 */
class Scouting_Memories_2026_Nav_Walker extends Walker_Nav_Menu {

	/**
	 * Opens a sub-menu: the green fold panel, scrollable when long.
	 *
	 * @param string        $output Used to append additional content (passed by reference).
	 * @param int           $depth  Depth of the menu item.
	 * @param stdClass|null $args   An object of wp_nav_menu() arguments.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		// The fold's panel: the old site's green dropdown (colours and the 400px scroll limit are in the stylesheet).
		$output .= '<ul>';
	}

	/**
	 * Closes a sub-menu.
	 *
	 * @param string        $output Used to append additional content (passed by reference).
	 * @param int           $depth  Depth of the menu item.
	 * @param stdClass|null $args   An object of wp_nav_menu() arguments.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '</ul>';
	}

	/**
	 * Opens a menu item: a link, or a fold (details/summary) when it has children.
	 *
	 * @param string        $output Used to append additional content (passed by reference).
	 * @param WP_Post       $item   The menu item.
	 * @param int           $depth  Depth of the menu item.
	 * @param stdClass|null $args   An object of wp_nav_menu() arguments.
	 * @param int           $id     The menu item's id.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes      = empty( $item->classes ) ? array() : (array) $item->classes;
		$has_children = in_array( 'menu-item-has-children', $classes, true );
		// The old walker marked the current item and the parent of the current item.
		$is_current = in_array( 'current-menu-item', $classes, true ) || in_array( 'current-menu-parent', $classes, true );

		$output .= '<li' . ( $is_current ? ' class="sm-nav-current"' : '' ) . '>';

		// WordPress's own menu filters, applied exactly as the core walker applies them.
		$title = apply_filters( 'the_title', $item->title, $item->ID ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		if ( $has_children && 0 === $depth ) {
			$output .= '<details name="sm-nav"><summary>' . esc_html( $title ) . '</summary>';
			return;
		}

		$atts = array(
			'href'   => ! empty( $item->url ) ? $item->url : '#',
			'target' => $item->target,
			'rel'    => $item->xfn,
			'title'  => $item->attr_title,
		);
		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( ! empty( $value ) ) {
				$attributes .= ' ' . $attr . '="' . esc_attr( $value ) . '"';
			}
		}

		$output .= '<a' . $attributes . '>' . esc_html( $title ) . '</a>';
	}

	/**
	 * Closes a menu item.
	 *
	 * @param string        $output Used to append additional content (passed by reference).
	 * @param WP_Post       $item   The menu item.
	 * @param int           $depth  Depth of the menu item.
	 * @param stdClass|null $args   An object of wp_nav_menu() arguments.
	 */
	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		$classes      = empty( $item->classes ) ? array() : (array) $item->classes;
		$has_children = in_array( 'menu-item-has-children', $classes, true );

		if ( $has_children && 0 === $depth ) {
			$output .= '</details>';
		}
		$output .= '</li>';
	}
}
