import type { Page } from '@playwright/test';
import { test, expect, signIn, signInAsDm, toasts, BRYN, SELA, FROSTGATE, SECOND_CAMPAIGN } from './fixtures';

/** Pick a campaign in the sidebar and wait for the dashboard it lands on, so the next goto doesn't race the redirect. */
const switchTo = async (page: Page, campaign: string) => {
  await page.getByLabel('Campaign', { exact: true }).selectOption({ label: campaign });
  await expect(toasts(page)).toContainText(`Now running ${campaign}.`);
  await expect(page).toHaveURL(/\/dm$/);
};

test('the DM sees one campaign at a time and can switch', async ({ page }) => {
  await signInAsDm(page);
  const party = page.getByRole('region', { name: 'The Party' });
  await expect(page.locator('.page-head')).toContainText('The Sunken Crown');
  await expect(party).toContainText('Bryn Ashvale');
  await expect(party).not.toContainText('Sela Morrow');

  await switchTo(page, SECOND_CAMPAIGN);
  await expect(page.locator('.page-head')).toContainText(SECOND_CAMPAIGN);
  await expect(party).toContainText('Sela Morrow');
  await expect(party).not.toContainText('Bryn Ashvale');

  // The choice sticks across pages.
  await page.goto('/dm/stores');
  await expect(page.getByRole('navigation', { name: 'Stores' })).toHaveText(/The Frostgate Market/);
  await expect(page.getByRole('navigation', { name: 'Stores' })).not.toContainText('The Rusty Anvil');
  await page.goto('/dm/history');
  await expect(page.locator('tbody')).toContainText('Sela Morrow');
  await expect(page.locator('tbody')).not.toContainText('Bryn Ashvale');
});

test('pages from another campaign are out of reach', async ({ page }) => {
  await signInAsDm(page);
  for (const path of [`/dm/inventory/${SELA}`, `/dm/history/${SELA}`]) {
    await page.goto(path);
    await expect(page.getByText('No such character.')).toBeVisible();
  }
  await page.goto(`/dm/stores/${FROSTGATE}`);
  await expect(page.getByText('No such store.')).toBeVisible();

  await page.goto('/dm');
  await switchTo(page, SECOND_CAMPAIGN);
  await page.goto(`/dm/inventory/${BRYN}`);
  await expect(page.getByText('No such character.')).toBeVisible();
});

test('a player only sees shops in their own campaign', async ({ page }) => {
  await signIn(page, 'sela');
  await expect(page).toHaveURL(new RegExp(`/shop/${FROSTGATE}$`));
  await expect(page.getByRole('main')).not.toContainText('The Rusty Anvil');
  await page.goto('/shop/1');
  await expect(page.getByText('That shop is closed to you.')).toBeVisible();

  await signIn(page, 'bryn');
  await page.goto(`/shop/${FROSTGATE}`);
  await expect(page.getByText('That shop is closed to you.')).toBeVisible();
});

test('carts wait in their own campaign', async ({ page }) => {
  await signIn(page, 'sela');
  await page.getByRole('button', { name: 'Add Torch to cart' }).click();
  await page.locator('#cart').getByRole('button', { name: 'Send to DM for approval' }).click();
  await expect(page.locator('#cart')).toContainText('Waiting on your DM.');

  await signInAsDm(page);
  await expect(page.locator('#cart-badge')).toHaveText('');
  await page.goto('/dm/carts');
  await expect(page.locator('article', { hasText: 'Sela Morrow' })).toHaveCount(0);

  await page.goto('/dm');
  await switchTo(page, SECOND_CAMPAIGN);
  await expect(page.locator('#cart-badge')).toHaveText('1');
  await page.goto('/dm/carts');
  const card = page.locator('article', { hasText: 'Sela Morrow' });
  await card.getByRole('button', { name: 'Approve' }).click();
  await expect(card.locator('.status')).toHaveText('Approved');
});

test('a new campaign starts with an empty party and can be renamed and deleted', async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm/campaigns');
  await page.getByLabel('New campaign').fill('Tomb of Ash');
  await page.getByRole('button', { name: 'Start campaign' }).click();
  await expect(page).toHaveURL(/\/dm\/party$/);
  await expect(page.getByLabel('Campaign', { exact: true }).locator('option:checked')).toHaveText('Tomb of Ash');
  await expect(page.locator('tbody')).toContainText('No one has joined yet.');

  // A character added here stays here.
  await page.getByLabel('Character name').fill('Ilse Varn');
  await page.getByRole('button', { name: 'Add to party' }).click();
  await expect(page.locator('tbody')).toContainText('Ilse Varn');
  await switchTo(page, 'The Sunken Crown');
  await page.goto('/dm/party');
  await expect(page.locator('tbody')).not.toContainText('Ilse Varn');

  // A campaign with a party can't be deleted; once empty it can. Find its row by name once, then by id,
  // since renaming changes the name.
  await page.goto('/dm/campaigns');
  const rowId = await page.locator('tbody tr', { has: page.locator('input[value="Tomb of Ash"]') }).getAttribute('id');
  const row = page.locator(`#${rowId}`);
  await expect(row.getByRole('button', { name: 'Delete' })).toHaveCount(0);
  await row.getByLabel(/^Name of/).fill('Tomb of Embers');
  await row.getByRole('button', { name: 'Rename' }).click();
  await expect(toasts(page)).toContainText('Renamed Tomb of Ash to Tomb of Embers.');

  await row.getByRole('button', { name: 'Switch to' }).click();
  await expect(toasts(page)).toContainText('Now running Tomb of Embers.');
  await page.goto('/dm/party');
  await page.locator('tr', { hasText: 'Ilse Varn' }).getByRole('button', { name: 'Remove', exact: true }).click();
  await expect(page.locator('tr', { hasText: 'Ilse Varn' })).toHaveCount(0);

  await page.goto('/dm/campaigns');
  await row.getByRole('button', { name: 'Delete' }).click();
  await expect(toasts(page)).toContainText('Deleted Tomb of Embers.');
  await expect(page.getByLabel('Campaign', { exact: true }).locator('option')).toHaveText(['The Sunken Crown', SECOND_CAMPAIGN]);
});
