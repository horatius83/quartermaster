import { defineConfig, devices } from '@playwright/test';
import { BASE_URL, PORT, phpCommand, phpEnv, ROOT } from './tests/e2e/php';

// Each worker has a database of its own (tests/e2e/test-db.php), reset from a seeded template before every test.
const WORKERS = Number(process.env.QM_E2E_WORKERS) || 4;

export default defineConfig({
  testDir: './tests/e2e',
  globalSetup: './tests/e2e/global-setup.ts',
  // Every test resets its worker's database first, so any test can run on any worker.
  fullyParallel: true,
  workers: WORKERS,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? 'github' : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: BASE_URL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
  ],
  webServer: {
    command: [...phpCommand(), '-S', `127.0.0.1:${PORT}`, '-t', 'public', 'tests/e2e/router.php'].join(' '),
    cwd: ROOT,
    // PHP's built-in server answers one request at a time unless given workers (Linux and WSL only; Windows PHP
    // ignores this and the workers wait on each other). Two per test worker covers a page and its htmx requests.
    env: { ...phpEnv(), PHP_CLI_SERVER_WORKERS: String(WORKERS * 2) },
    // A request without a worker's X-QM-Test-Db header gets 400 here, which Playwright still counts as up; an old
    // server reused from another checkout answers too, and global-setup.ts then stops the run with a clear message.
    url: `${BASE_URL}/login`,
    reuseExistingServer: !process.env.CI,
    // PHP's built-in server logs every request to stderr. PHP errors still show on the page (debug is on).
    stderr: 'ignore',
    timeout: 30_000,
  },
});
