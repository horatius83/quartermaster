import { test as base, expect, type Page } from '@playwright/test';
import { resetDatabase } from './php';

// Demo seed logins (scripts/seed-demo.php). Character ids follow the seed order.
export const DM = { username: 'dm', password: 'dungeon123' };
export const PLAYER_PASSWORD = 'adventure';
export const BRYN = 1;
export const TAMSIN = 2;
export const WREN = 4;

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

/** The toast area; messages fade after a few seconds, so check them straight after the action. */
export const toasts = (page: Page) => page.locator('#toasts');

export const test = base.extend<{ freshDatabase: void }>({
  // Every test starts from the demo seed and accepts hx-confirm dialogs.
  freshDatabase: [
    async ({ page }, use) => {
      resetDatabase();
      page.on('dialog', (dialog) => dialog.accept());
      await use();
    },
    { auto: true },
  ],
});

export { expect };
