<?php
/**
 * The main search form (the front page's Search Memories pane). Included by the
 * core plugin's SearchForm::show_form(), which provides $sm_states_options.
 * Submits to admin-post action search_form, which redirects to the Info Page.
 *
 * @package Scouting_Memories_2026
 */

?>

<div class="w-full">
	<form method="post" enctype="multipart/form-data" class="grid grid-cols-12 gap-x-2" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'search_form', 'search_nonce' ); ?>
		<input type="hidden" name="action" value="search_form">
		<input type="hidden" name="page" value="info-page">

		<div class="mb-2 col-span-12">
			<label class="block text-sm">Select the following optional fields to search memories.</label>
		</div>

		<div class="mb-2 col-span-12">
			<label class="block text-sm mb-1" for="select_state">Select State</label>
			<select name="select_state[]" class="select w-full" id="select_state" multiple="multiple" style="display: none;">
				<?php echo $sm_states_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</div>

		<?php include __DIR__ . '/search-fields-downstream.php'; ?>

		<div class="mb-2 col-span-6 pr-1">
			<label class="block text-sm mb-1" for="start_date">Start Date</label>
			<input name="start_date" class="input w-full" id="start_date" value="1900" />
		</div>
		<div class="mb-2 col-span-6 pl-1">
			<label class="block text-sm mb-1" for="end_date">End Date</label>
			<input name="end_date" class="input w-full" id="end_date" value="<?php echo esc_attr( date( 'Y' ) ); ?>" />
		</div>

		<?php include __DIR__ . '/search-footer.php'; ?>
	</form>
</div>
