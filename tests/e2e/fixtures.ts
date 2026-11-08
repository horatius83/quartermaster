import { test as base, expect, type BrowserContext, type BrowserContextOptions, type Page } from '@playwright/test';
import { resetDatabase, testDb } from './php';

/** Tells the test server which worker's database a browser's requests use (tests/e2e/test-db.php). */
const withTestDb = (headers?: Record<string, string>) => ({ ...headers, 'X-QM-Test-Db': testDb() });

// Demo seed logins (scripts/seed-demo.php). Character ids follow the seed order.
export const DM = { username: 'dm', password: 'dungeon123' };
export const ADMIN = { username: 'admin', password: 'quartermaster' };
export const PLAYER_PASSWORD = 'adventure';
export const BRYN = 1;
export const TAMSIN = 2;
export const WREN = 4;
// The seed's second campaign: one character and one shop of its own.
export const SECOND_CAMPAIGN = 'Winter’s Teeth';
export const SELA = 5;
export const FROSTGATE = 5;

/** Sign in, dropping any session first so one test can switch between the DM and players. */
export async function signIn(page: Page, username: string, password = PLAYER_PASSWORD): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.getByLabel('Username').fill(username);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Open the ledger' }).click();
  await expect(page).not.toHaveURL(/\/login$/);
}

export const signInAsDm = (page: Page) => signIn(page, DM.username, DM.password);
export const signInAsAdmin = (page: Page) => signIn(page, ADMIN.username, ADMIN.password);

/** Load a system's SRD items from the admin dashboard, then sign back in as the DM. */
export async function loadSrd(page: Page, system: string): Promise<void> {
  await signInAsAdmin(page);
  await page.goto('/admin');
  await page.getByRole('button', { name: `Load ${system} SRD items` }).click();
  await expect(toasts(page)).toContainText(`${system} SRD item`);
  await signInAsDm(page);
}

/** The toast area; messages fade after a few seconds, so check them straight after the action. */
export const toasts = (page: Page) => page.locator('#toasts');

/** Opens a page in a browser context of its own (its own cookies, so another visitor); closed after the test. */
export type OpenPage = (options?: BrowserContextOptions) => Promise<Page>;

export const test = base.extend<{ freshDatabase: void; openPage: OpenPage }>({
  extraHTTPHeaders: async ({ extraHTTPHeaders }, use) => {
    await use(withTestDb(extraHTTPHeaders));
  },
  openPage: async ({ browser }, use) => {
    const contexts: BrowserContext[] = [];
    await use(async (options) => {
      const context = await browser.newContext({ ...options, extraHTTPHeaders: withTestDb(options?.extraHTTPHeaders) });
      contexts.push(context);
      return context.newPage();
    });
    await Promise.all(contexts.map((c) => c.close()));
  },
  // Every test starts from the demo seed and accepts hx-confirm dialogs.
  freshDatabase: [
    async ({ page }, use) => {
      await resetDatabase();
      page.on('dialog', (dialog) => dialog.accept());
      await use();
    },
    { auto: true },
  ],
});

export { expect };
