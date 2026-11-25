<?php
/**
 * The search forms' shared footer: the Submit button (3 of 12 columns from the
 * sm screen size) and the Show Merged Councils tick box, which the form's
 * Alpine component (memorySearch in javascript/search.js) reads.
 *
 * @package Scouting_Memories_2026
 */

?>

<div class="mb-4 w-full max-w-full shrink-0 px-3"></div>

<div class="mb-2 max-w-full shrink-0 px-3 sm:w-1/4">
	<button type="submit" class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::BTN, Scouting_Memories_2026_UI::BTN_PRIMARY ) ); ?>"><?php esc_html_e( 'Submit', 'scouting-memories-2026' ); ?></button>
</div>
<div class="mb-2 max-w-full min-w-0 flex-1 px-3 sm:w-3/4 sm:flex-none">
	<div class="mr-4 inline-block">
		<label>
			<input type="checkbox" class="mt-1 mb-2 size-4 align-top accent-blue" id="show_merged" value="" x-model="showMerged" @change="mergedToggled()"> Show Merged Councils?
		</label>
	</div>
</div>
