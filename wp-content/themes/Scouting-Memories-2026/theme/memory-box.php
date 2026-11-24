<?php
/**
 * The Memory Box: Formidable form 39 (Video Interviews) wired to the Cliptakes
 * recorder, ported from the old theme with its glue script kept verbatim - it
 * drives plugin markup (frm_* / ctiv-* ids), not theme markup. The old page's
 * blue button override is kept so the recorder looks the way contributors
 * know it.
 *
 * Template Name: Memory Box
 *
 * @package Scouting_Memories_2026
 */

$sm_interview_form = new Form( '39' );

get_header();
?>

	<div id="primary" class="content-area w-full mt-8">
		<main id="main" class="site-main p-4 lg:p-12" role="main">
			<div class="flex justify-center">
				<div class="w-full md:w-1/2">
					<?php echo $sm_interview_form->show_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo Helpers::doShortCode( '[cliptakes_interview templateId="brkOaLmaBpyaAaLoi3mw"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</main><!-- #main -->
	</div>

	<style>
		.btn-primary {
			color: #fff !important;
			background-color: #0d6efd !important;
			border-color: #0d6efd !important;
		}
		.frm_message { display: none !important; }
		.ctiv-nav-button svg { width: 25px !important; }
	</style>
	<script type="text/javascript">
		(function ($) {

			let button_style = "<div class='frm_style_bootstrap5 with_frm_style js'><div class='frm_submit' ></div></div>";

			$("#ctiv-signup-form .ctiv-signup-row").addClass('hidden');
			$("#form_videointerviews .form-group.frm_submit").addClass('hidden');
			$("#ctiv-main-container button").wrap(button_style).addClass("btn-primary").addClass("btn");
			$("#ctiv-signup-form input[value='Continue']").wrap(button_style).addClass("btn-primary").addClass("btn");

			$(document).on("keyup", "#field_mb_name_first", function () {
				$("#ctiv-signup-first-name").val($("#field_mb_name_first").val());
			});
			$(document).on("keyup", "#field_mb_name_last", function () {
				$("#ctiv-signup-last-name").val($("#field_mb_name_last").val());
			});

			$(document).on("click", ".frm_slider", function () {
				$("#mb_lodge_indexing").toggleClass("hidden");
				$("#mb_lodge_text").toggleClass("hidden");
			});

			$("#ctiv-intro-next").on("click", function () {
				$(".frm_page_num_1 #mbox_submit").click();
			});
			$("#ctiv-signup-form input[value='Continue']").on("click", function () {
				$(".frm_page_num_2 #mbox_submit").click();
				$("#frm_form_39_container").addClass("hidden");
			});

		})(jQuery);
	</script>

<?php
get_footer();
