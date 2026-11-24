<?php
/**
 * My Account, Indexing tab, when it comes from Scouting Forms:
 * one indexing browser that carries its own Councils / Camps / Lodges / Tools
 * pills, search and paging.
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Forms\FormsGateway;

$smem_forms = $args['forms'];

echo $smem_forms->view( FormsGateway::VIEW_INDEXING ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
