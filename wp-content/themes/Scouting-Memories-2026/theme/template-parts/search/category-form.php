<?php
/**
 * The category pages' search form (adds the category picker). Included by the
 * core plugin's SearchForm::show_category_form(), which provides
 * $smem_states_options and $smem_category. Submits to action search_category_form.
 *
 * The form is the Alpine component memorySearch (javascript/search.js); the
 * admin-ajax address and the nonce its lists need travel in the form's data
 * attributes. The category and state fields show a spinner until their boxes
 * are built.
 *
 * @package Scouting_Memories_2026
 */

$smem_category_slug = ( isset( $smem_category ) && $smem_category instanceof WP_Term ) ? $smem_category->slug : '';

$smem_label   = Scouting_Memories_2026_UI::LABEL;
$smem_field   = Scouting_Memories_2026_UI::FIELD;
$smem_spinner = 'inline-block size-8 animate-spin rounded-full border-4 border-brand border-r-transparent align-[-0.125em]';
?>

<div class="w-full flex-auto p-4">
	<form method="post" enctype="multipart/form-data" class="-mx-3 flex w-full flex-wrap px-3" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" x-data="memorySearch" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'search_form' ) ); ?>">
		<?php wp_nonce_field( 'search_form', 'search_nonce' ); ?>
		<input type="hidden" name="action" value="search_category_form">
		<input type="hidden" name="page" value="<?php echo esc_attr( $smem_category_slug ); ?>">

		<div class="mb-2 w-full max-w-full shrink-0 px-3">
			<label class="<?php echo esc_attr( $smem_label ); ?>"><?php esc_html_e( 'Select the following optional fields to search memories.', 'scouting-memories-2026' ); ?></label>
		</div>

		<div id="select_category_loading" class="w-1/2 max-w-full shrink-0 px-3 md:w-1/4" :class="{ hidden: ready }">
			<div class="flex justify-center">
				<span class="<?php echo esc_attr( $smem_spinner ); ?>" role="status"><span class="sr-only"><?php esc_html_e( 'Loading...', 'scouting-memories-2026' ); ?></span></span>
			</div>
		</div>
		<div class="mb-2 hidden w-1/2 max-w-full shrink-0 px-3 md:w-1/4" :class="{ hidden: ! ready }">
			<label class="<?php echo esc_attr( $smem_label ); ?>" for="cat"><?php esc_html_e( 'Select Category', 'scouting-memories-2026' ); ?></label>
			<select name="cat" id="cat" multiple="multiple">
				<option value="3"><?php esc_html_e( 'History', 'scouting-memories-2026' ); ?></option>
				<option value="4"><?php esc_html_e( 'Memorabilia', 'scouting-memories-2026' ); ?></option>
				<option value="5"><?php esc_html_e( 'Photographs', 'scouting-memories-2026' ); ?></option>
				<option value="6"><?php esc_html_e( 'Oral History', 'scouting-memories-2026' ); ?></option>
				<option value="7"><?php esc_html_e( 'Museums', 'scouting-memories-2026' ); ?></option>
				<option value="8"><?php esc_html_e( 'Movies', 'scouting-memories-2026' ); ?></option>
			</select>
		</div>

		<div id="select_states_loading" class="w-1/2 max-w-full shrink-0 px-3 md:w-1/4" :class="{ hidden: ready }">
			<div class="flex justify-center">
				<span class="<?php echo esc_attr( $smem_spinner ); ?>" role="status"><span class="sr-only"><?php esc_html_e( 'Loading...', 'scouting-memories-2026' ); ?></span></span>
			</div>
		</div>
		<div class="mb-2 hidden w-1/2 max-w-full shrink-0 px-3 md:w-1/4" :class="{ hidden: ! ready }">
			<label class="<?php echo esc_attr( $smem_label ); ?>" for="select_state"><?php esc_html_e( 'Select State', 'scouting-memories-2026' ); ?></label>
			<select name="select_state[]" id="select_state" multiple="multiple">
				<?php echo $smem_states_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</div>

		<div class="mb-2 w-1/2 max-w-full shrink-0 px-3 md:w-1/4">
			<label class="<?php echo esc_attr( $smem_label ); ?>" for="start_date"><?php esc_html_e( 'Start Date', 'scouting-memories-2026' ); ?></label>
			<input name="start_date" class="<?php echo esc_attr( $smem_field ); ?>" id="start_date" value="1900" />
		</div>
		<div class="mb-2 w-1/2 max-w-full shrink-0 px-3 md:w-1/4">
			<label class="<?php echo esc_attr( $smem_label ); ?>" for="end_date"><?php esc_html_e( 'End Date', 'scouting-memories-2026' ); ?></label>
			<input name="end_date" class="<?php echo esc_attr( $smem_field ); ?>" id="end_date" value="2020" />
		</div>

		<?php
		$smem_downstream_side_by_side = true;
		require __DIR__ . '/search-fields-downstream.php';
		?>

		<?php require __DIR__ . '/search-footer.php'; ?>
	</form>
</div>
