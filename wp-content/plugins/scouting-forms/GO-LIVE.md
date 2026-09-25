# Scouting Forms: going live without Formidable

For the site owner. Nothing here has been done on the live site; you decide when and do each step.
Everything below was rehearsed on the local copy (http://localhost:8088) with Formidable and all its
add-ons turned off.

## What changes

The Scouting Forms plugin takes over everything Formidable did on the site, using the same database
tables, so all existing forms, fields, entries and views stay exactly where they are:

- Forms on pages (`[formidable id=…]`), views (`[display-frm-data …]`), `[frm-field-value]`,
  `[frm-show-entry]`, login / reset-password pages, the Add a Post help pop-up.
- The theme's own Formidable code keeps working: the plugin supplies the Formidable classes and hooks
  the theme uses (council/camp/lodge slugs, council numbers in lists, Add a Post categories, Edit Users).
- Emails, confirmations, member registration and Add a Post's post creation.
- wp-admin: Scouting Forms > Forms & Fields, Views, Entries (the builder), with the same staff rights.

On purpose, some things now work differently (safer):

- Only people allowed to use a form can see or submit it (e.g. subscribers no longer get the
  Add a Council form; Edit Users is for administrators only).
- A custom role in a form's settings must actually be held; regional coordinators may edit everyone's
  councils, lodges and camps.
- Only people WordPress lets publish can choose Published / Scheduled in Add a Post; others submit for
  review.
- Uploads are checked (type, size); submissions are rate limited (30 per 10 minutes per person).
- Text typed into forms can never run as a shortcode; entry data only shows where it should.

## Before

1. Make a WP Engine backup point (so the database can be put back if needed).
2. Publish the plugin as usual (develop > master > dashboard). With Formidable still active the plugin
   stays in the background: its Formidable stand-ins only load when Formidable is off, so the site
   keeps working as it does today.
3. On live, check that **Scouting Forms** is active (Plugins screen) and open any wp-admin page once
   (the plugin gives staff roles its matching rights on the first admin visit).

## Switch

4. Deactivate, on the Plugins screen: Formidable Forms, Formidable Forms Pro, Formidable Visual Views,
   Formidable Registration, Formidable Bootstrap Modal, Formidable Bootstrap, Formidable Logs,
   Formidable Zapier. (No form uses Zapier or Logs, so nothing is lost.)
   Do not delete them yet.

## Check right after (logged in as each kind of member where it says so)

5. Home page, History / Photographs / … category pages with state and council filters, search.
6. Councils, camps, lodges lists and their searches (My Account > Indexing), paging.
7. Contact Us: send one message. This is the only place the reCAPTCHA check with Google can be tested
   (it cannot run on a local copy). You should receive the admin email.
8. Register a test member (real email address you control): welcome email arrives, member is logged in.
   Log out, log in, use "Lost password".
9. As an index contributor: Add a Council / Camp / Lodge, then edit it (the slug and the council
   list with numbers should look as before). As a historian: Add a Post (editor with Add Media,
   state/council defaults); as a contributor: the post goes to Pending Review.
10. My Account: Edit Account Info, Edit Account Defaults, avatar upload.
11. As administrator: Edit Users on a test member (one role, then two roles).
12. wp-admin > Scouting Forms: open a form, a view and some entries; export a CSV.
13. Watch the PHP error log for a while. Known harmless theme warnings: `sm_categories.php`,
    `SearchForm.php` line 361, `CampEntry.php` line 106, `PostEntry.php` lines 52-76.

## If something is wrong: roll back

Reactivate the Formidable plugins (step 4 in reverse). The data never moved, so entries made while
Scouting Forms was in charge are there for Formidable too. Nothing else needs undoing.

## Later

- When everything has run well for a while, the Formidable plugins can be deleted.
- Open questions for you: the three search forms (Search Councils / Camps / Lodges) email the admin
  on every search (switch those email actions off in the builder if not wanted); submit button colour.
- Theme problems the plugin works around (they affect live today while Formidable runs): Edit Users
  with a single role left the member with no role; adding a council/camp/lodge as a non-administrator
  could break the ACF list of names.
