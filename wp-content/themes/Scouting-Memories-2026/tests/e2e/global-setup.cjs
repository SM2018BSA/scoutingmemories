// Logs each test role in once and saves its session for the specs.
// The only POST the suite ever makes is this sign-in.
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright-core');

function readEnv() {
	const file = path.join(__dirname, '..', '.env');
	return Object.fromEntries(
		fs.readFileSync(file, 'utf8').split(/\r?\n/).filter(Boolean).map((l) => l.split('='))
	);
}

async function login(browser, user, password, stateFile) {
	const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
	const page = await ctx.newPage();
	await page.goto('https://sm-tailwind.ddev.site/wp-login.php', { waitUntil: 'domcontentloaded' });
	if (await page.locator('#user_login').count()) {
		await page.fill('#user_login', user);
		await page.fill('#user_pass', password);
		await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
	} else {
		await page.fill('input[name="log"]', user);
		await page.fill('input[name="pwd"]', password);
		await Promise.all([page.waitForNavigation(), page.click('input[type="submit"], button[type="submit"]')]);
	}
	await ctx.storageState({ path: stateFile });
	await ctx.close();
}

module.exports = async () => {
	const env = readEnv();
	const authDir = path.join(__dirname, '..', '.auth');
	fs.mkdirSync(authDir, { recursive: true });
	const browser = await chromium.launch({ channel: 'chrome', headless: true });
	await login(browser, env.SM_TEST_ADMIN, env.SM_TEST_PASSWORD, path.join(authDir, 'admin.json'));
	await login(browser, env.SM_TEST_INDEXER, env.SM_TEST_PASSWORD, path.join(authDir, 'indexer.json'));
	await login(browser, env.SM_TEST_SUBSCRIBER, env.SM_TEST_PASSWORD, path.join(authDir, 'subscriber.json'));
	await browser.close();
};
