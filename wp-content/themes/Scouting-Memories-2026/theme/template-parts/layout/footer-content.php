<?php
/**
 * The dark footer: National Scouting Museum banner, copyright and the three
 * standing links — same content as the old theme, rebuilt with daisyUI.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

?>

<footer id="colophon" class="bg-neutral text-neutral-content w-full p-1">

	<div class="flex justify-center">
		<a href="https://www.facebook.com/nationalscoutingmuseumbsa/" target="_blank" rel="noopener">
			<img class="max-w-full h-auto"
				src="https://storage.scoutingmemories.org/2020/10/8fa5300b-nsm_psr-banner-20201013.png"
				height="80" width="640" alt="<?php esc_attr_e( 'National Scouting Museum on Facebook (opens in a new tab)', 'scouting-memories-2026' ); ?>">
		</a>
	</div>

	<div id="footer-bottom" class="w-full p-4">
		<div class="text-center">
			<div class="mb-2">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php esc_html_e( 'Scouting Memories Project | Boy Scouts of America', 'scouting-memories-2026' ); ?></div>
			<div class="flex flex-wrap justify-center">
				<a class="link link-hover px-3 border-r border-neutral-content/40" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Contact Us', 'scouting-memories-2026' ); ?></a>
				<a class="link link-hover px-3 border-r border-neutral-content/40" href="<?php echo esc_url( home_url( '/publishing-guidelines/' ) ); ?>"><?php esc_html_e( 'Publishing Guidelines', 'scouting-memories-2026' ); ?></a>
				<a class="link link-hover px-3" href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'scouting-memories-2026' ); ?></a>
			</div>
		</div>
	</div>

</footer>
