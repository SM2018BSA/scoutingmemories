<?php
/**
 * The chained council / merged council / lodge / camp fields shared by every
 * search form. The ids and names are load-bearing: javascript/search.js finds
 * each <select> by its id, fills it and turns it into a Tom Select box. Each
 * field (and each spinner) stays hidden until the form's Alpine component
 * (memorySearch) says otherwise.
 *
 * The front page's form stacks them full width. The category form and the wide
 * form set $smem_downstream_side_by_side before including this file, and get
 * the old side-by-side widths: Council 4/12, Selected Merged Council 8/12,
 * Lodge 4/12, Camp 4/12.
 *
 * @package Scouting_Memories_2026
 */

if ( ! empty( $smem_downstream_side_by_side ) ) {
	$smem_width_field  = 'w-1/3 max-w-full shrink-0 px-3';
	$smem_width_merged = 'w-2/3 max-w-full shrink-0 px-3';
} else {
	$smem_width_field  = 'w-full max-w-full shrink-0 px-3';
	$smem_width_merged = 'w-full max-w-full shrink-0 px-3';
}

$smem_label   = Scouting_Memories_2026_UI::LABEL;
$smem_spinner = 'inline-block size-8 animate-spin rounded-full border-4 border-brand border-r-transparent align-[-0.125em]';
?>

<div id="select_council_loading" class="hidden <?php echo esc_attr( $smem_width_field ); ?>" :class="{ hidden: ! loading.council }">
	<div class="flex justify-center">
		<span class="<?php echo esc_attr( $smem_spinner ); ?>" role="status"><span class="sr-only"><?php esc_html_e( 'Loading...', 'scouting-memories-2026' ); ?></span></span>
	</div>
</div>
<div class="mb-2 hidden <?php echo esc_attr( $smem_width_field ); ?>" :class="{ hidden: ! shown.council }">
	<label class="<?php echo esc_attr( $smem_label ); ?>" for="select_council"><?php esc_html_e( 'Select Council', 'scouting-memories-2026' ); ?></label>
	<select name="select_council[]" id="select_council" multiple="multiple"></select>
</div>

<div id="select_merged_council_loading" class="hidden <?php echo esc_attr( $smem_width_field ); ?>" :class="{ hidden: ! loading.merged }">
	<div class="flex justify-center">
		<span class="<?php echo esc_attr( $smem_spinner ); ?>" role="status"><span class="sr-only"><?php esc_html_e( 'Loading...', 'scouting-memories-2026' ); ?></span></span>
	</div>
</div>
<div class="mb-2 hidden <?php echo esc_attr( $smem_width_merged ); ?>" :class="{ hidden: ! shown.merged }">
	<label class="<?php echo esc_attr( $smem_label ); ?>" for="select_merged_council"><?php esc_html_e( 'Selected Merged Council', 'scouting-memories-2026' ); ?></label>
	<select name="select_merged_council[]" id="select_merged_council" multiple="multiple"></select>
</div>

<div id="select_lodge_loading" class="hidden <?php echo esc_attr( $smem_width_field ); ?>" :class="{ hidden: ! loading.lodge }">
	<div class="flex justify-center">
		<span class="<?php echo esc_attr( $smem_spinner ); ?>" role="status"><span class="sr-only"><?php esc_html_e( 'Loading...', 'scouting-memories-2026' ); ?></span></span>
	</div>
</div>
<div class="mb-2 hidden <?php echo esc_attr( $smem_width_field ); ?>" :class="{ hidden: ! shown.lodge }">
	<label class="<?php echo esc_attr( $smem_label ); ?>" for="select_lodge"><?php esc_html_e( 'Select Lodge', 'scouting-memories-2026' ); ?></label>
	<select name="select_lodge[]" id="select_lodge" multiple="multiple"></select>
</div>

<div id="select_camp_loading" class="hidden <?php echo esc_attr( $smem_width_field ); ?>" :class="{ hidden: ! loading.camp }">
	<div class="flex justify-center">
		<span class="<?php echo esc_attr( $smem_spinner ); ?>" role="status"><span class="sr-only"><?php esc_html_e( 'Loading...', 'scouting-memories-2026' ); ?></span></span>
	</div>
</div>
<div class="mb-2 hidden <?php echo esc_attr( $smem_width_field ); ?>" :class="{ hidden: ! shown.camp }">
	<label class="<?php echo esc_attr( $smem_label ); ?>" for="select_camp"><?php esc_html_e( 'Select Camp', 'scouting-memories-2026' ); ?></label>
	<select name="select_camp[]" id="select_camp" multiple="multiple"></select>
</div>
