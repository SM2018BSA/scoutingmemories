<?php
/**
 * The main search form (the front page's Search Memories pane). Included by the
 * core plugin's SearchForm::show_form(), which provides $smem_states_options.
 * Submits to admin-post action search_form, which redirects to the Info Page.
 *
 * The form is the Alpine component memorySearch (javascript/search.js); the
 * admin-ajax address and the nonce its lists need travel in the form's data
 * attributes.
 *
 * @package Scouting_Memories_2026
 */

$smem_label = Scouting_Memories_2026_UI::LABEL;
$smem_field = Scouting_Memories_2026_UI::FIELD;
?>

<div class="w-full flex-auto p-4">
	<form method="post" enctype="multipart/form-data" class="-mx-3 flex w-full flex-wrap px-3" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" x-data="memorySearch" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'search_form' ) ); ?>">
		<?php wp_nonce_field( 'search_form', 'search_nonce' ); ?>
		<input type="hidden" name="action" value="search_form">
		<input type="hidden" name="page" value="info-page">

		<div class="mb-2 w-full max-w-full shrink-0 px-3">
			<label class="<?php echo esc_attr( $smem_label ); ?>"><?php esc_html_e( 'Select the following optional fields to search memories.', 'scouting-memories-2026' ); ?></label>
		</div>

		<div class="mb-2 hidden w-full max-w-full shrink-0 px-3" :class="{ hidden: ! ready }">
			<label class="<?php echo esc_attr( $smem_label ); ?>" for="select_state"><?php esc_html_e( 'Select State', 'scouting-memories-2026' ); ?></label>
			<select name="select_state[]" id="select_state" multiple="multiple">
				<?php echo $smem_states_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		</div>

		<?php require __DIR__ . '/search-fields-downstream.php'; ?>

		<div class="mb-2 w-1/2 max-w-full shrink-0 px-3">
			<label class="<?php echo esc_attr( $smem_label ); ?>" for="start_date"><?php esc_html_e( 'Start Date', 'scouting-memories-2026' ); ?></label>
			<input name="start_date" class="<?php echo esc_attr( $smem_field ); ?>" id="start_date" value="1900" />
		</div>
		<div class="mb-2 w-1/2 max-w-full shrink-0 px-3">
			<label class="<?php echo esc_attr( $smem_label ); ?>" for="end_date"><?php esc_html_e( 'End Date', 'scouting-memories-2026' ); ?></label>
			<input name="end_date" class="<?php echo esc_attr( $smem_field ); ?>" id="end_date" value="<?php echo esc_attr( wp_date( 'Y' ) ); ?>" />
		</div>

		<?php require __DIR__ . '/search-footer.php'; ?>
	</form>
</div>
