# Tailwind only: the rule this theme follows

The owner built the old site (`scoutingmemories` theme) with Bootstrap 5.3.8, select2, jQuery and
some CSS of his own. This theme is the same site, with the same look, where Tailwind replaces all
of that. He checks the page source. Anything that is not a Tailwind utility class is a defect.

## The rule

1. Every style is a Tailwind utility class written on the element (Tailwind 4).
2. No daisyUI (removed). No component classes of any library.
3. No CSS rules of our own: no stylesheet files, no `@apply`, no `@utility`, no `<style>` blocks,
   no `style="..."` attributes.
4. No made-up class names used for styling (`sm-...`, `ma-...`, `ft-...`). An `id`, or a class a
   script or a test needs to find an element, is fine when no style hangs on it.
5. No Bootstrap class names in what the site prints (`btn`, `card`, `row`, `col-md-4`, `d-flex`,
   `form-control`, `table`, `nav-link`, `text-muted`, ...).
6. No select2 (Tom Select instead, styled only with Tailwind classes; its stylesheet is never
   loaded). No jQuery in our code (Alpine.js and `fetch` instead).
7. The look is the OLD site's look: same elements, order, text, sizes, spacing, colours.

## How to translate

- Open the OLD template (`E:\WEB\worktrees\sm-forms\wp-content\themes\scoutingmemories\...`),
  read each Bootstrap class on each element, and write the Tailwind utilities that produce exactly
  what Bootstrap 5.3.8 produced (its real values: `.btn` padding .375rem .75rem, radius .375rem,
  1rem text; `.card` 1px border rgba(0,0,0,.175), radius .375rem; `.container` 12px side padding
  and 540/720/960/1140/1320px; gutters 1.5rem; `mt-5` = 3rem = Tailwind `mt-12`; and so on).
  Keep the NEW template's PHP logic (gateways, `Access::` checks, escaping, translations).
- Where the old Bootstrap class did nothing in 5.3.8 (`font-weight-bold`, `border-right`,
  `img-responsive`, `data-toggle`), the old page showed nothing for it: do the same.
- What the deleted stylesheets implemented can be read with
  `git -C E:/WEB/worktrees/sm-tailwind show HEAD:wp-content/themes/Scouting-Memories-2026/tailwind/custom/components/<name>.css`
  (`components`, `scouting-memories`, `scouting-forms`, `public-pages`). Every look in them must
  now come from classes on elements.
- State that used to be a CSS selector becomes a Tailwind variant on the element:
  `hover:`, `focus-visible:`, `aria-[current=page]:`, `data-[open]:`, `peer-checked:`,
  `has-[:checked]:`, `group-has-[#id:checked]:`, `open:`, `first:`, `[&>li]:` and other arbitrary
  variants are all allowed. Alpine.js may toggle Tailwind classes with `:class`.
- Text an editor typed (post and page content) gets Tailwind's own Typography plugin:
  `prose max-w-none` plus `prose-...:` modifiers (for example `prose-a:text-blue`,
  `prose-headings:font-slab`).

## Named once

- Colours, container widths, screen-size steps, fonts: `tailwind/tailwind-theme.css` (`@theme`).
  Use `bg-brand`, `bg-brand-dark`, `text-blue`, `hover:text-blue-link-hover`, `bg-grey`,
  `bg-green`, `bg-red`, `bg-cyan`, `text-ink`, `border-line`, `border-field-line`,
  `text-menu-text`, `bg-light`. Never write a colour code in a template; if a colour is missing,
  use the nearest Tailwind value with an opacity (`border-black/[.175]`, `text-white/55`).
- Screen-size steps are the old site's: `sm` 576px, `md` 768px, `lg` 992px, `xl` 1200px,
  `2xl` 1400px.
- Shared class lists: `theme/inc/class-scouting-memories-2026-ui.php`
  (`Scouting_Memories_2026_UI::CONTAINER`, `BTN`, `BTN_SM`, `BTN_PRIMARY`, `BTN_SECONDARY`,
  `BTN_SUCCESS`, `BTN_DANGER`, `BTN_INFO`, `CARD`, `CARD_HEADER`, `CARD_BODY`, `LABEL`, `FIELD`,
  `SELECT`, `HELP`, `TABLE`, `TH`, `TD`, `H1`...`H6`, `LINK`, `PAGING`, `PAGE_LINK`,
  `PAGE_LINK_HERE`, and `classes( ...$lists )` to join them). Use these instead of retyping.
  Tailwind only builds classes that appear as whole literal strings in a file: never build a
  class name from pieces.

## Scripts

- `javascript/script.js` is the main entry (bundled by esbuild into `theme/js/script.min.js`).
  It imports Alpine.js, calls `registerSearch( Alpine )` from `javascript/search.js`,
  `registerAccount( Alpine )` from `javascript/account.js` and `registerMedia( Alpine )` from
  `javascript/media.js` (each file's default export), puts Alpine on `window.Alpine`, and
  starts it on `DOMContentLoaded` (not at once, so a deferred script printed later can
  register its part first).
- `javascript/editor.js` is its own bundle (`theme/js/editor.min.js`): the rich-text editor
  (Tiptap) as the Alpine part `richText`. It does not import Alpine; it registers on the
  `alpine:init` event with `window.Alpine`. `functions.php` registers it as
  `scouting-memories-2026-editor` and loads it only when the action `sm_forms_rich_text_field`
  fires (Scouting Forms fires it when it prints a rich-text field). Tiptap's own stylesheet is
  switched off (`injectCSS: false`): the writing area's classes come from the field's markup.
- No inline `<script>` blocks in templates. Small behaviour is Alpine attributes on the element.
- Nothing loads from a CDN.

## No jQuery on the front end

- The theme asks for no jQuery, and never calls `wp_enqueue_media()` or `wp_enqueue_editor()`
  (WordPress's media window and its TinyMCE editor both need jQuery).
- Media: the theme's own library (`theme/template-parts/media/library.php`, behaviour in
  `javascript/media.js`, WordPress's REST API `wp/v2/media` with `fetch`). It is printed on the
  My Account page's Media tab and, in "pick" mode, inside the `<dialog>` of
  `theme/template-parts/media/picker.php` (footer, only for users who may upload). Open the
  chooser with `window.dispatchEvent( new CustomEvent( 'sm-media-pick' ) )`; the answer is the
  window event `sm-media-picked` with `detail { id, url, alt, title, mime }`. Who may use it:
  the core plugin's `ScoutingMemories\Core\Media\MediaLibrary`.
- Rich text: `<div x-data="richText" data-rich-text>` with a toolbar (`data-rich-toolbar`,
  buttons calling `run('bold')` and reading `active('bold')`), `<div x-ref="editor"
  data-editor-class="..." aria-label="...">` and `<textarea x-ref="field">`. The names `run()`
  knows are listed at the top of `javascript/editor.js`.
- `tests/e2e/no-jquery.spec.cjs` checks every public page, every My Account screen and the Add
  pages for `window.jQuery` and for jQuery-based scripts.
