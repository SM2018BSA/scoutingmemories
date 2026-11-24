<?php
/**
 * The green masthead: logo, Scouting Alumni banner and the primary menu —
 * the same three-column layout as the old theme, rebuilt with daisyUI.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

$sm_menu_args = array(
	'theme_location' => 'primary',
	'depth'          => 2,
	'container'      => false,
	'fallback_cb'    => false,
	'walker'         => new SM_Nav_Walker(),
);
?>

<header id="masthead" class="sm_green_bkg_color text-white p-2 w-full">

	<div class="flex items-center justify-between gap-2 w-full">

		<div class="w-1/3">
			<a class="relative block" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<div id="sm_logo" class="absolute" style="top:-2em;">
					<img src="//storage.scoutingmemories.org/2021/02/b5564491-scouting_memories_project-e1613015799300.png"
						class="block max-w-full h-auto"
						style="min-height:120px; min-width:120px"
						alt="Scouting Memories">
				</div>
			</a>
		</div>

		<div class="w-1/3 text-center">
			<a href="http://www.scoutingalumni.org/" target="_blank" rel="noopener">
				<img class="inline-block max-w-full h-auto"
					src="//storage.scoutingmemories.org/2023/08/93036515-bsaaaa_banner.png"
					alt="Scouting Alumni and Alumnae Association">
			</a>
		</div>

		<div class="w-1/3 flex justify-end">

			<nav id="site-navigation" class="hidden lg:block" aria-label="<?php esc_attr_e( 'Main Navigation', 'scouting-memories-2026' ); ?>">
				<?php
				wp_nav_menu(
					array_merge(
						$sm_menu_args,
						array(
							'menu_id'    => 'primary-menu',
							'items_wrap' => '<ul id="%1$s" class="menu menu-horizontal flex-nowrap px-1 %2$s">%3$s</ul>',
						)
					)
				);
				?>
			</nav>

			<div class="dropdown dropdown-end lg:hidden">
				<div tabindex="0" role="button" class="btn btn-ghost" aria-label="<?php esc_attr_e( 'Toggle navigation', 'scouting-memories-2026' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
					</svg>
				</div>
				<?php
				wp_nav_menu(
					array_merge(
						$sm_menu_args,
						array(
							'menu_id'    => 'primary-menu-mobile',
							'items_wrap' => '<ul id="%1$s" tabindex="0" class="menu dropdown-content sm_green_bkg_color text-white z-50 mt-3 w-64 p-2 shadow %2$s">%3$s</ul>',
						)
					)
				);
				?>
			</div>

		</div>

	</div>

</header><!-- #masthead -->
