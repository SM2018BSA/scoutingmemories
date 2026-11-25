<?php
/**
 * The search forms' shared footer: the Submit button and the Show Merged
 * Councils toggle (its id drives js/search_ajax.js).
 *
 * @package Scouting_Memories_2026
 */

?>

<div class="col-span-12 mt-2 flex flex-wrap items-center gap-4">
	<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Submit', 'scouting-memories-2026' ); ?></button>
	<label class="flex items-center gap-2 cursor-pointer">
		<input type="checkbox" class="checkbox checkbox-sm" id="show_merged" value=""> Show Merged Councils?
	</label>
</div>
