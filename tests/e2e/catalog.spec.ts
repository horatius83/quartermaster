import type { Page } from '@playwright/test';
import { test, expect, signInAsDm, toasts } from './fixtures';

const descriptionOf = (page: Page, name: string) =>
  page.locator('#catalog-body tr').filter({ has: page.locator(`input[name="name"][value="${name}"]`) }).getByLabel('Description');

test.beforeEach(async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm/catalog');
});

test('DM adds an item with a description', async ({ page }) => {
  const form = page.locator('#catalog-form');
  await form.getByLabel('New item').fill('Folding Boat');
  await form.getByLabel('Description').fill('Holds far more than it should.');
  await form.getByRole('button', { name: 'Add item' }).click();
  await expect(toasts(page)).toContainText('Added Folding Boat to the catalog.');
  await expect(form.getByLabel('Description')).toHaveValue('');

  await page.reload();
  await expect(descriptionOf(page, 'Folding Boat')).toHaveValue('Holds far more than it should.');
});

test('DM adds an item without a description', async ({ page }) => {
  const form = page.locator('#catalog-form');
  await form.getByLabel('New item').fill('Plain Sack');
  await form.getByRole('button', { name: 'Add item' }).click();
  await expect(toasts(page)).toContainText('Added Plain Sack to the catalog.');
  await expect(descriptionOf(page, 'Plain Sack')).toHaveValue('');
});

test('DM edits and clears a description', async ({ page }) => {
  const description = descriptionOf(page, 'Torch');
  await description.fill('Burns for six hours.\nSmells of tallow.');
  await description.blur();
  await expect(toasts(page)).toContainText('Saved Torch.');
  await page.reload();
  await expect(descriptionOf(page, 'Torch')).toHaveValue('Burns for six hours.\nSmells of tallow.');

  await descriptionOf(page, 'Torch').fill('');
  await descriptionOf(page, 'Torch').blur();
  await expect(descriptionOf(page, 'Torch')).toHaveValue('');
  await page.reload();
  await expect(descriptionOf(page, 'Torch')).toHaveValue('');
});

test('Editing another field keeps the description', async ({ page }) => {
  const description = descriptionOf(page, 'Torch');
  await description.fill('Burns for six hours.');
  await description.blur();
  await expect(toasts(page)).toContainText('Saved Torch.');

  const row = page.locator('#catalog-body tr').filter({ has: page.locator('input[name="name"][value="Torch"]') });
  await row.getByLabel('Price').fill('9 gp');
  await row.getByLabel('Price').blur();
  await expect(row.getByLabel('Price')).toHaveValue('9 gp');
  await page.reload();
  await expect(descriptionOf(page, 'Torch')).toHaveValue('Burns for six hours.');
});
