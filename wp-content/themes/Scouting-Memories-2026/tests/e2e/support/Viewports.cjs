// The screen sizes every page is checked at. One job: the list.
// The theme's menu switches from the hamburger to the full bar at MENU_BREAKPOINT (Tailwind's lg).
const MENU_BREAKPOINT = 1024;

const VIEWPORTS = [
	{ name: '320', width: 320, height: 568 },
	{ name: '375', width: 375, height: 812 },
	{ name: '414', width: 414, height: 896 },
	{ name: '812-sideways', width: 812, height: 375 },
	{ name: '768', width: 768, height: 1024 },
	{ name: '1024', width: 1024, height: 768 },
	{ name: '1280', width: 1280, height: 800 },
	{ name: '1440', width: 1440, height: 900 },
	{ name: '1920', width: 1920, height: 1080 },
].map((v) => ({ ...v, compact: v.width < MENU_BREAKPOINT, phone: Math.min(v.width, v.height) < 500 }));

// A page is loaded fresh once in each group, then resized through the group's sizes.
const GROUPS = [VIEWPORTS.filter((v) => v.compact), VIEWPORTS.filter((v) => !v.compact)];

module.exports = { VIEWPORTS, GROUPS, MENU_BREAKPOINT };
