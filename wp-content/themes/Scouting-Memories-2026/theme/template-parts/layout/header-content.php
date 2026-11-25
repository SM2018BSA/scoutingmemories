<?php
/**
 * The green masthead, laid out as on the old site: the badge logo, the Scouting Alumni
 * banner and the primary menu, in three equal columns.
 *
 * From 992px wide the menu shows in the bar. Below that a button opens the same menu as a
 * panel under the bar. Alpine (the "siteMenu" part in javascript/script.js) keeps
 * aria-expanded in step and closes the panel on Escape and on a tap outside.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

$smem_menu_args = array(
	'theme_location' => 'primary',
	'depth'          => 2,
	'container'      => false,
	'fallback_cb'    => false,
	'walker'         => new Scouting_Memories_2026_Nav_Walker(),
);
?>

<header id="masthead" class="relative z-40 w-full bg-brand p-2 text-white" x-data="siteMenu" @keydown.escape.window="close(true)" @click.window="outside($event)">

	<div class="grid grid-cols-3 items-center px-3 py-2">

		<div class="min-w-0">
			<a class="relative block" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<div id="sm_logo" class="absolute top-[-2em]">
					<img class="block h-auto min-h-[120px] w-2/5 max-w-full min-w-[120px] sm:w-[45%] md:w-1/2 lg:w-3/4 xl:w-[85%] min-[1600px]:w-full"
						src="<?php echo esc_url( scouting_memories_2026_logo_url() ); ?>"
						alt="<?php esc_attr_e( 'Scouting Memories', 'scouting-memories-2026' ); ?>">
				</div>
			</a>
		</div>

		<div class="min-w-0 text-center">
			<a id="alumni-banner" href="https://www.scoutingalumni.org/" target="_blank" rel="noopener">
				<img class="inline h-auto max-w-full align-middle"
					src="https://storage.scoutingmemories.org/2023/08/93036515-bsaaaa_banner.png"
					alt="<?php esc_attr_e( 'Scouting Alumni and Alumnae Association (opens in a new tab)', 'scouting-memories-2026' ); ?>">
			</a>
		</div>

		<div class="flex min-w-0 items-center justify-end pr-3 lg:px-6">

			<nav id="site-navigation" class="hidden min-w-0 lg:block" aria-label="<?php esc_attr_e( 'Main', 'scouting-memories-2026' ); ?>">
				<?php
				wp_nav_menu(
					array_merge(
						$smem_menu_args,
						array(
							'menu_id'    => 'primary-menu',
							'menu_class' => 'm-0 flex list-none flex-wrap items-center justify-end py-2',
						)
					)
				);
				?>
			</nav>

			<button id="mobile-menu-toggle" type="button" x-ref="toggle" @click="toggle()"
				class="cursor-pointer rounded-md border border-white/10 bg-transparent px-3 py-1 text-xl leading-none text-white/55 focus:ring-4 focus:ring-current focus:outline-0 lg:hidden"
				aria-expanded="false" :aria-expanded="open" aria-controls="mobile-navigation"
				aria-label="<?php esc_attr_e( 'Toggle navigation', 'scouting-memories-2026' ); ?>">
				<svg class="inline-block size-[1.5em] align-middle" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 30" fill="none" aria-hidden="true" focusable="false">
					<path class="stroke-current stroke-2" stroke-linecap="round" stroke-miterlimit="10" d="M4 7h22M4 15h22M4 23h22" />
				</svg>
			</button>

		</div>

	</div>

	<nav id="mobile-navigation" x-ref="panel" class="absolute inset-x-0 top-full max-h-[calc(100vh-5rem)] overflow-y-auto bg-brand shadow-lg lg:hidden" aria-label="<?php esc_attr_e( 'Main, small screens', 'scouting-memories-2026' ); ?>" hidden :hidden="! open">
		<?php
		wp_nav_menu(
			array_merge(
				$smem_menu_args,
				array(
					'menu_id'    => 'primary-menu-mobile',
					'menu_class' => 'm-0 flex list-none flex-col px-5 py-2',
				)
			)
		);
		?>
	</nav>

</header><!-- #masthead -->
