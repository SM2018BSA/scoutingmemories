<?php
/**
 * My Account, Indexing tab: the site's own three lists (councils, camps, lodges) and the
 * Tools card, as on the old site, whichever forms plugin draws the lists. The theme lays
 * them out as pills: radio buttons inside their labels, the checked one drawn as a blue
 * pill, and only the checked one's list showing (my-account.php holds the "group" these
 * group-has-[...] classes look up to).
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Forms\FormsGateway;

$smem_forms = $args['forms'];
$smem_sub   = $args['sub'];
$smem_pill  = $args['pill_class'];
$smem_label = $args['scroll_label'];
?>

<div class="flex flex-wrap" data-ma="sublist" role="group" aria-label="<?php esc_attr_e( 'Indexing lists', 'scouting-memories-2026' ); ?>">
	<label class="<?php echo esc_attr( $smem_pill ); ?>" for="idx-myCouncils"><input class="sr-only" type="radio" name="ma-idx" id="idx-myCouncils"<?php checked( 'myCouncils', $smem_sub ); ?>><?php esc_html_e( 'Councils', 'scouting-memories-2026' ); ?></label>
	<label class="<?php echo esc_attr( $smem_pill ); ?>" for="idx-myCamps"><input class="sr-only" type="radio" name="ma-idx" id="idx-myCamps"<?php checked( 'myCamps', $smem_sub ); ?>><?php esc_html_e( 'Camps', 'scouting-memories-2026' ); ?></label>
	<label class="<?php echo esc_attr( $smem_pill ); ?>" for="idx-myLodges"><input class="sr-only" type="radio" name="ma-idx" id="idx-myLodges"<?php checked( 'myLodges', $smem_sub ); ?>><?php esc_html_e( 'Lodges', 'scouting-memories-2026' ); ?></label>
	<label class="<?php echo esc_attr( $smem_pill ); ?>" for="idx-myTools"><input class="sr-only" type="radio" name="ma-idx" id="idx-myTools"><?php esc_html_e( 'Tools', 'scouting-memories-2026' ); ?></label>
</div>

<div class="hidden overflow-x-auto pt-4 group-has-[#idx-myCouncils:checked]:block" data-ma="subpane" id="idx-pane-councils" x-data="scrollRegion" x-on:resize.window="check()" x-on:change.window="check()" data-label="<?php echo esc_attr( $smem_label ); ?>"><?php echo $smem_forms->view( FormsGateway::VIEW_COUNCILS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<div class="hidden overflow-x-auto pt-4 group-has-[#idx-myCamps:checked]:block" data-ma="subpane" id="idx-pane-camps" x-data="scrollRegion" x-on:resize.window="check()" x-on:change.window="check()" data-label="<?php echo esc_attr( $smem_label ); ?>"><?php echo $smem_forms->view( FormsGateway::VIEW_CAMPS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<div class="hidden overflow-x-auto pt-4 group-has-[#idx-myLodges:checked]:block" data-ma="subpane" id="idx-pane-lodges" x-data="scrollRegion" x-on:resize.window="check()" x-on:change.window="check()" data-label="<?php echo esc_attr( $smem_label ); ?>"><?php echo $smem_forms->view( FormsGateway::VIEW_LODGES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<div class="hidden pt-4 group-has-[#idx-myTools:checked]:block" data-ma="subpane" id="idx-pane-tools">
	<div class="px-3"><?php get_template_part( 'template-parts/account/end-dates-card' ); ?></div>
</div>
