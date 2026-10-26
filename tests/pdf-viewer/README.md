# PDF viewer tests

Browser and server tests for `wp-content/plugins/scouting-pdf-embedder`. They run the real
plugin and its bundled PDF.js in a throwaway WordPress (SQLite, PHP's built-in server) with
sample posts and generated test PDFs.

```sh
cd tests/pdf-viewer
npm install
npm run setup          # builds WordPress in /tmp/scouting-pdf-wp and starts it on :8888
npx playwright test
```

Needs PHP 8 with `pdo_sqlite` and `curl`, Node 18+, and Python 3 with `reportlab` and
`Pillow`. WordPress itself comes from npm, so wordpress.org doesn't need to be reachable.

## On Windows (Git Bash)

Put the test WordPress and a Python with the two libraries in folders of your choice, and use
the installed Chrome instead of downloading Playwright's browser:

```sh
python -m venv E:/test-venv && E:/test-venv/Scripts/python.exe -m pip install reportlab Pillow
WP_DIR=E:/test-wp PYTHON=E:/test-venv/Scripts/python.exe npm run setup
WP_DIR=E:/test-wp PLAYWRIGHT_CHROMIUM_PATH="C:/Program Files/Google/Chrome/Application/chrome.exe" npx playwright test
```

The plugin is copied into the test WordPress (Windows makes copies instead of symlinks), so
run `npm run setup` again after changing it. The server runs as a hidden process whose number
is in `$WP_DIR/server.pid`; stop it with `taskkill //F //PID $(cat $WP_DIR/server.pid)`.
PHP's built-in server answers one request at a time on Windows, so a full run is slower.

## Stream endpoint tests

Run `npm run setup` as root (Linux) to include the stream endpoint tests: it adds a fake storage
server (`https://pdf-proxy-test.example`, on a loopback alias with a public-looking address and
a certificate from a throwaway CA) so the endpoint's TLS, address pinning, range and redirect
handling can be tested without the internet. Without root those tests are skipped.

## Page OCR (the Text button)

`fake-ocr.mjs` stands in for the OCR app on the development server. The OCR tests start it on
`127.0.0.1:8899` themselves. It checks every request's signature exactly as the real app does,
and the tests switch it between answering quickly, slowly, with a failure, with no text, busy, or
not at all. `ocr-state.php` switches the feature on and off, sets the daily limits and clears
the saved pages between tests.

The Text button is only offered for documents on `storage.scoutingmemories.org`, so the OCR test
post links there. The tests hand the browser the local test file instead, and `wp-test-env.php`
stops the test site's stream endpoint from downloading anything from the real storage.

The real OCR app is tested end to end separately, on the `sm-ocr` dev site and on the
development server.

## What's covered

Loading with PDF.js 6.3, printed page numbers, `#page=` links, citations (Chicago, MLA, APA,
RIS, Zotero meta tags) and Copy link in the Cite window, clipping part of a page as a picture,
the toolbars' buttons and labels (and that there is no Download, Print, menu or search box),
dragging to move, thumbnails, contents, document details, image adjustments, two-page view,
rotate, zoom, fullscreen, hints, page references in comments, small screens, security (other
hosts, `javascript:` URLs, escaping, and the stream endpoint's refusals), and page OCR: the Text
button on and off, logged out, reading a page, saved text for everyone, Copy text, nothing
readable, failed and retried reads, the service down or busy, daily limits, slow reads, closing
the window, the 10-minute retry, the top bar at five screen widths, and in PHP the one spelling
per document, refused links, signed requests, one row per page, limits and the off switch.

Nothing here runs on the live site: the PHP files exit unless run from the command line (or,
for `router.php`, PHP's built-in server).
