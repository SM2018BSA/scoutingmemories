// Who sees what: each login gets exactly its own tabs and pages, nothing more, nothing less,
// and every tab opens with a tap on a phone-size screen. Read-only.
const { test, expect } = require('@playwright/test');
const { DashboardPage } = require('./pages/DashboardPage.cjs');
const { ROLES, ADD_PAGES } = require('./support/Roles.cjs');

const PHONE = { name: '375', width: 375, height: 812, phone: true, compact: true };
const FORM_IN_MAIN = 'main form:has([type="submit"])';

for (const role of ROLES) {
	test.describe(`${role.key}`, () => {
		test.use({ storageState: role.authFile });

		test('sees exactly its own tabs', async ({ page }) => {
			const view = new DashboardPage(page);
			await view.open('/my-account/');
			expect(await view.tabLabels()).toEqual(role.tabs.map((t) => t.label));
			for (const tab of role.forbiddenTabs) {
				expect(await view.hasPane(tab.id), `${tab.label} must not be on the page at all`).toBe(false);
			}
		});

		test('a tab it may not see does not open from its address', async ({ page }) => {
			const view = new DashboardPage(page);
			for (const tab of role.forbiddenTabs) {
				await view.open(`/my-account/?tab=${tab.id}`);
				expect(await view.openPane(), `asked for ${tab.id}`).toBe('pane-myHome');
				expect(await view.hasPane(tab.id)).toBe(false);
			}
			await view.open('/my-account/?tab=%3Cscript%3Ealert(1)%3C/script%3E');
			expect(await view.openPane(), 'a made-up tab name falls back to Home').toBe('pane-myHome');
		});

		test('every tab opens with a tap on a phone', async ({ page }) => {
			const view = new DashboardPage(page);
			await page.setViewportSize({ width: PHONE.width, height: PHONE.height });
			await view.open('/my-account/');
			for (const tab of role.tabs.filter((t) => t.id !== 'myAdmin2')) { // Admin 2 opens from the Users list
				await view.tapTab(tab.id);
				expect(await view.openPane(), `tapped ${tab.label}`).toBe(`pane-${tab.id}`);
				expect(await view.problems(PHONE), `${tab.label} after the tap`).toEqual([]);
			}
		});

		test('Add a Council / Camp / Lodge pages', async ({ page }) => {
			const view = new DashboardPage(page);
			for (const target of ADD_PAGES) {
				await view.open(target.path);
				if (role.canAddIndexing) {
					await expect(page.locator(FORM_IN_MAIN).first(), `${target.name} shows its form`).toBeVisible();
					await expect(page.getByText(target.refusal)).toHaveCount(0);
				} else {
					await expect(page.getByText(target.refusal), `${target.name} refuses`).toBeVisible();
					await expect(page.locator(FORM_IN_MAIN)).toHaveCount(0);
				}
			}
		});

		test('Add a Post shows the form or a clear refusal', async ({ page }, testInfo) => {
			const view = new DashboardPage(page);
			await view.open('/add-a-post/');
			const hasForm = (await page.locator(FORM_IN_MAIN).count()) > 0;
			const refused = (await page.getByText(/do not have permission/i).count()) > 0;
			testInfo.annotations.push({ type: 'add-a-post', description: `${role.key}: ${hasForm ? 'form' : 'no permission message'}` });
			expect(hasForm || refused, 'the page shows the form or says why not').toBe(true);
		});
	});
}

test.describe('visitor (not logged in)', () => {
	test('My Account asks to log in and shows no dashboard', async ({ page }) => {
		const view = new DashboardPage(page);
		await view.open('/my-account/?tab=myAdmin');
		expect(await view.isLoginPrompt()).toBe(true);
		await expect(page.locator('main a[href*="login"]').first()).toBeVisible();
	});

	test('the Add pages show no form', async ({ page }) => {
		const view = new DashboardPage(page);
		for (const target of [{ path: '/add-a-post/' }, ...ADD_PAGES]) {
			await view.open(target.path);
			await expect(page.locator(FORM_IN_MAIN), `${target.path} as a visitor`).toHaveCount(0);
		}
	});
});
