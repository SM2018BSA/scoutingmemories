// Accessibility findings that belong to another part of the site, not to this theme.
// One job: the list. Each entry names its owner and the reason. They are kept out of the
// pass/fail result and printed with the test as a note, so they are not forgotten.
const KNOWN_ISSUES = [
	{ rule: 'label', target: /^#field_(user_avatar|nur_avatar_image)/, owner: 'Scouting Forms', why: 'the avatar upload field is printed without a label' },
	{ rule: 'aria-hidden-focus', target: /^#sm-modal-/, owner: 'Scouting Forms', why: 'its hidden pop-up still holds controls the keyboard can reach' },
];

/** The known issue a finding matches, or undefined. */
function knownIssue(ruleId, firstTarget) {
	return KNOWN_ISSUES.find((k) => k.rule === ruleId && k.target.test(firstTarget));
}

module.exports = { KNOWN_ISSUES, knownIssue };
