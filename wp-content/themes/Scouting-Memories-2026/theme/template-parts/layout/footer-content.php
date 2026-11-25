<?php
/**
 * The dark footer, as on the old site: the National Scouting Museum banner, then the
 * copyright line and the three standing links in one centred row that wraps on small screens.
 * (The uneven side padding copies the old Bootstrap rows, which sat 12px left of centre.)
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Scouting_Memories_2026
 */

?>

<footer id="colophon" class="w-full bg-ink p-1 text-white" role="contentinfo">

	<div class="flex justify-center pr-6">
		<a href="https://www.facebook.com/nationalscoutingmuseumbsa/" target="_blank" rel="noopener">
			<img class="inline h-auto max-w-full align-middle"
				src="https://storage.scoutingmemories.org/2020/10/8fa5300b-nsm_psr-banner-20201013.png"
				height="80" width="640" alt="<?php esc_attr_e( 'National Scouting Museum on Facebook (opens in a new tab)', 'scouting-memories-2026' ); ?>">
		</a>
	</div>

	<div id="footer-bottom" class="w-full py-4 pr-13 pl-7">
		<div class="flex flex-wrap justify-center">
			<div class="block px-4 py-2 text-base text-light">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php esc_html_e( 'Scouting Memories Project', 'scouting-memories-2026' ); ?>&nbsp; | <?php esc_html_e( 'Boy Scouts of America', 'scouting-memories-2026' ); ?></div>
			<div class="flex justify-center">
				<a class="block px-4 py-2 text-base text-light no-underline hover:text-light" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Contact Us', 'scouting-memories-2026' ); ?></a>
				<a class="block px-4 py-2 text-base text-light no-underline hover:text-light" href="<?php echo esc_url( home_url( '/publishing-guidelines/' ) ); ?>"><?php esc_html_e( 'Publishing Guidelines', 'scouting-memories-2026' ); ?></a>
				<a class="block px-4 py-2 text-base text-white no-underline hover:text-white" href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'scouting-memories-2026' ); ?></a>
			</div>
		</div>
	</div>

</footer>
