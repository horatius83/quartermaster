import { test, expect, signIn, signInAsDm, toasts, type Page } from './fixtures';

// The Rusty Anvil sells longswords at 15 gp, shields at 10 gp and battleaxes at 10 gp (scripts/seed-demo.php).
async function openAnvil(page: Page): Promise<void> {
  await signInAsDm(page);
  await page.goto('/dm/stores');
  await page.getByRole('link', { name: /The Rusty Anvil/ }).click();
  await expect(page.getByRole('heading', { name: 'The Rusty Anvil' })).toBeVisible();
}

const ware = (page: Page, name: string) => page.locator('.ware', { has: page.getByRole('heading', { name, exact: true }) });

test('the DM charges each player their own share of a store’s prices', async ({ page }) => {
  await openAnvil(page);
  await expect(page.getByLabel('Prices for Bryn Ashvale, as a percentage')).toHaveValue('100');
  await page.getByLabel('Prices for Bryn Ashvale, as a percentage').fill('120');
  await page.getByLabel('Prices for Tamsin Reed, as a percentage').fill('90');
  await page.getByRole('button', { name: 'Save details' }).click();
  await expect(page.getByText('Store details saved.')).toBeVisible();
  await expect(page.getByLabel('Prices for Bryn Ashvale, as a percentage')).toHaveValue('120');
  await expect(page.getByLabel('Prices for Tamsin Reed, as a percentage')).toHaveValue('90');
  await expect(page.getByLabel('Prices for Oskar Vell, as a percentage')).toHaveValue('100');
  // The shelf itself keeps its prices.
  await expect(page.getByLabel('Price of Longsword', { exact: true })).toHaveValue('15 gp');

  // Bryn pays a fifth more, and the cart and the DM's approval go by that price.
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(ware(page, 'Longsword').locator('.price')).toHaveText('18 gp');
  await page.getByRole('button', { name: 'Add Longsword to cart' }).click();
  const cart = page.locator('#cart');
  await expect(cart).toContainText('You’ll have 394 gp left.');
  await cart.getByRole('button', { name: 'Send to DM for approval' }).click();
  await expect(cart).toContainText('Waiting on your DM.');

  // Tamsin pays a tenth less, to the copper.
  await signIn(page, 'tamsin');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(ware(page, 'Longsword').locator('.price')).toHaveText('13 gp 5 sp');
  // Oskar pays what's on the shelf.
  await signIn(page, 'oskar');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(ware(page, 'Longsword').locator('.price')).toHaveText('15 gp');

  // Setting Bryn back to 100% doesn't change the cart he sent, and his card still says what it was sent at.
  await openAnvil(page);
  await page.getByLabel('Prices for Bryn Ashvale, as a percentage').fill('100');
  await page.getByRole('button', { name: 'Save details' }).click();
  await expect(page.getByText('Store details saved.')).toBeVisible();
  await page.goto('/dm/carts');
  const card = page.locator('article', { hasText: 'Bryn Ashvale' });
  await expect(card).toContainText('Sent at 120% of shelf prices');
  await card.getByRole('button', { name: 'Approve' }).click();
  await expect(card.locator('.status')).toHaveText('Approved');
  await signIn(page, 'bryn');
  await page.goto('/pack');
  await expect(page.locator('#purse')).toHaveAttribute('aria-label', 'Purse: 394 gp');
});

test('changing a player’s share of the prices reprices their open cart', async ({ page }) => {
  await signIn(page, 'tamsin');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await page.getByRole('button', { name: 'Add Shield to cart' }).click();
  const cart = page.locator('#cart');
  await expect(cart.locator('.price-lg')).toHaveText('10 gp');

  await openAnvil(page);
  await page.getByLabel('Prices for Tamsin Reed, as a percentage').fill('150');
  await page.getByRole('button', { name: 'Save details' }).click();
  await expect(page.getByText('Store details saved.')).toBeVisible();

  await signIn(page, 'tamsin');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(cart.locator('.price-lg')).toHaveText('15 gp');

  // Out of range is refused, and nothing changes.
  await openAnvil(page);
  await page.getByLabel('Prices for Tamsin Reed, as a percentage').evaluate((el: HTMLInputElement) => { el.removeAttribute('max'); el.value = '5000'; });
  await page.getByRole('button', { name: 'Save details' }).click();
  await expect(toasts(page)).toContainText('as a percentage from 0 to 1000');
  await page.reload();
  await expect(page.getByLabel('Prices for Tamsin Reed, as a percentage')).toHaveValue('150');
});

test('a cart withdrawn from the DM comes back without what was pulled or hidden, at today’s share', async ({ page }) => {
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  for (const name of ['Longsword', 'Battleaxe', 'Shield']) {
    await page.getByRole('button', { name: `Add ${name} to cart` }).click();
    await expect(page.locator('#cart .cart-line', { hasText: name })).toBeVisible();
  }
  const cart = page.locator('#cart');
  await cart.getByRole('button', { name: 'Send to DM for approval' }).click();
  await expect(cart).toContainText('Waiting on your DM.');

  // While it waits, the DM raises Bryn's prices, hides the battleaxe from him and pulls the shield.
  await openAnvil(page);
  await page.getByLabel('Prices for Bryn Ashvale, as a percentage').fill('150');
  await page.getByRole('button', { name: 'Save details' }).click();
  await expect(page.getByText('Store details saved.')).toBeVisible();
  const battleaxe = page.locator('#stock-body tr', { hasText: 'Battleaxe' });
  await page.getByLabel(/^Who sees Battleaxe:/).click();
  await battleaxe.getByRole('checkbox', { name: 'Bryn Ashvale' }).uncheck();
  await expect(page.getByLabel(/^Who sees Battleaxe:/)).toHaveText('Oskar Vell, Tamsin Reed, Wren Holloway');
  await page.locator('#stock-body tr', { hasText: 'Shield' }).getByRole('button', { name: 'Pull from shelf' }).click();
  await expect(page.locator('#stock-body tr', { hasText: 'Shield' })).toHaveCount(0);
  // The cart he sent is as he sent it, at 100%.
  await page.goto('/dm/carts');
  const card = page.locator('article', { hasText: 'Bryn Ashvale' });
  await expect(card).toContainText('Battleaxe');
  await expect(card).not.toContainText('Sent at');

  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await cart.getByRole('button', { name: 'Withdraw to make changes' }).click();
  await expect(toasts(page)).toContainText('2 things in it are no longer for sale, so they were taken out. Its prices have changed since you sent it.');
  await expect(cart.locator('.cart-line', { hasText: 'Battleaxe' })).toHaveCount(0);
  await expect(cart.locator('.cart-line', { hasText: 'Shield' })).toHaveCount(0);
  await expect(cart.locator('.price-lg')).toHaveText('22 gp 5 sp');
});

test('a price the DM haggled stays when the player’s share changes', async ({ page }) => {
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await page.getByRole('button', { name: 'Add Longsword to cart' }).click();
  const cart = page.locator('#cart');
  await cart.getByRole('button', { name: 'Send to DM for approval' }).click();
  await expect(cart).toContainText('Waiting on your DM.');

  await signInAsDm(page);
  await page.goto('/dm/carts');
  const card = page.locator('article', { hasText: 'Bryn Ashvale' });
  await card.getByLabel('Price of one Longsword, in this cart').fill('12 gp');
  await card.getByLabel('Price of one Longsword, in this cart').press('Enter');
  await expect(toasts(page)).toContainText('Longsword now costs 12 gp each, in this cart only.');

  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await cart.getByRole('button', { name: 'Withdraw to make changes' }).click();
  await expect(cart.locator('.price-lg')).toHaveText('12 gp');

  // Raising Bryn's share reprices what he adds, not the deal he struck.
  await openAnvil(page);
  await page.getByLabel('Prices for Bryn Ashvale, as a percentage').fill('150');
  await page.getByRole('button', { name: 'Save details' }).click();
  await expect(page.getByText('Store details saved.')).toBeVisible();
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(cart.locator('.cart-line', { hasText: 'Longsword' })).toContainText('12 gp each · shelf price 15 gp');
  await page.getByRole('button', { name: 'Add Shield to cart' }).click();
  await expect(cart.locator('.price-lg')).toHaveText('27 gp');
});

test('a page drawn before a character joined leaves that character as they were', async ({ page }) => {
  await openAnvil(page);
  await page.getByLabel('Prices for Wren Holloway, as a percentage').fill('130');
  await page.getByRole('button', { name: 'Save details' }).click();
  await expect(page.getByText('Store details saved.')).toBeVisible();

  // As if Wren had joined after the page was drawn: she isn't on it.
  await page.locator('.access-row', { hasText: 'Wren Holloway' }).evaluate((el) => el.remove());
  const access = page.getByRole('group', { name: 'Who can shop here' });
  await access.getByRole('checkbox', { name: 'Bryn Ashvale' }).uncheck();
  await page.getByRole('button', { name: 'Save details' }).click();
  await expect(page.getByText('Store details saved.')).toBeVisible();

  const battleaxe = page.locator('#stock-body tr', { hasText: 'Battleaxe' });
  await page.getByLabel(/^Who sees Battleaxe:/).click();
  await battleaxe.locator('label', { hasText: 'Wren Holloway' }).evaluate((el) => { el.previousElementSibling?.remove(); el.remove(); });
  await battleaxe.getByRole('checkbox', { name: 'Oskar Vell' }).uncheck();
  await expect(toasts(page)).toContainText('Only Bryn Ashvale, Tamsin Reed, Wren Holloway can see Battleaxe.');

  // Wren can still shop here, at her 130%, and still sees the battleaxe; Bryn can't shop here.
  await page.reload();
  await expect(access.getByRole('checkbox', { name: 'Wren Holloway' })).toBeChecked();
  await expect(access.getByRole('checkbox', { name: 'Bryn Ashvale' })).not.toBeChecked();
  await expect(page.getByLabel('Prices for Wren Holloway, as a percentage')).toHaveValue('130');
  await signIn(page, 'wren');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(ware(page, 'Battleaxe').locator('.price')).toHaveText('13 gp');
});

test('a malformed price share is refused rather than wiping the others', async ({ page }) => {
  await openAnvil(page);
  await page.getByLabel('Prices for Bryn Ashvale, as a percentage').fill('120');
  await page.getByRole('button', { name: 'Save details' }).click();
  await expect(page.getByText('Store details saved.')).toBeVisible();
  const csrf = await page.locator('body').evaluate((b) => JSON.parse(b.getAttribute('hx-headers')!)['X-CSRF-Token']);
  const saved = await page.request.post('/dm/stores/1', { form: { name: 'The Rusty Anvil', pct: '90' }, headers: { 'X-CSRF-Token': csrf, 'HX-Request': 'true' } });
  expect(saved.headers()['hx-trigger']).toContain('as a percentage from 0 to 1000');
  await page.reload();
  await expect(page.getByLabel('Prices for Bryn Ashvale, as a percentage')).toHaveValue('120');
});

test('the DM shows a shelf line only to the players they choose', async ({ page }) => {
  // Bryn has a battleaxe in an open cart before the DM hides it from him.
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await page.getByRole('button', { name: 'Add Battleaxe to cart' }).click();
  await expect(page.locator('#cart .cart-line', { hasText: 'Battleaxe' })).toBeVisible();
  const battleaxeId = await page.getByRole('button', { name: 'Add Battleaxe to cart' }).evaluate((b) => JSON.parse(b.getAttribute('hx-vals')!).stock_id);

  await openAnvil(page);
  const summary = page.getByLabel(/^Who sees Battleaxe:/);
  await expect(summary).toHaveText('Everyone');
  await summary.click();
  for (const name of ['Bryn Ashvale', 'Oskar Vell', 'Wren Holloway']) {
    await page.locator('#stock-body tr', { hasText: 'Battleaxe' }).getByRole('checkbox', { name }).uncheck();
    await expect(page.locator('#stock-body tr', { hasText: 'Battleaxe' }).getByRole('checkbox', { name })).not.toBeChecked();
  }
  await expect(toasts(page)).toContainText('Only Tamsin Reed can see Battleaxe.');
  await expect(summary).toHaveText('Tamsin Reed');

  // Gone from Bryn's shelf and cart, and he can't add it by its id either.
  await signIn(page, 'bryn');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(ware(page, 'Battleaxe')).toHaveCount(0);
  await expect(ware(page, 'Longsword')).toBeVisible();
  await expect(page.locator('#cart .cart-line', { hasText: 'Battleaxe' })).toHaveCount(0);
  const csrfNow = await page.locator('body').evaluate((b) => JSON.parse(b.getAttribute('hx-headers')!)['X-CSRF-Token']);
  expect(csrfNow).not.toBe('');
  const added = await page.request.post('/shop/1/add', { form: { stock_id: String(battleaxeId) }, headers: { 'X-CSRF-Token': csrfNow, 'HX-Request': 'true' } });
  expect(added.headers()['hx-trigger']).toContain('That item is no longer for sale.');

  // Tamsin sees it.
  await signIn(page, 'tamsin');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(ware(page, 'Battleaxe')).toBeVisible();

  // Nobody ticked hides it from everyone; everyone ticked shows it to all again.
  await openAnvil(page);
  await summary.click();
  await page.locator('#stock-body tr', { hasText: 'Battleaxe' }).getByRole('checkbox', { name: 'Tamsin Reed' }).uncheck();
  await expect(summary).toHaveText('Nobody');
  await signIn(page, 'tamsin');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(ware(page, 'Battleaxe')).toHaveCount(0);

  await openAnvil(page);
  await summary.click();
  for (const name of ['Bryn Ashvale', 'Tamsin Reed', 'Oskar Vell', 'Wren Holloway']) {
    await page.locator('#stock-body tr', { hasText: 'Battleaxe' }).getByRole('checkbox', { name }).check();
    await expect(page.locator('#stock-body tr', { hasText: 'Battleaxe' }).getByRole('checkbox', { name })).toBeChecked();
  }
  await expect(summary).toHaveText('Everyone');
  await signIn(page, 'wren');
  await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
  await expect(ware(page, 'Battleaxe')).toBeVisible();
});

test('a store with nobody ticked under Who can shop here is closed to every player', async ({ page }) => {
  await openAnvil(page);
  const access = page.getByRole('group', { name: 'Who can shop here' });
  for (const name of ['Bryn Ashvale', 'Tamsin Reed', 'Oskar Vell', 'Wren Holloway']) {
    await access.getByRole('checkbox', { name }).uncheck();
  }
  await page.getByRole('button', { name: 'Save details' }).click();
  await expect(page.getByText('Nobody is ticked, so no one can shop here.')).toBeVisible();
  for (const name of ['Bryn Ashvale', 'Tamsin Reed', 'Oskar Vell', 'Wren Holloway']) {
    await expect(access.getByRole('checkbox', { name })).not.toBeChecked();
  }
  await signIn(page, 'bryn');
  await page.goto('/shop');
  await expect(page.getByRole('link', { name: 'The Rusty Anvil' })).toHaveCount(0);
  await expect(page.getByRole('link', { name: 'Fletcher’s Rest' })).toBeVisible();
  await page.goto('/shop/1');
  await expect(page.getByRole('heading', { name: 'The Rusty Anvil' })).toHaveCount(0);

  // Ticking one player lets just them back in.
  await openAnvil(page);
  await access.getByRole('checkbox', { name: 'Tamsin Reed' }).check();
  await page.getByRole('button', { name: 'Save details' }).click();
  await expect(page.getByText('Store details saved.')).toBeVisible();
  await signIn(page, 'tamsin');
  await page.goto('/shop');
  await expect(page.getByRole('link', { name: 'The Rusty Anvil' })).toBeVisible();
  await signIn(page, 'bryn');
  await page.goto('/shop');
  await expect(page.getByRole('link', { name: 'The Rusty Anvil' })).toHaveCount(0);
});
