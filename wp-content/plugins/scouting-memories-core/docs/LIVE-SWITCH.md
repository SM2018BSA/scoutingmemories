# Switching the live site to the 2026 theme — the runbook

For much later, after the review on the development server's Forms website is signed
off. Every step is an owner action; nothing here happens on its own. Steps 1–2 use the
control panel as usual.

The theme and this plugin ship from the forms branch, `develop`, together with Scouting
Forms. They are edited in the Tailwind theme workspace (branch `tailwind-theme`, which
is never pushed) and copied into `develop` from there.

1. **Push** the `develop` branch from the panel's GitHub card.
2. **Deploy the parts** from the panel's Live card, from branch `develop`:
   - `wp-content/themes/Scouting-Memories-2026`
   - `wp-content/plugins/scouting-memories-core`
   - `wp-content/plugins/scouting-forms`, when Scouting Forms goes live in the same
     release (see that plugin's own GO-LIVE.md).
   Deploying files activates nothing; the live site keeps running the old theme.
3. On live (wp-admin), in this order:
   1. **Appearance → Themes → activate "Scouting Memories 2026".** (The new theme
      shows a plain notice if the core plugin is missing; order is still safe.)
   2. **Plugins → activate "Scouting Memories Core".**
   3. **Plugins → deactivate "Formidable Bootstrap"** if it is still active. The new
      theme styles the forms itself.
   4. **Pages → Add a Post → set Template to "Add Post Page"** (it has none today).
4. **Smoke test** on live: front page search chain, one category page, one single
   memory, log in and walk the My Account tabs, press the end-dates Update button.
5. **Rolling back** is the same list in reverse: reactivate Formidable Bootstrap,
   switch back to the ScoutingMemories theme, deactivate the core plugin. The old
   theme never changed, so rollback is immediate.

Notes
- The front-end pages (My Account, Add a Post, Add a Council / Camp / Lodge) show
  Scouting Forms' forms and views when Scouting Forms is active, and Formidable's when
  it is not. The choice is made in `src/Forms/GatewayFactory.php`.
- `/memory-box/` stays redirected to memo.fm by the Redirection rule; the new theme
  carries the page's template in case that rule is ever removed.
- The `?runit=yes` "Process Data" button from the old sidebar had no handler anywhere
  and was not carried over.
- After the switch has settled, the old theme folder and its branch become a normal
  clean-up task.
