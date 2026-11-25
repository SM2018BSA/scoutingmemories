<?php
/**
 * The front page: the banner slides plus the three card-tabs
 * (About / Search Memories / How To Guides).
 *
 * The banners sit side by side in a strip that Alpine (the "bannerSlides" part in
 * javascript/script.js) moves one banner to the left every 10 seconds; it stops for anyone
 * who asked their device for less motion. Without JavaScript the first banner shows.
 * The card-tabs are three radio buttons with a card as each one's label. The chosen radio
 * turns its card blue and shows its pane, with Tailwind's group-has-[...] variants on the
 * cards and panes themselves: no script and no stylesheet rule.
 * The search form itself comes from the core plugin's SearchForm, exactly as
 * the old theme embedded it.
 *
 * Template Name: Frontpage
 *
 * @package Scouting_Memories_2026
 */

// The width of the two centred columns at each screen size (the old col-md-9 ... col-xxl-5).
$smem_column = 'w-full px-3 md:w-9/12 lg:w-8/12 xl:w-7/12 2xl:w-5/12';

// One tab card: the old 14em-wide Bootstrap card, with a ring when its radio has the keyboard.
$smem_card = Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CARD, 'w-[14em] max-w-full cursor-pointer' );

get_header();
?>

	<div id="primary" class="mt-12">
		<main id="main" class="<?php echo esc_attr( Scouting_Memories_2026_UI::CONTAINER_FLUID ); ?>">

			<div class="-mx-3 flex flex-col items-center">

				<div class="<?php echo esc_attr( $smem_column ); ?>">
					<div id="carouselFrontpage" class="relative mb-4 overflow-hidden" role="region" aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'Scouting Memories banners', 'scouting-memories-2026' ); ?>"
						x-data="bannerSlides" @pointerenter="paused = true" @pointerleave="paused = false" @focusin="paused = true" @focusout="paused = false">
						<div class="flex transition-transform duration-[600ms] ease-in-out motion-reduce:transition-none"
							:class="{ '-translate-x-full': 1 === slide, '-translate-x-[200%]': 2 === slide }">
							<div class="w-full flex-none">
								<img src="https://storage.scoutingmemories.org/2021/02/935b3e27-smp-banner-graphic-1.jpg"
									class="block w-full" alt="Scouting Memories Banner 1">
							</div>
							<div class="w-full flex-none">
								<img src="https://storage.scoutingmemories.org/2021/02/8a5d960a-smp-banner-graphic-2.jpg"
									class="block w-full" alt="Scouting Memories Banner 2">
							</div>
							<div class="w-full flex-none">
								<img src="https://storage.scoutingmemories.org/2021/02/18062451-smp-banner-graphic-3.jpg"
									class="block w-full" alt="Scouting Memories Banner 3">
							</div>
						</div>
					</div>
				</div>

				<div class="group/front <?php echo esc_attr( $smem_column ); ?>">

					<input class="sr-only" type="radio" name="front-pane" id="front-about" checked>
					<input class="sr-only" type="radio" name="front-pane" id="front-search">
					<input class="sr-only" type="radio" name="front-pane" id="front-video">

					<ul class="mb-4 flex list-none flex-col flex-wrap pl-0 lg:flex-row">
						<li class="flex justify-center p-4 lg:w-1/3">
							<label for="front-about" class="<?php echo esc_attr( $smem_card ); ?> group-has-[#front-about:checked]/front:bg-blue group-has-[#front-about:checked]/front:text-white group-has-[#front-about:focus-visible]/front:outline-3 group-has-[#front-about:focus-visible]/front:outline-offset-3 group-has-[#front-about:focus-visible]/front:outline-brand">
								<span class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CARD_BODY, 'block' ) ); ?>">
									<span class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::H5, 'block text-center' ) ); ?>"><?php esc_html_e( 'Who We Are and What We’re Doing', 'scouting-memories-2026' ); ?></span>
								</span>
							</label>
						</li>
						<li class="flex justify-center p-4 lg:w-1/3">
							<label for="front-search" class="<?php echo esc_attr( $smem_card ); ?> group-has-[#front-search:checked]/front:bg-blue group-has-[#front-search:checked]/front:text-white group-has-[#front-search:focus-visible]/front:outline-3 group-has-[#front-search:focus-visible]/front:outline-offset-3 group-has-[#front-search:focus-visible]/front:outline-brand">
								<span class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CARD_BODY, 'block' ) ); ?>">
									<span class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::H5, 'block text-center' ) ); ?>"><?php esc_html_e( 'Search Memories', 'scouting-memories-2026' ); ?></span>
								</span>
							</label>
						</li>
						<li class="flex justify-center p-4 lg:w-1/3">
							<label for="front-video" class="<?php echo esc_attr( $smem_card ); ?> group-has-[#front-video:checked]/front:bg-blue group-has-[#front-video:checked]/front:text-white group-has-[#front-video:focus-visible]/front:outline-3 group-has-[#front-video:focus-visible]/front:outline-offset-3 group-has-[#front-video:focus-visible]/front:outline-brand">
								<span class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CARD_BODY, 'block' ) ); ?>">
									<span class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::H5, 'block text-center' ) ); ?>"><?php esc_html_e( 'How To Guides', 'scouting-memories-2026' ); ?></span>
								</span>
							</label>
						</li>
					</ul>

					<div class="mx-auto w-full px-3 sm:max-md:max-w-page-sm">
						<div class="m-4 hidden group-has-[#front-about:checked]/front:block">
							<div class="mx-auto p-4">
								<p class="mb-4">The Scouting Memories Project is an interactive, searchable archive website of Scouting
									history in the United States.</p>
								<p class="mb-4">As the national repository of the BSA, the Scouting Memories collection contains indexes
									of 2,323 councils (existing and past), 3,550 camps (present and
									past), and 1,002 lodges (present and past, including camp honor societies).</p>
								<p class="mb-4">Every council, camp, and lodge has a unique page to capture Scouting Memories in six
									categories: History, Photographs, Video, Oral History, Memorabilia, and Scout
									Museums.</p>
								<p class="mb-4">Under development through 2024, the website is nationally-chartered by the Boy Scouts and
									sponsored jointly by the National Scouting Museum, the National Order of the Arrow, and
									the Scouting Alumni Association.</p>
								<p class="mb-4">Currently Scout historians around the nation are digitizing and archiving their local
									historical information.</p>
								<p class="mb-4">After 2024, a public interface with gatekeeper monitoring for sharing of historical
									information is planned. Currently the general public may view and search the Scouting
									Memories website at: scoutingmemories.org </p>
							</div>
						</div>
						<div class="m-4 hidden group-has-[#front-search:checked]/front:block">
							<?php echo SearchForm::show_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="m-4 hidden group-has-[#front-video:checked]/front:block">
							<iframe class="h-[400px] w-full" loading="lazy"
								src="https://www.youtube.com/embed/a_lF5F-oeEY?rel=0&modestbranding=1"
								title="<?php esc_attr_e( 'How To Guides video', 'scouting-memories-2026' ); ?>"
								allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
								allowfullscreen></iframe>
						</div>
					</div>

				</div>

			</div>

		</main><!-- #main -->
	</div>

<?php
get_footer();
