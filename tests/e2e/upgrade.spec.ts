import { test, expect, toasts } from './fixtures';
import { makeOwnerlessCampaign, resetDatabase, upgradeOldDatabase } from './php';

// An install from before campaigns: everything it had moves into one campaign named from config.php,
// run by its DM (user 1). The admin role and the empty account log arrive too.
const UPGRADED = {
  campaigns: [{ id: 1, name: 'The Old Campaign', owner: 1 }],
  admins: 2,
  userLog: 0,
  // Upgrade 004: every login starts at session version 0 (dm, bryn, then the two admins added after).
  sessionVersions: [0, 0, 0, 0],
  // Upgrade 005: the table is there, with nothing tagged yet.
  itemTags: 0,
  characters: [1, 1],
  stores: [1],
  log: [1, 1],
};

test('an old database upgrades itself into one campaign', () => {
  expect(upgradeOldDatabase()).toEqual(UPGRADED);
});

test('the admin upgrade stops, rather than half-finishing, when it can’t widen the role check', () => {
  expect(() => upgradeOldDatabase(0, { oddRoleCheck: true })).toThrow(/CHECK constraint failed/);
});

test('an upgrade that stopped partway is finished on the next run', () => {
  // The first four statements: the campaigns table, its first row, and the characters column filled in.
  expect(upgradeOldDatabase(4)).toEqual(UPGRADED);
});

test('setup taking over an upgraded campaign converts its items to the chosen system', async ({ page }) => {
  await resetDatabase({ empty: true });
  makeOwnerlessCampaign();
  await page.goto('/setup');
  await page.getByLabel('DM username').fill('dm');
  await page.getByLabel('Password (8+ characters)').fill('dungeon123');
  await page.getByLabel('Campaign name').fill('Old Ways');
  await page.getByLabel('System').selectOption({ label: '3.5e' });
  await page.getByRole('button', { name: 'Create DM account' }).click();
  await expect(page).toHaveURL(/\/dm\/party$/);

  await page.goto('/dm/catalog');
  await expect(page.locator('#catalog-body').locator('input[name="name"][value="Old sword"]')).toHaveCount(1);
  await expect(page.locator('#catalog-body tr td').nth(-2)).toHaveText('1');
  await expect(page.getByRole('button', { name: 'Load 3.5e SRD items' })).toBeVisible();

  await page.goto('/dm/campaigns');
  await expect(page.getByRole('button', { name: 'Set once' })).toHaveCount(0);
  await expect(page.locator('tbody tr td').nth(1)).toHaveText('3.5e');
});

test('a fresh install names its first campaign during setup', async ({ page }) => {
  await resetDatabase({ empty: true });
  await page.goto('/');
  await expect(page).toHaveURL(/\/setup$/);
  // A too-short password comes back with the username and campaign still filled in.
  await page.getByLabel('DM username').fill('dm');
  await page.getByLabel('Password (8+ characters)').evaluate((el) => el.removeAttribute('minlength'));
  await page.getByLabel('Password (8+ characters)').fill('short');
  await page.getByLabel('Campaign name').fill('Shadows over Brindle');
  await page.getByRole('button', { name: 'Create DM account' }).click();
  // The error stays on the card as an alert instead of a toast that fades away.
  await expect(page.getByRole('alert')).toContainText('Pick a username, a password of at least 8 characters');
  await expect(toasts(page)).not.toContainText('Pick a username');
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
