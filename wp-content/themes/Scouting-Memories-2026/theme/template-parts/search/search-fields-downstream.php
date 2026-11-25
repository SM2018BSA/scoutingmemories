<?php
/**
 * The chained council / merged council / lodge / camp fields shared by every
 * search form. All ids, names and the `hidden` class are load-bearing:
 * js/search_ajax.js finds, fills and reveals these exact elements.
 *
 * The front page's form stacks them full width. The category form and the wide
 * form set $smem_downstream_side_by_side before including this file, and get
 * the old side-by-side widths: Council 4/12, Selected Merged Council 8/12,
 * Lodge 4/12, Camp 4/12.
 *
 * @package Scouting_Memories_2026
 */

if ( ! empty( $smem_downstream_side_by_side ) ) {
	$smem_width_field  = 'col-span-4';
	$smem_width_merged = 'col-span-8';
} else {
	$smem_width_field  = 'col-span-12';
	$smem_width_merged = 'col-span-12';
}
?>

<div id="select_council_loading" class="flex justify-center <?php echo esc_attr( $smem_width_field ); ?> hidden">
	<span class="loading loading-spinner loading-lg sm_green_color" role="status"></span>
</div>
<div class="mb-2 <?php echo esc_attr( $smem_width_field ); ?> hidden">
	<label class="block mb-2" for="select_council"><?php esc_html_e( 'Select Council', 'scouting-memories-2026' ); ?></label>
	<select name="select_council[]" class="select w-full" id="select_council" multiple="multiple"></select>
</div>

<div id="select_merged_council_loading" class="flex justify-center <?php echo esc_attr( $smem_width_field ); ?> hidden">
	<span class="loading loading-spinner loading-lg sm_green_color" role="status"></span>
</div>
<div class="mb-2 <?php echo esc_attr( $smem_width_merged ); ?> hidden">
	<label class="block mb-2" for="select_merged_council"><?php esc_html_e( 'Selected Merged Council', 'scouting-memories-2026' ); ?></label>
	<select name="select_merged_council[]" class="select w-full" id="select_merged_council" multiple="multiple"></select>
</div>

<div id="select_lodge_loading" class="flex justify-center <?php echo esc_attr( $smem_width_field ); ?> hidden">
	<span class="loading loading-spinner loading-lg sm_green_color" role="status"></span>
</div>
<div class="mb-2 <?php echo esc_attr( $smem_width_field ); ?> hidden">
	<label class="block mb-2" for="select_lodge"><?php esc_html_e( 'Select Lodge', 'scouting-memories-2026' ); ?></label>
	<select name="select_lodge[]" class="select w-full" id="select_lodge" multiple="multiple"></select>
</div>

<div id="select_camp_loading" class="flex justify-center <?php echo esc_attr( $smem_width_field ); ?> hidden">
	<span class="loading loading-spinner loading-lg sm_green_color" role="status"></span>
</div>
<div class="mb-2 <?php echo esc_attr( $smem_width_field ); ?> hidden">
	<label class="block mb-2" for="select_camp"><?php esc_html_e( 'Select Camp', 'scouting-memories-2026' ); ?></label>
	<select name="select_camp[]" class="select w-full" id="select_camp" multiple="multiple"></select>
</div>
