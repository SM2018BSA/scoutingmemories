// Every public template at phone, tablet, laptop and desktop widths:
// no sideways scrolling, the shell is present, no PHP fatals.
// SAFETY: the route guard blocks everything except GET.
const { test, expect } = require('@playwright/test');

const VIEWPORTS = [
	{ width: 375, height: 812 },
	{ width: 768, height: 1024 },
	{ width: 1024, height: 768 },
	{ width: 1440, height: 900 },
];

const PAGES = [
	{ path: '/', name: 'front' },
	{ path: '/history/?cat=3', name: 'sm-category-history' },
	{ path: '/museums/?cat=7', name: 'sm-category-museums' },
	{ path: '/category/history/', name: 'category-archive' },
	{ path: '/?s=camp', name: 'search-results' },
	{ path: '/evanston-troop-919-formerly-troop-19/', name: 'single-post' },
	{ path: '/login/', name: 'login' },
	{ path: '/no-such-page-hopefully-404/', name: 'not-found' },
];

test.beforeEach(async ({ page }) => {
	await page.route('**/*', (route) =>
		route.request().method() === 'GET' ? route.continue() : route.abort('blockedbyclient'));
});

for (const vp of VIEWPORTS) {
	for (const p of PAGES) {
		test(`${p.name} at ${vp.width}px`, async ({ page }) => {
			await page.setViewportSize(vp);
			await page.goto(p.path, { waitUntil: 'domcontentloaded' });

			await expect(page.locator('#masthead')).toBeVisible();
			await expect(page.locator('#colophon')).toBeAttached();
			expect(await page.evaluate(() => document.body.innerText.includes('Fatal error'))).toBe(false);

			const overflow = await page.evaluate(() =>
				document.documentElement.scrollWidth - document.documentElement.clientWidth);
			expect(overflow, 'page scrolls sideways').toBeLessThanOrEqual(0);

			await page.screenshot({ path: `tests/screenshots/${p.name}-${vp.width}.png`, fullPage: true });
		});
	}
}

test('search dropdown chain fills councils from a state', async ({ page }) => {
	// The chained dropdowns POST to admin-ajax.php (read-only lookups);
	// allow exactly those through the GET-only guard.
	await page.route('**/admin-ajax.php', (route) => {
		const body = route.request().postData() || '';
		if (route.request().method() === 'POST' && /(^|&)action=search_/.test(body)) {
			return route.continue();
		}
		return route.fallback();
	});
	await page.goto('/history/?cat=3', { waitUntil: 'networkidle' });
	await page.evaluate(() => { jQuery('#select_state').val(['IL']).trigger('change'); });
	await page.waitForFunction(() => document.querySelectorAll('#select_council option').length > 0, null, { timeout: 60000 });
	expect(await page.locator('#select_council option').count()).toBeGreaterThan(50);
});
