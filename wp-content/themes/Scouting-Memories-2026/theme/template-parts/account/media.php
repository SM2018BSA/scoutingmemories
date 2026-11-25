<?php
/**
 * My Account, Media tab: the user's media library (upload, search, details, delete).
 *
 * The old rule is kept: only a user who may upload files gets it; anyone else reads
 * "Please Login To Upload". Who that is comes from the core plugin's MediaLibrary class.
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Media\MediaLibrary;

if ( ! MediaLibrary::can_use() ) {
	?>
	<p class="mb-4"><?php esc_html_e( 'Please Login To Upload', 'scouting-memories-2026' ); ?></p>
	<?php
	return;
}

get_template_part( 'template-parts/media/library', null, array( 'pick' => false ) );
