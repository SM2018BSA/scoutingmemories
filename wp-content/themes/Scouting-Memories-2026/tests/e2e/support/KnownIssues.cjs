// Accessibility findings that belong to another part of the site, not to this theme.
// One job: the list. Each entry names its owner and the reason. They are kept out of the
// pass/fail result and printed with the test as a note, so they are not forgotten.
const KNOWN_ISSUES = [
	{ rule: 'label', target: /^#field_(user_avatar|nur_avatar_image)/, owner: 'Scouting Forms', why: 'the avatar upload field is printed without a label' },
	{ rule: 'aria-hidden-focus', target: /^#sm-modal-/, owner: 'Scouting Forms', why: 'its hidden pop-up still holds controls the keyboard can reach' },
	{ rule: 'color-contrast', target: /^#primary-menu(-mobile)? /, owner: 'the owner (old site design)', why: 'menu links that are not the current page are white at 55% on the green bar, exactly as on the old site' },
	{ rule: 'color-contrast', target: /^label\[for="(adm|idx)-/, owner: 'the owner (old site design)', why: 'the open pill is white on the button blue of the old site, exactly as it was' },
];

/** The known issue a finding matches, or undefined. */
function knownIssue(ruleId, firstTarget) {
	return KNOWN_ISSUES.find((k) => k.rule === ruleId && k.target.test(firstTarget));
}

module.exports = { KNOWN_ISSUES, knownIssue };
