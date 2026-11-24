// Any page of the site. One job: open it, size it, and say in plain sentences what is wrong with it.
const { ReadOnlyGuard } = require('../support/ReadOnlyGuard.cjs');
const { Site } = require('../support/Site.cjs');

/* Runs inside the browser. Returns facts only; the sentences are built in Node. */
function measure({ phone }) {
	const vw = document.documentElement.clientWidth;
	const label = (el) => {
		const cls = typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '';
		return `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}${cls}`;
	};
	const srOnly = (el, cs, r) => cs.position === 'absolute' && (r.width <= 1 || r.height <= 1 || cs.clip !== 'auto' || cs.clipPath !== 'none');
	const shown = (el, cs, r) => r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none' && Number(cs.opacity) !== 0 && !srOnly(el, cs, r);
	// Inside a box that scrolls or clips sideways and itself fits the screen: not the page's problem.
	const contained = (el) => {
		for (let p = el.parentElement; p && p !== document.documentElement; p = p.parentElement) {
			const ox = getComputedStyle(p).overflowX;
			if (ox === 'auto' || ox === 'scroll' || ox === 'hidden' || ox === 'clip') {
				const pr = p.getBoundingClientRect();
				if (p === document.body || (pr.right <= vw + 1 && pr.left >= -1)) return p !== document.body;
			}
		}
		return false;
	};

	const facts = {
		shell: {
			header: !!document.querySelector('#masthead'),
			main: !!document.querySelector('main'),
			footer: !!document.querySelector('#colophon'),
		},
		phpErrors: [], overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
		protruding: [], images: [], tinyTargets: [], smallTargets: [], smallText: [], clipped: [],
	};

	const text = document.body.innerText || '';
	const php = text.match(/\b(Fatal error|Parse error|Warning|Notice|Deprecated)\b:\s[^\n]{0,160}\son line\s\d+/g) || [];
	facts.phpErrors = php.slice(0, 3);
	if (/There has been a critical error on this website/.test(text)) facts.phpErrors.push('WordPress critical error page');

	const sticking = new Set();
	for (const el of document.body.querySelectorAll('*')) {
		const r = el.getBoundingClientRect();
		const cs = getComputedStyle(el);
		if (!shown(el, cs, r)) continue;

		// Parked far off the left edge on purpose (a spam trap, a screen-reader label): cannot cause scrolling.
		const parked = r.right <= 0;
		if (!parked && (r.right > vw + 1 || r.left < -1) && !contained(el)) {
			if (!sticking.has(el.parentElement)) facts.protruding.push(`${label(el)} spans ${Math.round(r.left)} to ${Math.round(r.right)} of ${vw}`);
			sticking.add(el);
		}

		if (el.tagName === 'IMG') {
			if (el.complete && el.naturalWidth === 0 && el.getAttribute('src')) facts.images.push(`broken: ${el.getAttribute('src').slice(0, 80)}`);
			const box = el.parentElement.getBoundingClientRect();
			if (r.width > box.width + 1 && !contained(el)) facts.images.push(`wider than its box (${Math.round(r.width)} in ${Math.round(box.width)}): ${label(el)}`);
		}

		const ownText = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim());
		if (ownText && (cs.overflowX === 'hidden' || cs.overflowX === 'clip') && el.scrollWidth > el.clientWidth + 1
			&& cs.textOverflow !== 'ellipsis' && el.clientWidth > 0) {
			facts.clipped.push(`${label(el)} "${el.textContent.trim().slice(0, 40)}"`);
		}

		// WordPress's own editor toolbar and media window are not the theme's to size.
		const wordpressChrome = el.closest('.wp-editor-wrap, .mce-container, .media-modal');
		if (phone && !wordpressChrome && el.matches('a.btn, button, [role="button"], [role="combobox"], label.tab, select, textarea, input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="file"])')) {
			if (r.height < 24) facts.tinyTargets.push(`${label(el)} is ${Math.round(r.height)}px tall`);
			else if (r.height < 44) facts.smallTargets.push(`${label(el)} ${Math.round(r.height)}px`);
			// Only fields a person types in make a phone zoom.
			const typedIn = el.matches('select, textarea, input:not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="image"]):not([type="range"]):not([type="color"])');
			if (typedIn && parseFloat(cs.fontSize) < 16) facts.smallText.push(`${label(el)} uses ${cs.fontSize} text`);
		}
	}
	for (const k of ['protruding', 'images', 'tinyTargets', 'smallTargets', 'smallText', 'clipped']) {
		facts[k] = [...new Set(facts[k])].slice(0, 6);
	}
	return facts;
}

class BasePage {
	constructor(page) {
		this.page = page;
		this.guard = new ReadOnlyGuard();
		this.jsErrors = [];
		this.notes = [];
		this.response = null;
		this.prepared = false;
	}

	async prepare() {
		if (this.prepared) return;
		await this.guard.attach(this.page);
		this.page.on('pageerror', (e) => this.jsErrors.push(String((e && e.message) || e).slice(0, 200)));
		this.prepared = true;
	}

	async open(urlPath) {
		await this.prepare();
		this.jsErrors.length = 0;
		this.response = await this.page.goto(urlPath, { waitUntil: 'load' });
		return this.response;
	}

	async resize(viewport) {
		await this.page.setViewportSize({ width: viewport.width, height: viewport.height });
		await this.settle();
	}

	/** Lets the layout finish after a resize or a click. */
	async settle() {
		await this.page.evaluate(() => new Promise((done) => requestAnimationFrame(() => requestAnimationFrame(done))));
	}

	/** Everything wrong with the page as it stands, as sentences. An empty list means fine. */
	async problems(viewport, { status = 200 } = {}) {
		const found = [];
		if (this.response && this.response.status() !== status) found.push(`answered ${this.response.status()}, expected ${status}`);

		const f = await this.page.evaluate(measure, { phone: viewport.phone });
		if (!f.shell.header) found.push('the header is missing');
		if (!f.shell.main) found.push('the main area is missing');
		if (!f.shell.footer) found.push('the footer is missing');
		f.phpErrors.forEach((e) => found.push(`PHP error on the page: ${e}`));
		if (f.overflow > 0) found.push(`the page scrolls sideways by ${f.overflow}px`);
		f.protruding.forEach((e) => found.push(`sticks out of the screen: ${e}`));
		f.images.forEach((e) => found.push(`image ${e}`));
		f.tinyTargets.forEach((e) => found.push(`too small to tap: ${e}`));
		f.smallText.forEach((e) => found.push(`small text in a field (phones zoom in on it): ${e}`));
		f.clipped.forEach((e) => found.push(`text is cut off: ${e}`));
		this.jsErrors.forEach((e) => found.push(`JavaScript error: ${e}`));
		if (f.smallTargets.length) this.notes.push(`${viewport.name}: under 44px tall: ${f.smallTargets.join(', ')}`);
		return found;
	}

	async screenshot(loginKey, pageName, viewport) {
		await this.page.screenshot({ path: Site.shotFile(loginKey, pageName, viewport.name), fullPage: true });
	}
}

module.exports = { BasePage };
