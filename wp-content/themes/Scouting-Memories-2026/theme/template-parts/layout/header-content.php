<?php
/**
 * The green masthead: the badge logo, the Scouting Alumni banner and the primary menu.
 *
 * Wide screens show the menu in the bar. Below the lg breakpoint a button opens the same
 * menu as a panel under the bar (javascript/script.js keeps aria-expanded in step, closes
 * on Escape and on a tap outside). Without JavaScript the panel is simply always open.
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

<header id="masthead" class="sm-masthead sm_green_bkg_color text-white relative z-40 w-full">

	<div class="sm-masthead-bar grid items-center gap-2 px-3">

		<div class="sm-masthead-logo relative self-stretch">
			<a id="sm_logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<img src="<?php echo esc_url( scouting_memories_2026_logo_url() ); ?>"
					width="300" height="300"
					alt="<?php esc_attr_e( 'Scouting Memories Project: home', 'scouting-memories-2026' ); ?>">
			</a>
		</div>

		<div class="min-w-0 text-center">
			<a class="inline-block" href="https://www.scoutingalumni.org/" target="_blank" rel="noopener">
				<img class="sm-masthead-banner inline-block w-auto max-w-full"
					src="https://storage.scoutingmemories.org/2023/08/93036515-bsaaaa_banner.png"
					alt="<?php esc_attr_e( 'Scouting Alumni and Alumnae Association (opens in a new tab)', 'scouting-memories-2026' ); ?>">
			</a>
		</div>

		<div class="flex justify-end">

			<nav id="site-navigation" class="hidden lg:block" aria-label="<?php esc_attr_e( 'Main', 'scouting-memories-2026' ); ?>">
				<?php
				wp_nav_menu(
					array_merge(
						$smem_menu_args,
						array(
							'menu_id'    => 'primary-menu',
							'items_wrap' => '<ul id="%1$s" class="menu menu-horizontal flex-nowrap whitespace-nowrap px-1 %2$s">%3$s</ul>',
						)
					)
				);
				?>
			</nav>

			<button id="mobile-menu-toggle" type="button" class="btn btn-ghost btn-square text-white hover:text-black lg:hidden"
				aria-expanded="false" aria-controls="mobile-navigation"
				aria-label="<?php esc_attr_e( 'Menu', 'scouting-memories-2026' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true" focusable="false">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
				</svg>
			</button>

		</div>

	</div>

	<nav id="mobile-navigation" class="sm-mobile-navigation sm_green_bkg_color lg:hidden" aria-label="<?php esc_attr_e( 'Main, small screens', 'scouting-memories-2026' ); ?>" hidden>
		<?php
		wp_nav_menu(
			array_merge(
				$smem_menu_args,
				array(
					'menu_id'    => 'primary-menu-mobile',
					'items_wrap' => '<ul id="%1$s" class="menu w-full p-2 text-base %2$s">%3$s</ul>',
				)
			)
		);
		?>
	</nav>
	<noscript><style>#mobile-navigation[hidden] { display: block; } #mobile-menu-toggle { display: none; }</style></noscript>

</header><!-- #masthead -->
