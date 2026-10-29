// Browser tests for the Scouting PDF Embedder. Start the test WordPress first:
//   npm run setup && npx playwright test
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    testMatch: /.*\.spec\.mjs/,
    timeout: 60000,
    // One worker: viewer.spec and dashboard.spec share the OCR settings, the saved pages
    // and the stand-in service on :8899, so the files must run one after the other (and the
    // test WordPress's PHP server answers one request at a time anyway)
    workers: 1,
    reporter: [['list']],
    use: {
        baseURL: process.env.WP_URL || 'http://localhost:8888',
        viewport: { width: 1280, height: 900 },
        acceptDownloads: true,
        permissions: ['clipboard-read', 'clipboard-write'],
        launchOptions: process.env.PLAYWRIGHT_CHROMIUM_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH } : {},
    },
});
