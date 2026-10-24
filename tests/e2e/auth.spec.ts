import { test, expect, signIn, signInAsDm } from './fixtures';

test('DM signs in to the party dashboard', async ({ page }) => {
  await signInAsDm(page);
  await expect(page).toHaveURL(/\/dm$/);
  await expect(page.getByRole('heading', { name: 'The Party Ledger' })).toBeVisible();
});

test('a wrong password is rejected', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Username').fill('dm');
  await page.getByLabel('Password').fill('not-the-password');
  await page.getByRole('button', { name: 'Open the ledger' }).click();
  await expect(page.getByRole('alert')).toContainText('don’t match');
});

test('a player signs in to the shops', async ({ page }) => {
  await signIn(page, 'bryn');
  await expect(page).toHaveURL(/\/shop\/\d+$/);
  await expect(page.getByRole('navigation', { name: 'Player' })).toBeVisible();
});

test('players cannot open DM pages', async ({ page }) => {
  await signIn(page, 'bryn');
  for (const path of ['/dm', '/dm/inventory/1', '/dm/history']) {
    await page.goto(path);
    await expect(page.getByText('Only the DM can do that.')).toBeVisible();
  }
});

test('signed-out visitors are sent to the login page', async ({ page }) => {
  await page.goto('/dm/history');
  await expect(page).toHaveURL(/\/login$/);
});
