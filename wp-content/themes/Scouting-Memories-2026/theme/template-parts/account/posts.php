<?php
/**
 * My Account, Posts tab: the list is the site's own "Posts" view (State, Title, Category, Edit),
 * whichever forms plugin draws it. The view has no heading, so the theme gives it the old one:
 * a blue "My Posts" pill.
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Forms\FormsGateway;

$smem_forms = $args['forms'];
?>

<p class="ma-pill mb-4"><?php esc_html_e( 'My Posts', 'scouting-memories-2026' ); ?></p>
<div class="sm-scroll-x"><?php echo $smem_forms->view( FormsGateway::VIEW_MY_POSTS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
