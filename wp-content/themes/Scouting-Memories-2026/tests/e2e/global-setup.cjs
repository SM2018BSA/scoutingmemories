// Signs each test login in once, through the site's own login page, and saves its session
// for the specs. This sign-in is the only form the suite ever submits.
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright-core');
const { Site } = require('./support/Site.cjs');
const { ROLES } = require('./support/Roles.cjs');

async function signIn(browser, role, password) {
	const context = await browser.newContext({ ignoreHTTPSErrors: true, baseURL: Site.baseURL, httpCredentials: Site.gate });
	const page = await context.newPage();
	await page.goto('/login/', { waitUntil: 'domcontentloaded' });
	const form = page.locator('form:has(input[name="log"])').first();
	await form.locator('input[name="log"]').fill(role.username);
	await form.locator('input[name="pwd"]').fill(password);
	await Promise.all([
		page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
		form.locator('[type="submit"]').first().click(),
	]);
	const cookies = await context.cookies();
	if (!cookies.some((c) => c.name.startsWith('wordpress_logged_in_'))) {
		throw new Error(`Sign-in failed for the test login "${role.key}" on ${Site.baseURL} (ended on ${page.url()}).`);
	}
	fs.mkdirSync(path.dirname(role.authFile), { recursive: true });
	await context.storageState({ path: role.authFile });
	await context.close();
}

module.exports = async () => {
	const password = Site.env.SM_TEST_PASSWORD;
	const browser = await chromium.launch({ channel: 'chrome', headless: true });
	try {
		for (const role of ROLES) await signIn(browser, role, password);
	} finally {
		await browser.close();
	}
};
