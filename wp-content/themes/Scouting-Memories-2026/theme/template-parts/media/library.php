<?php
/**
 * The media library: upload box, search, the files as cards, and (on the My Account page)
 * one file's details. Printed twice from this one file: on the My Account page's Media tab
 * (template-parts/account/media.php) and inside the "choose a file" window of the rich-text
 * editor (template-parts/media/picker.php, with the argument 'pick' => true, where pressing
 * a card hands the file to the editor instead of opening its details).
 *
 * Presentation only. The behaviour is the Alpine part "mediaLibrary" (javascript/media.js),
 * which talks to WordPress's REST API; who may use the library and what the script needs
 * come from the core plugin's MediaLibrary class. Whoever includes this file has already
 * checked MediaLibrary::can_use(). The data-media attributes are only there for the tests.
 *
 * @package Scouting_Memories_2026
 */

use ScoutingMemories\Core\Media\MediaLibrary;

$smem_pick = ! empty( $args['pick'] );
$smem_data = MediaLibrary::page_data();

// The sentences the script says (javascript/media.js fills in %1$s, %2$s ...).
$smem_messages = array(
	'loading'    => __( 'Loading files...', 'scouting-memories-2026' ),
	/* translators: 1: number of files shown, 2: number of files in all. */
	'showing'    => __( 'Showing %1$s of %2$s files.', 'scouting-memories-2026' ),
	'none'       => __( 'No files found.', 'scouting-memories-2026' ),
	/* translators: 1: number of the file being sent, 2: number of files to send, 3: the file's name. */
	'uploading'  => __( 'Uploading %1$s of %2$s: %3$s', 'scouting-memories-2026' ),
	/* translators: 1: number of files uploaded, 2: number of files chosen. */
	'uploaded'   => __( 'Uploaded %1$s of %2$s files.', 'scouting-memories-2026' ),
	/* translators: %1$s: the file's title. */
	'saved'      => __( 'Saved: %1$s', 'scouting-memories-2026' ),
	/* translators: %1$s: the file's title. */
	'deleted'    => __( 'Deleted: %1$s', 'scouting-memories-2026' ),
	'copied'     => __( 'The address was copied.', 'scouting-memories-2026' ),
	'copyByHand' => __( 'The address is selected: press Ctrl+C (or Cmd+C) to copy it.', 'scouting-memories-2026' ),
	'tooLarge'   => __( 'The file is too large for this site.', 'scouting-memories-2026' ),
	'notAllowed' => __( 'You are not allowed to do that. If you have been away for a while, reload the page and log in again.', 'scouting-memories-2026' ),
	'offline'    => __( 'The site could not be reached. Check your connection and try again.', 'scouting-memories-2026' ),
	/* translators: %1$s: the error number the site answered with. */
	'failed'     => __( 'That did not work (error %1$s). Please try again.', 'scouting-memories-2026' ),
);

$smem_btn_primary   = Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::BTN, Scouting_Memories_2026_UI::BTN_PRIMARY );
$smem_btn_secondary = Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::BTN, Scouting_Memories_2026_UI::BTN_SECONDARY );
$smem_btn_danger    = Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::BTN, Scouting_Memories_2026_UI::BTN_DANGER );
$smem_card_button   = Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CARD, 'w-full cursor-pointer overflow-hidden p-0 text-left hover:border-blue focus-visible:ring-4 focus-visible:ring-blue/50 focus-visible:outline-0' );
?>

<div
	x-data="mediaLibrary"
	x-id="['media']"
	data-media="library"
	data-mode="<?php echo $smem_pick ? 'pick' : 'manage'; ?>"
	data-endpoint="<?php echo esc_url( $smem_data['endpoint'] ); ?>"
	data-nonce="<?php echo esc_attr( $smem_data['nonce'] ); ?>"
	data-user="<?php echo esc_attr( (string) $smem_data['user'] ); ?>"
	data-all="<?php echo $smem_data['all'] ? '1' : '0'; ?>"
	data-messages="<?php echo esc_attr( wp_json_encode( $smem_messages ) ); ?>"
>

	<?php // Upload: a file box, which is also where files are dropped. ?>
	<div
		class="mb-4 rounded-md border-2 border-dashed p-4"
		:class="dragging ? 'border-blue bg-light' : 'border-field-line'"
		data-media="upload"
		x-on:dragover.prevent="dragging = true"
		x-on:dragleave.prevent="dragging = false"
		x-on:drop.prevent="dropped($event)"
	>
		<label class="<?php echo esc_attr( Scouting_Memories_2026_UI::LABEL ); ?>" :for="$id('media') + '-file'"><?php esc_html_e( 'Upload files', 'scouting-memories-2026' ); ?></label>
		<input
			class="block w-full cursor-pointer rounded-md border border-field-line bg-white text-base leading-normal text-ink file:mr-3 file:cursor-pointer file:border-0 file:bg-light file:px-3 file:py-1.5 file:text-ink focus-visible:border-[#86b7fe] focus-visible:ring-4 focus-visible:ring-blue/25 focus-visible:outline-0 disabled:opacity-65"
			type="file"
			multiple
			:id="$id('media') + '-file'"
			:aria-describedby="$id('media') + '-file-help'"
			:disabled="uploading"
			x-on:change="chosen($event.target)"
		>
		<p class="<?php echo esc_attr( Scouting_Memories_2026_UI::HELP ); ?>" :id="$id('media') + '-file-help'"><?php esc_html_e( 'Choose one or more files, or drag them onto this box.', 'scouting-memories-2026' ); ?></p>
		<template x-if="uploading">
			<progress class="mt-2 block h-2 w-full" :value="uploadDone" :max="uploadCount" aria-label="<?php esc_attr_e( 'Upload progress', 'scouting-memories-2026' ); ?>"></progress>
		</template>
	</div>

	<form class="mb-4 flex flex-wrap items-end gap-2" role="search" x-on:submit.prevent="load(true)">
		<div class="min-w-0 flex-auto">
			<label class="<?php echo esc_attr( Scouting_Memories_2026_UI::LABEL ); ?>" :for="$id('media') + '-search'"><?php esc_html_e( 'Search files', 'scouting-memories-2026' ); ?></label>
			<input class="<?php echo esc_attr( Scouting_Memories_2026_UI::FIELD ); ?>" type="search" x-model="search" :id="$id('media') + '-search'">
		</div>
		<button class="<?php echo esc_attr( $smem_btn_primary ); ?>" type="submit" :disabled="loading"><?php esc_html_e( 'Search', 'scouting-memories-2026' ); ?></button>
	</form>

	<?php // What happened, said aloud by a screen reader: politely for news, at once for a problem. ?>
	<p class="mb-2 min-h-6 text-sm text-ink" role="status" aria-live="polite" data-media="status" x-text="status"></p>
	<p class="mb-2 text-red-dark" role="alert" data-media="error" :class="error ? '' : 'hidden'" x-text="error"></p>

	<?php if ( ! $smem_pick ) : ?>
		<?php // One file's details: its words, its address, and deleting it. ?>
		<template x-if="selected">
			<section class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CARD, 'mb-4 focus:outline-0' ) ); ?>" data-media="details" tabindex="-1" x-ref="details" :aria-labelledby="$id('media') + '-details'" x-on:keydown.escape.stop="close()">
				<div class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CARD_HEADER, 'flex items-center justify-between gap-2' ) ); ?>">
					<h2 class="mt-0 mb-0 font-slab text-xl leading-tight font-medium" :id="$id('media') + '-details'"><?php esc_html_e( 'File details', 'scouting-memories-2026' ); ?></h2>
					<button class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::BTN_SM, Scouting_Memories_2026_UI::BTN_SECONDARY ) ); ?>" type="button" x-on:click="close()"><?php esc_html_e( 'Close', 'scouting-memories-2026' ); ?></button>
				</div>
				<form class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::CARD_BODY, 'grid gap-4 md:grid-cols-[12rem_minmax(0,1fr)]' ) ); ?>" x-on:submit.prevent="save()">
					<div>
						<template x-if="selected.image">
							<img class="h-auto max-w-full rounded-md border border-line" :src="selected.thumb" :alt="selected.alt">
						</template>
						<template x-if="! selected.image">
							<p class="flex aspect-square w-full items-center justify-center rounded-md border border-line bg-light font-slab text-2xl text-ink" x-text="selected.kind"></p>
						</template>
					</div>
					<div class="min-w-0">
						<div class="mb-4">
							<label class="<?php echo esc_attr( Scouting_Memories_2026_UI::LABEL ); ?>" :for="$id('media') + '-title'"><?php esc_html_e( 'Title', 'scouting-memories-2026' ); ?></label>
							<input class="<?php echo esc_attr( Scouting_Memories_2026_UI::FIELD ); ?>" type="text" x-model="form.title" :id="$id('media') + '-title'" :disabled="busy">
						</div>
						<template x-if="selected.image">
							<div class="mb-4">
								<label class="<?php echo esc_attr( Scouting_Memories_2026_UI::LABEL ); ?>" :for="$id('media') + '-alt'"><?php esc_html_e( 'Alt text', 'scouting-memories-2026' ); ?></label>
								<input class="<?php echo esc_attr( Scouting_Memories_2026_UI::FIELD ); ?>" type="text" x-model="form.alt" :id="$id('media') + '-alt'" :aria-describedby="$id('media') + '-alt-help'" :disabled="busy">
								<p class="<?php echo esc_attr( Scouting_Memories_2026_UI::HELP ); ?>" :id="$id('media') + '-alt-help'"><?php esc_html_e( 'What the picture shows, for someone who cannot see it.', 'scouting-memories-2026' ); ?></p>
							</div>
						</template>
						<div class="mb-4">
							<label class="<?php echo esc_attr( Scouting_Memories_2026_UI::LABEL ); ?>" :for="$id('media') + '-caption'"><?php esc_html_e( 'Caption', 'scouting-memories-2026' ); ?></label>
							<textarea class="<?php echo esc_attr( Scouting_Memories_2026_UI::FIELD ); ?>" rows="3" x-model="form.caption" :id="$id('media') + '-caption'" :disabled="busy"></textarea>
						</div>
						<div class="mb-4">
							<label class="<?php echo esc_attr( Scouting_Memories_2026_UI::LABEL ); ?>" :for="$id('media') + '-address'"><?php esc_html_e( 'Address of the file', 'scouting-memories-2026' ); ?></label>
							<div class="flex flex-wrap gap-2">
								<input class="<?php echo esc_attr( Scouting_Memories_2026_UI::classes( Scouting_Memories_2026_UI::FIELD, 'min-w-0 flex-1 bg-light' ) ); ?>" type="text" readonly x-ref="address" :value="selected.url" :id="$id('media') + '-address'">
								<button class="<?php echo esc_attr( $smem_btn_secondary ); ?>" type="button" x-on:click="copy()"><?php esc_html_e( 'Copy address', 'scouting-memories-2026' ); ?></button>
							</div>
						</div>
						<div class="flex flex-wrap gap-2">
							<button class="<?php echo esc_attr( $smem_btn_primary ); ?>" type="submit" :disabled="busy"><?php esc_html_e( 'Save', 'scouting-memories-2026' ); ?></button>
							<button class="<?php echo esc_attr( $smem_btn_danger ); ?>" type="button" :disabled="busy || confirming" x-on:click="confirming = true"><?php esc_html_e( 'Delete', 'scouting-memories-2026' ); ?></button>
						</div>
						<template x-if="confirming">
							<div class="mt-4 rounded-md border border-red/40 bg-red/10 p-4" role="group" :aria-labelledby="$id('media') + '-sure'">
								<p class="mb-4" :id="$id('media') + '-sure'"><?php esc_html_e( 'Delete this file for good? This cannot be undone.', 'scouting-memories-2026' ); ?></p>
								<div class="flex flex-wrap gap-2">
									<button class="<?php echo esc_attr( $smem_btn_secondary ); ?>" type="button" x-init="$nextTick(() => $el.focus())" x-on:click="confirming = false"><?php esc_html_e( 'No, keep it', 'scouting-memories-2026' ); ?></button>
									<button class="<?php echo esc_attr( $smem_btn_danger ); ?>" type="button" :disabled="busy" x-on:click="remove()"><?php esc_html_e( 'Yes, delete it', 'scouting-memories-2026' ); ?></button>
								</div>
							</div>
						</template>
					</div>
				</form>
			</section>
		</template>
	<?php endif; ?>

	<?php // The files. Pressing a card opens its details, or (in the chooser) hands the file over. ?>
	<div class="focus:outline-0" role="region" tabindex="-1" data-media="list" x-ref="list" aria-label="<?php esc_attr_e( 'Files', 'scouting-memories-2026' ); ?>" :aria-busy="loading ? 'true' : 'false'">
		<ul class="grid list-none grid-cols-2 gap-4 pl-0 sm:grid-cols-3 lg:grid-cols-4">
			<template x-for="item in items" :key="item.id">
				<li class="min-w-0">
					<button class="<?php echo esc_attr( $smem_card_button ); ?>" type="button" data-media="card" x-on:click="press(item, $el)">
						<template x-if="item.image">
							<img class="aspect-square w-full object-cover" loading="lazy" :src="item.thumb" :alt="item.alt">
						</template>
						<template x-if="! item.image">
							<span class="flex aspect-square w-full items-center justify-center bg-light font-slab text-2xl text-ink" x-text="item.kind"></span>
						</template>
						<span class="block truncate border-t border-line px-2 py-1 text-sm text-ink" x-text="item.title || item.kind"></span>
					</button>
				</li>
			</template>
		</ul>
	</div>

	<template x-if="more">
		<div class="mt-4 text-center">
			<button class="<?php echo esc_attr( $smem_btn_secondary ); ?>" type="button" data-media="more" :disabled="loading" x-on:click="load()"><?php esc_html_e( 'Load more', 'scouting-memories-2026' ); ?></button>
		</div>
	</template>

</div>
