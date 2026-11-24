<?php
/**
 * My Account, Posts tab, when the list comes from Scouting Forms:
 * its posts list brings its own heading, "Add" button and paging.
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Forms\FormsGateway;

$smem_forms = $args['forms'];

echo $smem_forms->view( FormsGateway::VIEW_MY_POSTS ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
