// Responsive, role and accessibility checks for the Scouting Memories 2026 theme.
// Run from this folder with: npm run test:e2e
//   default site: the local Forms site (https://sm-forms.ddev.site, must be running)
//   another site: set SM_BASE_URL (and SM_GATE_USER / SM_GATE_PASS for the development server's gate)
// Uses the installed Chrome; test user names and their password live in tests/.env.
const { defineConfig } = require('@playwright/test');
const { Site } = require('./tests/e2e/support/Site.cjs');

module.exports = defineConfig({
	testDir: 'tests/e2e',
	timeout: 240_000,
	expect: { timeout: 20_000 },
	fullyParallel: true,
	workers: Number(process.env.SM_WORKERS || 3),
	// One retry: the pages load pictures from the site's storage, and a single failed download is not a defect.
	retries: 1,
	reporter: [['line'], ['json', { outputFile: `test-results/report-${Site.name}.json` }]],
	globalSetup: require.resolve('./tests/e2e/global-setup.cjs'),
	// Baseline pictures for visual.spec.cjs live with the other pictures (not in Git).
	snapshotPathTemplate: 'tests/screenshots/baseline/{arg}{ext}',
	use: {
		channel: 'chrome',
		headless: true,
		baseURL: Site.baseURL,
		httpCredentials: Site.gate,
		ignoreHTTPSErrors: true,
	},
});
