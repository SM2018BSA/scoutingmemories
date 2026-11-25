<?php
/**
 * The primary menu's walker.
 *
 * @package Scouting_Memories_2026
 */

/**
 * Renders the primary menu, printing every Tailwind class on the li, the link and the
 * sub-menu itself (the old site's Bootstrap navbar-dark colours with its green dropdown).
 *
 * Two levels, like the old Bootstrap walker: a top-level item with children becomes a
 * <details><summary> fold, which the browser opens and closes without JavaScript. Alpine
 * (the "menuFold" part in javascript/script.js) closes it on Escape and on a click outside,
 * and keeps one open at a time. Hiding items by role stays with the Nav Menu Roles plugin,
 * which filters the items before any walker runs.
 *
 * The same menu is printed twice by the header: in the bar (from 992px, the lg: classes)
 * and in the panel under the bar (below 992px, the classes without lg:).
 */
class Scouting_Memories_2026_Nav_Walker extends Walker_Nav_Menu {

	// A top-level item: the anchor for its dropdown panel.
	const ITEM = 'group/item relative';

	// A top-level link or fold title: white at 55%, 75% under the pointer.
	const TOP_LINK = 'block cursor-pointer py-2.5 text-base text-white/55 no-underline hover:text-white/75 focus:text-white/75 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current lg:p-2';

	// The same for the page you are on (or its parent): full white.
	const TOP_LINK_CURRENT = 'block cursor-pointer py-2.5 text-base text-white no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current lg:p-2';

	// A fold: its title turns full white while it is open.
	const FOLD = '[&[open]>summary]:text-white';

	// A fold's title: no browser marker, Bootstrap's small down-pointing caret after the words.
	const FOLD_TITLE = "list-none after:ml-[.255em] after:inline-block after:border-x-[.3em] after:border-t-[.3em] after:border-b-0 after:border-x-transparent after:align-[.255em] after:content-[''] [&::-webkit-details-marker]:hidden";

	// The dropdown panel: green, at most 400px tall, then it scrolls. Under its item in the bar
	// (the last one opens leftwards so it stays on the screen); in place in the small-screen panel.
	const PANEL = 'm-0 mt-0.5 max-h-[400px] min-w-40 list-none overflow-y-auto rounded-md border border-black/[.175] bg-brand py-2 text-left text-base lg:absolute lg:top-full lg:left-0 lg:z-[1000] lg:max-w-[calc(100vw-1.5rem)] lg:group-last/item:right-0 lg:group-last/item:left-auto';

	// A link in the dropdown: pale grey, white on a darker strip under the pointer.
	const PANEL_LINK = 'block w-full px-4 py-2.5 font-normal text-menu-text no-underline hover:bg-black/10 hover:text-white focus:bg-black/10 focus:text-white focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-current active:bg-brand-dark active:text-white lg:py-1 lg:whitespace-nowrap';

	// The same for the page you are on: white on the darker green.
	const PANEL_LINK_CURRENT = 'block w-full bg-brand-dark px-4 py-2.5 font-normal text-white no-underline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-current lg:py-1 lg:whitespace-nowrap';

	/**
	 * Opens a sub-menu: the green fold panel, scrollable when long.
	 *
	 * @param string        $output Used to append additional content (passed by reference).
	 * @param int           $depth  Depth of the menu item.
	 * @param stdClass|null $args   An object of wp_nav_menu() arguments.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '<ul class="' . esc_attr( self::PANEL ) . '">';
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

		if ( 0 === $depth ) {
			$output    .= '<li class="' . esc_attr( self::ITEM ) . '">';
			$link_class = $is_current ? self::TOP_LINK_CURRENT : self::TOP_LINK;
		} else {
			$output    .= '<li>';
			$link_class = $is_current ? self::PANEL_LINK_CURRENT : self::PANEL_LINK;
		}

		// WordPress's own menu filters, applied exactly as the core walker applies them.
		$title = apply_filters( 'the_title', $item->title, $item->ID ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		if ( $has_children && 0 === $depth ) {
			$output .= '<details name="primary-menu-fold" class="' . esc_attr( self::FOLD ) . '" x-data="menuFold" @toggle="only()" @click.outside="shut()" @keydown.escape.window="shut(true)">';
			$output .= '<summary class="' . esc_attr( Scouting_Memories_2026_UI::classes( $link_class, self::FOLD_TITLE ) ) . '">' . esc_html( $title ) . '</summary>';
			return;
		}

		$atts = array(
			'href'         => ! empty( $item->url ) ? $item->url : '#',
			'target'       => $item->target,
			'rel'          => $item->xfn,
			'title'        => $item->attr_title,
			'aria-current' => ! empty( $item->current ) ? 'page' : '',
		);
		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		// The classes are this walker's own: a filter cannot swap them for something else.
		$atts['class'] = $link_class;

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
