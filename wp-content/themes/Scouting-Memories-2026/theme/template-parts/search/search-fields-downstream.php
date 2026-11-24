<?php
/**
 * The chained council / merged council / lodge / camp fields shared by every
 * search form. All ids, names and the `hidden` class are load-bearing:
 * js/search_ajax.js finds, fills and reveals these exact elements.
 *
 * @package Scouting_Memories_2026
 */

?>

<div id="select_council_loading" class="flex justify-center col-span-12 hidden">
	<span class="loading loading-spinner loading-lg sm_green_color" role="status"></span>
</div>
<div class="mb-2 col-span-12 hidden">
	<label class="block text-sm mb-1" for="select_council"><?php esc_html_e( 'Select Council', 'scouting-memories-2026' ); ?></label>
	<select name="select_council[]" class="select w-full" id="select_council" multiple="multiple"></select>
</div>

<div id="select_merged_council_loading" class="flex justify-center col-span-12 hidden">
	<span class="loading loading-spinner loading-lg sm_green_color" role="status"></span>
</div>
<div class="mb-2 col-span-12 hidden">
	<label class="block text-sm mb-1" for="select_merged_council"><?php esc_html_e( 'Selected Merged Council', 'scouting-memories-2026' ); ?></label>
	<select name="select_merged_council[]" class="select w-full" id="select_merged_council" multiple="multiple"></select>
</div>

<div id="select_lodge_loading" class="flex justify-center col-span-12 hidden">
	<span class="loading loading-spinner loading-lg sm_green_color" role="status"></span>
</div>
<div class="mb-2 col-span-12 hidden">
	<label class="block text-sm mb-1" for="select_lodge"><?php esc_html_e( 'Select Lodge', 'scouting-memories-2026' ); ?></label>
	<select name="select_lodge[]" class="select w-full" id="select_lodge" multiple="multiple"></select>
</div>

<div id="select_camp_loading" class="flex justify-center col-span-12 hidden">
	<span class="loading loading-spinner loading-lg sm_green_color" role="status"></span>
</div>
<div class="mb-2 col-span-12 hidden">
	<label class="block text-sm mb-1" for="select_camp"><?php esc_html_e( 'Select Camp', 'scouting-memories-2026' ); ?></label>
	<select name="select_camp[]" class="select w-full" id="select_camp" multiple="multiple"></select>
</div>
