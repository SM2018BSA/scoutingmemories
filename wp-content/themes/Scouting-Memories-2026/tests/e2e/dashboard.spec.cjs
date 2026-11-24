// The My Account dashboard per role: which tabs each role sees (the Access
// matrix), deep links, and no sideways scrolling at phone width.
// SAFETY: the route guard blocks everything except GET (sign-in happens once
// in global-setup, whose sessions these tests reuse).
const path = require('path');
const { test, expect } = require('@playwright/test');

const ROLES = [
	{ name: 'admin', tabs: ['Home', 'Posts', 'Media', 'Defaults', 'Indexing', 'Admin', 'Admin 2'] },
	{ name: 'indexer', tabs: ['Home', 'Posts', 'Media', 'Defaults', 'Indexing'] },
	{ name: 'subscriber', tabs: ['Home', 'Posts', 'Media', 'Defaults'] },
];

for (const role of ROLES) {
	test.describe(`${role.name}`, () => {
		test.use({ storageState: path.join(__dirname, '..', '.auth', `${role.name}.json`) });

		test.beforeEach(async ({ page }) => {
			await page.route('**/*', (route) =>
				route.request().method() === 'GET' ? route.continue() : route.abort('blockedbyclient'));
		});

		test('sees exactly the allowed tabs', async ({ page }) => {
			await page.goto('/my-account/', { waitUntil: 'domcontentloaded' });
			const labels = await page.locator('.ma-tablist label').allTextContents();
			expect(labels.map((t) => t.trim())).toEqual(role.tabs);
			expect(await page.evaluate(() => document.body.innerText.includes('Fatal error'))).toBe(false);
		});

		test('no sideways scroll at phone width', async ({ page }) => {
			await page.setViewportSize({ width: 375, height: 812 });
			await page.goto('/my-account/', { waitUntil: 'domcontentloaded' });
			expect(await page.evaluate(() =>
				document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
		});
	});
}

test.describe('admin deep links', () => {
	test.use({ storageState: path.join(__dirname, '..', '.auth', 'admin.json') });

	test.beforeEach(async ({ page }) => {
		await page.route('**/*', (route) =>
			route.request().method() === 'GET' ? route.continue() : route.abort('blockedbyclient'));
	});

	test('?tab and ?tab2 open the right panes, junk falls back to Home', async ({ page }) => {
		await page.goto('/my-account/?tab=myIndexing&tab2=myLodges', { waitUntil: 'domcontentloaded' });
		const open = await page.evaluate(() => ({
			pane: [...document.querySelectorAll('.ma-pane')].find((p) => getComputedStyle(p).display !== 'none')?.id,
			sub: [...document.querySelectorAll('.ma-subpane')].find((p) => getComputedStyle(p).display !== 'none' && p.id.startsWith('idx'))?.id,
		}));
		expect(open.pane).toBe('pane-myIndexing');
		expect(open.sub).toBe('idx-pane-lodges');

		await page.goto('/my-account/?tab=%3Cscript%3Ealert(1)%3C/script%3E', { waitUntil: 'domcontentloaded' });
		expect(await page.evaluate(() =>
			[...document.querySelectorAll('.ma-pane')].find((p) => getComputedStyle(p).display !== 'none')?.id)).toBe('pane-myHome');
	});
});
