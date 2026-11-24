<?php
/**
 * The category pages' search form (adds the category picker). Included by the
 * core plugin's SearchForm::show_category_form(), which provides
 * $smem_states_options and $smem_category. Submits to action search_category_form.
 *
 * @package Scouting_Memories_2026
 */

$smem_category_slug = ( isset( $smem_category ) && $smem_category instanceof WP_Term ) ? $smem_category->slug : '';
?>

<div class="w-full">
	<form method="post" enctype="multipart/form-data" class="grid grid-cols-12 gap-x-2" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'search_form', 'search_nonce' ); ?>
		<input type="hidden" name="action" value="search_category_form">
		<input type="hidden" name="page" value="<?php echo esc_attr( $smem_category_slug ); ?>">

		<div class="mb-2 col-span-12">
			<label class="block text-sm"><?php esc_html_e( 'Select the following optional fields to search memories.', 'scouting-memories-2026' ); ?></label>
		</div>

		<div id="select_category_loading" class="flex justify-center col-span-6 md:col-span-3">
			<span class="loading loading-spinner loading-lg sm_green_color" role="status"></span>
		</div>
		<div class="mb-2 col-span-6 md:col-span-3 hidden">
			<label class="block text-sm mb-1" for="cat"><?php esc_html_e( 'Select Category', 'scouting-memories-2026' ); ?></label>
			<select name="cat" class="select w-full" id="cat" multiple="multiple">
				<option value="3"><?php esc_html_e( 'History', 'scouting-memories-2026' ); ?></option>
				<option value="4"><?php esc_html_e( 'Memorabilia', 'scouting-memories-2026' ); ?></option>
				<option value="5"><?php esc_html_e( 'Photographs', 'scouting-memories-2026' ); ?></option>
				<option value="6"><?php esc_html_e( 'Oral History', 'scouting-memories-2026' ); ?></option>
				<option value="7"><?php esc_html_e( 'Museums', 'scouting-memories-2026' ); ?></option>
				<option value="8"><?php esc_html_e( 'Movies', 'scouting-memories-2026' ); ?></option>
			</select>
		</div>

		<div id="select_states_loading" class="flex justify-center col-span-6 md:col-span-3">
			<span class="loading loading-spinner loading-lg sm_green_color" role="status"></span>
		</div>
		<div class="mb-2 col-span-6 md:col-span-3 hidden">
			<label class="block text-sm mb-1" for="select_state"><?php esc_html_e( 'Select State', 'scouting-memories-2026' ); ?></label>
			<select name="select_state[]" class="select w-full" id="select_state" multiple="multiple">
				<?php echo $smem_states_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</div>

		<div class="mb-2 col-span-6 md:col-span-3">
			<label class="block text-sm mb-1" for="start_date"><?php esc_html_e( 'Start Date', 'scouting-memories-2026' ); ?></label>
			<input name="start_date" class="input w-full" id="start_date" value="1900" />
		</div>
		<div class="mb-2 col-span-6 md:col-span-3">
			<label class="block text-sm mb-1" for="end_date"><?php esc_html_e( 'End Date', 'scouting-memories-2026' ); ?></label>
			<input name="end_date" class="input w-full" id="end_date" value="2020" />
		</div>

		<?php require __DIR__ . '/search-fields-downstream.php'; ?>

		<?php require __DIR__ . '/search-footer.php'; ?>
	</form>
</div>
