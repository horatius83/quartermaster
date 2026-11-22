import type { Page } from '@playwright/test';
import { test, expect, signInAsDm, toasts, BRYN, TAMSIN } from './fixtures';

const packRow = (page: Page, name: string) => page.locator('#inv-body tr', { hasText: name });

test.beforeEach(async ({ page }) => {
  await signInAsDm(page);
  await page.goto(`/dm/inventory/${BRYN}`);
});

test('DM gives an item', async ({ page }) => {
  const form = page.locator('#give-form');
  await form.getByLabel('Item', { exact: true }).fill('Lantern');
  await form.getByLabel('Qty').fill('2');
  await form.getByRole('button', { name: 'Add to pack' }).click();
  await expect(toasts(page)).toContainText('Gave 2 × Lantern to Bryn Ashvale.');
  await expect(packRow(page, 'Lantern').locator('output')).toHaveText('2');
  await expect(form.locator('input[name="item"]')).toHaveValue('');
});

test('DM reads an item’s description in a pack, and it stays open while the count changes', async ({ page }) => {
  const row = packRow(page, 'Longsword');
  const text = row.getByText('Martial melee weapon. 1d8 slashing, versatile (1d10).');
  await expect(text).toBeHidden();
  await row.getByText('About the Longsword').click();
  await expect(text).toBeVisible();
  // + draws the row again; the description is still open in the new one.
  await row.getByRole('button', { name: 'Add one Longsword' }).click();
  await expect(row.locator('output')).toHaveText('2');
  await expect(packRow(page, 'Longsword').getByText('Martial melee weapon. 1d8 slashing, versatile (1d10).')).toBeVisible();
  // Closed, it stays closed through the next redraw.
  await packRow(page, 'Longsword').getByText('About the Longsword').click();
  await packRow(page, 'Longsword').getByRole('button', { name: 'Remove one Longsword' }).click();
  await expect(packRow(page, 'Longsword').locator('output')).toHaveText('1');
  await expect(packRow(page, 'Longsword').getByText('Martial melee weapon. 1d8 slashing, versatile (1d10).')).toBeHidden();
  // Items with no description have nothing to open.
  await expect(packRow(page, 'Silver locket').locator('details.item-desc')).toHaveCount(0);
});

test('− / + change the count', async ({ page }) => {
  const row = packRow(page, 'Potion of Healing');
  await expect(row.locator('output')).toHaveText('3');
  await row.getByRole('button', { name: 'Add one Potion of Healing' }).click();
  await expect(row.locator('output')).toHaveText('4');
  await row.getByRole('button', { name: 'Remove one Potion of Healing' }).click();
  await expect(row.locator('output')).toHaveText('3');
});

test('DM removes a stack', async ({ page }) => {
  await packRow(page, 'Silver locket').getByRole('button', { name: 'Remove', exact: true }).click();
  await expect(packRow(page, 'Silver locket')).toHaveCount(0);
  await page.reload();
  await expect(packRow(page, 'Silver locket')).toHaveCount(0);
});

test('DM moves a stack to another character', async ({ page }) => {
  const row = packRow(page, 'Shield');
  await row.getByText('Move to…').click();
  await row.getByLabel('Move Shield to').selectOption({ label: 'Tamsin Reed' });
  await row.getByRole('button', { name: 'Move', exact: true }).click();
  await expect(toasts(page)).toContainText('Moved 1 × Shield to Tamsin Reed.');
  await expect(packRow(page, 'Shield')).toHaveCount(0);

  await page.goto(`/dm/inventory/${TAMSIN}`);
  await expect(packRow(page, 'Shield')).toBeVisible();
});

test('DM edits the purse', async ({ page }) => {
  const sheet = page.locator('#sheet');
  await sheet.getByLabel('Gold').fill('500');
  await sheet.getByRole('button', { name: 'Save' }).click();
  await expect(toasts(page)).toContainText('Saved Bryn Ashvale’s purse: 500 gp.');
  await page.reload();
  await expect(page.locator('#sheet').getByLabel('Gold')).toHaveValue('500');
});
