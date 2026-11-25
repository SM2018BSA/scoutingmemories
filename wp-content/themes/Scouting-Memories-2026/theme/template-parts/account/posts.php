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

<div class="flex flex-wrap">
	<p class="block rounded-md bg-blue px-4 py-2 text-white"><?php esc_html_e( 'My Posts', 'scouting-memories-2026' ); ?></p>
</div>
<div class="overflow-x-auto pt-4" x-data="scrollRegion" x-on:resize.window="check()" x-on:change.window="check()" data-label="<?php echo esc_attr( $args['scroll_label'] ); ?>"><?php echo $smem_forms->view( FormsGateway::VIEW_MY_POSTS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
