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

	// The search form sits folded under the page's heading: open it.
	await page.locator('main summary').first().click();

	// The state list is a Tom Select box (javascript/search.js): wait for it, open it, pick Illinois.
	const stateBox = page.locator('#select_state-ts-control');
	await stateBox.waitFor({ state: 'visible', timeout: 60000 });
	await stateBox.click();
	await page.locator('#select_state-ts-dropdown [data-value="IL"]').click();

	// The picked state shows as a chip, and the council list fills from it and appears.
	await page.locator('[data-ts-item][data-value="IL"]').waitFor({ state: 'visible', timeout: 60000 });
	await page.waitForFunction(() => {
		const councils = document.querySelector('#select_council');
		return !!(councils && councils.tomselect && Object.keys(councils.tomselect.options).length > 50);
	}, null, { timeout: 60000 });
	await page.locator('#select_council-ts-control').waitFor({ state: 'visible', timeout: 60000 });
});
