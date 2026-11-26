// Browser tests for what the site does with the documents' text: the "Text of this document"
// section under the viewer, the site search finding words inside documents, the head tags
// for search engines, and the "Make document text available to the site" button.
import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const WP_DIR = process.env.WP_DIR || '/tmp/scouting-pdf-wp';
const GUIDE_BYTES = readFileSync(join(WP_DIR, 'wp-content/uploads/fixtures/leaders-guide.pdf'));
const DOC = '/ocr-document/';
const DASHBOARD = '/wp-admin/admin.php?page=scouting-pdf-text';

const textState = (...args) => execFileSync('php', [join(HERE, 'text-state.php'), WP_DIR, ...args], { encoding: 'utf8' });
const ocrState = (...args) => execFileSync('php', [join(HERE, 'ocr-state.php'), WP_DIR, ...args], { encoding: 'utf8' });
const copies = (path) => JSON.parse(textState('documents', path));

let problems = [];
test.beforeAll(() => {
    ocrState('on');
});
test.beforeEach(async ({ page }) => {
    problems = [];
    page.on('pageerror', (err) => problems.push('Page error: ' + err.message));
    textState('seed');
    // The document links to the real archive storage; the browser gets the local test file
    const pdf = (route) => route.fulfill({ body: GUIDE_BYTES, contentType: 'application/pdf', headers: { 'Access-Control-Allow-Origin': '*' } });
    await page.route(/^https:\/\/storage\.scoutingmemories\.org\//, pdf);
    await page.route(/\/wp-json\/scouting-pdf\/v1\/stream\?/, pdf);
});
test.afterAll(() => {
    textState('clear');
});

test('PHP checks: the text kept with posts, the search words, WordPress functions only', () => {
    const out = textState('checks');
    expect(out).toContain('ALL TEXT CHECKS PASSED');
});

test.describe('Text of this document', () => {
    const section = (page) => page.locator('[data-pdf-text]');
    const readText = (page) => page.locator('[data-pdf-viewer]').first().locator('[data-pdf-control="read-text"]');
    const pageRequests = (page) => {
        const seen = [];
        page.on('request', (r) => { if (r.url().includes('action=scouting_pdf_text_page')) seen.push(new URL(r.url()).searchParams.get('page')); });
        return seen;
    };
    async function ready(page, path = DOC) {
        await page.goto(path);
        const viewer = page.locator('[data-pdf-viewer]').first();
        await expect(viewer).toHaveAttribute('data-pdf-state', 'ready', { timeout: 20000 });
        return viewer;
    }
    const docKey = async (request) => /data-pdf-text-doc="([a-f0-9]{32})"/.exec(await (await request.get(DOC)).text())[1];

    test('the first page of text comes with the post, out of sight; the other pages do not come at all', async ({ request }) => {
        const html = await (await request.get(DOC)).text();
        expect(html).toContain('>Text of this document</h2>');
        expect(html).toContain('This text was read by a computer from the scanned pages and may contain mistakes. The scanned pages above are the original.');
        expect(html).toContain('Troop 12 hiked to Zephyrcrest Lookout in 1938.<br />');
        // Markup in the text is shown as text
        expect(html).toContain('the flag &amp; the &lt;b&gt;map&lt;/b&gt;.');
        expect(html).not.toContain('<b>map</b>');
        expect(html).not.toContain('every Scout swims with a buddy');
        expect(html).toMatch(/<div id="scouting-pdf-text-[a-f0-9]{32}" data-pdf-text[^>]* hidden>/);
        expect(html).toContain('assets/vendor/htmx/htmx.min.js?ver=2.0.11');
    });

    test('nothing shows until Read text is pressed; pressing it again puts the text away', async ({ page }) => {
        await ready(page);
        await expect(section(page)).toBeHidden();
        await expect(readText(page)).toHaveText('Read text');
        await expect(readText(page)).toHaveAttribute('aria-pressed', 'false');
        await readText(page).click();
        await expect(section(page).getByRole('heading', { level: 2, name: 'Text of this document' })).toBeVisible();
        await expect(section(page).getByRole('heading', { level: 3 })).toHaveText('Page 1');
        await expect(section(page).locator('[data-pdf-text-body]')).toContainText('The Quillfeather patrol carried the flag');
        await expect(readText(page)).toHaveAttribute('aria-pressed', 'true');
        await readText(page).click();
        await expect(section(page)).toBeHidden();
        await expect(readText(page)).toHaveAttribute('aria-pressed', 'false');
        expect(problems).toEqual([]);
    });

    test('one page at a time: Next page and Previous page fetch just that page', async ({ page }) => {
        const fetched = pageRequests(page);
        await ready(page);
        await readText(page).click();
        const area = section(page).locator('[data-pdf-text-area]');
        await expect(area).toContainText('Text page 1 of 3');
        await expect(area.getByRole('link', { name: 'Previous page' })).toHaveCount(0);
        expect(fetched).toEqual([]); // the first page came with the post

        await area.getByRole('link', { name: 'Next page' }).click();
        await expect(area.getByRole('heading', { level: 3 })).toHaveText('Page 2');
        await expect(area).toContainText('No text could be read from this page.');
        await expect(area).toContainText('Text page 2 of 3');

        // Page 3 has not been read, so the next page of text is page 4
        await area.getByRole('link', { name: 'Next page' }).click();
        await expect(area.getByRole('heading', { level: 3 })).toHaveText('Page 4');
        await expect(area.locator('[data-pdf-text-body]')).toContainText('every Scout swims with a buddy');
        await expect(area.getByRole('link', { name: 'Next page' })).toHaveCount(0);

        await area.getByRole('link', { name: 'Previous page' }).click();
        await expect(area.getByRole('heading', { level: 3 })).toHaveText('Page 2');
        expect(fetched).toEqual(['2', '4', '2']);
        await expect(page).toHaveURL(/\/ocr-document\/$/); // the address stays the post's
        expect(problems).toEqual([]);
    });

    test('Read text opens at the page the reader is on', async ({ page }) => {
        const fetched = pageRequests(page);
        const viewer = await ready(page);
        await viewer.evaluate((el) => el.scoutingPdf.goToPage(4));
        await expect.poll(() => viewer.evaluate((el) => el.scoutingPdf.currentPage())).toBe(4);
        await readText(page).click();
        await expect(section(page).getByRole('heading', { level: 3 })).toHaveText('Page 4');
        expect(fetched).toEqual(['4']);
        // On a page that has not been read, the next read page is shown
        await readText(page).click();
        await viewer.evaluate((el) => el.scoutingPdf.goToPage(3));
        await expect.poll(() => viewer.evaluate((el) => el.scoutingPdf.currentPage())).toBe(3);
        await readText(page).click();
        await expect(section(page).getByRole('heading', { level: 3 })).toHaveText('Page 4');
        expect(problems).toEqual([]);
    });

    test('Show this page in the viewer opens that page', async ({ page }) => {
        const viewer = await ready(page);
        await readText(page).click();
        const area = section(page).locator('[data-pdf-text-area]');
        await area.getByRole('link', { name: 'Next page' }).click();
        await area.getByRole('link', { name: 'Next page' }).click();
        await expect(area.getByRole('heading', { level: 3 })).toHaveText('Page 4');
        await area.getByRole('link', { name: 'Show this page in the viewer' }).click();
        await expect(page).toHaveURL(/#page=4$/);
        await expect.poll(() => viewer.evaluate((el) => el.scoutingPdf.currentPage())).toBe(4);
        expect(problems).toEqual([]);
    });

    test('the page links are real links: a page of text opens without scripts too', async ({ request }) => {
        const key = await docKey(request);
        const first = await (await request.get(DOC)).text();
        expect(first).toContain(`href="http://localhost:8888/ocr-document/?pdf_text=${key}-2#scouting-pdf-text-${key}"`);
        const fourth = await (await request.get(`${DOC}?pdf_text=${key}-4`)).text();
        expect(fourth).toContain('every Scout swims with a buddy');
        expect(fourth).toContain('<div data-pdf-text-page="4">');
        expect(fourth).not.toContain('<div data-pdf-text-page="1">');
        expect(fourth).not.toMatch(/data-pdf-text[^>]* hidden>/); // opened by its link, so it shows
    });

    test('the request for a page hands out one page of a published post, and nothing else', async ({ request }) => {
        const key = await docKey(request);
        const postId = ocrState('post-id', DOC).trim();
        const url = (doc, n, post = postId) => `/wp-admin/admin-ajax.php?action=scouting_pdf_text_page&post=${post}&doc=${doc}&page=${n}`;
        const good = await request.get(url(key, 4));
        expect(good.status()).toBe(200);
        const piece = await good.text();
        expect(piece).toContain('<div data-pdf-text-page="4">');
        expect(piece).toContain('every Scout swims with a buddy');
        expect(piece).not.toContain('<html');
        expect(piece).not.toContain('Troop 12 hiked');
        expect((await request.get(url('0'.repeat(32), 1))).status()).toBe(404);
        expect((await request.get(url(key, 1, ocrState('post-id', '/leaders-guide/').trim()))).status()).toBe(404);
        expect((await request.get(url(key, 1, '999999'))).status()).toBe(404);
        // A page number past the end gives the last read page, never an error page
        expect(await (await request.get(url(key, 9999))).text()).toContain('<div data-pdf-text-page="4">');
    });

    test('a reviewer\'s correction reaches the page', async ({ request }) => {
        textState('correct', '1', 'Troop 12 hiked to Zephyrcrest Lookout in 1939.');
        const html = await (await request.get(DOC)).text();
        expect(html).toContain('Zephyrcrest Lookout in 1939.');
        expect(html).not.toContain('in 1938.');
    });

    test('posts without read documents have no section and no Read text button', async ({ page, request }) => {
        const other = await (await request.get('/leaders-guide/')).text();
        expect(other).not.toContain('data-pdf-text');
        expect(other).not.toContain('data-pdf-control="read-text"');
        expect(await (await request.get('/')).text()).not.toContain('Text of this document');
        textState('clear');
        await ready(page);
        await expect(section(page)).toHaveCount(0);
        await expect(readText(page)).toHaveCount(0);
    });

    // The top bar with Read text beside Text: every button whole, inside the bar, on one line
    for (const [size, width] of [['lg', 1280], ['sm', 768], ['xxs', 360]]) {
        test(`the top bar with Read text still fits (${size}, ${width}px)`, async ({ page }) => {
            await page.setViewportSize({ width, height: 900 });
            const viewer = await ready(page);
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
            const layout = await viewer.evaluate((el) => {
                const bar = el.querySelector('[data-pdf-role="toolbar"]').getBoundingClientRect();
                return [...el.querySelectorAll('[data-pdf-role="toolbar"] button')].map((b) => {
                    const r = b.getBoundingClientRect();
                    return { name: b.getAttribute('data-pdf-control'), left: r.left, right: r.right, top: r.top, bottom: r.bottom, height: r.height, barLeft: bar.left, barRight: bar.right };
                });
            });
            expect(layout.map((b) => b.name)).toEqual(['sidebar', 'adjust', 'rotate', 'cite', 'ocr', 'read-text', 'spread', 'fullscreen']);
            for (const b of layout) {
                expect(b.left, b.name).toBeGreaterThanOrEqual(b.barLeft);
                expect(b.right, b.name).toBeLessThanOrEqual(b.barRight + 0.5);
                expect(b.height, b.name).toBeLessThan(50);
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

test.describe('site search', () => {
    // The stock test themes are block themes; the live theme is a classic one, whose search
    // results are a card per post with no excerpt. These tests run on a theme like it.
    test.beforeAll(() => {
        textState('theme', 'classic');
    });
    test.afterAll(() => {
        textState('theme', 'default');
    });

    test('a word that is only inside a document finds its post, with the page and the line', async ({ page }) => {
        await page.goto('/?s=Quillfeather');
        await expect(page.getByRole('link', { name: 'Camp Tahquitz Leaders Guide (OCR)' }).first()).toBeVisible();
        const hit = page.locator('[data-pdf-search-hit]');
        await expect(hit).toHaveCount(1);
        await expect(hit).toContainText('Found in the document text: page 1 — ');
        await expect(hit.locator('mark')).toHaveText('Quillfeather');
        // Markup in the text stays text in the line too
        expect(await hit.innerHTML()).toContain('&lt;b&gt;map&lt;/b&gt;');
        await expect(hit.getByRole('link', { name: 'page 1' })).toHaveAttribute('href', /\/ocr-document\/#page=1&find=quillfeather$/);
    });

    test('every page with the word is named; the link opens that page with the word marked', async ({ page }) => {
        await page.goto('/?s=zephyrcrest');
        const hit = page.locator('[data-pdf-search-hit]');
        await expect(hit.locator('a')).toHaveText(['page 1', 'page 4']);
        await hit.getByRole('link', { name: 'page 4' }).click();
        await expect(page).toHaveURL(/\/ocr-document\/#page=4&find=zephyrcrest$/);
        // The text section opens by itself, on that page, with the word marked
        const fourth = page.locator('[data-pdf-text] [data-pdf-text-page="4"]');
        await expect(fourth).toBeVisible();
        await expect(fourth.locator('mark')).toHaveText('Zephyrcrest');
        await expect(page.locator('[data-pdf-control="read-text"]')).toHaveAttribute('aria-pressed', 'true');
        const viewer = page.locator('[data-pdf-viewer]').first();
        await expect(viewer).toHaveAttribute('data-pdf-state', 'ready', { timeout: 20000 });
        await expect.poll(() => viewer.evaluate((el) => el.scoutingPdf.currentPage())).toBe(4);
        expect(problems).toEqual([]);
    });

    test('all the words must be in the document; several words are all marked', async ({ page }) => {
        await page.goto('/?s=otter+relay');
        const hit = page.locator('[data-pdf-search-hit]');
        await expect(hit.locator('a')).toHaveText(['page 4']);
        await expect(hit.locator('mark')).toHaveText(['relay', 'Otter']);
        await page.goto('/?s=otter+nosuchwordanywhere');
        await expect(page.locator('[data-pdf-search-hit]')).toHaveCount(0);
    });

    test('ordinary searches are WordPress\'s own: same results, no line', async ({ page }) => {
        await page.goto('/?s=Tahquitz');
        await expect(page.getByRole('link', { name: 'Camp Tahquitz Leaders Guide, 1952' }).first()).toBeVisible();
        await expect(page.getByRole('link', { name: 'Camp Tahquitz Leaders Guide (OCR)' }).first()).toBeVisible();
        await expect(page.locator('[data-pdf-search-hit]')).toHaveCount(0);
        await page.goto('/?s=nosuchwordanywhere');
        await expect(page.locator('[data-pdf-search-hit]')).toHaveCount(0);
        await expect(page.getByRole('link', { name: 'Camp Tahquitz Leaders Guide (OCR)' })).toHaveCount(0);
    });
});

test.describe('search engines', () => {
    test('a post with a document describes itself as an archive item', async ({ request }) => {
        const html = await (await request.get('/leaders-guide/')).text();
        const found = /<script type="application\/ld\+json">(.*?)<\/script>/s.exec(html);
        expect(found).not.toBeNull();
        const item = JSON.parse(found[1]);
        expect(item['@context']).toBe('https://schema.org');
        expect(item['@type']).toBe('ArchiveComponent');
        expect(item.name).toBe('Camp Tahquitz Leaders Guide, 1952');
        expect(item.url).toBe('http://localhost:8888/leaders-guide/');
        expect(item.dateCreated).toBe('June 1952');
        expect(item.holdingArchive).toEqual({ '@type': 'ArchiveOrganization', name: 'Scouting Memories', url: 'http://localhost:8888/' });
    });

    test('the description comes from the document\'s text when the post has no summary', async ({ request }) => {
        const html = await (await request.get(DOC)).text();
        expect(html).toContain('<meta name="description" content="Troop 12 hiked to Zephyrcrest Lookout in 1938. The Quillfeather patrol carried the flag &amp; the map.">');
        const item = JSON.parse(/<script type="application\/ld\+json">(.*?)<\/script>/s.exec(html)[1]);
        expect(item.description).toBe('Troop 12 hiked to Zephyrcrest Lookout in 1938. The Quillfeather patrol carried the flag & the map.');
        // Nothing in the text can end the script tag
        expect(found(html, '</script>')).toBe(found(html, '<script'));
    });

    test('a page of text is a page of its own for search engines: its own address, title and description', async ({ request }) => {
        const key = /data-pdf-text-doc="([a-f0-9]{32})"/.exec(await (await request.get(DOC)).text())[1];
        const fourth = await (await request.get(`${DOC}?pdf_text=${key}-4`)).text();
        expect(fourth).toContain(`<link rel="canonical" href="http://localhost:8888/ocr-document/?pdf_text=${key}-4" />`);
        expect(fourth).toMatch(/<title>Camp Tahquitz Leaders Guide \(OCR\) \(text of page 4\)/);
        expect(fourth).toContain('<meta name="description" content="Waterfront rules at Zephyrcrest: every Scout swims with a buddy. The relay was won by the Otter patrol.">');
        // The post itself, and a link to its first page of text, are one page: the post
        for (const path of [DOC, `${DOC}?pdf_text=${key}-1`]) {
            const html = await (await request.get(path)).text();
            expect(html).toContain('<link rel="canonical" href="http://localhost:8888/ocr-document/" />');
            expect(html).not.toContain('(text of page');
        }
        // An address for a document this post does not have changes nothing
        const other = await (await request.get(`${DOC}?pdf_text=${'0'.repeat(32)}-4`)).text();
        expect(other).toContain('<link rel="canonical" href="http://localhost:8888/ocr-document/" />');
    });

    test('pages without a document get neither', async ({ request }) => {
        const html = await (await request.get('/')).text();
        expect(html).not.toContain('application/ld+json');
    });
});

function found(text, piece) {
    return text.split(piece).length - 1;
}

test.describe('Make document text available to the site', () => {
    async function logIn(page, user, pass, landing) {
        await page.goto('/wp-login.php?redirect_to=' + encodeURIComponent('http://localhost:8888' + landing));
        await page.locator('#user_login').fill(user);
        await page.locator('#user_pass').fill(pass);
        await page.locator('#wp-submit').click();
        await page.waitForURL('http://localhost:8888' + landing);
    }

    test('the button brings back every post\'s copy and says when it is done', async ({ page }) => {
        textState('drop-copies');
        expect(copies(DOC)).toEqual([]);
        await logIn(page, 'admin', 'admin-pass-123', DASHBOARD);
        const button = page.getByRole('button', { name: 'Make document text available to the site' });
        await expect(button).toBeVisible();
        await button.click();
        await expect(page.locator('#scouting_pdf_text_sync-status')).toContainText('All document text is available to the site.', { timeout: 30000 });
        await expect(page.locator('#scouting_pdf_text_sync-status')).toContainText('1 of ');
        await expect(button).toBeEnabled();
        const copy = Object.values(copies(DOC))[0];
        expect(Object.keys(copy)).toEqual(['1', '2', '4']);
        expect(copy['4']).toContain('every Scout swims with a buddy');
    });

    test('only administrators get the button, and the request behind it', async ({ page }) => {
        await logIn(page, 'reader', 'reader-pass-123', '/');
        const answer = await page.evaluate(async () => {
            const body = new URLSearchParams({ action: 'scouting_pdf_text_sync', page: '1', _ajax_nonce: 'nope' });
            const r = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin', body });
            return r.status;
        });
        expect(answer).toBe(403);
    });
});

test.describe('Tag suggestions', () => {
    const SCREEN = '/wp-admin/admin.php?page=scouting-pdf-tag-suggestions';
    const suggestions = () => JSON.parse(textState('suggestions', DOC));
    const terms = () => JSON.parse(textState('terms', DOC));

    async function logIn(page, user, pass, landing) {
        await page.goto('/wp-login.php?redirect_to=' + encodeURIComponent('http://localhost:8888' + landing));
        await page.locator('#user_login').fill(user);
        await page.locator('#user_pass').fill(pass);
        await page.locator('#wp-submit').click();
        await page.waitForURL('http://localhost:8888' + landing);
    }

    async function find(page) {
        await page.getByRole('button', { name: 'Find tag suggestions for all documents' }).click();
        await expect(page.locator('#scouting_pdf_find_suggestions-status')).toContainText('Finished.', { timeout: 30000 });
    }

    test('finding, adding a tag, and putting a suggestion away', async ({ page }) => {
        await logIn(page, 'admin', 'admin-pass-123', SCREEN);
        await expect(page.getByRole('heading', { level: 1, name: 'Tag suggestions' })).toBeVisible();
        await expect(page.locator('tbody')).toContainText('No suggestions are waiting.');

        await find(page);
        await expect(page.locator('#scouting_pdf_find_suggestions-status')).toHaveText('Finished. 4 new suggestions. Reload this page to see them.');
        // Finding tags nothing
        expect(terms()).toEqual({ camp: [], lodge: [], council: [], state: [], start_date: [], end_date: [] });

        await page.reload();
        const rows = page.locator('tbody tr');
        await expect(rows).toHaveCount(4);
        const camp = rows.filter({ hasText: 'Zephyrcrest Lookout end date uncertain' });
        await expect(camp.locator('td').nth(0)).toHaveText('Camp Tahquitz Leaders Guide (OCR)');
        await expect(camp.locator('td').nth(1)).toHaveText('Camp');
        await expect(camp.locator('td').nth(4)).toHaveText('Troop 12 hiked to Zephyrcrest Lookout in 1938. The Quillfeather patrol carried the flag & the <b>map</b>.');
        await expect(camp.getByRole('link', { name: 'Page 1' })).toHaveAttribute('href', /\/ocr-document\/#page=1&find=Zephyrcrest%20Lookout$/);

        // Add tag: the post gets the term, the row leaves the list
        await camp.getByRole('button', { name: /^Add tag/ }).click();
        await expect(page.locator('.notice-success')).toContainText('The tag was added to the post.');
        await expect(page.locator('tbody tr')).toHaveCount(3);
        expect(terms().camp).toEqual(['Zephyrcrest_Lookout__end_date_uncertain__9001']);

        // Not right: nothing is tagged, and finding again does not bring it back
        const lodge = page.locator('tbody tr').filter({ hasText: 'Quillfeather' }).filter({ hasText: 'Lodge' });
        await lodge.getByRole('button', { name: /^Not right/ }).click();
        await expect(page.locator('.notice-success')).toContainText('The suggestion was put away. It will not be suggested again.');
        await expect(page.locator('tbody tr')).toHaveCount(2);
        expect(terms().lodge).toEqual([]);
        await find(page);
        await expect(page.locator('#scouting_pdf_find_suggestions-status')).toHaveText('Finished. 0 new suggestions. Reload this page to see them.');

        // A year becomes a term of its own
        const start = page.locator('tbody tr').filter({ hasText: 'Start date' });
        await start.getByRole('button', { name: /^Add tag/ }).click();
        await expect(page.locator('.notice-success')).toBeVisible();
        expect(terms().start_date).toEqual(['1938']);
        expect(suggestions().map((s) => s.status).sort()).toEqual(['added', 'added', 'rejected', 'waiting']);
    });

    test('readers get neither the screen nor the requests behind it', async ({ page }) => {
        await logIn(page, 'admin', 'admin-pass-123', SCREEN);
        await find(page);
        const waiting = suggestions().find((s) => s.status === 'waiting');
        await page.goto('/wp-login.php?action=logout');
        await page.getByRole('link', { name: 'log out' }).click();

        await logIn(page, 'reader', 'reader-pass-123', '/');
        const screen = await page.goto(SCREEN);
        expect(screen.status()).toBe(403);
        const answers = await page.evaluate(async (id) => {
            const post = (fields) => fetch(fields.url, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams(fields) }).then((r) => r.status);
            return {
                find: await post({ url: '/wp-admin/admin-ajax.php', action: 'scouting_pdf_find_suggestions', page: '1', _ajax_nonce: 'nope' }),
                decide: await post({ url: '/wp-admin/admin-post.php', action: 'scouting_pdf_suggestion', post_id: '1', suggestion: id, decision: 'add', _wpnonce: 'nope' }),
            };
        }, waiting.id);
        expect(answers).toEqual({ find: 403, decide: 403 });
        expect(suggestions().find((s) => s.id === waiting.id).status).toBe('waiting');
    });
});
