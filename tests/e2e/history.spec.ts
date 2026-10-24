import type { Page } from '@playwright/test';
import { test, expect, signIn, signInAsDm, BRYN, TAMSIN, WREN } from './fixtures';

const entries = (page: Page) => page.locator('tbody tr');

test('giving an item shows up first in the history', async ({ page }) => {
  await signInAsDm(page);
  await page.goto(`/dm/inventory/${BRYN}`);
  const form = page.locator('#give-form');
  await form.getByLabel('Item', { exact: true }).fill('Lantern');
  await form.getByLabel('Qty').fill('2');
  await form.getByRole('button', { name: 'Add to pack' }).click();
  await expect(page.locator('#inv-body')).toContainText('Lantern');

  await page.getByRole('link', { name: 'See what’s changed' }).click();
  await expect(page.getByRole('heading', { name: 'Bryn Ashvale’s History' })).toBeVisible();
  const first = entries(page).first();
  await expect(first).toContainText('+2');
  await expect(first).toContainText('Lantern');
  await expect(first).toContainText('Given by the DM');
  await expect(first).toContainText('dm');
});

test('repeated + clicks fold into one entry, and a − starts a new one', async ({ page }) => {
  await signInAsDm(page);
  await page.goto(`/dm/inventory/${BRYN}`);
  const row = page.locator('#inv-body tr', { hasText: 'Rations (1 day)' });
  const count = row.locator('output');
  await expect(count).toHaveText('6');
  for (const n of ['7', '8', '9']) {
    await row.getByRole('button', { name: 'Add one Rations (1 day)' }).click();
    await expect(count).toHaveText(n);
  }
  await row.getByRole('button', { name: 'Remove one Rations (1 day)' }).click();
  await expect(count).toHaveText('8');

  await page.goto(`/dm/history/${BRYN}`);
  await expect(entries(page).nth(0)).toContainText('−1');
  await expect(entries(page).nth(1)).toContainText('+3');
  await expect(entries(page).filter({ hasText: 'Count changed' })).toHaveCount(2);
});

test('a move is recorded on both characters, and players see only their own', async ({ page }) => {
  await signInAsDm(page);
  await page.goto(`/dm/inventory/${BRYN}`);
  const row = page.locator('#inv-body tr', { hasText: 'Shield' });
  await row.getByText('Move to…').click();
  await row.getByLabel('Move Shield to').selectOption({ label: 'Tamsin Reed' });
  await row.getByRole('button', { name: 'Move', exact: true }).click();
  await expect(row).toHaveCount(0);

  await page.goto(`/dm/history/${BRYN}`);
  await expect(entries(page).first()).toContainText('Handed over');
  await expect(entries(page).first()).toContainText('To Tamsin Reed');
  await page.goto(`/dm/history/${TAMSIN}`);
  await expect(entries(page).first()).toContainText('Received');

  await signIn(page, 'tamsin');
  await page.getByRole('link', { name: 'History' }).click();
  await expect(page).toHaveURL(/\/history$/);
  await expect(entries(page).first()).toContainText('From Bryn Ashvale');
  await expect(page.getByText('Handed over')).toHaveCount(0); // Bryn's side stays private
  await expect(page.getByText('Light crossbow')).toHaveCount(0); // Bryn's starting gear
});

test('a purse edit is logged as the change, not the new total', async ({ page }) => {
  await signInAsDm(page);
  await page.goto(`/dm/inventory/${BRYN}`);
  const sheet = page.locator('#sheet');
  await sheet.getByLabel('Gold').fill('500'); // from 412 gp
  await sheet.getByRole('button', { name: 'Save' }).click();
  await expect(page.locator('#toasts')).toContainText('500 gp');

  await page.goto(`/dm/history/${BRYN}`);
  await expect(entries(page).first()).toContainText('+88 gp');
  await expect(entries(page).first()).toContainText('Purse changed');
});

test('history outlives a character who leaves the party', async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm/party');
  await page.locator(`#party-${WREN}`).getByRole('button', { name: 'Remove', exact: true }).click();
  await expect(page.locator(`#party-${WREN}`)).toHaveCount(0);

  await page.goto('/dm/history');
  await expect(page.getByRole('heading', { name: 'Pack History' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Wren Holloway' })).toHaveCount(0);
  await expect(entries(page).filter({ hasText: 'Wren Holloway (left the party)' }).first()).toBeVisible();
});
