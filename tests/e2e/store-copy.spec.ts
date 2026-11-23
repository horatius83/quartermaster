import type { Page } from '@playwright/test';
import { test, expect, signIn, signInAsDm, toasts, SECOND_CAMPAIGN } from './fixtures';

// The seed's first store, in The Sunken Crown. Both seeded campaigns play 5e.
const RUSTY_ANVIL = 1;

const storeList = (page: Page) => page.getByRole('navigation', { name: 'Stores' });
/** The open store's details form (the new-store form has a Kind of shop too). */
const details = (page: Page) => page.locator('form', { has: page.locator('#store-h') });

async function copyStore(page: Page, storeId: number, option: string): Promise<void> {
  await page.goto(`/dm/stores/${storeId}`);
  await page.getByLabel('Copy to campaign').selectOption({ label: option });
  await page.getByRole('button', { name: 'Copy store' }).click();
}

async function switchTo(page: Page, campaign: string): Promise<void> {
  await page.getByLabel('Campaign', { exact: true }).selectOption({ label: campaign });
  await expect(toasts(page)).toContainText(`Now running ${campaign}.`);
  await expect(page).toHaveURL(/\/dm$/);
}

test('a campaign of the same system gets a copy with the shelves, and the store stays', async ({ page }) => {
  // Bryn has a cart waiting on the DM here; it stays with this store.
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await page.getByRole('button', { name: 'Add Shield to cart' }).click();
  await page.locator('#cart').getByRole('button', { name: 'Send to DM for approval' }).click();
  await expect(page.locator('#cart')).toContainText('Waiting on your DM.');

  await signInAsDm(page);
  await page.goto(`/dm/stores/${RUSTY_ANVIL}`);
  await expect(page.getByLabel('Copy to campaign').locator('option')).toHaveText([`${SECOND_CAMPAIGN} (5e, shelves too)`]);
  await copyStore(page, RUSTY_ANVIL, `${SECOND_CAMPAIGN} (5e, shelves too)`);
  await expect(toasts(page)).toContainText(`Copied The Rusty Anvil to ${SECOND_CAMPAIGN}, shelves and all.`);

  // The original is untouched: still here, still open, still stocked, and Bryn's cart still waits.
  await expect(page).toHaveURL(new RegExp(`/dm/stores/${RUSTY_ANVIL}$`));
  await expect(page.getByRole('switch')).toHaveText('Open to players');
  await expect(page.locator('#stock-body')).toContainText('Chain mail');
  await page.goto('/dm/carts');
  await expect(page.locator('article', { hasText: 'Bryn Ashvale' })).toContainText('Shield');

  // The copy starts hidden, with the same shelves and kind, and the whole party ticked.
  await page.goto('/dm');
  await switchTo(page, SECOND_CAMPAIGN);
  await page.goto('/dm/stores');
  await storeList(page).getByRole('link', { name: /The Rusty Anvil/ }).click();
  // /dm/stores opens The Frostgate Market first; wait for the copy's page before using its switch.
  await expect(page.locator('#store-h')).toHaveText('The Rusty Anvil');
  await expect(page).not.toHaveURL(new RegExp(`/dm/stores/${RUSTY_ANVIL}$`));
  await expect(page.getByRole('switch')).toHaveText('Hidden from players');
  await expect(details(page).getByLabel('Kind of shop')).toHaveValue('Smithy');
  const row = page.locator('#stock-body tr', { hasText: 'Chain mail' });
  await expect(row.locator('input[name="quantity"]')).toHaveValue('1');
  await expect(page.getByRole('checkbox', { name: 'Sela Morrow' })).toBeChecked();
  // No cart came along.
  await page.goto('/dm/carts');
  await expect(page.locator('article', { hasText: 'Bryn Ashvale' })).toHaveCount(0);

  // Once opened, Sela can shop there.
  await page.goto('/dm/stores');
  await storeList(page).getByRole('link', { name: /The Rusty Anvil/ }).click();
  // /dm/stores opens The Frostgate Market first; wait for the copy's page before using its switch.
  await expect(page.locator('#store-h')).toHaveText('The Rusty Anvil');
  await page.getByRole('switch').click();
  await expect(page.getByRole('switch')).toHaveText('Open to players');
  await signIn(page, 'sela');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(page.getByRole('button', { name: 'Add Longsword to cart' })).toBeVisible();
});

test('a campaign of another system gets an empty store of the same name', async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm/campaigns');
  await page.getByLabel('New campaign').fill('Tomb of Ash');
  await page.locator('form', { has: page.getByLabel('New campaign') }).getByLabel(/^System/).selectOption({ label: '3.5e' });
  await page.getByRole('button', { name: 'Start campaign' }).click();
  await expect(page).toHaveURL(/\/dm\/party$/);
  await page.goto('/dm');
  await switchTo(page, 'The Sunken Crown');

  await copyStore(page, RUSTY_ANVIL, 'Tomb of Ash (3.5e, empty shelves)');
  await expect(toasts(page)).toContainText('Tomb of Ash plays 3.5e, so these shelves can’t go there.');
  // This one stays as it was.
  await expect(page).toHaveURL(new RegExp(`/dm/stores/${RUSTY_ANVIL}$`));
  await expect(page.getByRole('switch')).toHaveText('Open to players');
  await expect(page.locator('#stock-body')).toContainText('Chain mail');

  await switchTo(page, 'Tomb of Ash');
  await page.goto('/dm/stores');
  await expect(storeList(page)).toHaveText(/The Rusty Anvil/);
  await expect(details(page).getByLabel('Kind of shop')).toHaveValue('Smithy');
  await expect(page.getByLabel('Shopkeeper’s note to players')).toHaveValue('Steel, mail and plate. Repairs while you wait.');
  await expect(page.getByRole('switch')).toHaveText('Hidden from players');
  await expect(page.locator('#stock-body')).toContainText('Empty shelves.');
});
