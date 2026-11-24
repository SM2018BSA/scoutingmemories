=== Scouting Memories 2026 ===
Contributors: scoutingmemories
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The Scouting Memories Project's theme: a responsive design for the public archive and the members' front-end pages.

== Description ==

Scouting Memories 2026 is the theme of scoutingmemories.org, the Scouting Memories Project's searchable archive of Scouting history.

* Public pages: the front page with its search, the six category pages, category archives, search results and single memories.
* Members' front-end pages: My Account (its tabs change with the member's role), Add a Post, Add a Council, Add a Camp, Add a Lodge.
* Built with Tailwind CSS 4 and daisyUI 5 on the _tw starter theme. Every page is checked at phone, tablet, laptop and desktop sizes with Playwright and axe-core.
* Presentation only. Who may see what, and which forms plugin supplies the forms and views, is decided by the Scouting Memories Core plugin, which must be active.

== Installation ==

1. Activate the Scouting Memories Core plugin.
2. Activate this theme in Appearance > Themes.
3. Assign the primary menu in Appearance > Menus.

== Changelog ==

= 1.0.0 =
* The front-end pages get their forms and views through the core plugin's forms gateway (Scouting Forms or Formidable).
* Responsive at every screen size: new masthead with a menu button on small screens, full-width fields, tables that scroll inside their own box.
* Keyboard and screen-reader improvements: skip link, visible focus, labelled menus, tabs that work from the keyboard.
* The heading font is served by the site itself; nothing loads from a CDN.

= 0.1.0 =
* First version: the old Bootstrap theme's templates rebuilt on _tw, Tailwind CSS and daisyUI.

== Copyright ==

Scouting Memories 2026 WordPress Theme, (C) 2026 Scouting Memories Project.
Scouting Memories 2026 is distributed under the terms of the GNU GPL v2 or later.

This theme bundles or is built from the following third-party resources:

_tw, (C) 2021-2026 Greg Sullivan
License: GNU GPL v2 or later
Source: https://underscoretw.com/

Underscores, (C) 2012-2026 Automattic, Inc.
License: GNU GPL v2 or later
Source: https://underscores.me/

Tailwind CSS, (C) Tailwind Labs, Inc.
License: MIT
Source: https://tailwindcss.com/

daisyUI, (C) Pouya Saadeghi
License: MIT
Source: https://daisyui.com/

Tailwind Typography (_tw fork), (C) Tailwind Labs, Inc. and Greg Sullivan
License: MIT
Source: https://github.com/gregsullivan/_tw-typography

Select2, (C) Kevin Brown, Igor Vaynberg and Select2 contributors
License: MIT
Source: https://select2.org/

Roboto Slab, (C) The Roboto Slab Project Authors
License: Apache License 2.0
Source: https://github.com/googlefonts/robotoslab (files from https://fontsource.org/)
