<?php
/**
 * The Memory Box: Formidable form 39 (Video Interviews) wired to the Cliptakes
 * recorder, ported from the old theme. The glue between the two plugins' markup
 * (frm_* / ctiv-* ids) is the "memoryBox" part in javascript/script.js; the three
 * looks the old page forced on that markup (hidden form messages, the 25px recorder
 * icons, the blue recorder buttons) are Tailwind classes here and in that script.
 *
 * Template Name: Memory Box
 *
 * @package Scouting_Memories_2026
 */

$smem_interview_form = new Form( '39' );

get_header();
?>

	<div id="primary" class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CONTAINER_FLUID, 'mt-12' ) ); ?>">
		<main id="main" class="sm:m-12 sm:p-0 lg:p-12">
			<div class="flex justify-center">
				<div class="w-full md:w-1/2 [&_.ctiv-nav-button_svg]:w-[25px]! [&_.frm_message]:hidden!" x-data="memoryBox">
					<?php echo $smem_interview_form->show_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo Helpers::doShortCode( '[cliptakes_interview templateId="brkOaLmaBpyaAaLoi3mw"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</main><!-- #main -->
	</div>

<?php
get_footer();
