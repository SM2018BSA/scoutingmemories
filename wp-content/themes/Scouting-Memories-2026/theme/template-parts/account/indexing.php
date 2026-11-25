<?php
/**
 * My Account, Indexing tab: the site's own three lists (councils, camps, lodges) and the
 * Tools card, as on the old site, whichever forms plugin draws the lists. The theme lays
 * them out as radio-driven sub-tabs.
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Forms\FormsGateway;

$smem_forms = $args['forms'];
$smem_sub   = $args['sub'];
?>

<input type="radio" name="ma-idx" id="idx-myCouncils"<?php checked( 'myCouncils', $smem_sub ); ?>>
<input type="radio" name="ma-idx" id="idx-myCamps"<?php checked( 'myCamps', $smem_sub ); ?>>
<input type="radio" name="ma-idx" id="idx-myLodges"<?php checked( 'myLodges', $smem_sub ); ?>>
<input type="radio" name="ma-idx" id="idx-myTools">

<div class="ma-sublist tabs mb-4" role="group" aria-label="<?php esc_attr_e( 'Indexing lists', 'scouting-memories-2026' ); ?>">
	<label class="tab" for="idx-myCouncils"><?php esc_html_e( 'Councils', 'scouting-memories-2026' ); ?></label>
	<label class="tab" for="idx-myCamps"><?php esc_html_e( 'Camps', 'scouting-memories-2026' ); ?></label>
	<label class="tab" for="idx-myLodges"><?php esc_html_e( 'Lodges', 'scouting-memories-2026' ); ?></label>
	<label class="tab" for="idx-myTools"><?php esc_html_e( 'Tools', 'scouting-memories-2026' ); ?></label>
</div>

<div class="ma-subpane sm-scroll-x" id="idx-pane-councils"><?php echo $smem_forms->view( FormsGateway::VIEW_COUNCILS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<div class="ma-subpane sm-scroll-x" id="idx-pane-camps"><?php echo $smem_forms->view( FormsGateway::VIEW_CAMPS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<div class="ma-subpane sm-scroll-x" id="idx-pane-lodges"><?php echo $smem_forms->view( FormsGateway::VIEW_LODGES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
<div class="ma-subpane" id="idx-pane-tools">
	<?php get_template_part( 'template-parts/account/end-dates-card' ); ?>
</div>
