import type { Page } from '@playwright/test';
import { test, expect, signIn, signInAsDm, toasts, ADMIN, BRYN } from './fixtures';
import { resetDatabase } from './php';

const signInAsAdmin = (page: Page) => signIn(page, ADMIN.username, ADMIN.password);
const userRow = (page: Page, username: string) =>
  page.getByRole('table', { name: 'All logins' }).getByRole('row').filter({ has: page.getByText(username, { exact: true }) });

async function addLogin(page: Page, username: string, password: string, role: 'Dungeon Master' | 'Admin' = 'Dungeon Master') {
  await page.goto('/admin/users');
  await page.getByLabel('Username').fill(username);
  await page.getByLabel('Password (8+ characters)').fill(password);
  await page.getByLabel('Role').selectOption({ label: role });
  await page.getByRole('button', { name: 'Add login' }).click();
}

test('the admin signs in to the list of every login', async ({ page }) => {
  await signInAsAdmin(page);
  await expect(page).toHaveURL(/\/admin\/users$/);
  await expect(userRow(page, 'dm')).toContainText('The Sunken Crown');
  await expect(userRow(page, 'dm')).toContainText('Winter’s Teeth');
  await expect(userRow(page, 'bryn')).toContainText('Bryn Ashvale');
  await expect(userRow(page, 'bryn')).toContainText('Player');
  // No delete button on your own login.
  await expect(userRow(page, 'admin').getByRole('button', { name: /Delete/ })).toHaveCount(0);
});

test('only the admin can open the admin pages', async ({ page }) => {
  for (const who of [() => signInAsDm(page), () => signIn(page, 'bryn')]) {
    await who();
    for (const path of ['/admin/users', '/admin/log']) {
      await page.goto(path);
      await expect(page.getByText('Only the admin can do that.')).toBeVisible();
    }
  }
  // And the admin can't run campaigns.
  await signInAsAdmin(page);
  await page.goto('/dm');
  await expect(page.getByText('Only the DM can do that.')).toBeVisible();
});

test('a new DM sees only their own campaigns', async ({ page }) => {
  await signInAsAdmin(page);
  await addLogin(page, 'morgan', 'dungeon456');
  await expect(toasts(page)).toContainText('morgan can now sign in');
  await expect(userRow(page, 'morgan')).toContainText('DM');

  await signIn(page, 'morgan', 'dungeon456');
  await expect(page).toHaveURL(/\/dm$/);
  const nav = page.getByRole('navigation', { name: 'Main' });
  await expect(nav).toContainText('My Campaign');
  await expect(nav).not.toContainText('The Sunken Crown');
  await page.goto('/dm/campaigns');
  await expect(page.locator('table.ledger')).not.toContainText('Winter’s Teeth');
  // The other DM's characters can't be reached by id either.
  await page.goto(`/dm/inventory/${BRYN}`);
  await expect(page.getByText('No such character.')).toBeVisible();
  // Nor can their campaign be switched to.
  const status = await page.evaluate(async () => {
    const csrf = (document.querySelector('input[name=_csrf]') as HTMLInputElement).value;
    const res = await fetch('/dm/campaigns/switch', { method: 'POST', body: new URLSearchParams({ _csrf: csrf, campaign_id: '1' }) });
    return res.status;
  });
  expect(status).toBe(404);
});

test('adding a login keeps the form when the username is taken', async ({ page }) => {
  await signInAsAdmin(page);
  await addLogin(page, 'bryn', 'whatever1', 'Admin');
  await expect(toasts(page)).toContainText('The username “bryn” is already taken.');
  await expect(page.getByLabel('Username')).toHaveValue('');
  await expect(page.getByLabel('Role')).toHaveValue('admin');
});

test('a DM whose campaigns still have a party can’t be deleted', async ({ page }) => {
  await signInAsAdmin(page);
  await page.goto('/admin/users');
  await userRow(page, 'dm').getByRole('button', { name: 'Delete dm' }).click();
  await expect(toasts(page)).toContainText('dm still runs');
  await expect(userRow(page, 'dm')).toBeVisible();
});

test('deleting a DM with only empty campaigns removes them, and it is logged', async ({ page }) => {
  await signInAsAdmin(page);
  await addLogin(page, 'morgan', 'dungeon456');
  await expect(toasts(page)).toContainText('morgan can now sign in');
  // Signing in once gives morgan an empty "My Campaign".
  await signIn(page, 'morgan', 'dungeon456');
  await signInAsAdmin(page);
  await page.goto('/admin/users');
  await expect(userRow(page, 'morgan')).toContainText('My Campaign');
  await userRow(page, 'morgan').getByRole('button', { name: 'Delete morgan' }).click();
  await expect(toasts(page)).toContainText('Deleted morgan.');
  await expect(userRow(page, 'morgan')).toHaveCount(0);

  await page.goto('/admin/log');
  const rows = page.locator('table.ledger tbody tr');
  await expect(rows.nth(0)).toContainText('Deleted DM morgan');
  await expect(rows.nth(0)).toContainText('Empty campaigns deleted too: My Campaign');
  await expect(rows.nth(0)).toContainText('admin');
  await expect(rows.nth(1)).toContainText('Added DM morgan');

  // The deleted DM is signed out and can't sign back in.
  await page.context().clearCookies();
  await page.goto('/login');
  await page.getByLabel('Username').fill('morgan');
  await page.getByLabel('Password').fill('dungeon456');
  await page.getByRole('button', { name: 'Open the ledger' }).click();
  await expect(page.getByRole('alert')).toContainText('don’t match');
});

test('the admin hands a campaign to another DM, and it is logged', async ({ page }) => {
  await signInAsAdmin(page);
  await addLogin(page, 'morgan', 'dungeon456');
  await expect(toasts(page)).toContainText('morgan can now sign in');
  const winter = page.getByRole('table', { name: 'Campaigns' }).getByRole('row').filter({ hasText: 'Winter’s Teeth' });
  await winter.getByLabel('DM running Winter’s Teeth').selectOption({ label: 'morgan' });
  await winter.getByRole('button', { name: 'Hand over' }).click();
  await expect(toasts(page)).toContainText('morgan now runs Winter’s Teeth.');
  await expect(userRow(page, 'morgan')).toContainText('Winter’s Teeth');
  await expect(userRow(page, 'dm')).not.toContainText('Winter’s Teeth');

  await page.goto('/admin/log');
  await expect(page.locator('table.ledger tbody tr').first()).toContainText('Handed a campaign to DM morgan');
  await expect(page.locator('table.ledger tbody tr').first()).toContainText('Winter’s Teeth, from DM dm');

  await signIn(page, 'morgan', 'dungeon456');
  await expect(page.getByRole('navigation', { name: 'Main' })).toContainText('Winter’s Teeth');
  await signInAsDm(page);
  await page.goto('/dm/campaigns');
  await expect(page.locator('table.ledger')).not.toContainText('Winter’s Teeth');
});

test('deleting a player keeps their character without a login', async ({ page }) => {
  await signInAsAdmin(page);
  await page.goto('/admin/users');
  await userRow(page, 'bryn').getByRole('button', { name: 'Delete bryn' }).click();
  await expect(toasts(page)).toContainText('Deleted bryn.');
  await page.goto('/admin/log');
  await expect(page.locator('table.ledger tbody tr').first()).toContainText('Bryn Ashvale stays in The Sunken Crown without a login');

  await signInAsDm(page);
  await page.goto('/dm/party');
  await expect(page.locator(`#party-${BRYN}`)).toContainText('none');
});

test('player logins the DM adds and removes are logged too', async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm/party');
  await page.getByLabel('Character name').fill('Pip');
  await page.getByLabel('Player username').fill('pip');
  await page.getByLabel('Temporary password').fill('secret1');
  await page.getByRole('button', { name: 'Add to party' }).click();
  await expect(toasts(page)).toContainText('Pip has joined the party.');
  await page.getByRole('row').filter({ hasText: 'Pip' }).getByRole('button', { name: 'Remove' }).click();
  await expect(page.getByRole('row').filter({ hasText: 'Pip' })).toHaveCount(0);

  await signInAsAdmin(page);
  await page.goto('/admin/log');
  const rows = page.locator('table.ledger tbody tr');
  await expect(rows.nth(0)).toContainText('Deleted player pip');
  await expect(rows.nth(0)).toContainText('Pip left The Sunken Crown');
  await expect(rows.nth(1)).toContainText('Added player pip');
  await expect(rows.nth(1)).toContainText('Player for Pip in The Sunken Crown');
  await expect(rows.nth(1)).toContainText('dm');
});

test('a fresh install’s DM creates the admin account once', async ({ page }) => {
  resetDatabase({ empty: true });
  await page.goto('/setup');
  await page.getByLabel('DM username').fill('dm');
  await page.getByLabel('Password (8+ characters)').fill('dungeon123');
  await page.getByLabel('Campaign name').fill('Shadows over Brindle');
  await page.getByRole('button', { name: 'Create DM account' }).click();
  await expect(page).toHaveURL(/\/dm\/party$/);

  await page.getByRole('link', { name: 'Create the admin account' }).click();
  await page.getByLabel('Admin username').fill('keeper');
  await page.getByLabel('Admin password (8+ characters)').fill('keeper123');
  await page.getByRole('button', { name: 'Create admin account' }).click();
  await expect(toasts(page)).toContainText('Admin account keeper created.');
  await expect(page.getByRole('link', { name: 'Create the admin account' })).toHaveCount(0);
  await page.goto('/dm/admin-account');
  await expect(page).toHaveURL(/\/dm$/);

  await signIn(page, 'keeper', 'keeper123');
  await expect(page).toHaveURL(/\/admin\/users$/);
  await expect(userRow(page, 'dm')).toContainText('Shadows over Brindle');
  await page.goto('/admin/log');
  const rows = page.locator('table.ledger tbody tr');
  await expect(rows.nth(0)).toContainText('Added admin keeper');
  await expect(rows.nth(0)).toContainText('First admin, made by DM dm');
  await expect(rows.nth(1)).toContainText('Added DM dm');
});
