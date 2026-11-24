// Browser tests for the PDF Text dashboard (wp-admin > PDF Text) and the "PDF text" box on
// the post edit screen, against the test WordPress and the stand-in OCR service. Unlike
// viewer.spec.mjs these pages are wp-admin, whose own scripts are not ours, so there is no
// blanket page-error guard here.
import { test, expect } from '@playwright/test';
import { execFileSync, spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const WP_DIR = process.env.WP_DIR || '/tmp/scouting-pdf-wp';
const FAKE_OCR = 'http://127.0.0.1:8899';
const FAKE_TEXT = 'Camp Tahquitz opened in 1952\non the shore of the lake.\n\nWaterfront rules: every Scout swims with a buddy.';
const STORAGE_PDF = 'https://storage.scoutingmemories.org/sm-tests/leaders-guide.pdf';
const WALK_PDF = 'https://storage.scoutingmemories.org/sm-tests/queue-walk.pdf';
const DASHBOARD = '/wp-admin/admin.php?page=scouting-pdf-text';
const SETTINGS = '/wp-admin/options-general.php?page=scouting-pdf-ocr';
let service;

const ocrState = (...args) => execFileSync('php', [join(HERE, 'ocr-state.php'), WP_DIR, ...args], { encoding: 'utf8' });
const fake = async (path, body) => (await fetch(FAKE_OCR + path, body === undefined ? {} : { method: 'POST', body: JSON.stringify(body) })).json();
const setMode = (mode) => fake('/__test/mode', { mode });
const sentToService = () => fake('/__test/requests');
const serviceUp = async () => {
    try { return (await fake('/v1/health')).ok === true; } catch { return false; }
};
const docs = () => JSON.parse(ocrState('docs')).map((d) => ({
    ...d,
    total_pages: Number(d.total_pages),
    pages_done: Number(d.pages_done),
    pages_failed: Number(d.pages_failed),
    priority: Number(d.priority),
}));
const docFor = (url) => docs().find((d) => d.pdf_url === url);
const pageRow = (page) => JSON.parse(ocrState('page-row', String(page)));

async function logIn(page, user, pass, landing) {
    await page.goto('/wp-login.php?redirect_to=' + encodeURIComponent('http://localhost:8888' + landing));
    await page.locator('#user_login').fill(user);
    await page.locator('#user_pass').fill(pass);
    await page.locator('#wp-submit').click();
    await page.waitForURL('http://localhost:8888' + landing);
}
const logInAdmin = (page) => logIn(page, 'admin', 'admin-pass-123', '/wp-admin/');

// The block editor keeps meta boxes in a collapsed "Meta Boxes" drawer at the bottom;
// open it (WordPress remembers the choice per user, but our seeded preferences reset it)
async function openPdfTextBox(page) {
    const toggle = page.getByRole('button', { name: 'Meta Boxes' });
    await expect(toggle).toBeVisible({ timeout: 20000 });
    if (await toggle.getAttribute('aria-expanded') === 'false') {
        // The drawer's drag-to-resize handle sits over the toggle and swallows real clicks
        await toggle.dispatchEvent('click');
    }
    const box = page.locator('#scouting-pdf-text-box');
    await expect(box).toBeVisible();
    return box;
}

// One run of the queue, asked for from a wp-admin page that carries the dashboard config
const tick = (page) => page.evaluate(async () => {
    const cfg = window.ScoutingPdfAdminConfig;
    const res = await fetch(cfg.restRoot + 'queue', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'tick' }),
    });
    return res.json();
});
async function tickUntil(page, isDone, cap = 25) {
    for (let i = 0; i < cap; i++) {
        await tick(page);
        if (isDone()) return;
        await page.waitForTimeout(200);
    }
    expect(isDone()).toBe(true);
}

test.describe('PDF Text dashboard and the PDF text box', () => {
    test.beforeAll(async () => {
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
        ocrState('auto-approve', 'off');
        ocrState('queue-off');
    });

    test.beforeEach(async () => {
        await fake('/__test/reset', {});
        ocrState('clear');
        ocrState('queue-on');
    });

    test('the page is there for an administrator and closed to a subscriber', async ({ page }) => {
        await logInAdmin(page);
        await expect(page.locator('#adminmenu')).toContainText('PDF Text');
        await page.goto(DASHBOARD);
        await expect(page.locator('h1')).toHaveText('PDF Text');
        await expect(page.locator('#sm-ocr-state')).toHaveText('The queue is running.');
        await expect(page.locator('#sm-ocr-docs-line')).toContainText('Documents: 0 waiting');

        await page.context().clearCookies();
        await logIn(page, 'reader', 'reader-pass-123', '/');
        await expect(page.locator('#wpadminbar')).not.toContainText('PDF Text');
        const refused = await page.goto(DASHBOARD);
        expect(refused.status()).toBe(403);
    });

    test('a document added by its link waits at the front of the queue', async ({ page }) => {
        await logInAdmin(page);
        await page.goto(DASHBOARD + '&tab=queue');
        await page.locator('#sm-ocr-add-url').fill(WALK_PDF);
        await page.locator('#sm-ocr-add').click();
        await expect(page.locator('#sm-ocr-add-result')).toHaveText('Added. It will be read next.');
        await expect(page.locator('#sm-ocr-docs')).toContainText('queue-walk.pdf');
        await expect(page.locator('#sm-ocr-docs')).toContainText('Waiting');
        expect(docFor(WALK_PDF)).toMatchObject({ status: 'queued', source: 'bulk', priority: 1 });

        await page.locator('#sm-ocr-add-url').fill(WALK_PDF);
        await page.locator('#sm-ocr-add').click();
        await expect(page.locator('#sm-ocr-add-result')).toHaveText('This document is already in the queue.');
    });

    test('the queue reads a 12-page document to the end', async ({ page }) => {
        await logInAdmin(page);
        await page.goto(DASHBOARD + '&tab=queue');
        await page.locator('#sm-ocr-add-url').fill(WALK_PDF);
        await page.locator('#sm-ocr-add').click();
        await expect(page.locator('#sm-ocr-add-result')).toHaveText('Added. It will be read next.');

        await tickUntil(page, () => docFor(WALK_PDF)?.status === 'done');
        expect(docFor(WALK_PDF)).toMatchObject({ status: 'done', total_pages: 12, pages_done: 12, pages_failed: 0 });

        await page.reload();
        await expect(page.locator('#sm-ocr-docs')).toContainText('12 of 12 read');
        await expect(page.locator('#sm-ocr-docs')).toContainText('Done');
    });

    test('the progress area shows each document being read and the overall bar', async ({ page }) => {
        await logInAdmin(page);
        await page.goto(DASHBOARD + '&tab=queue');
        await page.locator('#sm-ocr-add-url').fill(WALK_PDF);
        await page.locator('#sm-ocr-add').click();
        await expect(page.locator('#sm-ocr-add-result')).toHaveText('Added. It will be read next.');

        await page.goto(DASHBOARD);
        await tick(page); // page 1 goes out
        await page.reload();
        const progress = page.locator('#sm-ocr-progress');
        await expect(progress).toBeVisible();
        await expect(progress).toHaveClass(/sm-ocr-running/);
        await expect(page.locator('#sm-ocr-overall-text')).toHaveText('0 of 1 documents finished (0%)');
        const card = page.locator('#sm-ocr-reading .sm-ocr-card');
        await expect(card).toHaveCount(1);
        await expect(card).toContainText('queue-walk.pdf');

        await tick(page); // page 1 answered: the count is known, the next pages go out
        await expect(card).toContainText(/\d+ of 12 pages read · reading pages? [\d, and]+ now/, { timeout: 15000 });

        // The Queue tab shows the same progress area, and a bar in the document's Pages cell
        await page.goto(DASHBOARD + '&tab=queue');
        await expect(page.locator('#sm-ocr-progress')).toBeVisible();
        await expect(page.locator('#sm-ocr-overall-bar')).toBeVisible();
        await expect(page.locator('#sm-ocr-reading .sm-ocr-card')).toHaveCount(1);
        await expect(page.locator('#sm-ocr-docs .sm-ocr-bar-row').first()).toBeVisible({ timeout: 15000 });
        await page.goto(DASHBOARD);

        await tickUntil(page, () => docFor(WALK_PDF)?.status === 'done');
        await expect(page.locator('#sm-ocr-overall-text')).toHaveText('1 of 1 documents finished (100%)', { timeout: 15000 });
        await expect(page.locator('#sm-ocr-overall-bar')).toHaveAttribute('aria-valuenow', '100');
        await expect(page.locator('#sm-ocr-reading')).toHaveText('Nothing left to read.');
        await expect(page.locator('#sm-ocr-speed')).toContainText('12 pages read in the last hour.');
        await expect(page.locator('#sm-ocr-timeleft')).toHaveText('Time left: none, every document is finished.');
    });

    test('Pause stops the queue; Resume starts it again', async ({ page }) => {
        await logInAdmin(page);
        await page.goto(DASHBOARD + '&tab=queue');
        await page.locator('#sm-ocr-add-url').fill(WALK_PDF);
        await page.locator('#sm-ocr-add').click();
        await expect(page.locator('#sm-ocr-add-result')).toHaveText('Added. It will be read next.');

        await page.goto(DASHBOARD);
        await page.locator('#sm-ocr-pause').click();
        await expect(page.locator('#sm-ocr-state')).toContainText('The queue is paused.');
        await fake('/__test/reset', {});
        await tick(page);
        expect(await sentToService()).toEqual([]);

        await page.locator('#sm-ocr-resume').click();
        await expect(page.locator('#sm-ocr-state')).toHaveText('The queue is running.');
        await tick(page);
        const sent = await sentToService();
        expect(sent.length).toBeGreaterThan(0);
        expect(sent[0]).toMatchObject({ method: 'POST', path: '/v1/jobs' });
    });

    test('a busy service shows the busy line and the queue backs off', async ({ page }) => {
        await setMode('busy');
        await logInAdmin(page);
        await page.goto(DASHBOARD + '&tab=queue');
        await page.locator('#sm-ocr-add-url').fill(WALK_PDF);
        await page.locator('#sm-ocr-add').click();
        await expect(page.locator('#sm-ocr-add-result')).toHaveText('Added. It will be read next.');
        await page.goto(DASHBOARD);
        await tick(page);
        // The state line follows on the dashboard's next poll; a reload is that poll
        await page.reload();
        await expect(page.locator('#sm-ocr-state')).toContainText('The reading service is busy or full. The queue will try again at', { timeout: 15000 });
        // The refused page was handed back, nothing half-done is left behind
        expect(docFor(WALK_PDF).pages_done).toBe(0);
    });

    test('Try every failed document again puts a failed document back and it is read', async ({ page }) => {
        await setMode('fail');
        await logInAdmin(page);
        await page.goto(DASHBOARD + '&tab=queue');
        await page.locator('#sm-ocr-add-url').fill(WALK_PDF);
        await page.locator('#sm-ocr-add').click();
        await expect(page.locator('#sm-ocr-add-result')).toHaveText('Added. It will be read next.');
        await tickUntil(page, () => docFor(WALK_PDF)?.status === 'failed');

        await setMode('fast');
        await page.reload();
        await expect(page.locator('#sm-ocr-docs')).toContainText('Failed');
        await page.locator('#sm-ocr-retry-all').click();
        await expect(page.locator('#sm-ocr-retry-all-progress')).toHaveText('Done: 1 document(s) put back in the queue.');
        await expect(page.locator('#sm-ocr-docs')).toContainText('Waiting');
        expect(docFor(WALK_PDF)).toMatchObject({ status: 'queued', pages_failed: 0 });

        await tickUntil(page, () => docFor(WALK_PDF)?.status === 'done');
        expect(docFor(WALK_PDF)).toMatchObject({ status: 'done', total_pages: 12, pages_done: 12, pages_failed: 0 });

        await page.locator('#sm-ocr-retry-all').click();
        await expect(page.locator('#sm-ocr-retry-all-progress')).toHaveText('Nothing has failed, so there is nothing to try again.');
    });

    test('the PDF text box: read the document, check, correct and approve its text', async ({ page }) => {
        test.setTimeout(120000);
        const postId = ocrState('post-id', '/ocr-document/').trim();
        expect(Number(postId)).toBeGreaterThan(0);
        // No block-editor welcome window or fullscreen over the box
        await page.addInitScript(() => {
            window.localStorage.setItem('WP_PREFERENCES_USER_1',
                JSON.stringify({ 'core/edit-post': { welcomeGuide: false, fullscreenMode: false } }));
        });
        await logInAdmin(page);
        await page.goto('/wp-admin/post.php?post=' + postId + '&action=edit');
        let box = await openPdfTextBox(page);
        await expect(box).toContainText('This document has not been read yet.');

        await box.getByRole('button', { name: 'Read this document' }).click();
        await expect(box).toContainText('Being read:');
        expect(docFor(STORAGE_PDF)).toMatchObject({ status: 'queued', source: 'auto', priority: 1 });

        await tickUntil(page, () => docFor(STORAGE_PDF)?.status === 'done');
        await page.reload();
        box = await openPdfTextBox(page);
        await expect(box).toContainText('Page 1');
        const first = box.locator('.sm-ocr-box-page').first();
        await expect(first.locator('textarea')).toHaveValue(FAKE_TEXT);
        await expect(first.getByRole('link', { name: 'Open this page' })).toHaveAttribute('href', /#page=1$/);

        // Approve page 1 as it stands
        await first.getByRole('button', { name: 'Approve', exact: true }).click();
        await expect(box).toContainText('Approved.');
        await expect(box.locator('.sm-ocr-box-page').first()).toContainText(/Page 1 — checked by admin on \d{4}-\d{2}-\d{2}\./);

        // Correct page 2, then approve the correction
        const second = box.locator('.sm-ocr-box-page').nth(1);
        const saveApprove = second.getByRole('button', { name: 'Save changes and approve' });
        await expect(saveApprove).toBeHidden();
        await second.locator('textarea').fill('Corrected by the test.');
        await expect(saveApprove).toBeVisible();
        await saveApprove.click();
        await expect(box.locator('.sm-ocr-box-page').nth(1)).toContainText('(corrected)');
        expect(pageRow(2)).toMatchObject({ review_status: 'approved', text: 'Corrected by the test.', text_original: FAKE_TEXT });

        // The rest in one go
        await box.getByRole('button', { name: 'Approve all 10 unchecked pages of this document' }).click();
        await expect(box.getByRole('button', { name: /Approve all/ })).toHaveCount(0);
        expect(pageRow(7)).toMatchObject({ review_status: 'approved', text_original: null });
    });

    test('the site scan finds the test post\'s document', async ({ page }) => {
        await logInAdmin(page);
        await page.goto(DASHBOARD + '&tab=queue');
        await page.locator('#sm-ocr-scan').click();
        await expect(page.locator('#sm-ocr-scan-progress')).toContainText('new document(s) added to the queue.', { timeout: 30000 });
        expect(docFor(STORAGE_PDF)).toMatchObject({ status: 'queued', source: 'bulk', priority: 0 });
    });

    test('settings: a tick is saved, and the result shows beside the buttons', async ({ page }) => {
        ocrState('queue-off');
        await logInAdmin(page);
        await page.goto(SETTINGS);
        const queueBox = page.locator('input[name="scouting_pdf_ocr[queue_enabled]"]');
        await expect(queueBox).not.toBeChecked();
        await queueBox.check();
        await page.getByRole('button', { name: 'Save Changes' }).click();
        await page.waitForURL(/settings-updated=true#sm-ocr-result$/);
        await expect(page.locator('#sm-ocr-result')).toContainText('Settings saved. Background reading is switched on.');
        await expect(page.locator('#sm-ocr-result')).toBeInViewport();
        await expect(queueBox).toBeChecked();
    });

    test('settings: Save and test connection keeps unsaved ticks, then tests', async ({ page }) => {
        ocrState('queue-off');
        ocrState('auto-approve', 'off');
        try {
            await logInAdmin(page);
            await page.goto(SETTINGS);
            await page.locator('input[name="scouting_pdf_ocr[queue_enabled]"]').check();
            await page.locator('input[name="scouting_pdf_ocr[auto_approve]"]').check();
            await page.getByRole('button', { name: 'Save and test connection' }).click();
            await page.waitForURL(/ocr_test=ok#sm-ocr-result$/);
            const result = page.locator('#sm-ocr-result');
            await expect(result).toContainText('Settings saved. Background reading is switched on. Automatic approval is on.');
            await expect(result).toContainText('Connected: the service answered and accepted the secret.');
            await expect(page.locator('input[name="scouting_pdf_ocr[queue_enabled]"]')).toBeChecked();
            await expect(page.locator('input[name="scouting_pdf_ocr[auto_approve]"]')).toBeChecked();

            // The PDF Text page's own Test connection link lands on the same result box
            await page.goto(DASHBOARD);
            await page.getByRole('link', { name: 'Test connection' }).click();
            await page.waitForURL(/ocr_test=ok#sm-ocr-result$/);
            await expect(result).toContainText('Connected: the service answered and accepted the secret.');
        } finally {
            ocrState('auto-approve', 'off');
        }
    });

    test('auto-approve: new text arrives already checked', async ({ page }) => {
        ocrState('auto-approve', 'on');
        try {
            await logInAdmin(page);
            await page.goto(DASHBOARD + '&tab=queue');
            await page.locator('#sm-ocr-add-url').fill(WALK_PDF);
            await page.locator('#sm-ocr-add').click();
            await expect(page.locator('#sm-ocr-add-result')).toHaveText('Added. It will be read next.');
            await tickUntil(page, () => docFor(WALK_PDF)?.status === 'done');
            expect(pageRow(1)).toMatchObject({ status: 'done', review_status: 'auto' });
        } finally {
            ocrState('auto-approve', 'off');
        }
    });
});
