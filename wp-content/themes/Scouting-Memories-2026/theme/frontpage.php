<?php
/**
 * The front page: banner carousel plus the three card-tabs
 * (About / Search Memories / How To Guides).
 *
 * The carousel is daisyUI's scroll-snap carousel with a small autoplay script;
 * the card-tabs are radio buttons styled as cards, switched entirely in CSS
 * (see "Front page card-tabs" in tailwind/custom/components/scouting-memories.css).
 * The search form itself comes from the core plugin's SearchForm, exactly as
 * the old theme embedded it.
 *
 * Template Name: Frontpage
 *
 * @package Scouting_Memories_2026
 */

get_header();
?>

	<div id="primary" class="content-area mt-12">
		<main id="main" class="site-main w-full" role="main">

			<div class="flex flex-col items-center">

				<div class="w-full sm:w-10/12 md:w-9/12 lg:w-8/12 xl:w-7/12 2xl:w-5/12">
					<div id="carouselFrontpage" class="carousel w-full mb-4 rounded-box">
						<div class="carousel-item w-full">
							<img src="https://storage.scoutingmemories.org/2021/02/935b3e27-smp-banner-graphic-1.jpg"
								class="block w-full" alt="Scouting Memories Banner 1">
						</div>
						<div class="carousel-item w-full">
							<img src="https://storage.scoutingmemories.org/2021/02/8a5d960a-smp-banner-graphic-2.jpg"
								class="block w-full" alt="Scouting Memories Banner 2">
						</div>
						<div class="carousel-item w-full">
							<img src="https://storage.scoutingmemories.org/2021/02/18062451-smp-banner-graphic-3.jpg"
								class="block w-full" alt="Scouting Memories Banner 3">
						</div>
					</div>
				</div>

				<div class="w-full sm:w-10/12 md:w-9/12 lg:w-8/12 xl:w-7/12 2xl:w-5/12 ft-tabs">

					<input type="radio" name="ft-tab" id="ft-about" checked>
					<input type="radio" name="ft-tab" id="ft-search">
					<input type="radio" name="ft-tab" id="ft-video">

					<ul class="ft-cards flex flex-col sm:flex-row sm:flex-wrap sm:justify-center mb-4" role="tablist">
						<li class="sm:flex-1 p-3 flex justify-center" role="presentation">
							<label for="ft-about" class="cursor">
								<div class="card card-border bg-base-100 shadow-sm" style="width: 14em;">
									<div class="card-body">
										<h5 class="text-center text-lg">Who We Are and What We&rsquo;re Doing</h5>
									</div>
								</div>
							</label>
						</li>
						<li class="sm:flex-1 p-3 flex justify-center" role="presentation">
							<label for="ft-search" class="cursor">
								<div class="card card-border bg-base-100 shadow-sm" style="width: 14em;">
									<div class="card-body">
										<h5 class="text-center text-lg">Search Memories</h5>
									</div>
								</div>
							</label>
						</li>
						<li class="sm:flex-1 p-3 flex justify-center" role="presentation">
							<label for="ft-video" class="cursor">
								<div class="card card-border bg-base-100 shadow-sm" style="width: 14em;">
									<div class="card-body">
										<h5 class="text-center text-lg">How To Guides</h5>
									</div>
								</div>
							</label>
						</li>
					</ul>

					<div class="ft-panes w-full">
						<div class="ft-pane ft-pane-about m-4" role="tabpanel">
							<div class="p-4 mx-auto">
								<p>The Scouting Memories Project is an interactive, searchable archive website of Scouting
									history in the United States.</p>
								<p>As the national repository of the BSA, the Scouting Memories collection contains indexes
									of 2,323 councils (existing and past), 3,550 camps (present and
									past), and 1,002 lodges (present and past, including camp honor societies).</p>
								<p>Every council, camp, and lodge has a unique page to capture Scouting Memories in six
									categories: History, Photographs, Video, Oral History, Memorabilia, and Scout
									Museums.</p>
								<p>Under development through 2024, the website is nationally-chartered by the Boy Scouts and
									sponsored jointly by the National Scouting Museum, the National Order of the Arrow, and
									the Scouting Alumni Association.</p>
								<p>Currently Scout historians around the nation are digitizing and archiving their local
									historical information.</p>
								<p>After 2024, a public interface with gatekeeper monitoring for sharing of historical
									information is planned. Currently the general public may view and search the Scouting
									Memories website at: scoutingmemories.org </p>
							</div>
						</div>
						<div class="ft-pane ft-pane-search m-4" role="tabpanel">
							<?php echo SearchForm::show_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
						<div class="ft-pane ft-pane-video m-4" role="tabpanel">
							<iframe width="100%" height="400"
								src="https://www.youtube.com/embed/a_lF5F-oeEY?rel=0&modestbranding=1"
								title="YouTube video player" frameborder="0"
								allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
								allowfullscreen></iframe>
						</div>
					</div>

				</div>

			</div>

		</main><!-- #main -->
	</div>

	<script>
		// Carousel autoplay: advance every 10 seconds, like the old Bootstrap carousel.
		(function () {
			var carousel = document.getElementById('carouselFrontpage');
			if (!carousel) return;
			var index = 0;
			setInterval(function () {
				index = (index + 1) % carousel.children.length;
				carousel.scrollTo({ left: carousel.clientWidth * index, behavior: 'smooth' });
			}, 10000);
		})();
	</script>

<?php
get_footer();
