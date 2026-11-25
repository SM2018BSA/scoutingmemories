<?php
/**
 * The green masthead, laid out as on the old site: the badge logo, the Scouting Alumni
 * banner and the primary menu, in three equal columns.
 *
 * From 992px wide the menu shows in the bar. Below that a button opens the same menu as a
 * panel under the bar (javascript/script.js keeps aria-expanded in step, closes on Escape
 * and on a tap outside). Without JavaScript the panel is simply always open.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

$smem_menu_args = array(
	'theme_location' => 'primary',
	'depth'          => 2,
	'container'      => false,
	'menu_class'     => 'sm-nav',
	'fallback_cb'    => false,
	'walker'         => new Scouting_Memories_2026_Nav_Walker(),
);
?>

<header id="masthead" class="sm-masthead sm_green_bkg_color relative z-40 w-full p-2 text-white">

	<div class="sm-masthead-bar grid grid-cols-3 items-center px-3 py-2">

		<div class="sm-masthead-logo min-w-0">
			<a class="relative block" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<div id="sm_logo">
					<img src="<?php echo esc_url( scouting_memories_2026_logo_url() ); ?>"
						alt="<?php esc_attr_e( 'Scouting Memories', 'scouting-memories-2026' ); ?>">
				</div>
			</a>
		</div>

		<div class="min-w-0 text-center">
			<a href="https://www.scoutingalumni.org/" target="_blank" rel="noopener">
				<img class="inline h-auto max-w-full align-baseline"
					src="https://storage.scoutingmemories.org/2023/08/93036515-bsaaaa_banner.png"
					alt="<?php esc_attr_e( 'Scouting Alumni and Alumnae Association (opens in a new tab)', 'scouting-memories-2026' ); ?>">
			</a>
		</div>

		<div class="sm-masthead-menu">

			<nav id="site-navigation" class="sm-desktop-navigation" aria-label="<?php esc_attr_e( 'Main', 'scouting-memories-2026' ); ?>">
				<?php
				wp_nav_menu(
					array_merge(
						$smem_menu_args,
						array(
							'menu_id' => 'primary-menu',
						)
					)
				);
				?>
			</nav>

			<button id="mobile-menu-toggle" type="button" class="sm-navbar-toggler"
				aria-expanded="false" aria-controls="mobile-navigation"
				aria-label="<?php esc_attr_e( 'Toggle navigation', 'scouting-memories-2026' ); ?>">
				<span class="sm-navbar-toggler-icon"></span>
			</button>

		</div>

	</div>

	<nav id="mobile-navigation" class="sm-mobile-navigation sm_green_bkg_color" aria-label="<?php esc_attr_e( 'Main, small screens', 'scouting-memories-2026' ); ?>" hidden>
		<?php
		wp_nav_menu(
			array_merge(
				$smem_menu_args,
				array(
					'menu_id' => 'primary-menu-mobile',
				)
			)
		);
		?>
	</nav>
	<noscript><style>#mobile-navigation[hidden] { display: block; } #mobile-menu-toggle { display: none; }</style></noscript>

</header><!-- #masthead -->
