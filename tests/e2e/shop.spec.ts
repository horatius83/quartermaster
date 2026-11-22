import { test, expect, signIn, signInAsDm } from './fixtures';

test('a player buys something and the DM approves it', async ({ page }) => {
  // Bryn starts with 412 gp; a longsword at The Rusty Anvil costs 15 gp.
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await page.getByRole('button', { name: 'Add Longsword to cart' }).click();

  const cart = page.locator('#cart');
  await expect(cart.locator('.cart-line', { hasText: 'Longsword' })).toBeVisible();
  await expect(cart).toContainText('You’ll have 397 gp left.');
  await cart.getByLabel('Note to the DM (optional)').fill('For my squire');
  await cart.getByRole('button', { name: 'Send to DM for approval' }).click();
  await expect(cart).toContainText('Waiting on your DM.');

  await signInAsDm(page);
  await page.goto('/dm/carts');
  const card = page.locator('article', { hasText: 'Bryn Ashvale' });
  await expect(card).toContainText('For my squire');
  await card.getByRole('button', { name: 'Approve' }).click();
  await expect(card.locator('.status')).toHaveText('Approved');

  await signIn(page, 'bryn');
  await page.goto('/pack');
  await expect(page.locator('#purse')).toHaveAttribute('aria-label', 'Purse: 397 gp');
  // Bought longswords stack separately from Bryn's named +1 blade.
  await expect(page.locator('tbody tr', { hasText: 'Longsword' })).toHaveCount(2);
  await expect(page.locator('li', { hasText: 'The Rusty Anvil' }).locator('.status')).toHaveText('Approved');
});

test('the DM can settle a haggle by changing a price in one cart, not on the shelf', async ({ page }) => {
  // Bryn starts with 412 gp; a longsword at The Rusty Anvil costs 15 gp.
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await page.getByRole('button', { name: 'Add Longsword to cart' }).click();
  await page.getByRole('button', { name: 'One more Longsword' }).click();
  const cart = page.locator('#cart');
  await cart.getByLabel('Note to the DM (optional)').fill('Two for 24 gp?');
  await cart.getByRole('button', { name: 'Send to DM for approval' }).click();
  await expect(cart).toContainText('Waiting on your DM.');

  await signInAsDm(page);
  await page.goto('/dm/carts');
  const card = page.locator('article', { hasText: 'Bryn Ashvale' });
  const price = card.getByLabel('Price of one Longsword, in this cart');
  await expect(price).toHaveValue('15 gp');
  await expect(card.locator('.price-lg')).toHaveText('30 gp');

  // Nonsense, more than one amount, a negative price and a number too big to be one are refused and change nothing.
  for (const [typed, refusal] of [
    ['a fair price', 'Type one price'],
    ['12 gp (was 15 gp)', 'Type one price'],
    ['-5', 'can’t be below 0 gp'],
    ['1e400', 'Type one price'],
  ]) {
    await price.fill(typed);
    await price.press('Tab');
    await expect(page.locator('.toast', { hasText: refusal }).last()).toBeVisible();
    await expect(card.locator('.price-lg')).toHaveText('30 gp');
  }

  await price.fill('12 gp');
  await price.press('Tab');
  await expect(card.locator('.price-lg')).toHaveText('24 gp');
  await expect(card).toContainText('shelf price 15 gp');

  // The player sees the haggled price while the cart waits, and the shelf keeps its own.
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(cart).toContainText('12 gp each · shelf price 15 gp');
  await expect(cart.locator('.price-lg')).toHaveText('24 gp');
  await expect(page.locator('#wares article', { hasText: 'Longsword' })).toContainText('15 gp');

  // The DM can change the quantity and keep the haggled price.
  await signInAsDm(page);
  await page.goto('/dm/carts');
  await card.getByLabel('Quantity of Longsword').fill('3');
  await card.getByLabel('Quantity of Longsword').press('Tab');
  await expect(card.locator('.price-lg')).toHaveText('36 gp');
  await card.getByLabel('Quantity of Longsword').fill('2');
  await card.getByLabel('Quantity of Longsword').press('Tab');
  await expect(card.locator('.price-lg')).toHaveText('24 gp');
  await card.getByRole('button', { name: 'Approve' }).click();
  await expect(card.locator('.status')).toHaveText('Approved');
  await expect(page.locator('.toast', { hasText: 'Bryn Ashvale paid 24 gp' })).toBeVisible();

  await signIn(page, 'bryn');
  await page.goto('/pack');
  await expect(page.locator('#purse')).toHaveAttribute('aria-label', 'Purse: 388 gp');
  await page.goto('/history');
  await expect(page.locator('tr', { hasText: 'Longsword' }).first()).toContainText('for 12 gp each (shelf price 15 gp)');
});

test('a haggled price goes back to the shelf’s when cleared, or when the player changes the quantity', async ({ page }) => {
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await page.getByRole('button', { name: 'Add Longsword to cart' }).click();
  await page.getByRole('button', { name: 'Add Shield to cart' }).click();
  const cart = page.locator('#cart');
  await cart.getByRole('button', { name: 'Send to DM for approval' }).click();

  await signInAsDm(page);
  await page.goto('/dm/carts');
  const card = page.locator('article', { hasText: 'Bryn Ashvale' });
  const sword = card.getByLabel('Price of one Longsword, in this cart');
  const shield = card.getByLabel('Price of one Shield, in this cart');
  await sword.fill('10 gp');
  await sword.press('Tab');
  await expect(card).toContainText('shelf price 15 gp');
  await sword.fill('');
  await sword.press('Tab');
  await expect(sword).toHaveValue('15 gp');
  await expect(card).not.toContainText('shelf price');
  await sword.fill('10 gp');
  await sword.press('Tab');
  await expect(card).toContainText('shelf price 15 gp');
  await shield.fill('5 sp');
  await shield.press('Tab');
  await expect(shield).toHaveValue('5 sp');

  // Bryn withdraws to add a second longsword: that line is a new lot, back at the shelf price. The shield keeps its deal.
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await cart.getByRole('button', { name: 'Withdraw to make changes' }).click();
  await cart.getByRole('button', { name: 'One more Longsword' }).click();
  await expect(cart.locator('.cart-line', { hasText: 'Longsword' })).not.toContainText('shelf price');
  await expect(cart.locator('.cart-line', { hasText: 'Longsword' })).toContainText('30 gp');
  await expect(cart.locator('.cart-line', { hasText: 'Shield' })).toContainText('5 sp each · shelf price 10 gp');
});

test('a haggle marks only prices the DM changed, not a shelf price changed since', async ({ page }) => {
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await page.getByRole('button', { name: 'Add Shield to cart' }).click();
  await page.locator('#cart').getByRole('button', { name: 'Send to DM for approval' }).click();

  await signInAsDm(page);
  await page.goto('/dm/stores');
  await page.getByRole('link', { name: /The Rusty Anvil/ }).click();
  const shieldPrice = page.getByLabel('Price of Shield', { exact: true });
  await shieldPrice.fill('12 gp');
  await shieldPrice.press('Tab');
  await expect(page.locator('.toast', { hasText: 'Saved Shield: 12 gp' })).toBeVisible();
  await page.reload();
  await expect(shieldPrice).toHaveValue('12 gp');

  // Bryn's cart keeps the 10 gp it was sent at, and nothing calls that a haggle.
  await page.goto('/dm/carts');
  const card = page.locator('article', { hasText: 'Bryn Ashvale' });
  await expect(card.locator('.price-lg')).toHaveText('10 gp');
  await expect(card).not.toContainText('shelf price');
  await card.getByRole('button', { name: 'Approve' }).click();
  await expect(card.locator('.status')).toHaveText('Approved');

  await page.goto('/dm/history');
  await expect(page.locator('tr', { hasText: 'Shield' }).first()).not.toContainText('shelf price');
});

test('Approve refuses a cart that changed since the DM’s page showed it', async ({ page }) => {
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await page.getByRole('button', { name: 'Add Longsword to cart' }).click();
  await page.locator('#cart').getByRole('button', { name: 'Send to DM for approval' }).click();

  await signInAsDm(page);
  await page.goto('/dm/carts');
  const card = page.locator('article', { hasText: 'Bryn Ashvale' });
  await expect(card.locator('.price-lg')).toHaveText('15 gp');

  // Another tab haggles the price up while this one still shows 15 gp.
  const other = await page.context().newPage();
  await other.goto('/dm/carts');
  const price = other.locator('article', { hasText: 'Bryn Ashvale' }).getByLabel('Price of one Longsword, in this cart');
  await price.fill('20 gp');
  await price.press('Tab');
  await expect(other.locator('article', { hasText: 'Bryn Ashvale' }).locator('.price-lg')).toHaveText('20 gp');
  await other.close();

  await card.getByRole('button', { name: 'Approve' }).click();
  await expect(page.locator('.toast', { hasText: 'This cart changed since you opened it' })).toBeVisible();
  await expect(card.locator('.status')).toHaveText('Pending');
  await expect(card.locator('.price-lg')).toHaveText('20 gp');

  // Now the card shows what it costs, Approve goes through.
  await card.getByRole('button', { name: 'Approve' }).click();
  await expect(card.locator('.status')).toHaveText('Approved');
  await signIn(page, 'bryn');
  await page.goto('/pack');
  await expect(page.locator('#purse')).toHaveAttribute('aria-label', 'Purse: 392 gp');
});

test('a player can withdraw a cart before the DM decides', async ({ page }) => {
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await page.getByRole('button', { name: 'Add Shield to cart' }).click();
  const cart = page.locator('#cart');
  await cart.getByRole('button', { name: 'Send to DM for approval' }).click();
  await cart.getByRole('button', { name: 'Withdraw to make changes' }).click();
  await expect(cart.getByRole('button', { name: 'One more Shield' })).toBeVisible();
});

test('a store stocks one item more than once, each with its own notes, and they follow it into the pack', async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm/stores');
  await page.getByRole('link', { name: /The Rusty Anvil/ }).click();
  await expect(page.getByRole('heading', { name: 'The Rusty Anvil' })).toBeVisible();
  const form = page.locator('#stock-form');
  const shelf = page.locator('#stock-body');
  const notesOn = (spell: string) => shelf.locator(`input[name="notes"][value="${spell}"]`);
  for (const spell of ['Magic Missile', 'Shield']) {
    await form.getByLabel('Notes').fill(spell);
    await form.getByLabel('Price').fill('25 gp');
    await form.getByLabel('Stock an item').fill('Spell scroll (1st level)');
    await form.getByRole('button', { name: 'Add to shelf' }).click();
    await expect(notesOn(spell)).toHaveCount(1);
  }
  // The same item and notes again updates that line rather than adding a third.
  await form.getByLabel('Notes').fill('Shield');
  await form.getByLabel('In stock').fill('2');
  await form.getByLabel('Stock an item').fill('Spell scroll (1st level)');
  await form.getByRole('button', { name: 'Add to shelf' }).click();
  await expect(page.getByLabel('Stock of Spell scroll (1st level) (Shield)')).toHaveValue('2');
  await expect(shelf.getByLabel('Notes on Spell scroll (1st level)')).toHaveCount(2);

  // Notes can be changed on the shelf, but not to another line's.
  await notesOn('Shield').fill('Sleep');
  await notesOn('Shield').blur();
  await expect(page.getByLabel('Stock of Spell scroll (1st level) (Sleep)')).toHaveValue('2');
  await notesOn('Sleep').fill('Magic Missile');
  await notesOn('Sleep').blur();
  await expect(page.locator('#toasts')).toContainText('Spell scroll (1st level) (Magic Missile) is already on the shelf.');

  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(page.locator('.ware', { hasText: 'Magic Missile' })).toContainText('Spell scroll (1st level)');
  await page.getByRole('button', { name: 'Add Spell scroll (1st level) (Sleep) to cart' }).click();
  const cart = page.locator('#cart');
  await expect(cart.getByRole('button', { name: 'One more Spell scroll (1st level) (Sleep)' })).toBeVisible();
  await page.getByRole('button', { name: 'Add Spell scroll (1st level) (Magic Missile) to cart' }).click();
  await expect(cart.locator('.cart-line')).toHaveCount(2);
  await cart.getByRole('button', { name: 'Send to DM for approval' }).click();

  await signInAsDm(page);
  await page.goto('/dm/carts');
  const card = page.locator('article', { hasText: 'Bryn Ashvale' });
  await expect(card).toContainText('Sleep');
  await expect(card).toContainText('Magic Missile');
  await card.getByRole('button', { name: 'Approve' }).click();
  await expect(card.locator('.status')).toHaveText('Approved');
  await page.goto('/dm/stores');
  await page.getByRole('link', { name: /The Rusty Anvil/ }).click();
  await expect(page.getByRole('heading', { name: 'The Rusty Anvil' })).toBeVisible();
  await expect(page.getByLabel('Stock of Spell scroll (1st level) (Sleep)')).toHaveValue('1');

  await signIn(page, 'bryn');
  await page.goto('/pack');
  const scrolls = page.locator('tbody tr', { hasText: 'Spell scroll (1st level)' });
  await expect(scrolls).toHaveCount(2);
  await expect(scrolls.filter({ hasText: 'Sleep' })).toHaveCount(1);
  await expect(scrolls.filter({ hasText: 'Magic Missile' })).toHaveCount(1);
});

test('new notes on a shelf line wait for carts sent to the DM, and take it out of open ones', async ({ page }) => {
  const openAnvil = async () => {
    await page.goto('/dm/stores');
    await page.getByRole('link', { name: /The Rusty Anvil/ }).click();
    await expect(page.getByRole('heading', { name: 'The Rusty Anvil' })).toBeVisible();
  };
  const rename = async (from: string, to: string) => {
    const notes = page.locator(`#stock-body input[name="notes"][value="${from}"]`);
    await notes.fill(to);
    await notes.blur();
  };
  await signInAsDm(page);
  await openAnvil();
  const form = page.locator('#stock-form');
  await form.getByLabel('Notes').fill('Fireball');
  await form.getByLabel('Stock an item').fill('Spell scroll (1st level)');
  await form.getByRole('button', { name: 'Add to shelf' }).click();
  await expect(page.locator('#stock-body input[name="notes"][value="Fireball"]')).toHaveCount(1);

  for (const player of ['bryn', 'tamsin']) {
    await signIn(page, player);
    await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
    await page.getByRole('button', { name: 'Add Spell scroll (1st level) (Fireball) to cart' }).click();
    await expect(page.locator('#cart .cart-line')).toHaveCount(1);
    if (player === 'bryn') {
      await page.locator('#cart').getByRole('button', { name: 'Send to DM for approval' }).click();
      await expect(page.locator('#cart')).toContainText('Waiting on your DM.');
    }
  }

  // Bryn's cart is waiting on the DM, so the scroll can't quietly become another spell under it.
  await signInAsDm(page);
  await openAnvil();
  await rename('Fireball', 'Haste');
  await expect(page.locator('#toasts')).toContainText('A cart waiting on you holds Spell scroll (1st level) (Fireball).');
  await page.goto('/dm/carts');
  const card = page.locator('article', { hasText: 'Bryn Ashvale' });
  await card.getByRole('button', { name: 'Decline' }).click();
  await expect(card.locator('.status')).toHaveText('Declined');

  await openAnvil();
  await rename('Fireball', 'Haste');
  await expect(page.locator('#toasts')).toContainText('Taken out of an open cart.');
  await expect(page.getByLabel('Price of Spell scroll (1st level) (Haste)')).toBeVisible();

  // Tamsin's open cart no longer holds the old scroll; the new one is on the shelf for her to add.
  await signIn(page, 'tamsin');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(page.locator('#cart .cart-line')).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Add Spell scroll (1st level) (Haste) to cart' })).toBeVisible();
});

test('players can read a ware’s description from the catalog', async ({ page }) => {
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'Mossbottom Apothecary' }).click();
  const potion = page.locator('.ware', { hasText: 'Potion of Healing' });
  const about = potion.getByText('About the Potion of Healing');
  const text = potion.getByText(/restores 2d4 \+ 2 hit points/);
  await expect(text).toBeHidden();
  await about.click();
  await expect(text).toBeVisible();
  // Wares with no description have nothing to open.
  await expect(page.locator('.ware', { hasText: 'Antitoxin' }).locator('details')).toHaveCount(0);
});

test('players can read the descriptions of what’s in their pack', async ({ page }) => {
  await signIn(page, 'bryn');
  await page.goto('/pack');
  const row = page.locator('tbody tr', { hasText: 'Longsword' });
  const text = row.getByText('Martial melee weapon. 1d8 slashing, versatile (1d10).');
  await expect(text).toBeHidden();
  await row.getByText('About the Longsword').click();
  await expect(text).toBeVisible();
  // Opening one wraps its text in the column as it is, rather than widening it and moving the others.
  const potion = page.locator('tbody tr', { hasText: 'Potion of Healing' });
  const widths = () => potion.locator('xpath=ancestor::table[1]').locator('thead th')
    .evaluateAll((ths) => ths.map((th) => Math.round(th.getBoundingClientRect().width)));
  const closed = await widths();
  await potion.getByText('About the Potion of Healing').click();
  await expect(potion.getByText(/restores 2d4 \+ 2 hit points/)).toBeVisible();
  expect(await widths()).toEqual(closed);
  // Items with no description have nothing to open, only the Sell… fold-out.
  await expect(page.locator('tbody tr', { hasText: 'Silver locket' }).locator('details.item-desc')).toHaveCount(0);
});
