// One job: take a page through every screen size and report what is wrong at each.
// The page is loaded fresh once per group of sizes (small screens, wide screens) and then
// resized through that group, so the checks see what a real visitor at that size would.
const { expect } = require('@playwright/test');
const { GROUPS } = require('./Viewports.cjs');

/**
 * @param {import('../pages/BasePage.cjs').BasePage} view  the page object
 * @param {() => Promise<void>} load                        opens the page (called once per group)
 * @param {{ login: string, name: string, status?: number }} what
 * @param {import('@playwright/test').TestInfo} testInfo
 */
async function sweep(view, load, what, testInfo) {
	for (const group of GROUPS) {
		await view.page.setViewportSize({ width: group[0].width, height: group[0].height });
		await load();
		for (const viewport of group) {
			await view.resize(viewport);
			const problems = await view.problems(viewport, { status: what.status || 200 });
			expect.soft(problems, `${what.login} / ${what.name} at ${viewport.name}`).toEqual([]);
			await view.screenshot(what.login, what.name, viewport);
		}
	}
	if (view.notes.length) testInfo.annotations.push({ type: 'note', description: view.notes.join(' | ').slice(0, 900) });
	if (view.guard.blocked.length) testInfo.annotations.push({ type: 'blocked', description: [...new Set(view.guard.blocked)].join(' | ').slice(0, 600) });
}

module.exports = { sweep };
