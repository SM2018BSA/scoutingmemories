/**
 * Front-end JavaScript
 *
 * Processed by esbuild into `../theme/js/script.min.js`, which
 * `../theme/functions.php` enqueues (deferred). One small class per job;
 * every one of them only adds to a page that already works without it.
 */

/**
 * The small-screen menu: one button, one panel.
 * Keeps aria-expanded in step with the panel, closes on Escape (focus goes back
 * to the button), on a tap outside, and when the screen becomes wide enough for
 * the menu in the bar.
 */
class MobileMenu {
	constructor(toggle, panel, wideQuery) {
		this.toggle = toggle;
		this.panel = panel;
		this.wide = window.matchMedia(wideQuery);
	}

	get isOpen() {
		return !this.panel.hidden;
	}

	open() {
		this.panel.hidden = false;
		this.toggle.setAttribute('aria-expanded', 'true');
	}

	close({ focusToggle = false } = {}) {
		this.panel.hidden = true;
		this.toggle.setAttribute('aria-expanded', 'false');
		if (focusToggle) {
			this.toggle.focus();
		}
	}

	listen() {
		this.toggle.addEventListener('click', () =>
			this.isOpen ? this.close() : this.open()
		);
		document.addEventListener('keydown', (event) => {
			if (event.key === 'Escape' && this.isOpen) {
				this.close({ focusToggle: true });
			}
		});
		document.addEventListener('click', (event) => {
			if (
				this.isOpen &&
				!this.panel.contains(event.target) &&
				!this.toggle.contains(event.target)
			) {
				this.close();
			}
		});
		this.wide.addEventListener('change', (event) => {
			if (event.matches) {
				this.close();
			}
		});
	}
}

/**
 * The front page's banner carousel: turns to the next banner every few seconds.
 * It does not turn for someone who asked their device for less motion, and it
 * waits while the pointer or the keyboard is on it.
 */
class BannerCarousel {
	constructor(carousel, everyMs) {
		this.carousel = carousel;
		this.everyMs = everyMs;
		this.index = 0;
		this.paused = false;
	}

	next() {
		if (this.paused || !this.carousel.children.length) {
			return;
		}
		this.index = (this.index + 1) % this.carousel.children.length;
		this.carousel.scrollTo({
			left: this.carousel.clientWidth * this.index,
			behavior: 'smooth',
		});
	}

	start() {
		if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			return;
		}
		for (const type of ['pointerenter', 'focusin']) {
			this.carousel.addEventListener(type, () => (this.paused = true));
		}
		for (const type of ['pointerleave', 'focusout']) {
			this.carousel.addEventListener(type, () => (this.paused = false));
		}
		window.setInterval(() => this.next(), this.everyMs);
	}
}

/**
 * Boxes that scroll sideways (wide tables on small screens) can be reached and
 * scrolled with the keyboard, and say what they are.
 */
class ScrollRegions {
	constructor(selector, label) {
		this.selector = selector;
		this.label = label;
	}

	apply(root = document) {
		for (const box of root.querySelectorAll(this.selector)) {
			if (box.scrollWidth > box.clientWidth && !box.hasAttribute('tabindex')) {
				box.setAttribute('tabindex', '0');
				box.setAttribute('role', 'region');
				box.setAttribute('aria-label', this.label);
			}
		}
	}

	listen() {
		this.apply();
		window.addEventListener('resize', () => this.apply());
		// Panes that were closed measure as zero wide: look again when a tab is switched.
		document.addEventListener('change', (event) => {
			if (event.target.matches('.ma-tabs input[type="radio"]')) {
				this.apply();
			}
		});
	}
}

/**
 * select2 dropdowns get the name of the field they replace, so a screen reader
 * says "Select State" instead of nothing. select2 builds its boxes later (and
 * again after each lookup), so new boxes are named as they appear.
 */
class Select2Names {
	nameAll(root = document) {
		for (const select of root.querySelectorAll('select.select2-hidden-accessible')) {
			const label = select.id && document.querySelector(`label[for="${select.id}"]`);
			const box = select.nextElementSibling && select.nextElementSibling.querySelector('.select2-selection');
			if (label && box && !box.hasAttribute('aria-label')) {
				box.setAttribute('aria-label', label.textContent.trim());
			}
		}
	}

	listen() {
		this.nameAll();
		new MutationObserver(() => this.nameAll()).observe(document.body, {
			childList: true,
			subtree: true,
		});
	}
}

const menuToggle = document.getElementById('mobile-menu-toggle');
const menuPanel = document.getElementById('mobile-navigation');
if (menuToggle && menuPanel) {
	new MobileMenu(menuToggle, menuPanel, '(min-width: 1024px)').listen();
}

const bannerCarousel = document.getElementById('carouselFrontpage');
if (bannerCarousel) {
	new BannerCarousel(bannerCarousel, 10000).start();
}

new ScrollRegions('.sm-scroll-x, .table-responsive, .sm-view', 'Table: scrolls sideways').listen();

if (document.querySelector('select')) {
	new Select2Names().listen();
}
