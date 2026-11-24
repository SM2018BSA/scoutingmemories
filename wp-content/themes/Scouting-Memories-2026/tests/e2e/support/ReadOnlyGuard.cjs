// SAFETY. One job: make sure a test can only look.
// Every request that could change something is stopped before it leaves the browser.
// Let through: GET and HEAD, and the search dropdown lookups (admin-ajax.php with
// action=search_*), which only read. Visitor-counting services are stopped whatever the
// method, so a test visit is never counted as a real one. The one sign-in per test login
// happens in global-setup, outside this guard.
const COUNTERS = /(^|\.)(google-analytics\.com|googletagmanager\.com|doubleclick\.net|analytics\.google\.com)$/;

class ReadOnlyGuard {
	constructor() {
		this.blocked = [];
	}

	async attach(page) {
		await page.route('**/*', (route) => {
			const request = route.request();
			const method = request.method();
			let host = '';
			try { host = new URL(request.url()).hostname; } catch { host = ''; }
			if (COUNTERS.test(host)) return route.abort('blockedbyclient');
			if (method === 'GET' || method === 'HEAD') return route.continue();
			if (method === 'POST' && /\/admin-ajax\.php(\?|$)/.test(request.url())
				&& /(^|&)action=search_/.test(request.postData() || '')) {
				return route.continue();
			}
			this.blocked.push(`${method} ${request.url().split('?')[0]}`);
			return route.abort('blockedbyclient');
		});
		return this;
	}
}

module.exports = { ReadOnlyGuard };
