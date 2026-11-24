// The front-end pages behind the login, for every kind of login, at every screen size:
// each My Account tab and sub-tab that login gets, and the Add pages. Read-only.
const { test } = require('@playwright/test');
const { DashboardPage } = require('./pages/DashboardPage.cjs');
const { ROLES, ADD_PAGES } = require('./support/Roles.cjs');
const { sweep } = require('./support/Sweep.cjs');

for (const role of ROLES) {
	test.describe(`${role.key}`, () => {
		test.use({ storageState: role.authFile });

		for (const screen of role.screens) {
			test(`my-account ${screen.name}`, async ({ page }, testInfo) => {
				const view = new DashboardPage(page);
				await sweep(view, () => view.openScreen(screen), { login: role.key, name: `my-account-${screen.name}` }, testInfo);
			});
		}

		for (const target of [{ name: 'add-a-post', path: '/add-a-post/' }, ...ADD_PAGES]) {
			test(`${target.name}`, async ({ page }, testInfo) => {
				const view = new DashboardPage(page);
				await sweep(view, () => view.open(target.path), { login: role.key, name: target.name }, testInfo);
			});
		}
	});
}
