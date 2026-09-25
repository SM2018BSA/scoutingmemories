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
- [x] 0.5 (2026-09-24) Compare tool (admin only): pick a form or view, see Formidable's output and the plugin's
      output side by side, plus a field-by-field comparison of what each would save.
- [x] 0.6 (2026-09-24) Front-end styling to Bootstrap 5.3: rewrite `src/Ui/ThemeClasses.php` to Bootstrap classes,
      stop loading Tailwind on the front end (keep it for the admin builder), site green highlights.
- [x] 0.7 (2026-09-24) Health: PHP 8.2 lint of all plugin files, no notices with WP_DEBUG, remove leftovers.

### Phase 1: Contact Us (form #2): the pattern for everything else
- Findings from the Compare tool (2026-09-24), to fix in 1.1–1.3: description HTML is escaped (shows `<p>` as text); captcha field renders as a plain text input; no honeypot field (Formidable renders one: "If you are human, leave this field blank."); plugin wraps the form in a card with a small title, Formidable shows a large heading and no card; plugin marks 4 fields required while Formidable shows no markers (check field `required` + style settings); Formidable submit is `btn btn-secondary` coloured by its frm_style (light blue), plugin uses site green `btn-scout` (see Open questions).
- [x] 1.1 (2026-09-25) Render text, email, textarea, captcha, submit exactly like Formidable (labels, order,
      required marks, descriptions, placeholders, default values).
- [x] 1.2 (2026-09-25) Validation parity (required, email format, max length) with Formidable's messages.
- [x] 1.3 (2026-09-25) Spam protection: honeypot + minimum-time check always; if Formidable has reCAPTCHA/hCaptcha/
      Turnstile keys configured, verify tokens server-side with the same keys.
- [x] 1.4 (2026-09-25) Actions engine (first two action types): `email` (to/cc/bcc/from/reply-to/subject/body,
      `[default-message]`, field shortcodes `[123]`, `[sitename]`, `[admin_email]`, conditions) and
      `on_submit` (message / redirect / page).
- [x] 1.5 (2026-09-25) Saved entry identical in structure to a Formidable-created one (item_key, name, ip,
      user_id, is_draft, metas), verified with the Compare tool.

### Phase 2: Views (12 frm_display posts)
- [x] 2.1 (2026-09-25) Read every view's settings: form, show_count (all/one/dynamic/calendar), filters (where),
      order, limit, page size, before/content/after, detail page, empty message, CSS classes.
- [x] 2.2 (2026-09-25) Shortcodes in view content: field tags with options (`show=`, `sep=`, `link_id`), `[id]`,
      `[key]`, `[created-at]`, `[updated-at]`, `[user_id]`, `[if]`/`[/if]` with conditions,
      `[foreach]`, `[detaillink]`, `[editlink]`, `[deletelink]`, `[get param=]`, `[frm-...]` helpers used on pages.
      (`[foreach]` and `link_id` are not used by any view on the site, so they are not implemented.)
- [x] 2.3 (2026-09-25) Filters incl. current-user and URL-parameter filters (My Posts, Pending Review, searches),
      pagination, dynamic detail pages (All Camps).
- [x] 2.4 (2026-09-25) Output parity for all 12 views via the Compare tool (normalised text/HTML).

### Phase 3: Index forms (councils, camps, lodges, states, links)
- [x] 3.1 (2026-09-25) Dynamic `data` fields: options from another form's field, dependent (filtered by parent
      Dynamic field), multiple selection, select/checkbox/autocomplete display, saved values = entry IDs.
      `Forms\Rendering\DynamicOptions` ports Formidable's rules (independent lists alphabetical; dependent
      lists via meta_through_join, incl. serialized multi-values and repeater child entries; "just show it"
      fields show and save the parent entry's linked value, computed on the server). The browser loads
      dependent choices from `admin-ajax.php?action=sm_forms_dynamic` (`Ajax\DynamicFields`: public and
      read-only like Formidable's, no nonce because pages are cached; answers only for real dependent
      fields of published forms, numeric values, visible fields). Autocomplete is the plugin's own
      searchable dropdown (no library): type to filter, arrow keys, chips for multi-select, labelled
      remove buttons. Server only accepts entry IDs the field actually offers for the parent's choice.
- [x] 3.2 (2026-09-25) Conditional logic engine (client + server): `hide_field`, `hide_field_cond`, `hide_opt`,
      `show_hide`, `any_all`; hidden fields are not validated or saved (Formidable behaviour).
      `Forms\Logic\FieldLogic` (server) and `assets/js/forms-front.js` (browser) apply the same rules,
      including "Dynamic field is anything", list values, hidden sections and field visibility roles.
- [x] 3.3 (2026-09-25) Sections (divider/end_divider, collapsible, repeaters if any are configured), page breaks,
      toggle, hidden fields with default-value shortcodes (`[user_id]`, `[get param=]`, etc.), user_id.
      Sections wrap their fields; collapsible ones open/close by click or keyboard (start open when they
      hold an error; open without JavaScript); repeating sections (7, 8, 11 -> child forms 27, 23, 25) use
      Formidable's names, add/remove rows, and save one child entry per non-blank row plus the list of child
      IDs on the section field. Page breaks (forms 17, 39) become in-browser pages with Previous/Next and a
      required-field check; the server checks everything on submit and reopens the page with the first
      error (Formidable instead posts each page to the server; the result is the same). Layout classes
      (frm_half, frm_first, frm2 ...) have their own 12-column grid in forms-front.css, so they keep working
      without Formidable's stylesheet. Error messages follow Formidable exactly (519/519 identical).
- [x] 3.4 (2026-09-25) Front-end entry editing (edit links, `frm_action=edit`) with Formidable's permission rules.
      `?frm_action=edit&entry=ID|key` opens the entry only if the form is editable and
      `Permissions::canEditEntry` allows it; otherwise the page shows a new empty form (as Formidable).
      One-entry-per-user forms open the user's own entry. The update is nonce-tied to the entry and
      re-checked on submit; values follow Formidable's update rules (blank or hidden values removed), the
      owner in the User ID field is kept, fields the editor may not see are left alone, rows are updated /
      added / removed, "update" actions run, and the form's edit_* confirmation shows ("Update" button).
- [x] 3.5 (2026-09-25) Parity on forms 7, 8, 10, 11, 23, 25, 27 (render, validate, save, actions).
      `[frm-field-value]` (used by the index forms' emails to reach the submitter) has a plugin version,
      `[sm_field_value]`, identical in output. Found and fixed on the way: text typed into a form could
      run shortcodes in confirmation messages and emails (values were inserted before do_shortcode);
      values now have their brackets encoded first, so only shortcodes written in the settings run.

### Phase 4: Add a Post (form #6)
- [x] 4.1 (2026-09-25) All 44 fields incl. 7 conditional rules, file uploads to the media library.
      Rendered by the Phase 3 engine. Added for this form: category fields list the site's categories
      (term IDs, like Formidable); default shortcodes `[user_meta key=x]` (43 uses: a member's own
      council, lodge, dates...), `[date format="Y"]`, `[post_id]` and `[frm-field-value ...]`; date
      inputs accept older m/d/Y values. (Form 6 has no file fields; uploads of other forms are linked
      to the post when a form has both.)
- [x] 4.2 (2026-09-25) `wppost` action exactly as configured: post type, title/content/excerpt mapping, status from
      field 465, 23 custom fields, category; entry `post_id` link; updates when the entry is edited.
      `Actions\PostAction` ports FrmProPost: post built from the mapped fields, custom fields (blank =
      removed, dates Y-m-d), categories/taxonomies, author = the entry's user, post linked to the entry,
      mapped values then removed from the entry; on update the same post is updated. The Publish button
      and other single-field updates change the post itself (Formidable's update_single_field). A
      visitor's text in the post body cannot run shortcodes (brackets encoded, as Formidable does).
- [x] 4.3 (2026-09-25) Role rules (who may publish vs pending), redirects/messages, email notifications.
      Messages and emails come from the form's on_submit and email actions (both work). Fixed on the
      way: Formidable lets anyone who can open Add a Post pick "Published", "Private" or "Scheduled"
      (the status field is visible to everyone). The plugin offers those only to people allowed to
      publish posts (administrators, managers, authors, historians, regional coordinators); others
      (contributors, index contributors, subscribers) choose Draft or Pending Review, and the server
      saves their post as pending whatever is sent. See Open questions.
- [ ] 4.4 Fire `frm_after_create_entry` / `frm_after_update_entry` so the theme's existing hooks run
      (depends on Phase 7 compatibility work). The theme's PostEntry hook turns the post's state/council
      IDs into the abbreviations and slugs the theme reads (e.g. state "IL", council JSON list).

### Phase 5: Accounts (forms #17, #22, #33, #34)
- [x] 5.1 (2026-09-25) `register` action: create user (role subscriber), username/email rules, password handling,
      15 user-meta mappings, auto-login, one entry per user.
      `Actions\RegisterAction` ports Formidable Registration (FrmRegEntry/FrmRegUser): the add-on's checks
      and messages (email taken, blank/backslash password, username rules; password optional when an
      account is updated), account created with the action's role (never from the form), username from
      the email, mapped user meta and avatar, entry moved to the new user, "user_registration" emails,
      the new member logged in. Administrators (the action's "create users" roles) register new
      accounts instead of editing their own. Passwords are never stored with the entry or shown again,
      and are hashed in the slashed form WordPress's login screen checks (quotes in passwords work).
      Password fields get Formidable's inline "Confirm" box. Unique fields ignore the entry being edited.
- [x] 5.2 (2026-09-25) Edit Account Info: update user fields/meta, avatar upload, password change, captcha.
      One-entry-per-user forms (22, 34) open the member's own entry; account fields show the account's
      current details (email, names, meta), as the add-on does.
- [ ] 5.3 Registration/admin emails; brute-force/rate limiting; capability checks for Edit Users.
      Done: registration emails (welcome to the member, notice to the admins), login and reset-password
      pages (`Accounts\AccountPages`: [sm_login], [sm_reset_password], and [frm-login]/[frm-reset-password]
      plus WordPress's login/lost-password/reset screens sent to pages 229/842 once the add-on is gone;
      WordPress itself checks passwords and sends reset links), avatars (`Accounts\Avatar`). Local
      copies now log WordPress's own mail too (password/email change notices, reset links), never send.
      Left for Phase 7: Edit Users (form 33) works through the theme's frm_pre_create_entry hook (it edits
      the chosen user instead of saving an entry), so it needs Formidable's hooks fired. Brute-force
      protection stays with WordPress / WP Engine (the forms themselves have the honeypot, timing check
      and reCAPTCHA).

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
      Already done: `frm_where_filter` (Phase 2, camp/lodge searches). Depends on this: the council slug
      fields (556 on lodges, 560 on camps) are filled by the theme's frm_after_create/update_entry hooks;
      Add a Post / registration defaults come from the theme's frm_setup_new_fields_vars.
- [ ] 7.4 Automated check: every theme call site exercised with Formidable active vs. plugin shim.

### Phase 8: Local cutover rehearsal (local only, needs the owner's OK to deactivate Formidable locally)
- [ ] 8.1 Deactivate Formidable (and its add-ons) on the local site only.
- [ ] 8.2 Click through every page, form and view as: logged out, subscriber, historian,
      index_contributor, regional, administrator. PHP error log clean.
- [ ] 8.3 Re-activate Formidable locally afterwards; record results here.

### Phase 9: Handoff (the owner decides; Claude does not publish)
- [ ] 9.1 Release notes + a go-live checklist (must include: submit Contact Us on live once to confirm the reCAPTCHA server check, which cannot run on localhost) (what to verify on live, how to roll back:
      reactivate Formidable).
- [ ] 9.2 Owner reviews, moves the plugin to `master` when ready and publishes with the dashboard.

---

## Open questions for the owner
- Submit button colour: Formidable shows light blue (its own style settings); the plugin uses the site green like the PDF viewer. Default: site green, unless the owner prefers matching the old look.
- Who can edit council/camp/lodge entries (forms 7, 8, 11): their "edit other people's entries" role is `index_contributor`. Formidable (and so the plugin, which copies it exactly) also lets anyone with a built-in WordPress role pass that check. Today that is 29 plain subscribers and 4 historian+subscriber accounts, as well as the 5 regional coordinators (author+historian+regional) who probably should. Suggested fix, in each form's settings, not code: set the role list to administrator, index_contributor and regional. Nothing changes until the owner decides.
- Add a Post status: on the live site today anyone who can open Add a Post can choose "Published" in the Post Status field and skip review (Formidable shows it to everyone). The plugin limits Published/Private/Scheduled to people WordPress lets publish posts; contributors, index contributors and subscribers get Draft or Pending Review. Say if the old behaviour is wanted instead.
- Search forms 36, 37 and 38 (Search Councils/Camps/Lodges) each have an active email action to the site admin, so every search sends the admin an email on live today. The plugin does the same (locally it only logs). The owner may want those three actions turned off.

## Progress log
- 2026-09-24: Plan written. Audit of Formidable usage and plugin coverage recorded above.
- 2026-09-24: 0.1 done. `src/Tools/FormidableAudit.php` writes `docs/formidable-usage.json` (git-ignored via the plugin's own `.gitignore`): 22 forms, 263 fields, 36 actions, 14 views, 10 placements, 139 theme call sites; emails redacted, no entries.
- 2026-09-24: 0.2 done. Every init handler is guarded by its own `sm_action` + nonce; admin assets load only on plugin pages. Fixed: (a) `[formidable]`/`[display-frm-data]` fallbacks now register late on `init` and only if Formidable's classes are absent; (b) the guide notice shows only on Dashboard/Plugins screens; (c) the indexing "update end dates" action used the theme's own `smp_action=update_end_dates` (the plugin hijacked the theme's My Account tool) and had no nonce. It is now `sm_indexing_action` + nonce `sm_update_end_dates`. Verified: Formidable owns its shortcodes, Contact Us renders Formidable's form, plugin action without nonce returns 403, no PHP warnings.
- 2026-09-24: 0.3 + 0.4 done. New `Support\Environment` (local detection), `Support\Mailer` (all plugin mail goes through it; logged, never sent, on local), `Support\TestData` (smtest- keys, `_sm_test_data` flag, cleanup) and `Models\EntryRepository` (single entry-creation path; the 3 old copies in DynamicFormRenderer, IndexEntityForms and ApiController now use it). Admin page Scouting Forms > Test Tools (local only). Also fixed: hard-coded `wp_` table prefix and unescaped shortcode attributes in DynamicFormRenderer. Verified: a real `[sm_form id=2]` submission saved as `smtest-contact-form-…` with 4 metas; mail logged not sent; cleanup removed 2 entries + 1 post; DB back to 0 test rows.
- 2026-09-24: 0.5 + 0.6 done. `Tools\CompareTool`: `/?sm_compare=form&id=N`, `view&id=N`, `list`; local copies only; admins, or a signed 1-hour link from `CompareTool::previewUrl()` (no signature/bad signature = 403). `Ui\ThemeClasses` now returns Bootstrap 5.3 classes (same method names); new `assets/css/forms-front.css` scoped to `.sm-forms` (site-green `btn-scout`, required marker, focus colours, grids, thumbnails); all inline Tailwind removed from DynamicFormRenderer/DynamicViewRenderer; Tailwind registered only in wp-admin. Verified on Contact Us: Bootstrap `form-control` fields, forms-front.css loaded, no Tailwind on the public site.
- 2026-09-24: 0.7 done. 39 plugin PHP files pass `php -l` (PHP 8.2); rendering all 22 forms, 12 views and the 5 dashboard shortcodes as visitor and as admin with E_ALL gives no warnings/notices/deprecations from plugin code. Leftovers noted for Phase 6: hard-coded counts in admin menu labels and the guide notice ("23 forms, 266 fields", "Councils (2,323)"), and `assets/css/tailwindcss.css` duplicating `assets/builder/builder.css`. **Phase 0 complete.**
- 2026-09-25: **Phase 1 complete (Contact Us).** New pipeline: `Models\FormRepository`, `Forms\Rendering\{FieldRenderer,FormTemplate,DefaultValues}` (fields built from each field's Formidable `custom_html`, form from `before_html`/`submit_html`), `Forms\Submission\{Validator,SpamGuard}`, `Forms\Logic\Conditions`, `Actions\{ActionRunner,EmailAction,EntryShortcodes}`, `Support\FormidableSettings`. `DynamicFormRenderer` rewritten: submissions handled on `template_redirect` (so redirects work), then nonce, spam check, validation, uploads, EntryRepository, actions, and the on_submit message/redirect/page. EntryRepository now saves Formidable's exact shape: 5-char key, name from first filled field, browser/referrer JSON in description, no IP (Formidable `no_ips` is on), `unique_id` meta under field 0. Verified locally: Formidable's own messages (blank, invalid email), honeypot/too-fast/bad-nonce refused, valid entry saved, email logged with To/From/Bcc/subject/body per the action, success message shown and form hidden (show_form off), same labels/IDs/title/description/reCAPTCHA as Formidable in the Compare tool, all 22 forms render with no PHP warnings, test data cleaned (0 left). Not testable locally: reCAPTCHA server verification (Google rejects localhost); added to 9.1. Known gaps left for later phases: sections/page breaks (Phase 3), `wppost`/`register` actions (Phases 4/5), IndexEntityForms still uses its own name-based keys (revisit in Phase 3).
- 2026-09-25: **Phase 2 complete (views).** New `Views\{ViewRepository,EntryQuery,EntryValues,TemplateTags,EntryActions,ViewRenderer}`, `Models\PostFields`, `Support\Permissions` (rewritten), `Forms\Rendering\DynamicOptions`; `DynamicViewRenderer` now just registers `[sm_view]`, `[sm_show_entry]` and the fallbacks. What it copies from Formidable (read from its source): filters in SQL, including Dynamic-field text turned into linked entry IDs, "=" meaning "contains" for multi-value fields, blank = "is empty", empty `[get param]` filters ignored; fields mapped to a post (title, status, category, custom fields) read from and filtered on the post; `frm_where_filter` offered to the theme, which the camp/lodge search views need; content filter "limited" (curly quotes, paragraphs) over the whole view; `.frm_no_entries`; paging `?frm-page-ID=` with no arrow at the ends; shortcode attributes become `[get param]` values; option-list views output bare `<option>`s. `filter=limited` is Formidable's content switch, not "current user only" (the old renderer showed guests "Please log in" there). Edit/Delete/Publish visibility follows Formidable's per-form rules (editable, editable_role, open_editable_role, own drafts, frm_delete_entries). Delete and Publish are POST forms with a nonce tied to the entry and, for Publish, to the exact field and value; handled on `template_redirect` with a permission re-check, redirect back with a notice. Delete removes child entries and moves a linked post to the trash, as Formidable does. Search forms 36/37/38 now match: Dynamic State dropdown (73 states, alphabetical, URL value pre-selected), screen-reader legend, "do not store entries" honoured (entry exists only while its actions run), redirect `[557 show=113]` gives `?council_state=Alabama`. Also fixed: entry and field timestamps now stored in GMT like Formidable (were local time). Verified: all 12 views give the same text as Formidable with the same rows/options, unfiltered and with URL filters, searches (state names, council names, council slugs through the theme hook) and paging, as admin, logged out, historian, index_contributor, regional, subscriber and a post author (1,326 rows of My Posts); 13/13 button tests (delete, other user's nonce refused, publish, tampered value refused, wrong field refused, non-owner refused, GET ignored, notices); search submissions redirect correctly, store nothing, email only logged; 19 forms sweep: 40/44 Dynamic dropdowns identical, the other 4 are on later pages of multi-page forms (3.3); no PHP warnings from plugin code; test data 0 and mail log cleared.
- 2026-09-25: **Phase 3 complete (index forms).** New: `Forms\Logic\FieldLogic`, `Ajax\DynamicFields`, `Views\FieldValue`, `assets/js/forms-front.js` (no dependencies); `DynamicOptions`, `FieldRenderer`, `FormTemplate`, `Validator`, `DynamicFormRenderer`, `EntryRepository` (update, formValues), `EntryShortcodes` and forms-front.css extended. Plugin-owned class names for section toggles and row buttons (`sm-trigger`, `sm-toggle-container`, `sm-add-row`, `sm-remove-row`) so Formidable's global click handlers can never act on plugin forms while both are installed. Verified locally: dependent choices identical to Formidable's meta_through_join in 12 cases (incl. repeaters and the same-form case that has none); in the browser (Compare tool, form 11 and 17): State -> Council list loads (110 Ohio councils), searchable dropdown by keyboard, collapsible sections by mouse and keyboard with aria-expanded, rows add/remove/rename/clear, pages with Previous/Next and required check, role-based logic (Historian / Regional Coordinator); server tests: Add a Camp 16/16 (entry, council IDs, child row with server-computed slug and years, blank row skipped, wrong-state council refused, hidden field not required, row errors), editing 19/19 (prefill, Update, rename, swap rows, owner kept, rows cleared, no-permission blank form, forged/cross-entry updates refused), forms 7/8/10/11 save with the same stored fields as Formidable's own entries (differences explained: slug 556 set by the theme hook, toggles, optional repeater), [sm_field_value] = [frm-field-value] in 7 cases, camp email reaches the submitter, typed shortcodes stay text; all earlier suites still pass (12 views, 13 buttons, search forms, 519 messages). No PHP warnings; test data 0; mail log empty. Known differences kept on purpose: pages are stepped in the browser; `unique_id` (field 0) is stored on every top-level entry (Formidable only stores it when its script adds one; nothing reads it).
- 2026-09-25: **Phase 4 done (4.1-4.3; 4.4 waits for Phase 7).** New `Actions\PostAction`; `ActionRunner` runs wppost; `EntryRepository::updateField` writes post-mapped fields to the post; `FieldRenderer` lists category terms and limits post statuses by publish rights; `DefaultValues` resolves user_meta/date format/post_id/frm-field-value defaults. Fixed: on edit, the actions now get every submitted value (the post was not updated). Verified (22/22): historian's post published with title, body (shortcodes inert), category, custom fields, author; mapped values moved off the entry; contributor gets Draft/Pending only, a forged "publish" is refused and their post is pending; Publish button respects rights and never stores a value on the entry; the Edit link shows title/category from the post and updates the same post (cleared fields removed from it); deleting the entry trashes the post. Post meta keys match a Formidable-created post except values filled later by the theme hook (state/council) or ACF bookkeeping (_state, frm_entry_id). All earlier suites still pass; test data 0.
- 2026-09-25: **Phase 5 done (5.1, 5.2; 5.3 except Edit Users, which needs Phase 7).** New `Actions\RegisterAction`, `Accounts\AccountPages`, `Accounts\Avatar`; `Mailer::registerHooks` (local copies log all WordPress mail); `Validator` (password sanitizing, confirmation boxes, unique ignores the edited entry); `FieldRenderer` (confirmation box, autocomplete hints); `DynamicFormRenderer` (register first, passwords kept out of entries and re-renders, account prefill on edit). Verified: registration 25/25 (account created with role/username/names/meta, password with a quote logs in, entry moved to the member, password never stored or emailed, member logged in, welcome + admin emails logged; duplicate email / mismatch / blank / backslash refused; member updates details without a password, cannot take another member's email, changes password; WordPress's "Password Changed" notice logged not sent; an administrator registers a separate account and stays themselves); login/reset pages 20/20 (forms post to wp-login.php, messages are fixed texts only, full reset cycle with WordPress's reset email logged, bad key and mismatch refused, old password stops working, failed sign-in returns to the Login page, avatar from the upload); Edit Account Info opens the member's own entry with account details and no password. All earlier suites still pass; test data 0.
