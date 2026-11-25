// The My Account dashboard. One job: open its screens and read which tabs and panes are there.
const { BasePage } = require('./BasePage.cjs');

class DashboardPage extends BasePage {
	/** Opens one screen: a tab, optionally with extra address parameters or a radio sub-tab to tap. */
	async openScreen(screen) {
		await this.open(`/my-account/?tab=${screen.tab}${screen.query || ''}`);
		if (screen.sub) await this.tapSubTab(screen.sub);
	}

	async tapSubTab(inputId) {
		await this.page.locator(`label[for="${inputId}"]`).click();
		await this.settle();
	}

	async tapTab(tabId) {
		await this.page.locator(`[data-ma="tablist"] label[for="tab-${tabId}"]`).click();
		await this.settle();
	}

	/** The tab labels as printed, in order (Admin 2 is printed but kept hidden). */
	async tabLabels() {
		return (await this.page.locator('[data-ma="tablist"] label').allTextContents()).map((t) => t.trim());
	}

	/** The id of the pane that is showing, for example "pane-myHome". */
	async openPane() {
		return this.page.evaluate(() => {
			const pane = [...document.querySelectorAll('[data-ma="pane"]')].find((p) => getComputedStyle(p).display !== 'none');
			return pane ? pane.id : null;
		});
	}

	async hasPane(tabId) {
		return (await this.page.locator(`#pane-${tabId}`).count()) > 0;
	}

	async isLoginPrompt() {
		return (await this.page.locator('[data-ma="tabs"]').count()) === 0
			&& (await this.page.getByText('Please log in to see this page.').count()) > 0;
	}
}

module.exports = { DashboardPage };
