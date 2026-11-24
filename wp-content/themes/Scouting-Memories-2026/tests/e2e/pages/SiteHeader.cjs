// The green masthead and its menu. One job: say what is wrong with it at a given screen size.
class SiteHeader {
	constructor(page) {
		this.page = page;
		this.toggle = page.locator('#mobile-menu-toggle');
		this.panel = page.locator('#mobile-navigation');
		this.bar = page.locator('#site-navigation');
	}

	async problems(viewport) {
		const found = [];
		const vw = viewport.width;

		const overlap = await this.page.evaluate(() => {
			const logo = document.querySelector('#sm_logo');
			const banner = document.querySelector('.sm-masthead-banner');
			if (!logo || !banner) return 'the logo or the banner is missing';
			return logo.getBoundingClientRect().right > banner.getBoundingClientRect().left + 1 ? 'the logo overlaps the banner' : '';
		});
		if (overlap) found.push(overlap);

		if (viewport.compact) {
			if (!(await this.toggle.isVisible())) return [...found, 'the menu button is not shown'];
			if (await this.bar.isVisible()) found.push('the full menu bar is shown on a small screen');
			if (await this.panel.isVisible()) found.push('the menu is open before the button was pressed');

			await this.toggle.click();
			if (!(await this.panel.isVisible())) return [...found, 'pressing the menu button does not open the menu'];
			if ((await this.toggle.getAttribute('aria-expanded')) !== 'true') found.push('the menu button does not say it is open (aria-expanded)');

			const items = await this.panel.locator('#primary-menu-mobile > li > a, #primary-menu-mobile > li > details > summary').evaluateAll((els) =>
				els.map((el) => {
					const r = el.getBoundingClientRect();
					return { text: el.textContent.trim(), left: r.left, right: r.right, height: r.height };
				}));
			if (!items.length) found.push('the menu has no links');
			for (const item of items) {
				if (item.height < 24) found.push(`menu link "${item.text}" is only ${Math.round(item.height)}px tall`);
				if (item.left < -1 || item.right > vw + 1) found.push(`menu link "${item.text}" sticks out of the screen`);
			}

			await this.page.keyboard.press('Escape');
			if (await this.panel.isVisible()) found.push('Escape does not close the menu');
			else if ((await this.toggle.getAttribute('aria-expanded')) !== 'false') found.push('the menu button still says it is open after closing');
		} else {
			if (!(await this.bar.isVisible())) found.push('the menu bar is not shown on a wide screen');
			if (await this.toggle.isVisible()) found.push('the menu button is shown on a wide screen');
			const rows = await this.bar.locator('#primary-menu > li').evaluateAll((els) => [...new Set(els.map((el) => Math.round(el.getBoundingClientRect().top)))].length);
			if (rows > 1) found.push('the menu bar wraps onto more than one line');
		}
		return found;
	}
}

module.exports = { SiteHeader };
