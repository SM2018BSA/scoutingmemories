<?php
/**
 * The theme's repeated Tailwind class lists, each written once.
 *
 * One job: names for class lists that many templates share (the page container, buttons,
 * cards, form fields, headings, tables, paging), so no template types them twice. Every value
 * is a plain string of Tailwind utility classes, which Tailwind reads from this file like from
 * any template. The sizes, spacing and colours are the old Bootstrap site's.
 *
 * @package Scouting_Memories_2026
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class lists shared by the templates.
 */
final class Scouting_Memories_2026_UI {

	// Page width: centred, 12px at the sides, a fixed width at each screen-size step.
	const CONTAINER       = 'mx-auto w-full px-3 sm:max-w-page-sm md:max-w-page-md lg:max-w-page-lg xl:max-w-page-xl 2xl:max-w-page-2xl';
	const CONTAINER_FLUID = 'mx-auto w-full px-3';

	// Headings (Roboto Slab, medium weight, growing with the screen up to the size given).
	const H1 = 'mt-0 mb-2 font-slab text-[calc(1.375rem+1.5vw)] leading-tight font-medium xl:text-[2.5rem]';
	const H2 = 'mt-0 mb-2 font-slab text-[calc(1.325rem+0.9vw)] leading-tight font-medium xl:text-[2rem]';
	const H3 = 'mt-0 mb-2 font-slab text-[calc(1.3rem+0.6vw)] leading-tight font-medium xl:text-[1.75rem]';
	const H4 = 'mt-0 mb-2 font-slab text-[calc(1.275rem+0.3vw)] leading-tight font-medium xl:text-2xl';
	const H5 = 'mt-0 mb-2 font-slab text-xl leading-tight font-medium';
	const H6 = 'mt-0 mb-2 font-slab text-base leading-tight font-medium';

	// Links in running text.
	const LINK = 'text-blue underline hover:text-blue-link-hover';

	// Buttons: the shape first, then one colour.
	const BTN           = 'inline-block cursor-pointer rounded-md border px-3 py-1.5 text-center align-middle text-base leading-normal font-normal no-underline select-none transition-colors duration-150 focus-visible:ring-4 focus-visible:outline-0 disabled:pointer-events-none disabled:opacity-65';
	const BTN_SM        = 'inline-block cursor-pointer rounded border px-2 py-1 text-center align-middle text-sm leading-normal font-normal no-underline select-none transition-colors duration-150 focus-visible:ring-4 focus-visible:outline-0 disabled:pointer-events-none disabled:opacity-65';
	const BTN_PRIMARY   = 'border-blue bg-blue text-white hover:border-blue-link-hover hover:bg-blue-dark hover:text-white focus-visible:ring-blue/50';
	const BTN_SECONDARY = 'border-grey bg-grey text-white hover:border-grey-dark hover:bg-grey-dark hover:text-white focus-visible:ring-grey/50';
	const BTN_SUCCESS   = 'border-green bg-green text-white hover:border-green-dark hover:bg-green-dark hover:text-white focus-visible:ring-green/50';
	const BTN_DANGER    = 'border-red bg-red text-white hover:border-red-dark hover:bg-red-dark hover:text-white focus-visible:ring-red/50';
	const BTN_INFO      = 'border-cyan bg-cyan text-black hover:border-cyan-dark hover:bg-cyan-dark hover:text-black focus-visible:ring-cyan/50';
	const BTN_PLAIN     = 'border-transparent bg-transparent text-ink';

	// Cards.
	const CARD        = 'relative flex min-w-0 flex-col rounded-md border border-black/[.175] bg-white break-words';
	const CARD_HEADER = 'rounded-t-md border-b border-black/[.175] bg-ink/[.03] px-4 py-2';
	const CARD_BODY   = 'flex-auto p-4';

	// Form fields and their labels.
	const LABEL  = 'mb-2 inline-block';
	const FIELD  = 'block w-full appearance-none rounded-md border border-field-line bg-white px-3 py-1.5 text-base leading-normal font-normal text-ink focus:border-[#86b7fe] focus:ring-4 focus:ring-blue/25 focus:outline-0';
	const SELECT = 'block w-full rounded-md border border-field-line bg-white py-1.5 pr-9 pl-3 text-base leading-normal font-normal text-ink focus:border-[#86b7fe] focus:ring-4 focus:ring-blue/25 focus:outline-0';
	const HELP   = 'mt-1 text-sm text-grey';

	// Tables.
	const TABLE = 'mb-4 w-full border-collapse align-top text-ink';
	const TH    = 'border-b border-line p-2 text-left font-bold';
	const TD    = 'border-b border-line p-2';

	// Paging: a joined row of boxes, blue numbers, the current page solid blue.
	const PAGING         = 'flex flex-wrap list-none pl-0';
	const PAGE_LINK      = '-ml-px block border border-line bg-white px-3 py-1.5 text-blue no-underline first:ml-0 hover:bg-light hover:text-blue-link-hover';
	const PAGE_LINK_HERE = '-ml-px block border border-blue bg-blue px-3 py-1.5 text-white no-underline first:ml-0';

	/**
	 * Several class lists as one string, for a class attribute.
	 *
	 * @param string ...$lists Class lists.
	 */
	public static function classes( string ...$lists ): string {
		return implode( ' ', array_filter( $lists ) );
	}
}
