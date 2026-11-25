// The test-site role switcher (scouting-memories-core/src/Dev/RoleSwitcher.php): a real
// administrator sees the floating "Test role" box, can look at the site as another kind of user,
// and can always switch back from the same box. Other logins never get the box. Read-only: a
// switch is a link, and nothing is saved to the account.
const { test, expect } = require('@playwright/test');
const { DashboardPage } = require('./pages/DashboardPage.cjs');
const { ROLES } = require('./support/Roles.cjs');

const admin = ROLES.find((r) => r.key === 'admin');
const subscriber = ROLES.find((r) => r.key === 'subscriber');
const indexer = ROLES.find((r) => r.key === 'indexer');
const labels = (role) => role.tabs.map((t) => t.label);

/** Picks one kind of user in the box's drop-down and waits for the page it loads. */
async function pick(page, name) {
	const select = page.locator('#sm-dev-role-pick');
	const option = select.locator('option').filter({ hasText: name }).first();
	const value = await option.getAttribute('value');
	await Promise.all([
		page.waitForURL((url) => !url.search.includes('sm_dev_role'), { waitUntil: 'load' }),
		select.selectOption(value),
	]);
	await page.locator('#sm-dev-role').waitFor();
}

test.describe('a real administrator', () => {
	test.use({ storageState: admin.authFile });

	test('switches to other kinds of user and back, and the box never goes away', async ({ page }) => {
		const view = new DashboardPage(page);
		await view.open('/my-account/');
		const box = page.locator('#sm-dev-role');
		await expect(box).toBeVisible();
		await expect(box.locator('[data-sm-dev="head"] b')).toHaveText('Test role: Administrator');
		expect(await view.tabLabels()).toEqual(labels(admin));

		await pick(page, 'Subscriber');
		await view.settle();
		await expect(box.locator('[data-sm-dev="head"] b')).toHaveText('Test role: Subscriber');
		expect(await view.tabLabels(), 'the page is drawn as a subscriber sees it').toEqual(labels(subscriber));
		expect(await view.hasPane('myAdmin')).toBe(false);
		expect(page.url(), 'the address is clean again after a switch').not.toContain('sm_dev_role');

		await pick(page, 'Index Contributor');
		await view.settle();
		expect(await view.tabLabels()).toEqual(labels(indexer));

		await pick(page, /My real roles/);
		await view.settle();
		await expect(box.locator('[data-sm-dev="head"] b')).toHaveText('Test role: Administrator');
		expect(await view.tabLabels(), 'back to the real administrator').toEqual(labels(admin));
	});

	test('the box can be made small and big again, and has no way to close it', async ({ page }) => {
		const view = new DashboardPage(page);
		await view.open('/');
		const box = page.locator('#sm-dev-role');
		const body = box.locator('[data-sm-dev="body"]');
		await expect(body).toBeVisible();
		await box.locator('[data-sm-dev="min"]').click();
		await expect(body).toBeHidden();
		await expect(box.locator('[data-sm-dev="head"]')).toBeVisible();
		await page.reload();
		await expect(body, 'it stays small on the next page').toBeHidden();
		await box.locator('[data-sm-dev="min"]').click();
		await expect(body).toBeVisible();
		await expect(box.locator('button')).toHaveCount(1);
		await expect(box.locator('select'), 'the kinds of user are one drop-down').toHaveCount(1);
	});
});

test.describe('a login that is not an administrator', () => {
	test.use({ storageState: indexer.authFile });

	test('gets no box, and a switch address does nothing', async ({ page }) => {
		const view = new DashboardPage(page);
		await view.open('/my-account/?sm_dev_role=administrator');
		await expect(page.locator('#sm-dev-role')).toHaveCount(0);
		expect(await view.tabLabels()).toEqual(labels(indexer));
	});
});

test('a visitor who is not logged in gets no box', async ({ page }) => {
	await page.goto('/');
	await expect(page.locator('#sm-dev-role')).toHaveCount(0);
});
