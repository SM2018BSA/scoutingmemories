// Responsive and role checks for the Scouting Memories 2026 theme.
// Run from this folder with: npm run test:e2e   (panel site must be running)
// Uses the installed Chrome; test users and password live in tests/.env.
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
	testDir: 'tests/e2e',
	timeout: 120_000,
	expect: { timeout: 20_000 },
	fullyParallel: false,
	workers: 1,
	globalSetup: require.resolve('./tests/e2e/global-setup.cjs'),
	use: {
		channel: 'chrome',
		headless: true,
		baseURL: 'https://sm-tailwind.ddev.site',
		ignoreHTTPSErrors: true,
	},
});
