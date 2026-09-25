// End-to-end tests for every viewer feature, run against the test WordPress from
// setup-wordpress.sh (npm run setup), with the real plugin code and PDF.js build.
import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
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
    page.on('pageerror', (err) => problems.push('Page error: ' + err.message));
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

async function menu(viewer, item) {
    await button(viewer, 'more').click();
    await viewer.locator(`[data-pdf-role="menu"] [data-pdf-control="${item}"]`).click();
}

function pngSize(buf) {
    expect(buf.subarray(1, 4).toString()).toBe('PNG');
    return { width: buf.readUInt32BE(16), height: buf.readUInt32BE(20) };
}

test.describe('loading', () => {
    test('opens with the bundled PDF.js 6.3 in a worker and renders pages with a text layer', async ({ page }) => {
        const scripts = [];
        page.on('request', (r) => scripts.push(r.url()));
        const viewer = await openViewer(page, '/leaders-guide/');
        expect(scripts.some((u) => /vendor\/pdfjs\/pdf\.min\.js\?ver=6\.3\.289/.test(u))).toBe(true);
        expect(scripts.some((u) => /vendor\/pdfjs\/pdf\.worker\.min\.js/.test(u))).toBe(true);
        expect(scripts.some((u) => /vendor\/pdf\.(min\.)?js/.test(u))).toBe(false); // old 2.0 build is gone
        await expect(viewer.locator('[data-pdf-page]')).toHaveCount(12);
        await expect(viewer.locator('[data-pdf-page="1"] canvas')).toHaveCount(1);
        await expect(viewer.locator('[data-pdf-page="1"] .textLayer span').first()).toHaveText('Title page');
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

    test('Copy link to this page copies a link that reopens the same page', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await goTo(viewer, 7);
        await menu(viewer, 'copy-link');
        const link = await page.evaluate(() => navigator.clipboard.readText());
        expect(link).toBe('http://localhost:8888/leaders-guide/#page=7');
        const reopened = await openViewer(page, link);
        await expect.poll(() => currentPage(reopened)).toBe(7);
    });

    test('offers to continue where the reader left off', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await goTo(viewer, 7);
        await page.waitForTimeout(1200);
        const again = await openViewer(page, '/leaders-guide/');
        const toast = again.locator('[data-pdf-role="toasts"] .toast');
        await expect(toast).toContainText('You were reading p. 4 (PDF page 7)');
        await toast.getByRole('button', { name: 'Continue there' }).click();
        await expect.poll(() => currentPage(again)).toBe(7);
    });
});

test.describe('citations', () => {
    test('Cite gives Chicago, MLA and APA for the current page from the archive fields', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await goTo(viewer, 6);
        await button(viewer, 'cite').click();
        const dialog = viewer.locator('[data-pdf-role="dialog"]');
        await expect(dialog).toBeVisible();
        await expect(dialog).toContainText('Citing p. 3 (PDF page 6)');
        const chicago = dialog.locator('[data-pdf-citation="chicago"]');
        await expect(chicago).toContainText('“Camp Tahquitz Leaders Guide, 1952,” June 1952, p. 3.');
        await expect(chicago).toContainText('SM-TEST-0042.');
        await expect(chicago).toContainText('Digitized by Test Council Archives.');
        await expect(chicago).toContainText('http://localhost:8888/leaders-guide/#page=6 (accessed');
        await expect(dialog.locator('[data-pdf-citation="mla"]')).toContainText('SM Test, June 1952, p. 3, http://localhost:8888/leaders-guide/#page=6. Accessed');
        await expect(dialog.locator('[data-pdf-citation="apa"]')).toContainText('Test Council Archives. (1952). Camp Tahquitz Leaders Guide, 1952 [Archival document] (p. 3).');
        await expect(dialog.locator('[data-pdf-citation="apa"] i')).toHaveText('Camp Tahquitz Leaders Guide, 1952');

        // Without the page: no page number, link to the post
        await dialog.getByLabel('Include the page number').uncheck();
        await expect(chicago).not.toContainText('p. 3');
        await expect(chicago).not.toContainText('#page=');

        await dialog.getByLabel('Include the page number').check();
        await dialog.getByRole('button', { name: 'Copy MLA citation' }).click();
        await expect.poll(() => page.evaluate(() => navigator.clipboard.readText())).toContain('“Camp Tahquitz Leaders Guide, 1952.” SM Test, June 1952, p. 3');
    });

    test('RIS download for Zotero and EndNote', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await goTo(viewer, 4);
        await button(viewer, 'cite').click();
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

test.describe('download and print', () => {
    test('Download saves the original PDF', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        const [download] = await Promise.all([page.waitForEvent('download'), button(viewer, 'download').click()]);
        expect(download.suggestedFilename()).toBe('leaders-guide.pdf');
        expect(readFileSync(await download.path()).equals(GUIDE_BYTES)).toBe(true);
    });

    test('download="no" hides Download and Print', async ({ page }) => {
        const second = await openViewer(page, '/two-documents/', 1);
        await expect(button(second, 'download')).toBeHidden();
        await button(second, 'more').click();
        await expect(second.locator('[data-pdf-role="menu"] [data-pdf-control="print"]')).toBeHidden();
    });

    test('Print prints just the chosen pages, then cleans up', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await page.evaluate(() => {
            window.print = () => {
                const holder = document.getElementById('scouting-pdf-print');
                window.__printed = {
                    pages: [...holder.querySelectorAll('img')].map((i) => i.getAttribute('data-pdf-print-page')),
                    loaded: [...holder.querySelectorAll('img')].every((i) => i.naturalWidth > 1000),
                    printing: document.body.classList.contains('scouting-pdf-printing'),
                };
            };
        });
        await menu(viewer, 'print');
        const dialog = viewer.locator('[data-pdf-role="dialog"]');
        await dialog.getByLabel('PDF pages to print').fill('2-3, 5');
        await dialog.getByRole('button', { name: 'Print' }).click();
        await expect.poll(() => page.evaluate(() => window.__printed)).toEqual({ pages: ['2', '3', '5'], loaded: true, printing: true });

        // In print media only the page images show
        await page.emulateMedia({ media: 'print' });
        await expect(page.locator('#scouting-pdf-print')).toBeVisible();
        await expect(viewer).toBeHidden();
        await page.emulateMedia({ media: 'screen' });

        await page.evaluate(() => window.dispatchEvent(new Event('afterprint')));
        await expect(page.locator('#scouting-pdf-print')).toHaveCount(0);
        expect(await page.evaluate(() => document.body.classList.contains('scouting-pdf-printing'))).toBe(false);
    });
});

test.describe('search', () => {
    test('finds every match, case- and accent-insensitive, and steps through them', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await viewer.locator('[data-pdf-role="viewport"]').click();
        await page.keyboard.press('Control+f');
        const input = button(viewer, 'find-input');
        await expect(input).toBeFocused();
        await input.fill('order of the arrow');
        const status = viewer.locator('[data-pdf-role="find-status"]');
        await expect(status).toHaveText('1 of 3 matches · p. 1 (PDF page 4)');
        await expect.poll(() => currentPage(viewer)).toBe(4);
        await expect(viewer.locator('[data-pdf-page="4"] .highlight.selected')).toHaveText(['Order of the Arrow']);

        await input.press('Enter');
        await expect(status).toHaveText('2 of 3 matches · p. 3 (PDF page 6)');
        await expect.poll(() => currentPage(viewer)).toBe(6);
        await expect(viewer.locator('[data-pdf-page="6"] .highlight.selected')).toHaveText(['Order of the Arrow']);

        await input.press('Enter');
        await expect(status).toHaveText('3 of 3 matches · p. 8 (PDF page 11)');
        await expect(viewer.locator('[data-pdf-page="11"] .highlight.selected')).toHaveText(['ORDER OF THE ARROW']);

        await input.press('Shift+Enter');
        await expect(status).toHaveText('2 of 3 matches · p. 3 (PDF page 6)');

        await input.fill('tahquítz');
        await expect(status).toHaveText('1 of 1 matches · p. 1 (PDF page 4)');

        // Across a line break
        await input.fill('the lake. the order');
        await expect(status).toHaveText('1 of 1 matches · p. 1 (PDF page 4)');
        await expect(viewer.locator('[data-pdf-page="4"] .highlight.selected')).toHaveText(['the lake.', 'The Order']);

        await input.fill('zebra');
        await expect(status).toHaveText('No matches');
        await expect(viewer.locator('.highlight')).toHaveCount(0);

        await input.press('Escape');
        await expect(viewer.locator('[data-pdf-role="findbar"]')).toBeHidden();
    });

    test('lists all matches with their page and context', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await button(viewer, 'search').click();
        await button(viewer, 'find-input').fill('order of the arrow');
        await expect(viewer.locator('[data-pdf-role="find-status"]')).toContainText('of 3 matches');
        await button(viewer, 'find-list').click();
        const results = viewer.locator('[data-pdf-panel="results"] [data-pdf-result]');
        await expect(results).toHaveCount(3);
        await expect(results.nth(0)).toContainText('p. 1 (PDF page 4)');
        await expect(results.nth(0).locator('mark')).toHaveText('Order of the Arrow');
        await expect(results.nth(0)).toContainText('shore of the lake. The');
        await results.nth(2).click();
        await expect.poll(() => currentPage(viewer)).toBe(11);
        await expect(results.nth(2)).toHaveClass(/active/);
    });

    test('explains when a scan has no searchable text', async ({ page }) => {
        const viewer = await openViewer(page, '/two-documents/', 0);
        await button(viewer, 'search').click();
        await button(viewer, 'find-input').fill('camp');
        await expect(viewer.locator('[data-pdf-role="find-status"]')).toContainText('no searchable text');
    });
});

test.describe('text selection', () => {
    test('Select text mode lets readers drag to select and copy a quote', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await goTo(viewer, 4);
        await button(viewer, 'text-select').click();
        await expect(button(viewer, 'text-select')).toHaveAttribute('aria-pressed', 'true');
        const line = viewer.locator('[data-pdf-page="4"] .textLayer span', { hasText: 'Camp Tahquitz opened' });
        await line.scrollIntoViewIfNeeded();
        const box = await line.boundingBox();
        await page.mouse.move(box.x + 2, box.y + box.height / 2);
        await page.mouse.down();
        await page.mouse.move(box.x + box.width - 2, box.y + box.height / 2, { steps: 8 });
        await page.mouse.up();
        const selected = await page.evaluate(() => window.getSelection().toString());
        expect(selected).toContain('Camp Tahquitz opened in 1952');
    });

    test('hand mode (the default) drags the page instead of selecting', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await expect(button(viewer, 'text-select')).toHaveAttribute('aria-pressed', 'false');
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
        await menu(viewer, 'info');
        const info = viewer.locator('[data-pdf-panel="info"]');
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

    test('Save an area as a picture makes a high-resolution PNG with the citation underneath', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await goTo(viewer, 4);
        await menu(viewer, 'snapshot');
        const pageEl = viewer.locator('[data-pdf-page="4"]');
        const box = await pageEl.boundingBox();
        const scale = box.width / 612;
        const [download] = await Promise.all([
            page.waitForEvent('download'),
            (async () => {
                await page.mouse.move(box.x + 40, box.y + 40);
                await page.mouse.down();
                await page.mouse.move(box.x + 290, box.y + 190, { steps: 6 });
                await page.mouse.up();
            })(),
        ]);
        expect(download.suggestedFilename()).toBe('leaders-guide-p1.png');
        const size = pngSize(readFileSync(await download.path()));
        const factor = (300 / 72) / scale;
        expect(Math.abs(size.width - 250 * factor)).toBeLessThan(4);
        expect(size.height).toBeGreaterThan(150 * factor + 30); // room for the caption
        await expect(viewer).not.toHaveClass(/is-snapshot/);
    });

    test('Two-page view shows spreads (cover alone) and pages through a spread at a time', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await menu(viewer, 'spread');
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
        await menu(viewer, 'spread');
        await expect(rows).toHaveCount(0);
        await expect.poll(() => currentPage(viewer)).toBe(4);
    });

    test('Rotate turns pages and their text layer', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await viewer.locator('[data-pdf-role="viewport"]').click();
        await page.keyboard.press('r');
        const p1 = viewer.locator('[data-pdf-page="1"]');
        await expect.poll(() => p1.evaluate((e) => e.offsetWidth > e.offsetHeight)).toBe(true);
        await expect(p1.locator('.textLayer')).toHaveAttribute('data-main-rotation', '90');
    });

    test('fullscreen fills the screen, sidebar included', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await button(viewer, 'sidebar').click();
        await button(viewer, 'fullscreen').click();
        await expect.poll(() => page.evaluate(() => document.fullscreenElement && document.fullscreenElement.id)).toBe('scouting-pdf-1');
        const sizes = await viewer.evaluate((el) => ({
            viewer: el.getBoundingClientRect().height,
            body: el.querySelector('[data-pdf-role="body"]').getBoundingClientRect().bottom,
            sidebar: el.querySelector('[data-pdf-role="sidebar"]').getBoundingClientRect().bottom,
            screen: window.innerHeight,
        }));
        expect(sizes.viewer).toBe(sizes.screen);
        expect(Math.abs(sizes.body - sizes.screen)).toBeLessThan(2);
        expect(Math.abs(sizes.sidebar - sizes.screen)).toBeLessThan(2);
        await button(viewer, 'fullscreen').click();
        await expect.poll(() => page.evaluate(() => !!document.fullscreenElement)).toBe(false);
    });

    test('zoom buttons and fit modes still work', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        const level = button(viewer, 'zoom-level');
        const before = parseInt(await level.textContent(), 10);
        await button(viewer, 'zoom-in').click();
        await expect.poll(async () => parseInt(await level.textContent(), 10)).toBe(Math.round(before * 1.25));
        await expect(button(viewer, 'zoom-fit')).toHaveAttribute('aria-pressed', 'false');
        await button(viewer, 'zoom-page').click();
        await expect(button(viewer, 'zoom-page')).toHaveAttribute('aria-pressed', 'true');
    });
});

test.describe('menu', () => {
    test('More menu works from the keyboard and closes on Escape', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        const more = button(viewer, 'more');
        await more.click();
        const menuEl = viewer.locator('[data-pdf-role="menu"]');
        await expect(menuEl).toBeVisible();
        await expect(more).toHaveAttribute('aria-expanded', 'true');
        await expect(menuEl.locator('[data-pdf-control="copy-link"]')).toBeFocused();
        await page.keyboard.press('ArrowDown');
        await expect(menuEl.locator('[data-pdf-control="comment-page"]')).toBeFocused();
        await page.keyboard.press('ArrowUp');
        await page.keyboard.press('ArrowUp');
        await expect(menuEl.locator('[data-pdf-control="info"]')).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(menuEl).toBeHidden();
        await expect(more).toBeFocused();
        await more.click();
        await page.mouse.click(5, 5);
        await expect(menuEl).toBeHidden();
    });

    test('hints never block the buttons under them', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await button(viewer, 'sidebar').click();
        await page.waitForTimeout(400); // longer than the hint delay
        await button(viewer, 'search').click({ timeout: 3000 });
        await expect(viewer.locator('[data-pdf-role="findbar"]')).toBeVisible();
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

    test('Comment on this page starts a comment tagged with the printed page', async ({ page }) => {
        const viewer = await openViewer(page, '/leaders-guide/');
        await goTo(viewer, 5);
        await menu(viewer, 'comment-page');
        const box = page.locator('#commentform textarea[name="comment"]');
        await expect(box).toBeFocused();
        await expect(box).toHaveValue('[p. 2] ');
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
        await button(viewer, 'cite').click();
        await expect(viewer.locator('[data-pdf-citation="chicago"]')).toContainText('<img src=x onerror=alert(1)>');
        await expect(viewer.locator('[data-pdf-role="dialog"] img')).toHaveCount(0);
        await viewer.locator('[data-pdf-control="dialog-close"]').click();
        await menu(viewer, 'info');
        await expect(viewer.locator('[data-pdf-panel="info"]')).toContainText('<b>bold</b>');
        await expect(viewer.locator('[data-pdf-panel="info"] b')).toHaveCount(0);
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
