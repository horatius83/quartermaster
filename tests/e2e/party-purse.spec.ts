import { test, expect, signIn, signInAsDm, toasts, BRYN, WREN, type OpenPage } from './fixtures';
import type { Page } from '@playwright/test';

// The seed's party holds 412 + 288 + 517 + 67 = 1,284 gp between them. Bryn has 3 Potions of Healing.

/** As the DM, start or stop sharing the first campaign's purse. */
async function setSharing(page: Page, on: boolean): Promise<void> {
  await signInAsDm(page);
  await page.goto('/dm/campaigns');
  const row = page.locator('tbody tr', { hasText: 'Running now' });
  await row.getByRole('button', { name: on ? 'Share one purse' : 'Stop sharing' }).click();
  await expect(toasts(page)).toContainText(on ? 'now shares one purse' : 'purses of their own again');
}

/** Another tab of the DM's, which accepts confirm dialogs as the test's own page does. */
async function otherDm(openPage: OpenPage): Promise<Page> {
  const other = await openPage();
  other.on('dialog', (dialog) => dialog.accept());
  return other;
}

const purse = (page: Page) => page.locator('#purse');
const stashRow = (page: Page, item: string) => page.locator('#party-stash tbody tr', { hasText: item });

test('sharing pools the party’s coin, and carts are paid from it', async ({ page }) => {
  await setSharing(page, true);
  await expect(page.locator('tbody tr', { hasText: 'Running now' })).toContainText('One shared purse');
  await page.goto('/dm');
  await expect(page.locator('#party-purse-card')).toContainText('1,284');
  await page.goto(`/dm/inventory/${BRYN}`);
  await expect(page.locator('#sheet')).toContainText('Their coin is in the party purse.');

  await signIn(page, 'bryn');
  await expect(purse(page)).toHaveAttribute('aria-label', 'Party purse: 1,284 gp');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await page.getByRole('button', { name: 'Add Longsword to cart' }).click();
  await expect(page.locator('#cart')).toContainText('The party purse will have 1,269 gp left.');
  await page.locator('#cart').getByRole('button', { name: 'Send to DM for approval' }).click();

  await signInAsDm(page);
  await page.goto('/dm/carts');
  const card = page.locator('article', { hasText: 'Bryn Ashvale' });
  await expect(card).toContainText('Party purse: 1,284 gp → 1,269 gp after purchase.');
  await card.getByRole('button', { name: 'Approve' }).click();
  await expect(toasts(page)).toContainText('The party purse paid 15 gp for Bryn Ashvale’s cart.');

  // Everyone sees the same purse; the other campaign keeps its own.
  await signIn(page, 'wren');
  await expect(purse(page)).toHaveAttribute('aria-label', 'Party purse: 1,269 gp');
  await page.goto('/history');
  await expect(page.locator('tbody tr', { hasText: 'Paid at The Rusty Anvil, for Bryn Ashvale' })).toContainText('Party');
  await signIn(page, 'sela');
  await expect(purse(page)).toHaveAttribute('aria-label', 'Purse: 45 gp');
});

test('a sale pays into the party purse', async ({ page }) => {
  await setSharing(page, true);
  await signIn(page, 'bryn');
  await page.goto('/pack');
  const row = page.locator('tbody tr', { hasText: 'Potion of Healing' }).first();
  await row.getByText('Sell…').click();
  await row.getByLabel('How many').first().fill('1');
  await row.getByLabel('Asking price, for all').fill('40 gp');
  await row.getByRole('button', { name: 'Offer to the DM' }).click();
  await expect(page.locator('#sales')).toContainText('1 × Potion of Healing');

  await signInAsDm(page);
  await page.goto('/dm/sales');
  const card = page.locator('article.sale-card', { hasText: 'Bryn Ashvale' });
  await expect(card).toContainText('Party purse: 1,284 gp → 1,324 gp');
  await card.getByRole('button', { name: 'Accept 40 gp' }).click();
  await expect(card.locator('.status')).toHaveText('Sold');

  await page.goto('/dm/history/party');
  await expect(page.locator('tbody tr', { hasText: 'sold by Bryn Ashvale' })).toContainText('+40 gp');
  await signIn(page, 'tamsin');
  await expect(purse(page)).toHaveAttribute('aria-label', 'Party purse: 1,324 gp');
});

test('players put items in the party stash and anyone can take them', async ({ page }) => {
  // Without a shared purse there's no stash.
  await signIn(page, 'bryn');
  await page.goto('/pack');
  await expect(page.locator('#party-stash')).toHaveCount(0);

  await setSharing(page, true);
  await signIn(page, 'bryn');
  await page.goto('/pack');
  const potions = page.locator('tbody tr', { hasText: 'Potion of Healing' }).first();
  await potions.getByText('To party…').click();
  await potions.getByLabel('How many').last().fill('2');
  await potions.getByRole('button', { name: 'Put in the party stash' }).click();
  await expect(toasts(page)).toContainText('Put 2 × Potion of Healing in the party stash.');
  await expect(stashRow(page, 'Potion of Healing').locator('td').nth(2)).toHaveText('2');
  await expect(page.locator('tbody tr', { hasText: 'Potion of Healing' }).first().locator('td').nth(2)).toHaveText('1');

  await signIn(page, 'wren');
  await page.goto('/pack');
  const stashed = stashRow(page, 'Potion of Healing');
  await stashed.getByText('Take…').click();
  await stashed.getByLabel('How many').fill('1');
  await stashed.getByRole('button', { name: 'Take into my pack' }).click();
  await expect(toasts(page)).toContainText('Took 1 × Potion of Healing from the party stash.');
  await expect(stashRow(page, 'Potion of Healing').locator('td').nth(2)).toHaveText('1');

  await signInAsDm(page);
  await page.goto(`/dm/history/${WREN}`);
  await expect(page.locator('tbody tr', { hasText: 'From the party stash' })).toContainText('Potion of Healing');
  await page.goto('/dm/party-stash');
  await expect(page.locator('#inv-body tr', { hasText: 'Potion of Healing' }).locator('output')).toHaveText('1');
});

test('items on offer to sell can’t go in the stash', async ({ page }) => {
  await setSharing(page, true);
  await signIn(page, 'bryn');
  await page.goto('/pack');
  const row = page.locator('tbody tr', { hasText: 'Potion of Healing' }).first();
  await row.getByText('Sell…').click();
  await row.getByLabel('How many').first().fill('2');
  await row.getByLabel('Asking price, for all').fill('80 gp');
  await row.getByRole('button', { name: 'Offer to the DM' }).click();
  await expect(page.locator('#sales')).toContainText('2 × Potion of Healing');

  const after = page.locator('tbody tr', { hasText: 'Potion of Healing' }).first();
  await after.getByText('To party…').click();
  const qty = after.getByLabel('How many').last();
  await expect(qty).toHaveAttribute('max', '1');
  await qty.evaluate((el) => el.removeAttribute('max'));
  await qty.fill('2');
  await after.getByRole('button', { name: 'Put in the party stash' }).click();
  await expect(toasts(page)).toContainText('There’s only 1 that can go.');
});

test('a sheet drawn while the purse was shared doesn’t empty a purse after sharing stops', async ({ page, openPage }) => {
  await setSharing(page, true);
  await page.goto(`/dm/inventory/${BRYN}`);
  const sheet = page.locator('#sheet');
  await expect(sheet).toContainText('Their coin is in the party purse.');

  // Meanwhile, in another tab, sharing stops and Bryn gets a quarter of the 1,284 gp.
  await setSharing(await otherDm(openPage), false);

  await sheet.getByLabel('Class & level').fill('Fighter 6');
  await sheet.getByRole('button', { name: 'Save' }).click();
  await expect(toasts(page)).toContainText('Saved Bryn Ashvale’s purse: 321 gp.');
  await page.goto('/dm/party');
  await expect(page.locator('tr', { hasText: 'Bryn Ashvale' })).toContainText('321 gp');
});

test('a stale page can’t move a stack into a stash that’s no longer shared', async ({ page, openPage }) => {
  await setSharing(page, true);
  await page.goto(`/dm/inventory/${BRYN}`);
  await setSharing(await otherDm(openPage), false);

  const rope = page.locator('#inv-body tr', { hasText: 'Hempen rope' });
  await rope.getByText('Move to…').click();
  await rope.getByLabel('Move Hempen rope (50 ft) to').selectOption({ label: 'Party stash' });
  await rope.getByRole('button', { name: 'Move', exact: true }).click();
  await expect(toasts(page)).toContainText('no longer shares a party stash');
  await page.reload();
  await expect(page.locator('#inv-body tr', { hasText: 'Hempen rope' })).toHaveCount(1);
});

test('the DM moves a stack into the stash, edits the purse, and stopping splits it evenly', async ({ page }) => {
  await setSharing(page, true);
  await page.goto(`/dm/inventory/${BRYN}`);
  const rope = page.locator('#inv-body tr', { hasText: 'Hempen rope' });
  await rope.getByText('Move to…').click();
  await rope.getByLabel('Move Hempen rope (50 ft) to').selectOption({ label: 'Party stash' });
  await rope.getByRole('button', { name: 'Move', exact: true }).click();
  await expect(toasts(page)).toContainText('Moved 1 × Hempen rope (50 ft) to the party stash.');

  await page.getByRole('link', { name: 'Party stash' }).click();
  await expect(page.locator('#inv-body tr', { hasText: 'Hempen rope' })).toHaveCount(1);
  // 1,284 gp 3 cp splits four ways as 321 gp, with the 3 odd coppers going to Bryn, Oskar and Tamsin.
  const form = page.locator('#party-purse');
  await form.getByLabel('Copper').fill('3');
  await form.getByRole('button', { name: 'Save purse' }).click();
  await expect(toasts(page)).toContainText('Saved the party purse: 1,284 gp 3 cp.');

  await setSharing(page, false);
  await page.goto('/dm/party');
  await expect(page.locator('tr', { hasText: 'Bryn Ashvale' })).toContainText('321 gp 1 cp');
  await expect(page.locator('tr', { hasText: 'Wren Holloway' })).toContainText('321 gp');
  await expect(page.locator('tr', { hasText: 'Wren Holloway' })).not.toContainText('321 gp 1 cp');

  // The rope waits in the stash for the DM, but players no longer see it.
  await page.goto('/dm/party-stash');
  await expect(page.locator('main')).toContainText('no longer shares a purse');
  const stashedRope = page.locator('#inv-body tr', { hasText: 'Hempen rope' });
  await stashedRope.getByText('Give to…').click();
  await stashedRope.getByLabel('Give Hempen rope (50 ft) to').selectOption({ label: 'Wren Holloway' });
  await stashedRope.getByRole('button', { name: 'Give', exact: true }).click();
  await expect(toasts(page)).toContainText('Gave 1 × Hempen rope (50 ft) to Wren Holloway.');

  await signIn(page, 'bryn');
  await expect(purse(page)).toHaveAttribute('aria-label', 'Purse: 321 gp 1 cp');
  await page.goto('/pack');
  await expect(page.locator('#party-stash')).toHaveCount(0);
});
