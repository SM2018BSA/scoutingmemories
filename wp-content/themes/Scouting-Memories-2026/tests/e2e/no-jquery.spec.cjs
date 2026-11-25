// No jQuery on the front end: not the library itself and none of the WordPress scripts that
// need it (the media window, the TinyMCE editor, select2). Checked for a visitor on every
// public page and for the administrator on every My Account screen and the four Add pages.
// Also: the theme's own replacements are there (the media library on the Media tab, the
// rich-text editor on Add a Post). Read-only: nothing is uploaded and no form is sent.
const { test, expect } = require('@playwright/test');
const { BasePage } = require('./pages/BasePage.cjs');
const { DashboardPage } = require('./pages/DashboardPage.cjs');
const { PUBLIC_PAGES } = require('./pages/PublicPage.cjs');
const { ROLES, ADD_PAGES } = require('./support/Roles.cjs');

const FORBIDDEN = /jquery|backbone|underscore|tinymce|plupload|media-views|select2/i;
const ADD_A_POST = { name: 'add-a-post', path: '/add-a-post/' };
const admin = ROLES.find((r) => r.key === 'admin');

/** Notes the address of every script the page asks for, from now on. */
function watchScripts(page) {
	const asked = [];
	page.on('request', (request) => {
		if (request.resourceType() === 'script') asked.push(request.url());
	});
	return asked;
}

/** What is wrong, as sentences: jQuery in the page, or a forbidden script asked for or printed. */
async function jqueryProblems(page, asked) {
	const found = [];
	const facts = await page.evaluate(() => ({
		jquery: typeof window.jQuery,
		dollar: typeof window.$ === 'function' && !!window.$.fn,
		printed: [...document.scripts].map((s) => s.src).filter(Boolean),
	}));
	if (facts.jquery !== 'undefined') found.push(`window.jQuery is ${facts.jquery}`);
	if (facts.dollar) found.push('window.$ is jQuery');
	for (const url of new Set([...asked, ...facts.printed])) {
		if (FORBIDDEN.test(url)) found.push(`forbidden script: ${url}`);
	}
	return found;
}

/** Opens a page with `load` and checks it. */
async function check(page, load, name) {
	const asked = watchScripts(page);
	await load();
	expect(await page.evaluate(() => typeof window.jQuery), `${name}: window.jQuery`).toBe('undefined');
	expect(await jqueryProblems(page, asked), name).toEqual([]);
}

for (const target of PUBLIC_PAGES) {
	test(`visitor: ${target.name}`, async ({ page }) => {
		const view = new BasePage(page);
		await check(page, () => view.open(target.path), target.name);
	});
}

test.describe('administrator', () => {
	test.use({ storageState: admin.authFile });

	for (const screen of admin.screens) {
		test(`my-account ${screen.name}`, async ({ page }) => {
			const view = new DashboardPage(page);
			await check(page, () => view.openScreen(screen), screen.name);
		});
	}

	for (const target of [ADD_A_POST, ...ADD_PAGES]) {
		test(`${target.name}`, async ({ page }) => {
			const view = new DashboardPage(page);
			await check(page, () => view.open(target.path), target.name);
		});
	}

	test('the Media tab shows the media library', async ({ page }) => {
		const view = new DashboardPage(page);
		await view.openScreen({ name: 'myMedia', tab: 'myMedia' });
		const library = page.locator('#pane-myMedia [data-media="library"]');
		await expect(library).toBeVisible();
		await expect(library.locator('[data-media="list"]')).toHaveCount(1);
		await expect(library.locator('[data-media="upload"] input[type="file"]')).toBeVisible();
		// The list was fetched (a read): it says how many files there are, and reports no problem.
		await expect(library.locator('[data-media="status"]')).toHaveText(/Showing \d+ of \d+ files\.|No files found\./);
		await expect(library.locator('[data-media="error"]')).toBeHidden();
		expect(view.jsErrors, 'JavaScript errors').toEqual([]);
	});

	test('Add a Post shows the editor, and typing fills the field', async ({ page }) => {
		const view = new DashboardPage(page);
		await view.open(ADD_A_POST.path);
		const rich = page.locator('[data-rich-text]').first();
		await expect(rich.locator('[data-rich-toolbar]')).toBeVisible();
		await expect(rich.locator('[data-rich-toolbar] button').first()).toBeVisible();
		const area = rich.locator('[contenteditable="true"]');
		await expect(area).toBeVisible();
		await area.click();
		await page.keyboard.type('Typed by the no-jquery test');
		await expect(rich.locator('textarea')).toHaveValue(/Typed by the no-jquery test/);
		expect(view.jsErrors, 'JavaScript errors').toEqual([]);
	});
});
