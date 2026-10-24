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
  await expect(form.getByLabel('Item', { exact: true })).toHaveValue('');
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
