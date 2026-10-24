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
