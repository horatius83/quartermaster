import { BASE_URL, PORT, seedTemplate } from './php';

/**
 * Check the server is this checkout's test server, then seed the demo database once per run; each test then
 * starts from a copy of it (tests/e2e/router.php).
 */
export default async function globalSetup(): Promise<void> {
  // Outside CI Playwright reuses whatever already listens on the port, which may be a server from an older
  // checkout without the test routes: every test would then fail to reset its database.
  const ping = await fetch(`${BASE_URL}/__test/ping`).catch(() => undefined);
  if (ping?.status !== 204) {
    throw new Error(
      `The server on port ${PORT} isn't this checkout's test server (GET /__test/ping gave ${ping?.status ?? 'no answer'}). ` +
        'Stop whatever is listening there (often a PHP server left by an earlier run from another checkout), or set QM_E2E_PORT to a free port.',
    );
  }
  seedTemplate();
}
