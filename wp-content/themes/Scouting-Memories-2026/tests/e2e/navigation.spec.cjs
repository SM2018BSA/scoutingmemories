// The masthead menu at every screen size: the button and panel on small screens, the full
// bar on wide ones; for a visitor and for a logged-in administrator (the longest menu).
const { test, expect } = require('@playwright/test');
const { BasePage } = require('./pages/BasePage.cjs');
const { SiteHeader } = require('./pages/SiteHeader.cjs');
const { VIEWPORTS } = require('./support/Viewports.cjs');
const { ROLES } = require('./support/Roles.cjs');

const admin = ROLES.find((r) => r.key === 'admin');

async function checkMenu(page) {
	const view = new BasePage(page);
	const header = new SiteHeader(page);
	await view.open('/');
	for (const viewport of VIEWPORTS) {
		await view.resize(viewport);
		expect.soft(await header.problems(viewport), `menu at ${viewport.name}`).toEqual([]);
	}
}

test('visitor menu', async ({ page }) => checkMenu(page));

test.describe('administrator', () => {
	test.use({ storageState: admin.authFile });
	test('administrator menu', async ({ page }) => checkMenu(page));
});
