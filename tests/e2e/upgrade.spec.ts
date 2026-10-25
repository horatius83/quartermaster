import { test, expect } from './fixtures';
import { resetDatabase, upgradeOldDatabase } from './php';

// An install from before campaigns: everything it had moves into one campaign named from config.php.
const UPGRADED = {
  campaigns: [{ id: 1, name: 'The Old Campaign' }],
  characters: [1, 1],
  stores: [1],
  log: [1, 1],
};

test('an old database upgrades itself into one campaign', () => {
  expect(upgradeOldDatabase()).toEqual(UPGRADED);
});

test('an upgrade that stopped partway is finished on the next run', () => {
  // The first four statements: the campaigns table, its first row, and the characters column filled in.
  expect(upgradeOldDatabase(4)).toEqual(UPGRADED);
});

test('a fresh install names its first campaign during setup', async ({ page }) => {
  resetDatabase({ empty: true });
  await page.goto('/');
  await expect(page).toHaveURL(/\/setup$/);
  // A too-short password comes back with the username and campaign still filled in.
  await page.getByLabel('DM username').fill('dm');
  await page.getByLabel('Password (8+ characters)').evaluate((el) => el.removeAttribute('minlength'));
  await page.getByLabel('Password (8+ characters)').fill('short');
  await page.getByLabel('Campaign name').fill('Shadows over Brindle');
  await page.getByRole('button', { name: 'Create DM account' }).click();
  await expect(page.getByText('Pick a username, a password of at least 8 characters')).toBeVisible();
  await expect(page.getByLabel('DM username')).toHaveValue('dm');
  await expect(page.getByLabel('Campaign name')).toHaveValue('Shadows over Brindle');
  await expect(page.getByLabel('Password (8+ characters)')).toHaveValue('');

  await page.getByLabel('DM username').fill('dm');
  await page.getByLabel('Password (8+ characters)').fill('dungeon123');
  await page.getByLabel('Campaign name').fill('Shadows over Brindle');
  await page.getByRole('button', { name: 'Create DM account' }).click();
  await expect(page).toHaveURL(/\/dm\/party$/);

  await page.goto('/dm');
  await expect(page.locator('.page-head')).toContainText('Shadows over Brindle');
  await expect(page.getByRole('navigation', { name: 'Main' })).toContainText('Shadows over Brindle');
});
