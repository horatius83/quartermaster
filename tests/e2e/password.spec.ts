import type { Browser, Page } from '@playwright/test';
import { test, expect, signIn, signInAsDm, toasts, ADMIN, DM } from './fixtures';
import { checkSession } from './php';

const signInAsAdmin = (page: Page) => signIn(page, ADMIN.username, ADMIN.password);

async function changeOwnPassword(page: Page, current: string, next: string, again = next) {
  await page.goto('/account/password');
  await page.getByLabel('Current password').fill(current);
  await page.getByLabel(/^New password \(/).fill(next);
  await page.getByLabel('New password again').fill(again);
  await page.getByRole('button', { name: 'Change password' }).click();
}

async function expectSignInFails(page: Page, username: string, password: string) {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.getByLabel('Username').fill(username);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Open the ledger' }).click();
  await expect(page.getByRole('alert')).toContainText('don’t match');
}

test('a DM changes their own password, and other sessions end', async ({ page, browser }) => {
  // The same DM, signed in somewhere else.
  const elsewhere = await (await browser.newContext()).newPage();
  await signInAsDm(elsewhere);

  await signInAsDm(page);
  await page.getByRole('link', { name: 'Change password' }).click();
  await changeOwnPassword(page, 'not-it', 'dragon-hoard');
  await expect(toasts(page)).toContainText('Your current password isn’t right.');
  await changeOwnPassword(page, DM.password, 'dragon-hoard', 'dragon-hord');
  await expect(toasts(page)).toContainText('The two new passwords don’t match.');

  await changeOwnPassword(page, DM.password, 'dragon-hoard');
  await expect(toasts(page)).toContainText('Password changed.');
  // Still signed in here...
  await page.goto('/dm/party');
  await expect(page).toHaveURL(/\/dm\/party$/);
  // ...but not elsewhere.
  await elsewhere.goto('/dm');
  await expect(elsewhere).toHaveURL(/\/login$/);

  await expectSignInFails(page, DM.username, DM.password);
  await signIn(page, DM.username, 'dragon-hoard');
  await expect(page).toHaveURL(/\/dm$/);

  await signInAsAdmin(page);
  await page.goto('/admin/log');
  const first = page.locator('table.ledger tbody tr').first();
  await expect(first).toContainText('New password for DM dm');
  await expect(first).toContainText('Changed their own password');
  await expect(page.locator('table.ledger')).not.toContainText('dragon-hoard');
});

test('a copy of the session cookie stops working once its owner changes the password', async ({ page, browser }) => {
  await signInAsDm(page);
  const thief = await browser.newContext();
  await thief.addCookies(await page.context().cookies());
  const thiefPage = await thief.newPage();
  await thiefPage.goto('/dm');
  await expect(thiefPage).toHaveURL(/\/dm$/);

  await changeOwnPassword(page, DM.password, 'dragon-hoard');
  await expect(toasts(page)).toContainText('Password changed.');
  await page.goto('/dm');
  await expect(page).toHaveURL(/\/dm$/);
  await thiefPage.goto('/dm');
  await expect(thiefPage).toHaveURL(/\/login$/);
});

test('a session from before session versions, or of a deleted login, is signed out and emptied', () => {
  // The fixture has already reseeded the database.
  expect(checkSession('legacy')).toEqual({ user: null, sessionKeys: [] });
  expect(checkSession('deleted')).toEqual({ user: null, sessionKeys: [] });
  const current = checkSession('current');
  expect(current.user).toBe('dm');
  expect(current.sessionKeys).toEqual(expect.arrayContaining(['user_id', 'old_input', 'session_version']));
});

async function tryToSignIn(page: Page, username: string, password: string) {
  await page.goto('/login');
  await page.getByLabel('Username').fill(username);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Open the ledger' }).click();
}

test('too many wrong passwords at sign-in pause that name, and it is logged', async ({ page }) => {
  for (let i = 1; i < 5; i++) {
    await tryToSignIn(page, DM.username, `wrong-${i}`);
    await expect(page.getByRole('alert')).toContainText('don’t match');
  }
  await tryToSignIn(page, DM.username, 'wrong-5');
  await expect(page.getByRole('alert')).toContainText('Too many wrong passwords');
  // Even the right password waits now.
  await tryToSignIn(page, DM.username, DM.password);
  await expect(page.getByRole('alert')).toContainText('Too many wrong passwords');

  // Other names from the same address still work, and the admin sees what happened.
  await signInAsAdmin(page);
  await page.goto('/admin/log');
  const first = page.locator('table.ledger tbody tr').first();
  await expect(first).toContainText('Too many wrong passwords for DM dm');
  await expect(first).toContainText('at sign-in');
});

/** A page whose requests look like they come from $address (tests/e2e/config.php trusts X-Test-Client-IP). */
async function pageFrom(browser: Browser, address: string): Promise<Page> {
  const context = await browser.newContext({ extraHTTPHeaders: { 'X-Test-Client-IP': address } });
  return context.newPage();
}

test('a name paused from one address can still sign in from another', async ({ browser }) => {
  const stranger = await pageFrom(browser, '203.0.113.5');
  for (let i = 1; i <= 5; i++) {
    await tryToSignIn(stranger, DM.username, `wrong-${i}`);
  }
  await expect(stranger.getByRole('alert')).toContainText('Too many wrong passwords');

  const home = await pageFrom(browser, '203.0.113.6');
  await signInAsDm(home);
  await expect(home).toHaveURL(/\/dm$/);
});

test('guesses spread over many names pause the address, and it is logged', async ({ browser }) => {
  const sprayer = await pageFrom(browser, '203.0.113.7');
  const names = ['dm', 'admin', 'gm', 'bryn', 'tamsin'];
  for (let round = 1; round <= 4; round++) {
    for (const name of names) {
      await tryToSignIn(sprayer, name, `wrong-${round}`);
    }
  }
  await expect(sprayer.getByRole('alert')).toContainText('Too many wrong passwords');
  // No single name reached its limit, but the address waits, even for a right password.
  await tryToSignIn(sprayer, 'oskar', 'adventure');
  await expect(sprayer.getByRole('alert')).toContainText('Too many wrong passwords');

  const admin = await pageFrom(browser, '203.0.113.8');
  await signInAsAdmin(admin);
  await admin.goto('/admin/log');
  const first = admin.locator('table.ledger tbody tr').first();
  await expect(first).toContainText('Too many wrong passwords from one address');
  await expect(first).toContainText('from 203.0.113.7');
  await expect(first).toContainText('for: admin, bryn, dm, gm, tamsin');
});

test('other people’s wrong passwords at a shared address don’t sign a DM out', async ({ browser }) => {
  const dm = await pageFrom(browser, '203.0.113.9');
  await signInAsDm(dm);
  const players = await pageFrom(browser, '203.0.113.9');
  for (let i = 1; i <= 20; i++) {
    await tryToSignIn(players, `player-${i % 4}`, 'typo');
  }
  await expect(players.getByRole('alert')).toContainText('Too many wrong passwords');

  await changeOwnPassword(dm, DM.password, 'dragon-hoard');
  await expect(toasts(dm)).toContainText('Password changed.');
});

test('too many wrong current passwords sign the session out', async ({ page }) => {
  await signInAsDm(page);
  for (let i = 1; i < 5; i++) {
    await changeOwnPassword(page, `wrong-${i}`, 'dragon-hoard');
    await expect(toasts(page)).toContainText('Your current password isn’t right.');
  }
  await changeOwnPassword(page, 'wrong-5', 'dragon-hoard');
  await expect(page).toHaveURL(/\/login$/);
  await expect(toasts(page)).toContainText('Too many wrong passwords');
  await page.goto('/dm');
  await expect(page).toHaveURL(/\/login$/);
  // Signing straight back in to keep guessing is paused too.
  await tryToSignIn(page, DM.username, DM.password);
  await expect(page.getByRole('alert')).toContainText('Too many wrong passwords');

  await signInAsAdmin(page);
  await page.goto('/admin/log');
  await expect(page.locator('table.ledger tbody tr').first()).toContainText('on the change-password form');
});

test('the admin changes their own password', async ({ page }) => {
  await signInAsAdmin(page);
  await changeOwnPassword(page, ADMIN.password, 'keeper-of-keys');
  await expect(toasts(page)).toContainText('Password changed.');
  await expectSignInFails(page, ADMIN.username, ADMIN.password);
  await signIn(page, ADMIN.username, 'keeper-of-keys');
  await expect(page).toHaveURL(/\/admin\/users$/);
});

test('the admin resets a DM’s password, which signs the DM out', async ({ page, browser }) => {
  const dmPage = await (await browser.newContext()).newPage();
  await signInAsDm(dmPage);

  await signInAsAdmin(page);
  await page.getByLabel('New password for dm').fill('fresh-start');
  await page.getByRole('button', { name: 'Reset password' }).click();
  await expect(toasts(page)).toContainText('New password set for dm.');
  await page.goto('/admin/log');
  const first = page.locator('table.ledger tbody tr').first();
  await expect(first).toContainText('New password for DM dm');
  await expect(first).toContainText('Reset by the admin');

  await dmPage.goto('/dm');
  await expect(dmPage).toHaveURL(/\/login$/);
  await expectSignInFails(dmPage, DM.username, DM.password);
  await signIn(dmPage, DM.username, 'fresh-start');
  await expect(dmPage).toHaveURL(/\/dm$/);
});

test('only DMs have a reset form, and only the admin can use it', async ({ page }) => {
  await signInAsAdmin(page);
  await expect(page.getByLabel('New password for dm')).toBeVisible();
  await expect(page.getByLabel('New password for bryn')).toHaveCount(0);
  await expect(page.getByLabel('New password for admin')).toHaveCount(0);

  await signInAsDm(page);
  const text = await page.evaluate(async () => {
    const csrf = (document.querySelector('input[name=_csrf]') as HTMLInputElement).value;
    const res = await fetch('/admin/users/2/password', { method: 'POST', body: new URLSearchParams({ _csrf: csrf, password: 'hijacked1' }) });
    return res.text();
  });
  expect(text).toContain('Only the admin can do that.');
});

test('players can’t open the change-password page', async ({ page }) => {
  await signIn(page, 'bryn');
  await page.goto('/account/password');
  await expect(page.getByText('Ask your DM to change your password.')).toBeVisible();
});
