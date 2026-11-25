<?php
/**
 * The "choose a file" window: the media library inside a native <dialog>, printed once in
 * the footer (footer.php) for a user who may upload files, and for nobody else.
 *
 * Any script opens it with
 *   window.dispatchEvent( new CustomEvent( 'sm-media-pick' ) )
 * and gets the chosen file as the window event `sm-media-picked`, with
 *   detail { id, url, alt, title, mime }
 * after which the window closes (javascript/media.js). The rich-text editor's picture
 * button uses it (javascript/editor.js). The browser itself keeps the keyboard inside an
 * open dialog and closes it on Escape.
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Media\MediaLibrary;

if ( ! class_exists( MediaLibrary::class ) || ! MediaLibrary::can_use() ) {
	return;
}
?>

<dialog
	id="sm-media-picker"
	class="m-auto max-h-[90vh] w-[calc(100%-2rem)] max-w-4xl overflow-y-auto rounded-md border border-black/[.175] bg-white p-0 text-ink shadow-lg backdrop:bg-black/50"
	aria-labelledby="sm-media-picker-title"
	x-data
	x-on:sm-media-pick.window="$el.open || $el.showModal()"
	x-on:sm-media-picked.window="$el.open &amp;&amp; $el.close()"
>
	<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CARD_HEADER, 'sticky top-0 z-10 flex items-center justify-between gap-2 bg-white' ) ); ?>">
		<h2 class="mt-0 mb-0 font-slab text-xl leading-tight font-medium" id="sm-media-picker-title"><?php esc_html_e( 'Choose a file', 'scouting-memories-2026' ); ?></h2>
		<button class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::BTN_SM, Scouting_Memories_2026_UI::BTN_SECONDARY ) ); ?>" type="button" x-on:click="$root.close()"><?php esc_html_e( 'Close', 'scouting-memories-2026' ); ?></button>
	</div>
	<div class="p-4">
		<?php get_template_part( 'template-parts/media/library', null, array( 'pick' => true ) ); ?>
	</div>
</dialog>
