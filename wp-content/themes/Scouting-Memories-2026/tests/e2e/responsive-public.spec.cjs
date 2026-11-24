// Every page a visitor can open, at every screen size: no sideways scrolling, nothing sticking
// out, images fit, controls big enough to tap, no PHP or JavaScript errors. Read-only.
const { test } = require('@playwright/test');
const { BasePage } = require('./pages/BasePage.cjs');
const { PUBLIC_PAGES, VISITOR_PAGES } = require('./pages/PublicPage.cjs');
const { sweep } = require('./support/Sweep.cjs');

for (const target of [...PUBLIC_PAGES, ...VISITOR_PAGES]) {
	test(`visitor: ${target.name}`, async ({ page }, testInfo) => {
		const view = new BasePage(page);
		await sweep(view, () => view.open(target.path), { login: 'visitor', name: target.name, status: target.status }, testInfo);
	});
}

test('visitor: search dropdowns fill councils from a state', async ({ page }) => {
	const view = new BasePage(page);
	await view.open('/history/?cat=3');
	await page.waitForFunction(() => window.jQuery && document.querySelectorAll('#select_state option').length > 10, null, { timeout: 60000 });
	await page.evaluate(() => { window.jQuery('#select_state').val(['IL']).trigger('change'); });
	await page.waitForFunction(() => document.querySelectorAll('#select_council option').length > 50, null, { timeout: 60000 });
});
