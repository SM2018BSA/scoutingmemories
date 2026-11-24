<?php
/**
 * The wide search form (Info Page and /category/ archives). Included by the
 * core plugin's SearchForm::show_form2(), which provides $sm_states_options.
 *
 * @package Scouting_Memories_2026
 */

?>

<div class="w-full lg:w-3/4 mx-auto">
	<form method="post" enctype="multipart/form-data" class="grid grid-cols-12 gap-x-2" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'search_form', 'search_nonce' ); ?>
		<input type="hidden" name="action" value="search_form">
		<input type="hidden" name="page" value="info-page">

		<div class="mb-2 col-span-12">
			<label class="block text-sm">Select the following optional fields to search memories.</label>
		</div>

		<div class="mb-2 col-span-12 md:col-span-4">
			<label class="block text-sm mb-1" for="select_state">Select State</label>
			<select name="select_state[]" class="select w-full" id="select_state" multiple="multiple">
				<?php echo $sm_states_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</div>
		<div class="mb-2 col-span-6 md:col-span-4">
			<label class="block text-sm mb-1" for="start_date">Start Date</label>
			<input name="start_date" class="input w-full" id="start_date" value="1900" />
		</div>
		<div class="mb-2 col-span-6 md:col-span-4">
			<label class="block text-sm mb-1" for="end_date">End Date</label>
			<input name="end_date" class="input w-full" id="end_date" value="2020" />
		</div>

		<?php include __DIR__ . '/search-fields-downstream.php'; ?>

		<?php include __DIR__ . '/search-footer.php'; ?>
	</form>
</div>
