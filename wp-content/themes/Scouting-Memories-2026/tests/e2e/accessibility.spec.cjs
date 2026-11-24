// Accessibility with axe-core (WCAG 2.1 A and AA): no critical or serious problem on any
// public page or dashboard screen, at a phone size and a desktop size. Read-only.
const { test, expect } = require('@playwright/test');
const { AxeBuilder } = require('@axe-core/playwright');
const { BasePage } = require('./pages/BasePage.cjs');
const { DashboardPage } = require('./pages/DashboardPage.cjs');
const { PUBLIC_PAGES } = require('./pages/PublicPage.cjs');
const { ROLES, ADD_PAGES } = require('./support/Roles.cjs');
const { knownIssue } = require('./support/KnownIssues.cjs');

const SIZES = [{ name: '375', width: 375, height: 812 }, { name: '1440', width: 1440, height: 900 }];
const admin = ROLES.find((r) => r.key === 'admin');

/**
 * The serious and critical findings, one short line each. WordPress's own post editor is
 * left out (it is not the theme's), and findings on the known-issues list go to `notes`.
 */
async function findings(page, notes) {
	const results = await new AxeBuilder({ page })
		.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
		.exclude('iframe')
		.exclude('.g-recaptcha')
		.exclude('.wp-editor-wrap')
		.analyze();
	const lines = [];
	for (const v of results.violations.filter((x) => x.impact === 'critical' || x.impact === 'serious')) {
		const nodes = v.nodes.filter((n) => {
			const known = knownIssue(v.id, n.target.join(' '));
			if (known) notes.add(`${known.owner}: ${v.id} on ${n.target.join(' ')} (${known.why})`);
			return !known;
		});
		if (nodes.length) lines.push(`${v.id} (${v.impact}, ${nodes.length}x): ${nodes[0].target.join(' ')}`);
	}
	return lines;
}

/** Runs the check at both sizes after `load` has opened the page. */
async function checkAtBothSizes(page, load, name, testInfo) {
	const notes = new Set();
	for (const size of SIZES) {
		await page.setViewportSize({ width: size.width, height: size.height });
		await load();
		expect.soft(await findings(page, notes), `${name} at ${size.name}`).toEqual([]);
	}
	for (const note of notes) testInfo.annotations.push({ type: 'known issue', description: note });
}

for (const target of PUBLIC_PAGES) {
	test(`visitor: ${target.name}`, async ({ page }, testInfo) => {
		const view = new BasePage(page);
		await checkAtBothSizes(page, () => view.open(target.path), target.name, testInfo);
	});
}

test.describe('administrator', () => {
	test.use({ storageState: admin.authFile });

	for (const screen of admin.screens) {
		test(`my-account ${screen.name}`, async ({ page }, testInfo) => {
			const view = new DashboardPage(page);
			await checkAtBothSizes(page, () => view.openScreen(screen), screen.name, testInfo);
		});
	}

	for (const target of [{ name: 'add-a-post', path: '/add-a-post/' }, ...ADD_PAGES]) {
		test(`${target.name}`, async ({ page }, testInfo) => {
			const view = new DashboardPage(page);
			await checkAtBothSizes(page, () => view.open(target.path), target.name, testInfo);
		});
	}
});
