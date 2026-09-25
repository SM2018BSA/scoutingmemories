# Scouting Forms: game plan to replace Formidable Forms

Goal: `scouting-forms` does everything the Scouting Memories site uses Formidable Forms Pro
(and its add-ons) for, so Formidable can eventually be switched off and its license dropped.

This file is the single source of truth for the work. Whoever resumes (including Claude after a
rate-limit pause) starts here: read **Rules**, then **Resume checklist**, then continue at the
first unchecked task. Update the checkboxes and the **Progress log** as work lands.

---

## Rules (set by the site owner, 2026-09-24)

1. **Edit only `wp-content/plugins/scouting-forms/`.** Nothing else: not the theme, not other
   plugins, not mu-plugins, not the dashboard (`git-visualizer`), not `.gitignore`.
   Anything that seems to need a change outside this folder is solved inside the plugin
   (e.g. the Formidable compatibility layer, Phase 7) or written up here as a question.
2. **Never update the live site.** No pushes of any kind (WP Engine or GitHub), no dashboard
   buttons, no commits to `master` or `live`. The owner publishes when they decide to.
3. **Local only.** Test at http://localhost:8088 (Docker). Formidable stays installed and active
   locally; the plugin is tested side by side using its own shortcodes `[sm_form]` / `[sm_view]`
   and the Compare tool, never by replacing Formidable's shortcodes while Formidable is active.
4. **Commits:** local commits on `develop` only, and only plugin paths:
   `git -C E:/WEB/wpengine_repo add -- wp-content/plugins/scouting-forms`
   `git -C E:/WEB/wpengine_repo commit -m "..." -- wp-content/plugins/scouting-forms`
   Check `git status` first; never commit files outside the plugin.
5. **Keep Formidable's data where it is.** The plugin reads and writes Formidable's own tables
   (`wp_frm_forms`, `wp_frm_fields`, `wp_frm_items`, `wp_frm_item_metas`, `frm_display` and
   `frm_form_actions` posts). No data migration; the 16k existing entries, form/field IDs and the
   theme's 113 hard-coded IDs stay valid, and turning Formidable back on is an instant rollback.
6. **No real emails from local testing.** Plugin-triggered mail is intercepted and logged when the
   site URL is localhost (see Phase 0).
7. **Clean up test data.** Test submissions are marked (item_key prefix `smtest-`) and removed with
   the plugin's cleanup tool after each test session. Test posts/users created by test runs are
   removed too. Record anything left behind in the Progress log.
8. **Front end uses the site's Bootstrap 5.3** classes and fonts (like the PDF viewer). Tailwind +
   Reka UI are only for the wp-admin builder app.
9. **Fix problems for real** (no hiding/dismissing warnings). Security: nonces, capability checks,
   `$wpdb->prepare`, escaping on output, sanitizing on input.
10. PHP edits need `docker compose -f E:/WEB/wpengine_repo/docker-compose.yml restart wordpress`
    (opcache doesn't re-check files). JS/CSS don't.

## Resume checklist

1. Read this file and the memory notes.
2. `git -C E:/WEB/wpengine_repo status` and `git -C E:/WEB/wpengine_repo log --oneline -5 develop`
   (must be on `develop`; uncommitted changes should only be inside this plugin).
3. Make sure the local site answers: `curl -s -o /dev/null -w "%{http_code}" http://localhost:8088/`
   (start with `docker compose start` in `E:/WEB/wpengine_repo` if needed).
4. Continue at the first unchecked task below. Commit after each finished task.

---

## What the site uses today (audit 2026-09-24, local copy of live DB)

**Forms in real use** (entries / last entry): Add a Post #6 (2,054 / 2026-09-05), Contact Us #2
(235 / 2026-09-02), New User Registration #17 (75 / 2026-09-03), Edit Account Info #22 (24 / 2025-07).
Index forms (quiet since 2023): Councils Merged #23 (6,064), Add a Camp #11 (3,552), Add a Council #8
(2,323), Add a Lodge #7 (1,003), Link Additional Councils #25/#27, States #10, Select Memories #9/#16/#31.
Unused/empty: Edit Users #33, Edit Account Defaults #34, Regions #35, Support #26, Search Councils/Camps/
Lodges #36/#37/#38, Video Interviews #39 (47, 2022).

**Placed on pages:** Home `[formidable id=9]`, Info Page `[formidable id=16]`, Add a Council/Camp/Lodge/
Post (8/11/7/6), New Account (17), Contact Us (2); 2 pages use `[display-frm-data]`, 6 use `[frm-...]`.

**Field types:** text, email, textarea, rte, number, date, select, checkbox, radio, toggle, hidden,
html, divider/end_divider (sections), break (pages), file, password, user_id, captcha, and the
dominant **`data` (Dynamic) field** linking entries across forms.

**Conditional logic** on 13 forms (Registration 13 rules, Add a Post 7).

**Form actions:** email 18 active (7 drafts), on_submit 8 (forms 2, 6, 11, 17, 26), register 2
(forms 17, 22; role subscriber, auto-login, 15 user-meta mappings), wppost 1 (form 6: post type
`post`, status from field 465, 23 custom fields, 1 taxonomy).

**Views (frm_display):** 1172 All Councils, 1179 All Camps (dynamic), 1180 All Lodges, 1186 My Posts,
1315 Organized by State, 1316 Pending Review, 1322 Organized by Roles, 1323 Show All Users,
2300 Show all options, 2302 All Councils Search, 2303 All Lodge Search, 3102 Search All Camps.

**Permissions (frm_* capabilities):** administrator & system_administrator: all; historian:
view/create/edit/delete entries, view forms, reports; contributor: list forms/displays; revisor:
edit/list forms & displays, change settings. Roles in use: administrator 19, historian 39,
subscriber 35, index_contributor 7, author 5, regional 5, contributor 3, manager 2.

**Theme dependency:** 21 of 57 theme PHP files call Formidable directly, e.g. `FrmEntry::getAll` (10),
`frm_after_create_entry` hook (7), `frm_setup_new_fields_vars` (5), `FrmProDb` (4), `frm_where_filter`
(4), `frm_no_entries` (4), `FrmProEntriesController::get_field_value_shortcode` (3), `FrmEntry::get_meta`,
`FrmEntryMeta::update_entry_meta`, `FrmEntrymeta::getEntryIds`, `FrmProAppHelper::selected`,
`FrmSettings`, `FrmDb`, plus 113 field/form/view ID constants in `inc/formidable_constants.php`.
The theme must keep working unchanged when Formidable is off, so the plugin provides these (Phase 7).

## Where the plugin stands (2026-09-24)

~6,000 lines. Has: form rendering from Formidable tables (`[sm_form]`), `[formidable]` fallback only
when Formidable is absent, file uploads, views renderer with `[if]` (`[sm_view]`,
`[display-frm-data]` fallback), memory-post creation, council/camp/lodge index forms, indexing browser,
user defaults, REST API (admin-only) and a Vue 3 + Reka UI + Tailwind builder (forms, views, entries).
Missing: conditional logic, email notifications, registration/account actions, CAPTCHA/spam protection,
Formidable-exact create-post mapping, role-based permissions (admin-only today), Formidable
compatibility layer for the theme, Bootstrap front-end styling.

---

## Phases

Status: `[ ]` todo, `[~]` in progress, `[x]` done (with date).

### Phase 0: Foundation and safety
- [x] 0.1 (2026-09-24) Baseline: generate `docs/formidable-usage.json` (read-only export of forms, fields,
      field options, actions, views, placements) with a plugin CLI/admin tool, so later phases can
      check behaviour against it without re-querying by hand.
- [x] 0.2 (2026-09-24) Coexistence audit: while Formidable is active the plugin must not change any Formidable
      behaviour (shortcodes, hooks, admin menus, REST, scripts/styles on Formidable pages). Fix any overlap.
- [x] 0.3 (2026-09-24) Local mail interceptor: when the site URL host is `localhost`/`127.0.0.1`, mail sent by the
      plugin is logged (wp-admin > Scouting Forms > Test Mail Log) instead of sent.
- [x] 0.4 (2026-09-24) Test-data tools: "test mode" marks entries (`smtest-` item_key prefix) and an admin action
      deletes all test entries/metas (and test posts/users created from them).
- [ ] 0.5 Compare tool (admin only): pick a form or view, see Formidable's output and the plugin's
      output side by side, plus a field-by-field comparison of what each would save.
- [ ] 0.6 Front-end styling to Bootstrap 5.3: rewrite `src/Ui/ThemeClasses.php` to Bootstrap classes,
      stop loading Tailwind on the front end (keep it for the admin builder), site green highlights.
- [ ] 0.7 Health: PHP 8.2 lint of all plugin files, no notices with WP_DEBUG, remove leftovers.

### Phase 1: Contact Us (form #2): the pattern for everything else
- [ ] 1.1 Render text, email, textarea, captcha, submit exactly like Formidable (labels, order,
      required marks, descriptions, placeholders, default values).
- [ ] 1.2 Validation parity (required, email format, max length) with Formidable's messages.
- [ ] 1.3 Spam protection: honeypot + minimum-time check always; if Formidable has reCAPTCHA/hCaptcha/
      Turnstile keys configured, verify tokens server-side with the same keys.
- [ ] 1.4 Actions engine (first two action types): `email` (to/cc/bcc/from/reply-to/subject/body,
      `[default-message]`, field shortcodes `[123]`, `[sitename]`, `[admin_email]`, conditions) and
      `on_submit` (message / redirect / page).
- [ ] 1.5 Saved entry identical in structure to a Formidable-created one (item_key, name, ip,
      user_id, is_draft, metas), verified with the Compare tool.

### Phase 2: Views (12 frm_display posts)
- [ ] 2.1 Read every view's settings: form, show_count (all/one/dynamic/calendar), filters (where),
      order, limit, page size, before/content/after, detail page, empty message, CSS classes.
- [ ] 2.2 Shortcodes in view content: field tags with options (`show=`, `sep=`, `link_id`), `[id]`,
      `[key]`, `[created-at]`, `[updated-at]`, `[user_id]`, `[if]`/`[/if]` with conditions,
      `[foreach]`, `[detaillink]`, `[editlink]`, `[deletelink]`, `[get param=]`, `[frm-...]` helpers used on pages.
- [ ] 2.3 Filters incl. current-user and URL-parameter filters (My Posts, Pending Review, searches),
      pagination, dynamic detail pages (All Camps).
- [ ] 2.4 Output parity for all 12 views via the Compare tool (normalised text/HTML).

### Phase 3: Index forms (councils, camps, lodges, states, links)
- [ ] 3.1 Dynamic `data` fields: options from another form's field, dependent (filtered by parent
      Dynamic field), multiple selection, select/checkbox/autocomplete display, saved values = entry IDs.
- [ ] 3.2 Conditional logic engine (client + server): `hide_field`, `hide_field_cond`, `hide_opt`,
      `show_hide`, `any_all`; hidden fields are not validated or saved (Formidable behaviour).
- [ ] 3.3 Sections (divider/end_divider, collapsible, repeaters if any are configured), page breaks,
      toggle, hidden fields with default-value shortcodes (`[user_id]`, `[get param=]`, etc.), user_id.
- [ ] 3.4 Front-end entry editing (edit links, `frm_action=edit`) with Formidable's permission rules.
- [ ] 3.5 Parity on forms 7, 8, 10, 11, 23, 25, 27 (render, validate, save, actions).

### Phase 4: Add a Post (form #6)
- [ ] 4.1 All 44 fields incl. 7 conditional rules, file uploads to the media library.
- [ ] 4.2 `wppost` action exactly as configured: post type, title/content/excerpt mapping, status from
      field 465, 23 custom fields, category; entry `post_id` link; updates when the entry is edited.
- [ ] 4.3 Role rules (who may publish vs pending), redirects/messages, email notifications.
- [ ] 4.4 Fire `frm_after_create_entry` / `frm_after_update_entry` so the theme's existing hooks run
      (depends on Phase 7 compatibility work).

### Phase 5: Accounts (forms #17, #22, #33, #34)
- [ ] 5.1 `register` action: create user (role subscriber), username/email rules, password handling,
      15 user-meta mappings, auto-login, one entry per user.
- [ ] 5.2 Edit Account Info: update user fields/meta, avatar upload, password change, captcha.
- [ ] 5.3 Registration/admin emails; brute-force/rate limiting; capability checks for Edit Users.

### Phase 6: Admin and permissions
- [ ] 6.1 Capabilities `sm_*` mirroring the `frm_*` ones; on activation grant each role the `sm_*`
      equivalents of the `frm_*` caps it has (historian keeps entry access, etc.).
- [ ] 6.2 REST + admin screens use those capabilities instead of `manage_options`.
- [ ] 6.3 Entries admin: list/search/filter/view/edit/delete per form, CSV export.
- [ ] 6.4 Builder parity for the settings the site actually uses (fields, options, conditional
      logic, actions, views); rebuild `assets/builder` with Vite.

### Phase 7: Formidable compatibility layer (only loads when Formidable is inactive)
- [ ] 7.1 Catalogue every Formidable class/method/hook/constant the theme uses, with call sites.
- [ ] 7.2 Provide those classes/methods (`FrmEntry`, `FrmEntryMeta`, `FrmProEntriesController`,
      `FrmProAppHelper`, `FrmAppHelper`, `FrmDb`, `FrmProDb`, `FrmSettings`, ...) backed by the
      plugin, matching the signatures and return shapes the theme relies on.
- [ ] 7.3 Fire the Formidable hooks the theme listens to (`frm_after_create_entry`,
      `frm_setup_new_fields_vars`, `frm_where_filter`, `frm_no_entries`, `frm_include_meta_keys`,
      `frm_get_default_value`, `frm_rte_options`, ...) at the equivalent points.
- [ ] 7.4 Automated check: every theme call site exercised with Formidable active vs. plugin shim.

### Phase 8: Local cutover rehearsal (local only, needs the owner's OK to deactivate Formidable locally)
- [ ] 8.1 Deactivate Formidable (and its add-ons) on the local site only.
- [ ] 8.2 Click through every page, form and view as: logged out, subscriber, historian,
      index_contributor, regional, administrator. PHP error log clean.
- [ ] 8.3 Re-activate Formidable locally afterwards; record results here.

### Phase 9: Handoff (the owner decides; Claude does not publish)
- [ ] 9.1 Release notes + a go-live checklist (what to verify on live, how to roll back:
      reactivate Formidable).
- [ ] 9.2 Owner reviews, moves the plugin to `master` when ready and publishes with the dashboard.

---

## Open questions for the owner
- (none yet)

## Progress log
- 2026-09-24: Plan written. Audit of Formidable usage and plugin coverage recorded above.
- 2026-09-24: 0.1 done. `src/Tools/FormidableAudit.php` writes `docs/formidable-usage.json` (git-ignored via the plugin's own `.gitignore`): 22 forms, 263 fields, 36 actions, 14 views, 10 placements, 139 theme call sites; emails redacted, no entries.
- 2026-09-24: 0.2 done. Every init handler is guarded by its own `sm_action` + nonce; admin assets load only on plugin pages. Fixed: (a) `[formidable]`/`[display-frm-data]` fallbacks now register late on `init` and only if Formidable's classes are absent; (b) the guide notice shows only on Dashboard/Plugins screens; (c) the indexing "update end dates" action used the theme's own `smp_action=update_end_dates` (the plugin hijacked the theme's My Account tool) and had no nonce. It is now `sm_indexing_action` + nonce `sm_update_end_dates`. Verified: Formidable owns its shortcodes, Contact Us renders Formidable's form, plugin action without nonce returns 403, no PHP warnings.
- 2026-09-24: 0.3 + 0.4 done. New `Support\Environment` (local detection), `Support\Mailer` (all plugin mail goes through it; logged, never sent, on local), `Support\TestData` (smtest- keys, `_sm_test_data` flag, cleanup) and `Models\EntryRepository` (single entry-creation path; the 3 old copies in DynamicFormRenderer, IndexEntityForms and ApiController now use it). Admin page Scouting Forms > Test Tools (local only). Also fixed: hard-coded `wp_` table prefix and unescaped shortcode attributes in DynamicFormRenderer. Verified: a real `[sm_form id=2]` submission saved as `smtest-contact-form-…` with 4 metas; mail logged not sent; cleanup removed 2 entries + 1 post; DB back to 0 test rows.
