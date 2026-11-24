// The pages anyone can open. One job: the list.
const PUBLIC_PAGES = [
	{ name: 'front', path: '/' },
	{ name: 'category-history', path: '/history/?cat=3' },
	{ name: 'category-photographs', path: '/photographs/' },
	{ name: 'category-movies', path: '/movies/' },
	{ name: 'category-memorabilia', path: '/memorabilia/' },
	{ name: 'category-oral-history', path: '/oral-history/' },
	{ name: 'category-museums', path: '/museums/?cat=7' },
	{ name: 'category-archive', path: '/category/history/' },
	{ name: 'search-results', path: '/?s=camp' },
	{ name: 'single-memory', path: '/evanston-troop-919-formerly-troop-19/' },
	{ name: 'single-memory-pdf', path: '/the-trail-1914-1917/' },
	{ name: 'login', path: '/login/' },
	{ name: 'reset-password', path: '/reset-password/' },
	{ name: 'new-account', path: '/register/' },
	{ name: 'info-page', path: '/info-page/' },
	{ name: 'contact-us', path: '/contact-us/' },
	{ name: 'privacy-policy', path: '/privacy-policy/' },
	{ name: 'plain-page', path: '/publishing-guidelines/' },
	{ name: 'not-found', path: '/no-such-page-hopefully-404/', status: 404 },
];

// Members-only pages, as a visitor who is not logged in gets them.
const VISITOR_PAGES = [
	{ name: 'my-account', path: '/my-account/' },
	{ name: 'add-a-post', path: '/add-a-post/' },
	{ name: 'add-a-council', path: '/add-a-council/' },
	{ name: 'add-a-camp', path: '/add-a-camp/' },
	{ name: 'add-a-lodge', path: '/add-a-lodge/' },
];

module.exports = { PUBLIC_PAGES, VISITOR_PAGES };
