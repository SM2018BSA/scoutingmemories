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

<footer id="colophon" class="bg-neutral text-neutral-content w-full p-1" role="contentinfo">

	<div class="flex justify-center">
		<a href="https://www.facebook.com/nationalscoutingmuseumbsa/" target="_blank" rel="noopener">
			<img class="max-w-full h-auto"
				src="//storage.scoutingmemories.org/2020/10/8fa5300b-nsm_psr-banner-20201013.png"
				height="80" width="640" alt="National Scouting Museum">
		</a>
	</div>

	<div id="footer-bottom" class="w-full p-4">
		<div class="text-center">
			<div class="mb-2">&copy; <?php echo esc_html( date( 'Y' ) ); ?> Scouting Memories Project | Boy Scouts of America</div>
			<div class="flex flex-wrap justify-center">
				<a class="link link-hover px-3 border-r border-neutral-content/40" href="<?php echo esc_url( site_url( '/contact-us/' ) ); ?>">Contact Us</a>
				<a class="link link-hover px-3 border-r border-neutral-content/40" href="https://scoutingmemories.org/publishing-guidelines/">Publishing Guidelines</a>
				<a class="link link-hover px-3" href="<?php echo esc_url( site_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a>
			</div>
		</div>
	</div>

</footer>
