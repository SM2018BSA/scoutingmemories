// Browser tests for the Scouting PDF Embedder. Start the test WordPress first:
//   npm run setup && npx playwright test
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    testMatch: /.*\.spec\.mjs/,
    timeout: 60000,
    workers: 2,
    reporter: [['list']],
    use: {
        baseURL: process.env.WP_URL || 'http://localhost:8888',
        viewport: { width: 1280, height: 900 },
        acceptDownloads: true,
        permissions: ['clipboard-read', 'clipboard-write'],
        launchOptions: process.env.PLAYWRIGHT_CHROMIUM_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH } : {},
    },
});
