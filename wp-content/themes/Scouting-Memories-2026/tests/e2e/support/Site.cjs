// Which website the suite talks to. One job: turn environment variables into addresses and folders.
//   SM_BASE_URL   address of the site (default: the local Forms site)
//   SM_GATE_USER  login for the development server's password gate (online runs only)
//   SM_GATE_PASS  its password; given in the environment, never written to a file
const fs = require('fs');
const path = require('path');

const TESTS_DIR = path.join(__dirname, '..', '..');

class Site {
	static get baseURL() {
		return (process.env.SM_BASE_URL || 'https://sm-forms.ddev.site').replace(/\/+$/, '');
	}

	/** "local" for a DDEV site, "online" for anything else; used in folder names. */
	static get name() {
		return /\.ddev\.site$/.test(new URL(Site.baseURL).hostname) ? 'local' : 'online';
	}

	/** The gate login as Playwright wants it, or undefined when there is no gate. */
	static get gate() {
		if (!process.env.SM_GATE_USER) return undefined;
		return { username: process.env.SM_GATE_USER, password: process.env.SM_GATE_PASS || '' };
	}

	/** tests/.env as an object (test user names and their shared password). */
	static get env() {
		const file = path.join(TESTS_DIR, '.env');
		return Object.fromEntries(
			fs.readFileSync(file, 'utf8').split(/\r?\n/).filter((l) => l.includes('=')).map((l) => {
				const at = l.indexOf('=');
				return [l.slice(0, at).trim(), l.slice(at + 1).trim()];
			})
		);
	}

	static authFile(loginKey) {
		return path.join(TESTS_DIR, '.auth', Site.name, `${loginKey}.json`);
	}

	static shotFile(loginKey, pageName, viewportName) {
		return path.join(TESTS_DIR, 'screenshots', Site.name, loginKey, `${pageName}-${viewportName}.png`);
	}
}

module.exports = { Site };
