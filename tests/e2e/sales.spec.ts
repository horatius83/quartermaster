import { test, expect, signIn, signInAsDm, toasts, BRYN } from './fixtures';
import type { Page } from '@playwright/test';

// Bryn starts with 412 gp and 3 Potions of Healing (50 gp each in shops).

/** As the signed-in player, offer some of a pack stack to the DM. */
async function offer(page: Page, item: string, quantity: number, price: string, note = ''): Promise<void> {
  await page.goto('/pack');
  const row = page.locator('tbody tr', { hasText: item });
  await row.locator('summary').click();
  await row.getByLabel('How many').fill(String(quantity));
  await row.getByLabel('Asking price, for all').fill(price);
  if (note) {
    await row.getByLabel('Note to the DM (optional)').fill(note);
  }
  await row.getByRole('button', { name: 'Offer to the DM' }).click();
  await expect(page.locator('#sales')).toContainText(`${quantity} × ${item}`);
}

const saleCard = (page: Page) => page.locator('article.sale-card', { hasText: 'Bryn Ashvale' });
const potionRow = (page: Page) => page.locator('tbody tr', { hasText: 'Potion of Healing' });

test('the DM accepts an offer at the asking price', async ({ page }) => {
  await signIn(page, 'bryn');
  await offer(page, 'Potion of Healing', 2, '60 gp', 'Found a cleric who needs them');
  await expect(page.locator('#sales .status')).toHaveText('Waiting on DM');
  // Nothing leaves the pack until it's sold.
  await expect(potionRow(page).locator('td').nth(2)).toHaveText('3');

  await signInAsDm(page);
  await expect(page.locator('#sale-badge')).toHaveText('1');
  await page.goto('/dm/sales');
  const card = saleCard(page);
  await expect(card).toContainText('Found a cleric who needs them');
  await expect(card).toContainText('412 gp → 472 gp');
  await card.getByRole('button', { name: 'Accept 60 gp' }).click();
  await expect(card.locator('.status')).toHaveText('Sold');
  await expect(page.locator('#sale-badge')).toHaveText('');

  await page.goto(`/dm/history/${BRYN}`);
  await expect(page.locator('tbody tr', { hasText: 'Potion of Healing' }).first()).toContainText('Sold');

  await signIn(page, 'bryn');
  await page.goto('/pack');
  await expect(page.locator('#purse')).toHaveAttribute('aria-label', 'Purse: 472 gp');
  await expect(potionRow(page).locator('td').nth(2)).toHaveText('1');
  await expect(page.locator('#sales .status')).toHaveText('Sold');
});

test('the DM counters and the player accepts the counter-offer', async ({ page }) => {
  await signIn(page, 'bryn');
  await offer(page, 'Potion of Healing', 3, '150 gp');

  await signInAsDm(page);
  await page.goto('/dm/sales');
  const card = saleCard(page);
  await card.getByLabel('Counter-offer, for all').fill('90 gp');
  await card.getByLabel(/^Note/).fill('The apothecary is overstocked.');
  await card.getByRole('button', { name: 'Send counter-offer' }).click();
  await expect(card.locator('.status')).toHaveText('Countered');
  // A counter-offer waits on the player, not the DM.
  await expect(page.locator('#sale-badge')).toHaveText('');

  await signIn(page, 'bryn');
  await page.goto('/pack');
  const sales = page.locator('#sales');
  await expect(sales).toContainText('Your DM offers 90 gp for the lot.');
  await expect(sales).toContainText('The apothecary is overstocked.');
  await sales.getByRole('button', { name: 'Accept 90 gp' }).click();
  await expect(toasts(page)).toContainText('Sold 3 × Potion of Healing for 90 gp.');
  await expect(page.locator('#purse')).toHaveAttribute('aria-label', 'Purse: 502 gp');
  await expect(potionRow(page)).toHaveCount(0);

  await signInAsDm(page);
  await page.goto('/dm/sales');
  await expect(saleCard(page).locator('.status')).toHaveText('Sold');
  await expect(saleCard(page)).toContainText('Sold for');
});

test('the player declines a counter-offer and keeps the items', async ({ page }) => {
  await signIn(page, 'bryn');
  await offer(page, 'Shield', 1, '10 gp');

  await signInAsDm(page);
  await page.goto('/dm/sales');
  await saleCard(page).getByLabel('Counter-offer, for all').fill('2 gp');
  await saleCard(page).getByRole('button', { name: 'Send counter-offer' }).click();
  await expect(saleCard(page).locator('.status')).toHaveText('Countered');

  await signIn(page, 'bryn');
  await page.goto('/pack');
  await page.locator('#sales').getByRole('button', { name: 'Decline' }).click();
  await expect(page.locator('#sales .status')).toHaveText('You declined');
  await expect(page.locator('#purse')).toHaveAttribute('aria-label', 'Purse: 412 gp');
  await expect(page.locator('tbody tr', { hasText: 'Shield' })).toHaveCount(1);

  await signInAsDm(page);
  await page.goto('/dm/sales');
  await expect(saleCard(page).locator('.status')).toHaveText('Declined');
});

test('the DM rejects an offer with a reason the player sees', async ({ page }) => {
  await signIn(page, 'bryn');
  await offer(page, 'Shield', 1, '10 gp');

  await signInAsDm(page);
  await page.goto('/dm/sales');
  await saleCard(page).getByLabel(/^Note/).fill('Nobody in town wants a dented shield.');
  await saleCard(page).getByRole('button', { name: 'Reject' }).click();
  await expect(saleCard(page).locator('.status')).toHaveText('Rejected');

  await signIn(page, 'bryn');
  await page.goto('/pack');
  await expect(page.locator('#sales .status')).toHaveText('Rejected');
  await expect(page.locator('#sales')).toContainText('Nobody in town wants a dented shield.');
  await expect(page.locator('tbody tr', { hasText: 'Shield' })).toHaveCount(1);
});

test('a player can only offer what is not already on offer, and can withdraw', async ({ page }) => {
  await signIn(page, 'bryn');
  await offer(page, 'Potion of Healing', 2, '80 gp');
  // One potion is left to offer.
  await potionRow(page).locator('summary').click();
  await expect(potionRow(page).getByLabel('How many')).toHaveValue('1');
  await offer(page, 'Potion of Healing', 1, '40 gp');
  await expect(potionRow(page)).toContainText('On offer');

  await page.locator('#sales li', { hasText: '2 × Potion of Healing' }).getByRole('button', { name: 'Withdraw offer' }).click();
  await expect(toasts(page)).toContainText('Withdrew your offer');
  await expect(page.locator('#sales li')).toHaveCount(1);
  await potionRow(page).locator('summary').click();
  await expect(potionRow(page).getByLabel('How many')).toHaveValue('2');
});

test('an offer for items no longer in the pack cannot be accepted', async ({ page }) => {
  await signIn(page, 'bryn');
  await offer(page, 'Shield', 1, '10 gp');

  await signInAsDm(page);
  await page.goto(`/dm/inventory/${BRYN}`);
  await page.locator('tbody tr', { hasText: 'Shield' }).getByRole('button', { name: 'Remove', exact: true }).click();
  await expect(page.locator('tbody tr', { hasText: 'Shield' })).toHaveCount(0);

  await page.goto('/dm/sales');
  await expect(saleCard(page)).toContainText('No longer in the pack');
  await saleCard(page).getByRole('button', { name: 'Accept 10 gp' }).click();
  await expect(toasts(page)).toContainText('no longer has the Shield');
  await expect(saleCard(page).locator('.status')).toHaveText('Pending');
});

test('a counter-offer changed meanwhile is not accepted unseen', async ({ page, openPage }) => {
  await signIn(page, 'bryn');
  await offer(page, 'Shield', 1, '10 gp');

  const dm = await openPage();
  await signInAsDm(dm);
  await dm.goto('/dm/sales');
  await saleCard(dm).getByLabel('Counter-offer, for all').fill('8 gp');
  await saleCard(dm).getByRole('button', { name: 'Send counter-offer' }).click();
  await expect(saleCard(dm).locator('.status')).toHaveText('Countered');

  await page.goto('/pack');
  await expect(page.locator('#sales')).toContainText('Your DM offers 8 gp');

  await saleCard(dm).getByLabel('Counter-offer, for all').fill('3 gp');
  await saleCard(dm).getByRole('button', { name: 'Change counter-offer' }).click();
  await expect(saleCard(dm)).toContainText('3 gp');

  await page.locator('#sales').getByRole('button', { name: 'Accept 8 gp' }).click();
  await expect(toasts(page)).toContainText('Your DM changed their offer to 3 gp.');
  await expect(page.locator('#purse')).toHaveAttribute('aria-label', 'Purse: 412 gp');
  await expect(page.locator('#sales')).toContainText('Your DM offers 3 gp');
});

test('the DM can accept the asking price after countering, and a counter-offer must be a price', async ({ page }) => {
  await signIn(page, 'bryn');
  await offer(page, 'Shield', 1, '10 gp');

  await signInAsDm(page);
  await page.goto('/dm/sales');
  await saleCard(page).getByLabel('Counter-offer, for all').fill('0');
  await saleCard(page).getByRole('button', { name: 'Send counter-offer' }).click();
  await expect(toasts(page)).toContainText('Name your counter-offer');
  await expect(saleCard(page).locator('.status')).toHaveText('Pending');

  await saleCard(page).getByLabel('Counter-offer, for all').fill('6 gp');
  await saleCard(page).getByRole('button', { name: 'Send counter-offer' }).click();
  await expect(saleCard(page).locator('.status')).toHaveText('Countered');

  // The player sees the counter-offer waiting on them.
  await signIn(page, 'bryn');
  await page.goto('/pack');
  await expect(page.locator('#offer-badge')).toContainText('1');

  await signInAsDm(page);
  await page.goto('/dm/sales');
  await saleCard(page).getByRole('button', { name: 'Accept 10 gp' }).click();
  await expect(saleCard(page).locator('.status')).toHaveText('Sold');
  await expect(saleCard(page)).toContainText('Sold for');

  await signIn(page, 'bryn');
  await page.goto('/pack');
  await expect(page.locator('#purse')).toHaveAttribute('aria-label', 'Purse: 422 gp');
  await expect(page.locator('#sales')).toContainText('sold for 10 gp');
  await expect(page.locator('#offer-badge')).toHaveText('');
});

test('an item whose offers are all settled can still be deleted from the catalog', async ({ page }) => {
  await signIn(page, 'bryn');
  await offer(page, 'Light crossbow', 1, '20 gp');

  await signInAsDm(page);
  await page.goto('/dm/sales');
  await saleCard(page).getByRole('button', { name: 'Reject' }).click();
  await expect(saleCard(page).locator('.status')).toHaveText('Rejected');
  await page.goto(`/dm/inventory/${BRYN}`);
  await page.locator('tbody tr', { hasText: 'Light crossbow' }).getByRole('button', { name: 'Remove', exact: true }).click();
  await expect(page.locator('tbody tr', { hasText: 'Light crossbow' })).toHaveCount(0);

  await page.goto('/dm/catalog?q=Light+crossbow');
  await page.locator('#catalog-body tr').first().getByRole('button', { name: 'Delete' }).click();
  await expect(toasts(page)).toContainText('Deleted Light crossbow.');
});
