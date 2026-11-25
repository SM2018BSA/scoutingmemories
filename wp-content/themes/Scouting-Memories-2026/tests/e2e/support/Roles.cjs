// Who sees what. One job: for each test login, the exact screens it must and must not get.
// This mirrors scouting-memories-core/src/Access.php; when Access changes, change it here too.
const { Site } = require('./Site.cjs');

const EVERYONE_TABS = [
	{ id: 'myHome', label: 'Home' },
	{ id: 'myPosts', label: 'Posts' },
	{ id: 'myMedia', label: 'Media' },
	{ id: 'myDefaults', label: 'Defaults' },
];
const INDEXING_TAB = { id: 'myIndexing', label: 'Indexing' };
const ADMIN_TABS = [{ id: 'myAdmin', label: 'Admin' }, { id: 'myAdmin2', label: 'Admin 2' }];

// The Indexing tab's own pills (radio sub-tabs, as in the Admin tab).
const INDEXING_SUBTABS = ['idx-myCouncils', 'idx-myCamps', 'idx-myLodges', 'idx-myTools'];
const ADMIN_SUBTABS = ['adm-users', 'adm-posts', 'adm-states', 'adm-councils', 'adm-camps', 'adm-lodges', 'adm-tools'];

const ADD_PAGES = [
	{ name: 'add-a-council', path: '/add-a-council/', refusal: 'You do not have permissions to add a council.' },
	{ name: 'add-a-camp', path: '/add-a-camp/', refusal: 'You do not have permissions to add a camp.' },
	{ name: 'add-a-lodge', path: '/add-a-lodge/', refusal: 'You do not have permissions to add a lodge.' },
];

class Role {
	constructor({ key, envKey, roles, indexing = false, admin = false }) {
		Object.assign(this, { key, envKey, roles, indexing, admin });
	}

	get username() {
		return Site.env[this.envKey];
	}

	get authFile() {
		return Site.authFile(this.key);
	}

	/** The My Account tabs this login must see, in order. */
	get tabs() {
		return [...EVERYONE_TABS, ...(this.indexing ? [INDEXING_TAB] : []), ...(this.admin ? ADMIN_TABS : [])];
	}

	/** The My Account tabs this login must NOT see. */
	get forbiddenTabs() {
		return [...(this.indexing ? [] : [INDEXING_TAB]), ...(this.admin ? [] : ADMIN_TABS)];
	}

	/** Every My Account screen (tab, or tab + sub-tab) this login can open. */
	get screens() {
		const list = EVERYONE_TABS.map((t) => ({ name: t.id, tab: t.id }));
		if (this.indexing) INDEXING_SUBTABS.forEach((sub) => list.push({ name: `myIndexing-${sub}`, tab: 'myIndexing', sub }));
		if (this.admin) {
			ADMIN_SUBTABS.forEach((sub) => list.push({ name: `myAdmin-${sub}`, tab: 'myAdmin', sub }));
			list.push({ name: 'myAdmin2', tab: 'myAdmin2' });
		}
		return list;
	}

	/** May this login use the Add a Council / Camp / Lodge pages? Same set as the Indexing tab. */
	get canAddIndexing() {
		return this.indexing;
	}

	/** May this login run the end-dates tool (Indexing, Maintenance Tools)? Administrators only. */
	get canUpdateEndDates() {
		return this.admin;
	}
}

const ROLES = [
	new Role({ key: 'subscriber', envKey: 'SM_TEST_SUBSCRIBER', roles: ['subscriber'] }),
	new Role({ key: 'historian', envKey: 'SM_TEST_HISTORIAN', roles: ['historian'] }),
	new Role({ key: 'indexer', envKey: 'SM_TEST_INDEXER', roles: ['index_contributor'], indexing: true }),
	new Role({ key: 'regional', envKey: 'SM_TEST_REGIONAL', roles: ['regional'], indexing: true }),
	new Role({ key: 'manager', envKey: 'SM_TEST_MANAGER', roles: ['manager'], indexing: true }),
	new Role({ key: 'admin', envKey: 'SM_TEST_ADMIN', roles: ['administrator'], indexing: true, admin: true }),
	new Role({ key: 'admin-historian', envKey: 'SM_TEST_ADMIN_HISTORIAN', roles: ['administrator', 'historian'], indexing: true, admin: true }),
];

module.exports = { ROLES, ADD_PAGES, EVERYONE_TABS, INDEXING_TAB, ADMIN_TABS };
