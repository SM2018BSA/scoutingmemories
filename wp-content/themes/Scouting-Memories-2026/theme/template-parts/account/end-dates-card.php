<?php
/**
 * The "Update Active End Dates" tool card (Indexing → Tools and Admin → Tools).
 * The old theme ran this from a plain GET link anyone could hit; now it is a
 * nonce'd POST to the core plugin, administrators only.
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Access;
use ScoutingMemories\Core\Ajax;

?>

<div class="card card-border bg-base-100 shadow-sm md:w-1/2">
	<div class="card-body">
		<h5 class="card-title">Update Active End Dates</h5>
		<p>Click below to update the End Date values for indexing marked as "Active" with the current year.</p>
		<p>This indexing includes Councils, Lodges and Camps.</p>
		<?php if ( Access::can_run_end_dates() ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( Ajax::END_DATES_ACTION ); ?>">
				<?php wp_nonce_field( Ajax::END_DATES_NONCE ); ?>
				<button type="submit" class="btn btn-primary">Update</button>
			</form>
		<?php else : ?>
			<p class="text-sm text-base-content/60">Only an administrator can run this tool.</p>
		<?php endif; ?>
	</div>
</div>
