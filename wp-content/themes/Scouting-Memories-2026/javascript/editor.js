/**
 * The rich-text editor (Tiptap), as Alpine data.
 *
 * Processed by esbuild into `../theme/js/editor.min.js`: its own file, because Tiptap is
 * large and only a page with a rich-text field needs it. `../theme/functions.php` loads it
 * (deferred, after script.min.js) when a form prints such a field. It does not bring its own
 * Alpine: script.min.js puts Alpine on `window` and starts it when the page has been read,
 * so this file registers its part on Alpine's `alpine:init` event.
 *
 * One job: the behaviour behind this markup, which the form prints:
 *
 *   <div x-data="richText" data-rich-text>
 *     <div data-rich-toolbar> buttons:
 *       <button type="button" x-on:click="run('bold')" :aria-pressed="active('bold')">
 *     </div>
 *     <div x-ref="editor" data-editor-class="..." aria-label="..."></div>
 *     <textarea x-ref="field" name="..." id="..."> the HTML </textarea>
 *   </div>
 *
 * run(name): bold, italic, bulletList, orderedList, blockquote, alignLeft, alignCenter,
 * alignRight, link, paragraph, heading2, heading3, undo, redo, image, code.
 * active(name): true or false, for a button's pressed state.
 * Link: run('link') opens a small box the markup draws: linkOpen, linkUrl, applyLink(),
 * removeLink(). Code: showCode swaps the editor for the textarea with the HTML itself.
 * Picture: asks the media library's chooser window (javascript/media.js) for a file.
 *
 * Tiptap has no look of its own, and its own stylesheet is switched off: every class on
 * the writing area comes from data-editor-class, plus the few below it cannot work without.
 */

import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import TextAlign from '@tiptap/extension-text-align';

// What Tiptap's own stylesheet would have set, as Tailwind classes: typed spaces and line
// ends are kept, long words wrap, and a picture that is selected shows a blue outline.
const WRITING_AREA =
	'relative whitespace-pre-wrap wrap-break-word [&_.ProseMirror-selectednode]:outline-2 [&_.ProseMirror-selectednode]:outline-blue';

// Markup the editor cannot keep: a post that holds any of it opens as HTML (see onCreate).
const UNKNOWN_MARKUP = /<(table|iframe|div|figure|figcaption|video|audio|object|embed|span|sup|sub|u|dl|details)[\s>]/i;

/**
 * Pictures as the site's old posts have them: inside a line of text (so a picture can sit in
 * a paragraph, in a link, or inside a [caption] shortcode's text), and keeping their class
 * (alignleft, alignright, aligncenter, alignnone, size-..., wp-image-...) next to the width,
 * height, alt and title Tiptap keeps already.
 */
const Picture = Image.extend({
	addAttributes() {
		return {
			...this.parent?.(),
			class: { default: null },
		};
	},
}).configure({ inline: true });

const ALIGN = { alignLeft: 'left', alignCenter: 'center', alignRight: 'right' };
const HEADING = { heading2: 2, heading3: 3 };

// The toggles Tiptap names the same way the toolbar does.
const TOGGLES = {
	bold: 'toggleBold',
	italic: 'toggleItalic',
	bulletList: 'toggleBulletList',
	orderedList: 'toggleOrderedList',
	blockquote: 'toggleBlockquote',
};

/** An address a person typed, made into one a link can use ("example.org" gets https://). */
function linkAddress(typed) {
	const address = String(typed || '').trim();
	if (!address || /^(https?:|mailto:|tel:|\/|#)/i.test(address)) {
		return address;
	}
	if (/^[a-z][a-z0-9+.-]*:/i.test(address)) {
		return ''; // any other scheme (javascript:, data:) is refused
	}
	return `https://${address}`;
}

function richText() {
	// Kept out of Alpine's data on purpose: Tiptap breaks when Alpine wraps its editor.
	let editor = null;
	// Did the person change the text in the editor since it was last loaded from the field?
	let changed = false;
	// Names this editor in the chooser request, so only the editor that asked takes the answer.
	const token = {};
	// The chooser's two window events, listened to for as long as the editor is on the page.
	const listeners = {};

	return {
		showCode: false,
		linkOpen: false,
		linkUrl: '',
		// Counts the editor's changes. active() reads it, so Alpine asks again after each one.
		tick: 0,
		awaitingImage: false,

		init() {
			const mount = this.$refs.editor;
			const field = this.$refs.field;
			if (!mount || !field) {
				return;
			}
			const label = mount.getAttribute('aria-label') || '';
			const attributes = {
				class: `${mount.dataset.editorClass || ''} ${WRITING_AREA}`.trim(),
				role: 'textbox',
				'aria-multiline': 'true',
			};
			if (label) {
				attributes['aria-label'] = label;
				mount.removeAttribute('aria-label'); // the name now sits on the writing area itself
			}

			editor = new Editor({
				element: mount,
				injectCSS: false,
				extensions: [
					StarterKit.configure({
						// Both draw themselves with a stylesheet this theme does not load.
						dropcursor: false,
						gapcursor: false,
						// Links keep what they came with: nothing (target, rel, class) is added to them.
						link: {
							openOnClick: false,
							autolink: true,
							defaultProtocol: 'https',
							HTMLAttributes: {
								target: null,
								rel: null,
								class: null,
							},
						},
					}),
					Picture,
					TextAlign.configure({ types: ['heading', 'paragraph'] }),
				],
				content: field.value,
				editorProps: { attributes },
				onUpdate: () => {
					changed = true;
					this.write();
				},
				// An older post can hold markup this editor has no tool for (a table, a frame, a boxed
				// block). Editing it here would drop that markup, so such a post opens as its HTML
				// instead; the editor view stays one click away.
				onCreate: () => {
					if (UNKNOWN_MARKUP.test(field.value)) {
						this.showCode = true;
					}
				},
				onTransaction: () => {
					this.tick += 1;
				},
			});

			// The field always holds the editor's HTML when the form is sent.
			if (field.form) {
				field.form.addEventListener('submit', () => this.write(), true);
			}

			listeners['sm-media-pick'] = (event) => this.pickAsked(event);
			listeners['sm-media-picked'] = (event) => this.picked(event);
			for (const [name, listener] of Object.entries(listeners)) {
				window.addEventListener(name, listener);
			}
		},

		destroy() {
			for (const [name, listener] of Object.entries(listeners)) {
				window.removeEventListener(name, listener);
			}
			if (editor) {
				editor.destroy();
				editor = null;
			}
		},

		/**
		 * Puts the editor's HTML into the field. Nothing is written while the HTML itself is
		 * being edited, nor when the text was never touched: text that is only looked at is
		 * sent back exactly as it came.
		 */
		write() {
			if (!editor || this.showCode || !changed) {
				return;
			}
			const field = this.$refs.field;
			const html = editor.isEmpty ? '' : editor.getHTML();
			if (field.value !== html) {
				field.value = html;
				field.dispatchEvent(new Event('input', { bubbles: true }));
			}
		},

		/** Is this toolbar button's style on where the cursor is? */
		active(name) {
			void this.tick;
			if (name === 'code') {
				return this.showCode;
			}
			if (!editor || this.showCode) {
				return false;
			}
			if (name in ALIGN) {
				return editor.isActive({ textAlign: ALIGN[name] });
			}
			if (name in HEADING) {
				return editor.isActive('heading', { level: HEADING[name] });
			}
			if (name === 'undo' || name === 'redo' || name === 'image') {
				return false;
			}
			return editor.isActive(name);
		},

		/** A toolbar button was pressed. */
		run(name) {
			if (!editor) {
				return;
			}
			if (name === 'code') {
				this.toggleCode();
				return;
			}
			if (this.showCode) {
				return; // the other buttons work on the editor, which is not showing
			}
			if (name === 'link') {
				this.linkUrl = editor.getAttributes('link').href || '';
				this.linkOpen = !this.linkOpen;
				return;
			}
			if (name === 'image') {
				this.awaitingImage = true;
				window.dispatchEvent(
					new CustomEvent('sm-media-pick', {
						detail: { asker: token },
					})
				);
				return;
			}
			const chain = editor.chain().focus();
			if (name in TOGGLES) {
				chain[TOGGLES[name]]().run();
			} else if (name in ALIGN) {
				chain.setTextAlign(ALIGN[name]).run();
			} else if (name in HEADING) {
				chain.toggleHeading({ level: HEADING[name] }).run();
			} else if (name === 'paragraph') {
				chain.setParagraph().run();
			} else if (name === 'undo') {
				chain.undo().run();
			} else if (name === 'redo') {
				chain.redo().run();
			}
		},

		applyLink() {
			if (!editor) {
				return;
			}
			const href = linkAddress(this.linkUrl);
			if (!href) {
				this.removeLink();
				return;
			}
			const chain = editor.chain().focus();
			if (editor.state.selection.empty && !editor.isActive('link')) {
				// Nothing is selected: the address itself goes in, as a link.
				chain.insertContent({
					type: 'text',
					text: href,
					marks: [{ type: 'link', attrs: { href } }],
				});
			} else {
				chain.extendMarkRange('link').setLink({ href });
			}
			chain.run();
			this.linkOpen = false;
		},

		removeLink() {
			if (!editor) {
				return;
			}
			editor.chain().focus().extendMarkRange('link').unsetLink().run();
			this.linkOpen = false;
			this.linkUrl = '';
		},

		/** Swaps between the editor and the HTML itself (the textarea). */
		toggleCode() {
			if (this.showCode) {
				// Back to the editor: it takes whatever the HTML now says.
				editor.commands.setContent(this.$refs.field.value, {
					emitUpdate: false,
				});
				changed = false;
				this.showCode = false;
				this.$nextTick(() => editor && editor.commands.focus());
			} else {
				this.write();
				this.linkOpen = false;
				this.showCode = true;
				this.$nextTick(() => this.$refs.field.focus());
			}
			this.tick += 1;
		},

		/** Another editor asked the chooser for a file: this one stops waiting for an answer. */
		pickAsked(event) {
			if (!event.detail || event.detail.asker !== token) {
				this.awaitingImage = false;
			}
		},

		/** The chooser's answer: a picture goes in as a picture, any other file as a link to it. */
		picked(event) {
			if (
				!this.awaitingImage ||
				!editor ||
				!event.detail ||
				!event.detail.url
			) {
				return;
			}
			this.awaitingImage = false;
			const file = event.detail;
			if (String(file.mime || '').startsWith('image/')) {
				editor
					.chain()
					.focus()
					.setImage({
						src: file.url,
						alt: file.alt || '',
						title: file.title || '',
					})
					.run();
				return;
			}
			editor
				.chain()
				.focus()
				.insertContent({
					type: 'text',
					text: file.title || file.url,
					marks: [{ type: 'link', attrs: { href: file.url } }],
				})
				.run();
		},
	};
}

document.addEventListener('alpine:init', () => {
	window.Alpine.data('richText', richText);
});
