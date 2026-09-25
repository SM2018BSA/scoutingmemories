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

Run `npm run setup` as root to include the stream endpoint tests: it adds a fake storage
server (`https://pdf-proxy-test.example`, on a loopback alias with a public-looking address and
a certificate from a throwaway CA) so the endpoint's TLS, address pinning, range and redirect
handling can be tested without the internet. Without root those tests are skipped.

What's covered: loading with PDF.js 6.3, printed page numbers, `#page=` links, copy link,
resume reading, citations (Chicago, MLA, APA, RIS, Zotero meta tags), download, print,
search (case, accents, line breaks, match list, scans without text), text selection vs.
drag-to-move, thumbnails, contents, document details, image adjustments, save an area as a
picture, two-page view, rotate, zoom, fullscreen, the More menu from the keyboard, page
references in comments, small screens, and security (other hosts, `javascript:` URLs,
escaping, and the stream endpoint's refusals).

Nothing here runs on the live site: the PHP files exit unless run from the command line (or,
for `router.php`, PHP's built-in server).
