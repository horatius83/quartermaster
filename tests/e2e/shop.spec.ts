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
