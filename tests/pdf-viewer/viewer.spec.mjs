// End-to-end tests for every viewer feature, run against the test WordPress from
// setup-wordpress.sh (npm run setup), with the real plugin code and PDF.js build.
import { test, expect } from '@playwright/test';
import { execFileSync, spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const WP_DIR = process.env.WP_DIR || '/tmp/scouting-pdf-wp';
const GUIDE_BYTES = readFileSync(join(WP_DIR, 'wp-content/uploads/fixtures/leaders-guide.pdf'));

// Fail any test that hits a script error or pops up a dialog (a sign of injected script)
let problems = [];
test.beforeEach(async ({ page }) => {
    problems = [];
    page.on('pageerror', (err) => problems.push('Page error: ' + err.message + '\n' + (err.stack || '').split('\n').slice(1, 4).join('\n')));
    page.on('dialog', async (d) => { problems.push('Unexpected dialog: ' + d.message()); await d.dismiss(); });
});
test.afterEach(() => {
    expect(problems).toEqual([]);
});

async function openViewer(page, path, n = 0) {
    await page.goto(path);
    const viewer = page.locator('[data-pdf-viewer]').nth(n);
    await viewer.scrollIntoViewIfNeeded();
    await expect(viewer).toHaveAttribute('data-pdf-state', 'ready', { timeout: 20000 });
    return viewer;
}

const currentPage = (viewer) => viewer.evaluate((el) => el.scoutingPdf.currentPage());
const goTo = (viewer, num) => viewer.evaluate((el, n) => el.scoutingPdf.goToPage(n), num);
const button = (viewer, name) => viewer.locator(`[data-pdf-control="${name}"]`);

async function openCite(viewer) {
    await button(viewer, 'cite').click();
    const dialog = viewer.locator('[data-pdf-role="dialog"]');
    await expect(dialog).toBeVisible();
    return dialog;
}

async function openDetails(viewer) {
    await button(viewer, 'sidebar').click();
    await viewer.locator('[data-pdf-tab="info"]').click();
    return viewer.locator('[data-pdf-panel="info"]');
}

// Width and height from a JPEG's frame header
function jpegSize(buf) {
    expect(buf.readUInt16BE(0)).toBe(0xffd8);
    for (let i = 2; i < buf.length - 9;) {
        const marker = buf.readUInt16BE(i);
        if (marker >= 0xffc0 && marker <= 0xffc3) return { height: buf.readUInt16BE(i + 5), width: buf.readUInt16BE(i + 7) };
        i += 2 + buf.readUInt16BE(i + 2);
    }
    throw new Error('no JPEG frame header');
}

test.describe('loading', () => {
    test('opens with the bundled PDF.js 6.3 in a worker and renders pages', async ({ page }) => {
        const scripts = [];
        page.on('request', (r) => scripts.push(r.url()));
        const viewer = await openViewer(page, '/leaders-guide/');
        expect(scripts.some((u) => /vendor\/pdfjs\/pdf\.min\.js\?ver=6\.3\.289/.test(u))).toBe(true);
        expect(scripts.some((u) => /vendor\/pdfjs\/pdf\.worker\.min\.js/.test(u))).toBe(true);
        expect(scripts.some((u) => /vendor\/pdf\.(min\.)?js/.test(u))).toBe(false); // old 2.0 build is gone
        await expect(viewer.locator('[data-pdf-page]')).toHaveCount(12);
        await expect(viewer.locator('[data-pdf-page="1"] canvas')).toHaveCount(1);
        expect(await viewer.locator('[data-pdf-page="1"] canvas').evaluate((c) => c.width)).toBeGreaterThan(100);
        await expect(button(viewer, 'total-pages')).toHaveText('12');
    });

    test('a fold-out page does not push ordinary pages off-center', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        const box = await viewer.evaluate((el) => {
            const vp = el.querySelector('[data-pdf-role="viewport"]').getBoundingClientRect();
            const p1 = el.querySelector('[data-pdf-page="1"]').getBoundingClientRect();
            return { vpLeft: vp.left, vpRight: vp.right, left: p1.left, right: p1.right };
        });
        expect(box.left).toBeGreaterThanOrEqual(box.vpLeft);
        expect(box.right).toBeLessThanOrEqual(box.vpRight);
    });

    test('pages cached before this version (old markup and settings) still work', async ({ page }) => {
        const viewer = await openViewer(page, '/test-assets/stale-cache.html');
        await expect(viewer.locator('[data-pdf-page]')).toHaveCount(12);
        await expect(viewer.locator('[data-pdf-page="1"] canvas')).toHaveCount(1);
        await button(viewer, 'next').click();
        await expect.poll(() => currentPage(viewer)).toBe(2);
    });

    test('falls back to the stream endpoint when the browser cannot load storage directly', async ({ page }) => {
        const probe = await page.request.get('/wp-json/scouting-pdf/v1/stream?pdf_url=' + encodeURIComponent('https://pdf-proxy-test.example/fixtures/leaders-guide.pdf'));
        test.skip(probe.status() !== 200, 'fake storage server not running (needs root in setup-wordpress.sh)');
        const streamed = [];
        page.on('response', (r) => { if (r.url().includes('/wp-json/scouting-pdf/v1/stream')) streamed.push(r.status()); });
        const viewer = await openViewer(page, '/proxied/');
        await expect(viewer.locator('[data-pdf-page]')).toHaveCount(12);
        expect(streamed.length).toBeGreaterThan(0);
        expect(streamed.every((s) => s === 200 || s === 206)).toBe(true);
    });
});

test.describe('page numbers and links', () => {
    test('shows the printed page number next to the PDF page number', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        const badge = button(viewer, 'page-label');
        await expect(badge).toHaveText('p. i');
        await goTo(viewer, 5);
        await expect(badge).toHaveText('p. 2');
        await expect(viewer.locator('[data-pdf-page="5"]')).toHaveAttribute('aria-label', 'Page 5 of 12, printed page 2');
    });

    test('#page=N opens the document at that page, and follows later hash changes', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/#page=6');
        await expect.poll(() => currentPage(viewer)).toBe(6);
        await page.evaluate(() => { window.location.hash = '#page=9'; });
        await expect.poll(() => currentPage(viewer)).toBe(9);
    });

    test('#pdf2-page=N targets the second document on a post', async ({ page }) => {
        await page.goto('/two-documents/#pdf2-page=8');
        const second = page.locator('[data-pdf-viewer]').nth(1);
        await expect(second).toHaveAttribute('data-pdf-state', 'ready', { timeout: 20000 });
        await expect.poll(() => currentPage(second)).toBe(8);
    });

    test('the page="6" shortcode option opens at that page', async ({ page }) => {
        const second = await openViewer(page, '/two-documents/', 1);
        await expect.poll(() => currentPage(second)).toBe(6);
    });

    test('Copy link in Cite copies a link that reopens the same page', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await goTo(viewer, 7);
        const dialog = await openCite(viewer);
        await expect(dialog.getByRole('textbox', { name: 'Link to this page' })).toHaveValue('http://localhost:8888/leaders-guide/#page=7');
        await dialog.getByRole('button', { name: 'Copy link' }).click();
        await expect.poll(() => page.evaluate(() => navigator.clipboard.readText())).toBe('http://localhost:8888/leaders-guide/#page=7');
        await expect(viewer.locator('[data-pdf-role="toasts"] .toast')).toContainText('Link copied!');
        const reopened = await openViewer(page, 'http://localhost:8888/leaders-guide/#page=7');
        await expect.poll(() => currentPage(reopened)).toBe(7);
    });
});

test.describe('citations', () => {
    test('Cite gives Chicago, MLA and APA for the current page from the archive fields', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await goTo(viewer, 6);
        const dialog = await openCite(viewer);
        await expect(dialog.locator('[data-pdf-role="dialog-title"]')).toHaveText('Cite or share this page');
        await expect(dialog.locator('.badge')).toHaveText('p. 3 (PDF page 6)');

        // Chicago first; the other styles are one choice away
        const chicago = dialog.locator('[data-pdf-citation="chicago"]');
        const mla = dialog.locator('[data-pdf-citation="mla"]');
        const apa = dialog.locator('[data-pdf-citation="apa"]');
        await expect(chicago).toBeVisible();
        await expect(mla).toBeHidden();
        await expect(chicago).toContainText('“Camp Tahquitz Leaders Guide, 1952,” June 1952, p. 3.');
        await expect(chicago).toContainText('SM-TEST-0042.');
        await expect(chicago).toContainText('Digitized by Test Council Archives.');
        await expect(chicago).toContainText('http://localhost:8888/leaders-guide/#page=6 (accessed');
        const style = dialog.getByLabel('Style');
        await style.selectOption('mla');
        await expect(mla).toBeVisible();
        await expect(chicago).toBeHidden();
        await expect(mla).toContainText('SM Test, June 1952, p. 3, http://localhost:8888/leaders-guide/#page=6. Accessed');
        await style.selectOption('apa');
        await expect(apa).toContainText('Test Council Archives. (1952). Camp Tahquitz Leaders Guide, 1952 [Archival document] (p. 3).');
        await expect(apa.locator('i')).toHaveText('Camp Tahquitz Leaders Guide, 1952');

        // Without the page: no page number, link to the post
        const pointTo = dialog.getByLabel('Point to p. 3 (PDF page 6) (uncheck to cite the whole document)');
        await pointTo.uncheck();
        await expect(apa).not.toContainText('p. 3');
        await expect(apa).not.toContainText('#page=');

        await pointTo.check();
        await style.selectOption('mla');
        await dialog.getByRole('button', { name: 'Copy citation' }).click();
        await expect.poll(() => page.evaluate(() => navigator.clipboard.readText())).toContain('“Camp Tahquitz Leaders Guide, 1952.” SM Test, June 1952, p. 3');
        await expect(viewer.locator('[data-pdf-role="toasts"] .toast')).toContainText('MLA citation copied!');
    });

    test('RIS download for Zotero and EndNote', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await goTo(viewer, 4);
        await openCite(viewer);
        const [download] = await Promise.all([
            page.waitForEvent('download'),
            button(viewer, 'cite-ris').click(),
        ]);
        expect(download.suggestedFilename()).toBe('leaders-guide-p1.ris');
        const ris = readFileSync(await download.path(), 'utf8');
        expect(ris).toContain('TY  - GEN');
        expect(ris).toContain('TI  - Camp Tahquitz Leaders Guide, 1952');
        expect(ris).toContain('PY  - 1952');
        expect(ris).toContain('AN  - SM-TEST-0042');
        expect(ris).toContain('SP  - 1');
        expect(ris).toContain('UR  - http://localhost:8888/leaders-guide/#page=4');
        expect(ris).toContain('L1  - http://localhost:8888/wp-content/uploads/fixtures/leaders-guide.pdf');
        expect(ris.trim().endsWith('ER  -')).toBe(true);
    });

    test('pages carry citation tags that Zotero and Google Scholar read', async ({ page }) => {
        await page.goto('/leaders-guide/');
        const meta = (name) => page.locator(`meta[name="${name}"]`).getAttribute('content');
        expect(await meta('citation_title')).toBe('Camp Tahquitz Leaders Guide, 1952');
        expect(await meta('citation_date')).toBe('June 1952');
        expect(await meta('citation_publisher')).toBe('Test Council Archives');
        expect(await meta('citation_pdf_url')).toBe('http://localhost:8888/wp-content/uploads/fixtures/leaders-guide.pdf');
        expect(await meta('DC.identifier')).toBe('SM-TEST-0042');
    });
});

test.describe('toolbars', () => {
    // The owner's layout: top = Pages | Adjust, Rotate, Cite | Two-page view, Fullscreen;
    // bottom = Zoom out, %, Zoom in | Previous, Page x of y, Next | Fit width, Fit page.
    // Every button keeps its text label; no Download, Print, menu or search box anywhere.
    test('the toolbars hold exactly the chosen buttons, each with its label', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        const bar = (role) => viewer.locator(`[data-pdf-role="${role}"] button`).evaluateAll((els) => els.map((b) => [b.getAttribute('data-pdf-control'), b.textContent.trim()]));
        expect(await bar('toolbar')).toEqual([
            ['sidebar', 'Pages'], ['adjust', 'Adjust'], ['rotate', 'Rotate'], ['cite', 'Cite'],
            ['spread', 'Two-page view'], ['fullscreen', 'Fullscreen'],
        ]);
        expect(await bar('bottom-toolbar')).toEqual([
            ['zoom-out', 'Zoom out'], ['zoom-in', 'Zoom in'], ['prev', 'Previous'], ['next', 'Next'],
            ['zoom-fit', 'Fit width'], ['zoom-page', 'Fit page'],
        ]);
        for (const name of ['download', 'print', 'more', 'search', 'text-select']) {
            await expect(button(viewer, name), name).toHaveCount(0);
        }
        await expect(viewer.locator('[data-pdf-role="menu"], [data-pdf-role="findbar"]')).toHaveCount(0);
    });

    test('download="no" documents open like any other', async ({ page }) => {
        const second = await openViewer(page, '/two-documents/', 1);
        await expect(second).toHaveAttribute('data-pdf-download', '0');
        await expect(button(second, 'download')).toHaveCount(0);
        await expect.poll(() => currentPage(second)).toBe(6);
    });

    test('dragging with the mouse moves the page', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        const vp = viewer.locator('[data-pdf-role="viewport"]');
        const box = await vp.boundingBox();
        const before = await vp.evaluate((el) => el.scrollTop);
        await page.mouse.move(box.x + box.width / 2, box.y + 400);
        await page.mouse.down();
        await page.mouse.move(box.x + box.width / 2, box.y + 100, { steps: 6 });
        await page.mouse.up();
        expect(await vp.evaluate((el) => el.scrollTop)).toBeGreaterThan(before + 200);
        expect(await page.evaluate(() => window.getSelection().toString())).toBe('');
    });
});

test.describe('sidebar', () => {
    test('page thumbnails with printed numbers jump to the page', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await button(viewer, 'sidebar').click();
        const thumbs = viewer.locator('[data-pdf-thumb]');
        await expect(thumbs).toHaveCount(12);
        await expect(thumbs.nth(0)).toContainText('i');
        await expect(thumbs.nth(3)).toContainText('1');
        await expect(thumbs.nth(0).locator('canvas')).toHaveCount(1);
        await expect(thumbs.nth(0)).toHaveAttribute('aria-current', 'page');
        await thumbs.nth(7).scrollIntoViewIfNeeded();
        await thumbs.nth(7).click();
        await expect.poll(() => currentPage(viewer)).toBe(8);
        await expect(thumbs.nth(7)).toHaveAttribute('aria-current', 'page');
    });

    test('Contents lists the PDF outline and jumps to sections', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await button(viewer, 'sidebar').click();
        await viewer.locator('[data-pdf-tab="outline"]').click();
        const outline = viewer.locator('[data-pdf-panel="outline"]');
        await expect(outline.getByRole('button')).toHaveText(['Front matter', 'Chapter 1: Camp history', 'Waterfront']);
        await outline.getByRole('button', { name: 'Waterfront' }).click();
        await expect.poll(() => currentPage(viewer)).toBe(6);
    });

    test('Details shows archive fields and what the file says about itself', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        const info = await openDetails(viewer);
        await expect(info).toContainText('IdentifierSM-TEST-0042');
        await expect(info).toContainText('Date of originalJune 1952');
        await expect(info).toContainText('Physical description12 pages, stapled booklet');
        await expect(info).toContainText('Title in fileCamp Tahquitz Leaders Guide');
        await expect(info).toContainText('Author in fileTest Council, Boy Scouts of America');
        await expect(info).toContainText('Pages12 (printed numbers i–9)');
        await expect(info).toContainText('Page size8.50 × 11.00 in (21.6 × 27.9 cm)');
        await expect(info).toContainText('File nameleaders-guide.pdf');
    });
});

test.describe('reading scans', () => {
    test('Adjust changes brightness, contrast, black & white and invert, and resets', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await button(viewer, 'adjust').click();
        await button(viewer, 'brightness').fill('150');
        await button(viewer, 'contrast').fill('200');
        await viewer.getByLabel('Black & white').check();
        await viewer.getByLabel('Invert (negatives, blueprints)').check();
        const filter = () => viewer.locator('[data-pdf-page="1"] canvas').evaluate((c) => getComputedStyle(c).filter);
        await expect.poll(filter).toBe('brightness(1.5) contrast(2) grayscale(1) invert(1)');
        await button(viewer, 'adjust-reset').click();
        await expect.poll(filter).toBe('none');
    });

    test('Clip part of the page (in Cite) makes a high-resolution picture with the citation underneath', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await goTo(viewer, 4);
        const dialog = await openCite(viewer);
        await dialog.getByRole('button', { name: 'Clip part of the page' }).click();
        await expect(dialog).toBeHidden();
        const pageEl = viewer.locator('[data-pdf-page="4"]');
        const box = await pageEl.boundingBox();
        const scale = box.width / 612;
        await page.mouse.move(box.x + 40, box.y + 40);
        await page.mouse.down();
        await page.mouse.move(box.x + 290, box.y + 190, { steps: 6 });
        await page.mouse.up();
        await expect(dialog.locator('[data-pdf-role="dialog-title"]')).toHaveText('Share your clipping');
        const [download] = await Promise.all([
            page.waitForEvent('download'),
            dialog.getByRole('button', { name: 'Download picture' }).click(),
        ]);
        expect(download.suggestedFilename()).toBe('leaders-guide-p1.jpg');
        const size = jpegSize(readFileSync(await download.path()));
        const factor = (300 / 72) / scale;
        expect(Math.abs(size.width - 250 * factor)).toBeLessThan(4);
        expect(size.height).toBeGreaterThan(150 * factor + 30); // room for the caption
        await expect(viewer).not.toHaveClass(/is-snapshot/);
    });

    test('Two-page view shows spreads (cover alone) and pages through a spread at a time', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await button(viewer, 'spread').click();
        await expect(button(viewer, 'spread')).toHaveAttribute('aria-pressed', 'true');
        const rows = viewer.locator('[data-pdf-role="spread-row"]');
        await expect(rows).toHaveCount(7);
        await expect(rows.nth(0).locator('[data-pdf-page]')).toHaveCount(1);
        await expect(rows.nth(1).locator('[data-pdf-page]')).toHaveCount(2);
        const tops = await rows.nth(1).locator('[data-pdf-page]').evaluateAll((els) => els.map((e) => e.getBoundingClientRect().top));
        expect(Math.abs(tops[0] - tops[1])).toBeLessThan(2);
        await button(viewer, 'next').click();
        await expect.poll(() => currentPage(viewer)).toBe(2);
        await button(viewer, 'next').click();
        await expect.poll(() => currentPage(viewer)).toBe(4);
        await button(viewer, 'spread').click();
        await expect(rows).toHaveCount(0);
        await expect.poll(() => currentPage(viewer)).toBe(4);
    });

    test('Rotate turns only the current page', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        const landscape = (n) => viewer.locator(`[data-pdf-page="${n}"]`).evaluate((e) => e.offsetWidth > e.offsetHeight);
        await button(viewer, 'rotate').click();
        await expect.poll(() => landscape(1)).toBe(true);
        expect(await landscape(2)).toBe(false);
        // The R key does the same
        await viewer.locator('[data-pdf-role="viewport"]').click();
        await page.keyboard.press('r');
        await expect.poll(() => landscape(1)).toBe(false);
    });

    test('fullscreen fills the screen, sidebar and bottom bar included', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await button(viewer, 'sidebar').click();
        await button(viewer, 'fullscreen').click();
        await expect.poll(() => page.evaluate(() => document.fullscreenElement && document.fullscreenElement.id)).toBe('scouting-pdf-1');
        const sizes = await viewer.evaluate((el) => ({
            viewer: el.getBoundingClientRect().height,
            body: el.querySelector('[data-pdf-role="body"]').getBoundingClientRect().bottom,
            bottomBarTop: el.querySelector('[data-pdf-role="bottom-toolbar"]').getBoundingClientRect().top,
            bottomBar: el.querySelector('[data-pdf-role="bottom-toolbar"]').getBoundingClientRect().bottom,
            sidebar: el.querySelector('[data-pdf-role="sidebar"]').getBoundingClientRect().bottom,
            screen: window.innerHeight,
        }));
        expect(sizes.viewer).toBe(sizes.screen);
        expect(Math.abs(sizes.bottomBar - sizes.screen)).toBeLessThan(2);
        expect(Math.abs(sizes.body - sizes.bottomBarTop)).toBeLessThan(2);
        expect(Math.abs(sizes.sidebar - sizes.body)).toBeLessThan(2);
        await button(viewer, 'fullscreen').click();
        await expect.poll(() => page.evaluate(() => !!document.fullscreenElement)).toBe(false);
    });

    test('zoom buttons and fit modes still work', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        const level = button(viewer, 'zoom-level');
        const before = parseInt(await level.textContent(), 10);
        await button(viewer, 'zoom-in').click();
        // One step is 1.25 times; the shown percentage is rounded, so allow one point either way
        await expect.poll(async () => Math.abs(parseInt(await level.textContent(), 10) - before * 1.25)).toBeLessThanOrEqual(1);
        await expect(button(viewer, 'zoom-fit')).toHaveAttribute('aria-pressed', 'false');
        await button(viewer, 'zoom-page').click();
        await expect(button(viewer, 'zoom-page')).toHaveAttribute('aria-pressed', 'true');
    });
});

test.describe('hints and windows', () => {
    test('hints never block the buttons under them', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await button(viewer, 'adjust').click();
        await page.waitForTimeout(400); // longer than the hint delay
        await button(viewer, 'cite').click({ timeout: 3000 });
        await expect(viewer.locator('[data-pdf-role="dialog"]')).toBeVisible();
    });

    test('the Cite window closes with its X and with Escape', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        const dialog = await openCite(viewer);
        await viewer.locator('[data-pdf-control="dialog-close"]').click();
        await expect(dialog).toBeHidden();
        await openCite(viewer);
        await expect(dialog).toBeFocused(); // fully open: Bootstrap ignores closing while it fades in
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
    });
});

test.describe('comments', () => {
    test('page references in comments open the document at that page', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        const refs = page.locator('a.scouting-pdf-page-ref');
        await expect(refs).toHaveText(['p. 3', 'page ii']);
        await refs.nth(1).click();
        await expect.poll(() => currentPage(viewer)).toBe(2);
        await refs.nth(0).click();
        await expect.poll(() => currentPage(viewer)).toBe(6); // printed p. 3 is PDF page 6
    });
});

test.describe('security', () => {
    test('PDFs on other servers are links, not viewers; javascript: URLs are dropped', async ({ page }) => {
        await page.goto('/external-pdf/');
        await expect(page.locator('[data-pdf-viewer]')).toHaveCount(0);
        await expect(page.locator('a[href="https://files.example.com/evil.pdf"]')).toHaveText('Remote file');
        await expect(page.locator('a[href="https://files.example.com/other.pdf"]')).toHaveCount(1);
        expect(await page.content()).not.toContain('javascript:');
    });

    test('older markup (plain links, escaped shortcodes) still becomes a viewer', async ({ page }) => {
        await page.goto('/legacy-markup/');
        await expect(page.locator('[data-pdf-viewer]')).toHaveCount(2);
    });

    test('text in titles and archive fields is never run as HTML', async ({ page }) => {
        const viewer = await openViewer(page, '/xss-attempt/');
        await openCite(viewer);
        await expect(viewer.locator('[data-pdf-citation="chicago"]')).toContainText('<img src=x onerror=alert(1)>');
        await expect(viewer.locator('[data-pdf-role="dialog"] img')).toHaveCount(0);
        await viewer.locator('[data-pdf-control="dialog-close"]').click();
        const info = await openDetails(viewer);
        await expect(info).toContainText('<b>bold</b>');
        await expect(info.locator('b')).toHaveCount(0);
    });
});

test.describe('small screens', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('no sideways page scroll, and the sidebar slides over the pages', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
        await button(viewer, 'sidebar').click();
        const sidebar = viewer.locator('[data-pdf-role="sidebar"]');
        await expect(sidebar).toBeVisible();
        expect(await sidebar.evaluate((e) => getComputedStyle(e).position)).toBe('absolute');
        await viewer.locator('[data-pdf-thumb="3"]').click();
        await expect(sidebar).toBeHidden();
        await expect.poll(() => currentPage(viewer)).toBe(3);
    });
});

test.describe('stream endpoint', () => {
    const endpoint = (url) => '/wp-json/scouting-pdf/v1/stream?pdf_url=' + encodeURIComponent(url);
    const FAKE = 'https://pdf-proxy-test.example';

    test.beforeAll(async ({ request }) => {
        const res = await request.get(endpoint(FAKE + '/fixtures/leaders-guide.pdf'));
        test.skip(res.status() !== 200, 'fake storage server not running (needs root in setup-wordpress.sh)');
    });

    test('streams an allowed PDF as an inert file', async ({ request }) => {
        const res = await request.get(endpoint(FAKE + '/fixtures/leaders-guide.pdf'), { headers: { Origin: 'https://evil.example' } });
        expect(res.status()).toBe(200);
        const h = res.headers();
        expect(h['content-type']).toBe('application/pdf');
        expect(h['x-content-type-options']).toBe('nosniff');
        expect(h['content-security-policy']).toBe("default-src 'none'; sandbox");
        expect(h['access-control-allow-origin']).toBeUndefined();
        expect(h['x-upstream-secret']).toBeUndefined();
        expect((await res.body()).equals(GUIDE_BYTES)).toBe(true);
    });

    test('supports range requests and HEAD, which PDF.js uses for big files', async ({ request }) => {
        const part = await request.get(endpoint(FAKE + '/fixtures/leaders-guide.pdf'), { headers: { Range: 'bytes=0-99' } });
        expect(part.status()).toBe(206);
        expect(part.headers()['content-range']).toBe(`bytes 0-99/${GUIDE_BYTES.length}`);
        expect((await part.body()).equals(GUIDE_BYTES.subarray(0, 100))).toBe(true);

        const junk = await request.get(endpoint(FAKE + '/fixtures/leaders-guide.pdf'), { headers: { Range: 'items=0-5' } });
        expect(junk.status()).toBe(200); // malformed ranges are not passed on

        const head = await request.head(endpoint(FAKE + '/fixtures/leaders-guide.pdf'));
        expect(head.status()).toBe(200);
        expect(head.headers()['content-length']).toBe(String(GUIDE_BYTES.length));
    });

    test('follows redirects only to allowed hosts', async ({ request }) => {
        expect((await request.get(endpoint(FAKE + '/redirect-good.pdf'))).status()).toBe(200);
        for (const bad of ['/redirect-internal.pdf', '/redirect-metadata.pdf', '/redirect-loop.pdf']) {
            const res = await request.get(endpoint(FAKE + bad));
            expect(res.status(), bad).toBe(502);
        }
    });

    test('never passes on upstream error bodies, and serves HTML as a sandboxed PDF', async ({ request }) => {
        const missing = await request.get(endpoint(FAKE + '/missing.pdf'));
        expect(missing.status()).toBe(404);
        expect(await missing.text()).not.toContain('upstream-error-body');

        const html = await request.get(endpoint(FAKE + '/html.pdf'));
        expect(html.headers()['content-type']).toBe('application/pdf');
        expect(html.headers()['content-security-policy']).toContain('sandbox');
    });

    test('refuses anything that is not a PDF on the storage hosts', async ({ request }) => {
        const cases = [
            ['https://files.example.com/a.pdf', 403],
            ['http://localhost:8888/wp-content/uploads/fixtures/leaders-guide.pdf', 403],
            ['https://localhost/wp-content/uploads/fixtures/leaders-guide.pdf', 502], // allowed name, but a loopback address
            ['https://169.254.169.254/latest/meta-data.pdf', 403],
            [FAKE + ':8443/fixtures/leaders-guide.pdf', 403],
            ['https://user:pw@pdf-proxy-test.example/fixtures/leaders-guide.pdf', 400],
            ['file:///etc/passwd.pdf', 400],
            ['gopher://pdf-proxy-test.example/x.pdf', 400],
            [FAKE + '/wp-config.php', 400],
            [FAKE + '/download.php?file=a.pdf', 400],
        ];
        for (const [url, status] of cases) {
            const res = await request.get(endpoint(url));
            expect(res.status(), url).toBe(status);
            expect(res.headers()['content-type'], url).toContain('text/plain');
        }
        expect((await request.get('/wp-json/scouting-pdf/v1/stream')).status()).toBe(400);
        // The old admin-ajax copy of the endpoint is gone
        expect(await (await request.get('/wp-admin/admin-ajax.php?action=scouting_pdf_proxy&pdf_url=' + encodeURIComponent(FAKE + '/fixtures/leaders-guide.pdf'))).text()).toBe('0');
    });
});

test.describe('page OCR (Text button)', () => {
    // These tests share the stand-in service and the saved pages. Tests in one file run in
    // order in one worker, so they never overlap; each starts from a clean state.
    const FAKE_OCR = 'http://127.0.0.1:8899';
    const FAKE_TEXT = 'Camp Tahquitz opened in 1952\non the shore of the lake.\n\nWaterfront rules: every Scout swims with a buddy.';
    const DOC = '/ocr-document/';
    const STORAGE_PDF = 'https://storage.scoutingmemories.org/sm-tests/leaders-guide.pdf';
    let service;

    const ocrState = (...args) => execFileSync('php', [join(HERE, 'ocr-state.php'), WP_DIR, ...args], { encoding: 'utf8' });
    // Saved pages, with numbers as numbers (the database hands back strings)
    const savedRows = () => JSON.parse(ocrState('rows')).map((r) => ({ ...r, page: Number(r.page), asks: Number(r.asks) }));
    const fake = async (path, body) => (await fetch(FAKE_OCR + path, body === undefined ? {} : { method: 'POST', body: JSON.stringify(body) })).json();
    const setMode = (mode) => fake('/__test/mode', { mode });
    const sentToService = () => fake('/__test/requests');
    const isStatusQuestion = (r) => r.request().method() === 'GET' && r.url().includes('/wp-json/scouting-pdf/v1/ocr/');

    // Straight back to the site after logging in: WordPress's own profile page, where a
    // subscriber lands otherwise, throws a script error of its own when left
    async function logIn(page) {
        await page.goto('/wp-login.php?redirect_to=' + encodeURIComponent('http://localhost:8888/'));
        await page.locator('#user_login').fill('reader');
        await page.locator('#user_pass').fill('reader-pass-123');
        await page.locator('#wp-submit').click();
        await page.waitForURL('http://localhost:8888/');
    }

    // Open the test document at a PDF page and press Text
    async function openText(page, pdfPage = 4) {
        const viewer = await openViewer(page, DOC);
        await goTo(viewer, pdfPage);
        await button(viewer, 'ocr').click();
        const dialog = viewer.locator('[data-pdf-role="dialog"]');
        await expect(dialog).toBeVisible();
        return { viewer, dialog };
    }
    const readButton = (dialog) => dialog.getByRole('button', { name: 'Read this page' });
    // Wait until it has finished closing: Bootstrap ignores opening it again while it fades out
    async function closeDialog(viewer) {
        await viewer.locator('[data-pdf-control="dialog-close"]').click();
        await expect(viewer.locator('[data-pdf-role="dialog"]')).toBeHidden();
    }

    const serviceUp = async () => {
        try { return (await fake('/v1/health')).ok === true; } catch { return false; }
    };

    test.beforeAll(async () => {
        // After a failed test Playwright carries on in a new worker; reuse the stand-in if the
        // earlier worker's copy is still running
        if (!(await serviceUp())) {
            service = spawn(process.execPath, [join(HERE, 'fake-ocr.mjs'), '8899', 'fake-ocr-test-secret'], { stdio: 'ignore' });
            await expect.poll(serviceUp).toBe(true);
        }
        ocrState('on');
        ocrState('limits', '100', '20');
    });

    test.afterAll(() => {
        if (service) service.kill();
        ocrState('clear');
    });

    test.beforeEach(async ({ page }) => {
        await fake('/__test/reset', {});
        ocrState('clear');
        // The document links to the real archive storage. The browser gets the local test file
        // instead, whether it asks storage itself or the site's stream endpoint; nothing is
        // ever downloaded from the real storage.
        const pdf = (route) => route.fulfill({ body: GUIDE_BYTES, contentType: 'application/pdf', headers: { 'Access-Control-Allow-Origin': '*' } });
        await page.route(/^https:\/\/storage\.scoutingmemories\.org\//, pdf);
        await page.route(/\/wp-json\/scouting-pdf\/v1\/stream\?/, pdf);
    });

    test('the Text button is there only when the feature is on, and only for archive documents', async ({ page }) => {
        let viewer = await openViewer(page, DOC);
        await expect(button(viewer, 'ocr')).toBeVisible();
        await expect(button(viewer, 'ocr')).toHaveText('Text');
        await expect(viewer).toHaveAttribute('data-pdf-ocr-key', /^[a-f0-9]{32}$/);
        // Logged out: no login token on the page (pages for visitors may be cached)
        expect(await page.evaluate(() => window.ScoutingPdfConfig.ocrCanRequest)).toBe(false);
        expect(await page.evaluate(() => 'ocrNonce' in window.ScoutingPdfConfig)).toBe(false);

        // A PDF on this site itself: the service can't read it, so no button
        viewer = await openViewer(page, '/leaders-guide/');
        await expect(button(viewer, 'ocr')).toHaveCount(0);

        ocrState('off');
        try {
            viewer = await openViewer(page, DOC);
            await expect(button(viewer, 'ocr')).toHaveCount(0);
            expect(await viewer.getAttribute('data-pdf-ocr-key')).toBeNull();
            expect(await page.evaluate(() => 'ocrUrl' in window.ScoutingPdfConfig)).toBe(false);
            const res = await page.request.get('/wp-json/scouting-pdf/v1/ocr/' + 'a'.repeat(32) + '/4');
            expect(await res.json()).toEqual({ status: 'off' });
        } finally {
            ocrState('on');
        }
    });

    test('logged out: a page not read yet asks the reader to log in, and nothing is sent', async ({ page }) => {
        const { dialog } = await openText(page);
        await expect(dialog.locator('[data-pdf-role="dialog-title"]')).toHaveText('Text of page 1');
        await expect(dialog).toContainText('p. 1 (PDF page 4)');
        await expect(dialog).toContainText('This page has not been read yet. Log in to have it read.');
        const login = dialog.getByRole('link', { name: 'Log in to have it read.' });
        expect(decodeURIComponent(await login.getAttribute('href'))).toContain('wp-login.php?redirect_to=http://localhost:8888/ocr-document/');
        await expect(readButton(dialog)).toHaveCount(0);
        expect(await sentToService()).toEqual([]);

        // The site itself refuses a request without a login
        const res = await page.request.post('/wp-json/scouting-pdf/v1/ocr', { data: { url: STORAGE_PDF, page: 4 } });
        expect(res.status()).toBe(401);
        expect(savedRows()).toEqual([]);
    });

    test('a reader has a page read; the text is saved and shown to everyone after', async ({ page }) => {
        await logIn(page);
        const { viewer, dialog } = await openText(page);
        await expect(dialog).toContainText('This page has not been read yet.');
        await readButton(dialog).click();
        await expect(dialog).toContainText('Reading page 1. This usually takes less than a minute.');
        await expect(dialog.locator('.spinner-border')).toBeVisible();
        await expect(dialog).toContainText('Machine-read text. It may contain mistakes, so check it against the page.', { timeout: 15000 });

        // Shown as paragraphs, with the line breaks kept
        const paragraphs = dialog.locator('.border.rounded p');
        await expect(paragraphs).toHaveCount(2);
        await expect(paragraphs.nth(0).locator('br')).toHaveCount(1);
        await expect(paragraphs.nth(0)).toContainText('Camp Tahquitz opened in 1952');
        await expect(paragraphs.nth(1)).toHaveText('Waterfront rules: every Scout swims with a buddy.');

        await dialog.getByRole('button', { name: 'Copy text' }).click();
        // (Windows hands copied text back with Windows line endings)
        await expect.poll(async () => (await page.evaluate(() => navigator.clipboard.readText())).replace(/\r\n/g, '\n')).toBe(FAKE_TEXT);
        await expect(viewer.locator('[data-pdf-role="toasts"] .toast')).toContainText('Text copied.');

        // What the site sent the service: every request signed, the clean storage link in the body
        const sent = await sentToService();
        expect(sent.every((r) => r.signature === 'ok')).toBe(true);
        expect(sent[0]).toMatchObject({ method: 'POST', path: '/v1/jobs' });
        expect(JSON.parse(sent[0].body)).toEqual({ url: STORAGE_PDF, page: 4 });
        expect(sent.length).toBeGreaterThan(1);
        expect(sent.slice(1).every((r) => r.method === 'GET' && /^\/v1\/jobs\/[0-9a-f]{32}$/.test(r.path))).toBe(true);
        const rows = savedRows();
        expect(rows).toHaveLength(1);
        expect(rows[0]).toMatchObject({ page: 4, status: 'done', asks: 1, pdf_url: STORAGE_PDF });

        // A visitor who isn't logged in sees the saved text, and the service isn't asked again
        await page.context().clearCookies();
        const again = await openText(page);
        await expect(again.dialog).toContainText('Machine-read text.');
        await expect(again.dialog).toContainText('Waterfront rules: every Scout swims with a buddy.');
        expect(await sentToService()).toHaveLength(sent.length);
    });

    test('a page the service had already read comes back at once', async ({ page }) => {
        await logIn(page);
        await setMode('instant');
        const { dialog } = await openText(page);
        await readButton(dialog).click();
        await expect(dialog).toContainText('Machine-read text.');
        expect((await sentToService()).map((r) => r.method)).toEqual(['POST']);
        expect(savedRows()[0]).toMatchObject({ status: 'done' });
    });

    test('a page with nothing readable says so', async ({ page }) => {
        await logIn(page);
        await setMode('empty');
        const { dialog } = await openText(page);
        await readButton(dialog).click();
        await expect(dialog).toContainText("No readable text was found on this page. Handwriting and very faded pages often can't be read.", { timeout: 15000 });
        expect(savedRows()[0]).toMatchObject({ status: 'empty' });
    });

    test('a failed read can be tried again, and every try counts', async ({ page }) => {
        await logIn(page);
        await setMode('fail');
        const { dialog } = await openText(page);
        await readButton(dialog).click();
        await expect(dialog).toContainText('Reading this page did not work last time. You can try again.', { timeout: 15000 });
        await setMode('fast');
        await readButton(dialog).click();
        await expect(dialog).toContainText('Machine-read text.', { timeout: 15000 });
        expect(savedRows()[0]).toMatchObject({ status: 'done', asks: 2 });
    });

    test('the service not answering, or busy, is explained and not counted', async ({ page }) => {
        await logIn(page);
        await setMode('down');
        const { viewer, dialog } = await openText(page);
        await readButton(dialog).click();
        await expect(dialog).toContainText('The reading service is not answering right now. Please try again later.', { timeout: 15000 });
        expect(savedRows()[0]).toMatchObject({ status: 'failed', asks: 0 });

        await setMode('busy');
        await closeDialog(viewer);
        await button(viewer, 'ocr').click();
        await expect(dialog).toContainText('Reading this page did not work last time. You can try again.');
        await readButton(dialog).click();
        await expect(dialog).toContainText('The reading service is busy right now. Please try again in an hour.', { timeout: 15000 });
        expect(savedRows()[0]).toMatchObject({ status: 'failed', asks: 0 });
    });

    test("today's limit stops more pages being read", async ({ page }) => {
        await logIn(page);
        ocrState('limits', '100', '1');
        try {
            const { viewer, dialog } = await openText(page, 4);
            await readButton(dialog).click();
            await expect(dialog).toContainText('Machine-read text.', { timeout: 15000 });
            await closeDialog(viewer);

            await goTo(viewer, 5);
            await button(viewer, 'ocr').click();
            await expect(dialog).toContainText('This page has not been read yet.');
            await readButton(dialog).click();
            await expect(dialog).toContainText("Today's limit for reading pages has been reached. Please try again tomorrow.");
            expect(savedRows()).toHaveLength(1);
            expect((await sentToService()).filter((r) => r.method === 'POST')).toHaveLength(1);
        } finally {
            ocrState('limits', '100', '20');
        }
    });

    test('a slow read says it is taking long after 2 minutes', async ({ page }) => {
        await logIn(page);
        await setMode('slow');
        const { dialog } = await openText(page);
        await expect(readButton(dialog)).toBeVisible(); // the first look for saved text is answered
        // The popup asks every 3 seconds, 40 times; run its clock instead of waiting 2 minutes
        await page.clock.install();
        let questions = 0;
        page.on('response', (r) => { if (isStatusQuestion(r)) questions++; });
        await readButton(dialog).click();
        await expect.poll(async () => {
            await page.clock.runFor(3000);
            return dialog.textContent();
        }, { timeout: 90000, intervals: [100] }).toContain('This is taking longer than usual. You can close this window. The text will be here when you come back.');
        expect(questions).toBe(40);
        await expect(dialog.locator('.spinner-border')).toHaveCount(0);
    });

    test('closing the Text window stops the asking', async ({ page }) => {
        await logIn(page);
        await setMode('slow');
        const { viewer, dialog } = await openText(page);
        await expect(readButton(dialog)).toBeVisible();
        await page.clock.install();
        // Questions sent (a question already on its way when the window closes may still be answered)
        let questions = 0;
        page.on('request', (r) => { if (r.method() === 'GET' && r.url().includes('/wp-json/scouting-pdf/v1/ocr/')) questions++; });
        await readButton(dialog).click();
        await expect.poll(async () => {
            await page.clock.runFor(3000);
            return questions;
        }, { timeout: 30000, intervals: [100] }).toBeGreaterThanOrEqual(2);
        await closeDialog(viewer);
        await page.waitForTimeout(500);
        const asked = questions;
        await page.clock.runFor(30000);
        await page.waitForTimeout(500);
        expect(questions).toBe(asked);
    });

    test('a page still waiting after 10 minutes can be asked for again', async ({ page }) => {
        await logIn(page);
        await setMode('slow');
        const { viewer, dialog } = await openText(page);
        await readButton(dialog).click();
        await expect.poll(() => savedRows().map((r) => r.status)).toEqual(['queued']);
        await closeDialog(viewer);

        ocrState('age', '11');
        await setMode('fast');
        await button(viewer, 'ocr').click();
        await expect(dialog).toContainText('Reading this page did not work last time. You can try again.');
        await readButton(dialog).click();
        await expect(dialog).toContainText('Machine-read text.', { timeout: 15000 });
        expect(savedRows()[0]).toMatchObject({ status: 'done', asks: 2 });
    });

    // The top bar with the Text button: every button whole, inside the bar, its label next to
    // its icon, and none on top of another, from a wide screen down to a small phone
    for (const [size, width] of [['lg', 1280], ['md', 992], ['sm', 768], ['xs', 576], ['xxs', 360]]) {
        test(`the top bar with Text still fits (${size}, ${width}px)`, async ({ page }) => {
            await page.setViewportSize({ width, height: 900 });
            const viewer = await openViewer(page, DOC);
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
            const layout = await viewer.evaluate((el) => {
                const bar = el.querySelector('[data-pdf-role="toolbar"]').getBoundingClientRect();
                return [...el.querySelectorAll('[data-pdf-role="toolbar"] button')].map((b) => {
                    const r = b.getBoundingClientRect();
                    return { name: b.getAttribute('data-pdf-control'), label: b.textContent.trim(), left: r.left, right: r.right, top: r.top, bottom: r.bottom, height: r.height, barLeft: bar.left, barRight: bar.right };
                });
            });
            expect(layout.map((b) => b.name)).toEqual(['sidebar', 'adjust', 'rotate', 'cite', 'ocr', 'spread', 'fullscreen']);
            for (const b of layout) {
                expect(b.label, b.name).not.toBe('');
                expect(b.left, b.name).toBeGreaterThanOrEqual(b.barLeft);
                expect(b.right, b.name).toBeLessThanOrEqual(b.barRight + 0.5);
                expect(b.height, b.name).toBeLessThan(50); // one line: the label never wraps under the icon
            }
            for (let i = 0; i < layout.length; i++) {
                for (let j = i + 1; j < layout.length; j++) {
                    const a = layout[i], b = layout[j];
                    const overlap = a.left < b.right - 0.5 && b.left < a.right - 0.5 && a.top < b.bottom - 0.5 && b.top < a.bottom - 0.5;
                    expect(overlap, `${a.name} overlaps ${b.name}`).toBe(false);
                }
            }
        });
    }
});

test('PHP checks: URL validation, host lists, citation data, escaping', () => {
    const out = execFileSync('php', [join(HERE, 'php-checks.php'), WP_DIR], { encoding: 'utf8' });
    expect(out).toContain('ALL PHP CHECKS PASSED');
});

test('citation formatting', async ({ page }) => {
    await openViewer(page, '/leaders-guide/');
    const result = await page.evaluate(() => {
        const info = {
            title: 'Troop 12 scrapbook', container: 'Troop 12 collection', date: 'Summer 1938', identifier: 'SM-1',
            publisher: '', location: '', site: 'Scouting Memories', url: 'https://scoutingmemories.org/x/#page=3', page: 'iv',
        };
        return window.ScoutingPdfViewer.buildCitations(info, new Date(2026, 8, 5));
    });
    expect(result.chicago.text).toBe('“Troop 12 scrapbook,” Summer 1938, p. iv. In Troop 12 collection. SM-1. Scouting Memories. https://scoutingmemories.org/x/#page=3 (accessed September 5, 2026).');
    expect(result.mla.text).toBe('“Troop 12 scrapbook.” Troop 12 collection, Scouting Memories, Summer 1938, p. iv, https://scoutingmemories.org/x/#page=3. Accessed 5 Sept. 2026.');
    expect(result.apa.text).toBe('Scouting Memories. (1938). Troop 12 scrapbook [Archival document] (p. iv). https://scoutingmemories.org/x/#page=3');
    expect(result.apa.html).toContain('<i>Troop 12 scrapbook</i>');
});
