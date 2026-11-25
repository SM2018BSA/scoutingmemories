/**
 * Front-end JavaScript
 *
 * Processed by esbuild into `../theme/js/script.min.js`, which
 * `../theme/functions.php` enqueues (deferred).
 *
 * Everything here is an Alpine.js part: a small named piece of behaviour that a
 * template switches on with `x-data="name"` on the element it belongs to. This
 * file holds the parts of the page frame (menu, banners, Memory Box); the search
 * form and the My Account page register their own from their own files.
 */

import Alpine from 'alpinejs';
import registerSearch from './search.js';
import registerAccount from './account.js';

/**
 * The small-screen menu: one button, one panel (template-parts/layout/header-content.php).
 * Keeps the panel and the button's aria-expanded in step, closes on Escape (focus
 * goes back to the button), on a tap outside, and when the screen becomes wide
 * enough for the menu in the bar.
 */
Alpine.data('siteMenu', () => ({
	open: false,

	init() {
		// 62rem is the lg step (992px), where the menu moves into the bar.
		window.matchMedia('(min-width: 62rem)').addEventListener('change', (event) => {
			if (event.matches) {
				this.open = false;
			}
		});
	},

	toggle() {
		this.open = !this.open;
	},

	close(focusButton = false) {
		if (!this.open) {
			return;
		}
		this.open = false;
		if (focusButton) {
			this.$refs.toggle.focus();
		}
	},

	outside(event) {
		if (
			this.open &&
			!this.$refs.panel.contains(event.target) &&
			!this.$refs.toggle.contains(event.target)
		) {
			this.open = false;
		}
	},
}));

/**
 * One dropdown of the primary menu (a details/summary fold printed by the nav
 * walker). The browser opens and closes it; this closes it the way the old
 * site's dropdowns closed: on a click anywhere else, on Escape (focus goes back
 * to the dropdown's own title), and when another dropdown opens.
 */
Alpine.data('menuFold', () => ({
	shut(refocus = false) {
		const fold = this.$root;
		if (!fold.open) {
			return;
		}
		const hadFocus = fold.contains(document.activeElement);
		fold.open = false;
		if (refocus && hadFocus) {
			fold.querySelector('summary').focus();
		}
	},

	only() {
		const fold = this.$root;
		if (!fold.open) {
			return;
		}
		for (const other of fold.closest('ul').querySelectorAll('details[open]')) {
			if (other !== fold) {
				other.open = false;
			}
		}
	},
}));

/**
 * The front page's banners (frontpage.php): shows the next banner every 10
 * seconds. It does not turn for someone who asked their device for less motion,
 * and it waits while the pointer or the keyboard is on it.
 */
Alpine.data('bannerSlides', () => ({
	slide: 0,
	paused: false,

	init() {
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return;
		}
		const count = this.$root.querySelectorAll('img').length;
		if (count < 2) {
			return;
		}
		window.setInterval(() => {
			if (!this.paused) {
				this.slide = (this.slide + 1) % count;
			}
		}, 10000);
	},
}));

/**
 * The Memory Box page (memory-box.php): the glue between Formidable form 39 and
 * the Cliptakes recorder, as the old page had it. Both plugins print their own
 * markup, so this works on their ids and gives their buttons the site's blue
 * button classes.
 */
Alpine.data('memoryBox', () => ({
	// The old site's blue button; the colours are forced over the plugins' own.
	buttonClasses:
		'inline-block cursor-pointer rounded-md border px-3 py-1.5 text-center align-middle text-base leading-normal font-normal no-underline select-none border-blue! bg-blue! text-white!',

	hide(selector) {
		for (const element of document.querySelectorAll(selector)) {
			element.classList.add('hidden', 'hidden!');
		}
	},

	// Puts a recorder button inside the wrapper Formidable styles its own buttons in.
	dress(button) {
		const outer = document.createElement('div');
		outer.className = 'frm_style_bootstrap5 with_frm_style js';
		const inner = document.createElement('div');
		inner.className = 'frm_submit';
		outer.append(inner);
		button.before(outer);
		inner.append(button);
		button.classList.add(...this.buttonClasses.split(' '));
	},

	press(selector) {
		const button = document.querySelector(selector);
		if (button) {
			button.click();
		}
	},

	init() {
		const continueButtons = document.querySelectorAll("#ctiv-signup-form input[value='Continue']");

		this.hide('#ctiv-signup-form .ctiv-signup-row');
		this.hide('#form_videointerviews .form-group.frm_submit');
		for (const button of document.querySelectorAll('#ctiv-main-container button')) {
			this.dress(button);
		}
		for (const button of continueButtons) {
			this.dress(button);
		}

		// The recorder's name boxes follow the form's.
		document.addEventListener('keyup', (event) => {
			const copies = {
				field_mb_name_first: 'ctiv-signup-first-name',
				field_mb_name_last: 'ctiv-signup-last-name',
			};
			const target = document.getElementById(copies[event.target.id]);
			if (target) {
				target.value = event.target.value;
			}
		});

		// The form's switch swaps the lodge picker for a plain text box.
		document.addEventListener('click', (event) => {
			if (event.target.closest('.frm_slider')) {
				for (const id of ['mb_lodge_indexing', 'mb_lodge_text']) {
					const box = document.getElementById(id);
					if (box) {
						box.classList.toggle('hidden');
					}
				}
			}
		});

		// The recorder's Next and Continue buttons also send the form's pages.
		const next = document.getElementById('ctiv-intro-next');
		if (next) {
			next.addEventListener('click', () => this.press('.frm_page_num_1 #mbox_submit'));
		}
		for (const button of continueButtons) {
			button.addEventListener('click', () => {
				this.press('.frm_page_num_2 #mbox_submit');
				this.hide('#frm_form_39_container');
			});
		}
	},
}));

registerSearch(Alpine);
registerAccount(Alpine);

Alpine.start();
