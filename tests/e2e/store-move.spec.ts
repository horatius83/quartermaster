import type { Page } from '@playwright/test';
import { test, expect, signIn, signInAsDm, toasts, SECOND_CAMPAIGN } from './fixtures';

// The seed's first store, in The Sunken Crown. Both seeded campaigns play 5e.
const RUSTY_ANVIL = 1;

const storeList = (page: Page) => page.getByRole('navigation', { name: 'Stores' });
/** The open store's details form (the new-store form has a Kind of shop too). */
const details = (page: Page) => page.locator('form', { has: page.locator('#store-h') });

async function moveStore(page: Page, storeId: number, option: string): Promise<void> {
  await page.goto(`/dm/stores/${storeId}`);
  await page.getByLabel('Move to campaign').selectOption({ label: option });
  await page.getByRole('button', { name: 'Move store' }).click();
}

async function switchTo(page: Page, campaign: string): Promise<void> {
  await page.getByLabel('Campaign', { exact: true }).selectOption({ label: campaign });
  await expect(toasts(page)).toContainText(`Now running ${campaign}.`);
  await expect(page).toHaveURL(/\/dm$/);
}

test('a store moves to a campaign of the same system with its shelves', async ({ page }) => {
  // Bryn buys a longsword here first, and leaves a shield in a cart not yet sent.
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await page.getByRole('button', { name: 'Add Longsword to cart' }).click();
  await page.locator('#cart').getByRole('button', { name: 'Send to DM for approval' }).click();
  await signInAsDm(page);
  await page.goto('/dm/carts');
  await page.locator('article', { hasText: 'Bryn Ashvale' }).getByRole('button', { name: 'Approve' }).click();
  await expect(page.locator('article', { hasText: 'Bryn Ashvale' }).locator('.status')).toHaveText('Approved');
  await signIn(page, 'bryn');
  await page.goto(`/shop/${RUSTY_ANVIL}`);
  await page.getByRole('button', { name: 'Add Shield to cart' }).click();
  await expect(page.locator('#cart .cart-line', { hasText: 'Shield' })).toBeVisible();

  await signInAsDm(page);
  // The only other campaign plays 5e too, so the shelves can go.
  await page.goto(`/dm/stores/${RUSTY_ANVIL}`);
  await expect(page.getByLabel('Move to campaign').locator('option')).toHaveText([`${SECOND_CAMPAIGN} (5e, shelves go too)`]);
  await moveStore(page, RUSTY_ANVIL, `${SECOND_CAMPAIGN} (5e, shelves go too)`);
  await expect(toasts(page)).toContainText(`The Rusty Anvil moved to ${SECOND_CAMPAIGN}, shelves and all.`);
  await expect(toasts(page)).toContainText('An open cart here was emptied.');
  await expect(storeList(page)).not.toContainText('The Rusty Anvil');
  await page.goto(`/dm/stores/${RUSTY_ANVIL}`);
  await expect(page.getByText('No such store.')).toBeVisible();

  // Bryn can't shop there any more, and still has the receipt.
  await signIn(page, 'bryn');
  await page.goto(`/shop/${RUSTY_ANVIL}`);
  await expect(page.getByText('That shop is closed to you.')).toBeVisible();
  await page.goto('/pack');
  await expect(page.locator('li', { hasText: 'The Rusty Anvil' }).locator('.status')).toHaveText('Approved');

  // It arrives hidden, with its stock and its kind, and no one's left out once it opens.
  await signInAsDm(page);
  await page.goto('/dm');
  await switchTo(page, SECOND_CAMPAIGN);
  await page.goto(`/dm/stores/${RUSTY_ANVIL}`);
  await expect(page.getByRole('switch')).toHaveText('Hidden from players');
  await expect(details(page).getByLabel('Kind of shop')).toHaveValue('Smithy');
  await expect(page.locator('#stock-body')).toContainText('Chain mail');
  await expect(page.getByRole('checkbox', { name: 'Sela Morrow' })).toBeChecked();
  await page.getByRole('switch').click();
  await expect(page.getByRole('switch')).toHaveText('Open to players');
  // Bryn's receipt still hangs on this store, so deleting it here says so.
  await expect(page.getByRole('button', { name: 'Delete store' })).toHaveAttribute('hx-confirm', /So will 1 receipt from The Sunken Crown, from before it moved\./);

  await signIn(page, 'sela');
  await page.goto(`/shop/${RUSTY_ANVIL}`);
  await expect(page.getByRole('button', { name: 'Add Longsword to cart' })).toBeVisible();
});

test('a store with a cart waiting on the DM stays put', async ({ page }) => {
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await page.getByRole('button', { name: 'Add Shield to cart' }).click();
  await page.locator('#cart').getByRole('button', { name: 'Send to DM for approval' }).click();
  await expect(page.locator('#cart')).toContainText('Waiting on your DM.');

  await signInAsDm(page);
  await moveStore(page, RUSTY_ANVIL, `${SECOND_CAMPAIGN} (5e, shelves go too)`);
  await expect(toasts(page)).toContainText('A cart at The Rusty Anvil is waiting on you.');
  await expect(page).toHaveURL(new RegExp(`/dm/stores/${RUSTY_ANVIL}$`));
  await expect(storeList(page)).toContainText('The Rusty Anvil');
  await page.goto('/dm/carts');
  await expect(page.locator('article', { hasText: 'Bryn Ashvale' })).toContainText('Shield');
});

test('a campaign of another system gets an empty store of the same name', async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm/campaigns');
  await page.getByLabel('New campaign').fill('Tomb of Ash');
  await page.locator('form', { has: page.getByLabel('New campaign') }).getByLabel(/^System/).selectOption({ label: '3.5e' });
  await page.getByRole('button', { name: 'Start campaign' }).click();
  await expect(page).toHaveURL(/\/dm\/party$/);
  await switchTo(page, 'The Sunken Crown');

  await moveStore(page, RUSTY_ANVIL, 'Tomb of Ash (3.5e, empty store only)');
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
  await expect(page.locator('#stock-body')).not.toContainText('Chain mail');
});
