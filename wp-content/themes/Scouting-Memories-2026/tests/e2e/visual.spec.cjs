// Before/after pictures: every public page and every My Account screen must look the same as the
// saved baseline (tests/screenshots/baseline, not in Git). Used when the styles are reorganised
// without meaning to change the look. Make a new baseline with: npx playwright test visual --update-snapshots
// Read-only.
const { test, expect } = require('@playwright/test');
const { BasePage } = require('./pages/BasePage.cjs');
const { DashboardPage } = require('./pages/DashboardPage.cjs');
const { PUBLIC_PAGES, VISITOR_PAGES } = require('./pages/PublicPage.cjs');
const { ROLES, ADD_PAGES } = require('./support/Roles.cjs');

const SIZES = [{ name: '1440', width: 1440, height: 900 }, { name: '375', width: 375, height: 812 }];
const admin = ROLES.find((r) => r.key === 'admin');

/** Things that move or load from elsewhere are covered, so only the theme's own look is compared. */
const same = async (page, name) => {
	await page.evaluate(() => document.fonts.ready);
	await expect(page).toHaveScreenshot(`${name}.png`, {
		fullPage: true,
		animations: 'disabled',
		maxDiffPixelRatio: 0.002,
		mask: [page.locator('iframe'), page.locator('.g-recaptcha'), page.locator('#carouselFrontpage')],
	});
};

for (const size of SIZES) {
	test.describe(`${size.name} wide`, () => {
		test.use({ viewport: { width: size.width, height: size.height } });

		for (const target of [...PUBLIC_PAGES, ...VISITOR_PAGES]) {
			test(`visitor: ${target.name}`, async ({ page }) => {
				await new BasePage(page).open(target.path);
				await same(page, `visitor-${target.name}-${size.name}`);
			});
		}

		test.describe('administrator', () => {
			test.use({ storageState: admin.authFile });
			for (const screen of admin.screens) {
				test(`my-account ${screen.name}`, async ({ page }) => {
					await new DashboardPage(page).openScreen(screen);
					await same(page, `admin-${screen.name}-${size.name}`);
				});
			}
			for (const target of [{ name: 'add-a-post', path: '/add-a-post/' }, ...ADD_PAGES]) {
				test(`${target.name}`, async ({ page }) => {
					await new BasePage(page).open(target.path);
					await same(page, `admin-${target.name}-${size.name}`);
				});
			}
		});
	});
}
