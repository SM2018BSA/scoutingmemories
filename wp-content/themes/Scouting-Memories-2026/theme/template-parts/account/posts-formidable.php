<?php
/**
 * My Account, Posts tab, when the list comes from a Formidable view:
 * the view has no heading of its own, so the theme gives it one.
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Forms\FormsGateway;

$smem_forms = $args['forms'];
?>

<h2 class="mb-4 text-xl"><?php esc_html_e( 'My Posts', 'scouting-memories-2026' ); ?></h2>
<div class="sm-scroll-x"><?php echo $smem_forms->view( FormsGateway::VIEW_MY_POSTS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
