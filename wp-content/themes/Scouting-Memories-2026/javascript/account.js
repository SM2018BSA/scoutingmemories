/**
 * The My Account dashboard's small behaviours, as Alpine data.
 *
 * One job: register what the dashboard templates name in their x-data attributes.
 * The tabs themselves need no script (they are radio buttons, switched by Tailwind
 * variants in theme/my-account.php).
 */

/**
 * The Users list's Edit links open the Admin 2 tab: only those links, not every
 * link in the Admin pane. Used as x-data="editLinksOpenAdmin2" on the Users pane.
 */
function editLinksOpenAdmin2() {
	return {
		init() {
			for (const link of this.$el.querySelectorAll('a[href*="entry="]')) {
				const url = new URL(link.href, window.location.origin);
				url.searchParams.set('tab', 'myAdmin2');
				link.href = url.toString();
			}
		},
	};
}

/**
 * A box that scrolls sideways (a wide list on a small screen) can be reached and
 * scrolled with the keyboard, and says what it is. Used as x-data="scrollRegion"
 * with data-label="..." on the box; the template calls check() again when the
 * window is resized or a tab is switched (a closed pane measures as zero wide).
 */
function scrollRegion() {
	return {
		init() {
			this.check();
		},
		check() {
			const box = this.$el;
			if (box.scrollWidth > box.clientWidth) {
				box.setAttribute('tabindex', '0');
				box.setAttribute('role', 'region');
				box.setAttribute('aria-label', box.dataset.label || '');
			} else {
				box.removeAttribute('tabindex');
				box.removeAttribute('role');
				box.removeAttribute('aria-label');
			}
		},
	};
}

/**
 * The test sites' floating "Test role" box: big or small, remembered in this browser.
 */
function testRoleBox() {
	const KEY = 'smDevRoleSmall';
	return {
		small: false,
		init() {
			try {
				this.small = window.localStorage.getItem(KEY) === '1';
			} catch (e) {
				// No storage in this browser: the box just starts big.
			}
		},
		toggle() {
			this.small = !this.small;
			try {
				window.localStorage.setItem(KEY, this.small ? '1' : '0');
			} catch (e) {
				// Nothing to remember it in; it still works for this page.
			}
		},
	};
}

export default function registerAccount(Alpine) {
	Alpine.data('editLinksOpenAdmin2', editLinksOpenAdmin2);
	Alpine.data('scrollRegion', scrollRegion);
	Alpine.data('testRoleBox', testRoleBox);
}
