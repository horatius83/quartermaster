import { defineConfig, devices } from '@playwright/test';
import { phpCommand, phpEnv, ROOT } from './tests/e2e/php';

const PORT = 8099;

export default defineConfig({
  testDir: './tests/e2e',
  // Every test reseeds the one shared database, so tests run one at a time.
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? 'github' : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: `http://127.0.0.1:${PORT}`,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
  ],
  webServer: {
    command: [...phpCommand(), '-S', `127.0.0.1:${PORT}`, '-t', 'public', 'scripts/dev-router.php'].join(' '),
    cwd: ROOT,
    env: phpEnv,
    url: `http://127.0.0.1:${PORT}/login`,
    reuseExistingServer: !process.env.CI,
    // PHP's built-in server logs every request to stderr. PHP errors still show on the page (debug is on).
    stderr: 'ignore',
    timeout: 30_000,
  },
});
