import {defineConfig} from '@playwright/test';
import dotenv from 'dotenv';

/**
 * Fallback to load env vars from .env if we're in an environment
 * where they don't already exist. Doesn't overwrite existing
 * vars, and fails silently if .env file doesn't exist.
 *
 * Would rather populate env vars entirely without a runtime
 * script, but that's not currently possible with the VS Code
 * extension: https://github.com/microsoft/playwright/issues/22932
 */
dotenv.config({ path: __dirname + '/.env' });

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: __dirname + '/tests',
  /* Run tests in files in parallel */
  fullyParallel: true,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  /* Opt out of parallel tests on CI. */
  workers: process.env.CI ? 1 : undefined,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: [
    ['html'],
    ...(process.env.CI ? [
      ['github'],
      ['json', { outputFile: 'results.json' }],
    ] : []),
  ],

  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: process.env.PW_BASE_URL ?? 'http://localhost:9000',

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',

    /* https://playwright.dev/docs/videos */
    video: 'retain-on-failure',
  },
});
