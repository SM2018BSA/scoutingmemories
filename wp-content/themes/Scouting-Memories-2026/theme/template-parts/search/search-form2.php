<?php
/**
 * The wide search form (Info Page and /category/ archives). Included by the
 * core plugin's SearchForm::show_form2(), which provides $smem_states_options.
 * Full width; its two date fields are square with 0.875rem of padding, as the
 * old .divi-form rule drew them.
 *
 * The form is the Alpine component memorySearch (javascript/search.js); the
 * admin-ajax address and the nonce its lists need travel in the form's data
 * attributes.
 *
 * @package Scouting_Memories_2026
 */

$smem_label = Scouting_Memories_2026_UI::LABEL;
$smem_field = 'block w-full appearance-none rounded-none border border-field-line bg-white p-3.5 text-base leading-normal font-normal text-ink focus:border-blue/50 focus:ring-4 focus:ring-blue/25 focus:outline-0';
?>

<div class="mx-auto w-full flex-auto p-4">
	<form method="post" enctype="multipart/form-data" class="-mx-3 flex flex-wrap" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" x-data="memorySearch" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'search_form' ) ); ?>">
		<?php wp_nonce_field( 'search_form', 'search_nonce' ); ?>
		<input type="hidden" name="action" value="search_form">
		<input type="hidden" name="page" value="info-page">

		<div class="mb-2 w-full max-w-full shrink-0 px-3">
			<label class="<?php echo esc_attr( $smem_label ); ?>"><?php esc_html_e( 'Select the following optional fields to search memories.', 'scouting-memories-2026' ); ?></label>
		</div>

		<div class="mb-2 hidden w-full max-w-full shrink-0 px-3 md:w-1/3" :class="{ hidden: ! ready }">
			<label class="<?php echo esc_attr( $smem_label ); ?>" for="select_state"><?php esc_html_e( 'Select State', 'scouting-memories-2026' ); ?></label>
			<select name="select_state[]" id="select_state" multiple="multiple">
				<?php echo $smem_states_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</div>
		<div class="mb-2 w-1/2 max-w-full shrink-0 px-3 md:w-1/3">
			<label class="<?php echo esc_attr( $smem_label ); ?>" for="start_date"><?php esc_html_e( 'Start Date', 'scouting-memories-2026' ); ?></label>
			<input name="start_date" class="<?php echo esc_attr( $smem_field ); ?>" id="start_date" value="1900" />
		</div>
		<div class="mb-2 w-1/2 max-w-full shrink-0 px-3 md:w-1/3">
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
