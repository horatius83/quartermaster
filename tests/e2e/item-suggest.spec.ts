import type { Page } from '@playwright/test';
import { test, expect, signInAsDm, BRYN } from './fixtures';

// The item-name field on the give and stock forms (views/partials/item_suggest.php).

const giveForm = (page: Page) => page.locator('#give-form');
const itemInput = (page: Page) => giveForm(page).getByRole('combobox', { name: 'Item' });
const suggestions = (page: Page) => giveForm(page).getByRole('listbox');

test.beforeEach(async ({ page }) => {
  await signInAsDm(page);
});

test('DM picks a catalog suggestion by tapping it on a phone', async ({ openPage, browserName }) => {
  test.skip(browserName === 'firefox', 'Firefox has no mobile emulation');
  const page = await openPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  await signInAsDm(page);
  await page.goto(`/dm/inventory/${BRYN}`);
  const form = page.locator('#give-form');
  const input = form.getByRole('combobox', { name: 'Item' });
  await input.tap();
  await input.pressSequentially('torc');
  const option = form.getByRole('listbox').getByRole('option', { name: /Torch/ });
  await expect(option).toBeVisible();
  await option.tap();
  await expect(input).toHaveValue('Torch');
  await expect(form.getByRole('listbox')).toBeHidden();
  await expect(input).toBeFocused();
});

test('DM picks a catalog suggestion with the keyboard', async ({ page }) => {
  await page.goto(`/dm/inventory/${BRYN}`);
  const form = page.locator('#give-form');
  const input = form.getByRole('combobox', { name: 'Item' });
  await input.pressSequentially('torc');
  await expect(form.getByRole('listbox').getByRole('option').first()).toBeVisible();
  const first = await form.getByRole('listbox').getByRole('option').first().getAttribute('data-value');
  await input.press('ArrowDown');
  await expect(form.getByRole('listbox').getByRole('option').first()).toHaveAttribute('aria-selected', 'true');
  await input.press('Enter');
  await expect(input).toHaveValue(first ?? '');
  await expect(form.getByRole('listbox')).toBeHidden();

  // Escape closes the list without picking anything.
  await input.fill('');
  await input.pressSequentially('torc');
  await expect(form.getByRole('listbox')).toBeVisible();
  await input.press('Escape');
  await expect(form.getByRole('listbox')).toBeHidden();
  await expect(input).toHaveValue('torc');
});

test('clearing the field drops the old suggestions', async ({ page }) => {
  await page.goto(`/dm/inventory/${BRYN}`);
  const input = itemInput(page);
  await input.pressSequentially('torc');
  await expect(suggestions(page).getByRole('option', { name: /Torch/ })).toBeVisible();
  await input.fill('');
  await expect(suggestions(page)).toBeHidden();
  await expect(suggestions(page).getByRole('option')).toHaveCount(0);
  // Down then browses the chosen category rather than bringing the old search back.
  await input.press('ArrowDown');
  await expect(suggestions(page)).toContainText('Weapon, A–Z');
  await expect(suggestions(page).getByRole('option', { name: /Torch/ })).toHaveCount(0);
});

test('a reply that arrives after a pick doesn’t reopen the list', async ({ page }) => {
  // Hold back the reply for the last keystroke, as on a slow phone connection.
  let release = () => {};
  const held = new Promise<void>((resolve) => { release = resolve; });
  await page.route(/\/dm\/catalog\/suggest\?item=torc$/, async (route) => {
    await held;
    await route.continue();
  });
  await page.goto(`/dm/inventory/${BRYN}`);
  const input = itemInput(page);
  await input.pressSequentially('tor');
  const torch = suggestions(page).getByRole('option', { name: /Torch/ });
  await expect(torch).toBeVisible();

  const slow = page.waitForRequest(/item=torc$/);
  await input.press('c');
  await slow;
  await torch.click();
  await expect(input).toHaveValue('Torch');

  const reply = page.waitForResponse(/item=torc$/);
  release();
  await reply;
  // Give htmx a moment to swap, if it was going to.
  await page.waitForTimeout(300);
  await expect(suggestions(page)).toBeHidden();
});

test('DM stocks a shelf by picking a suggestion', async ({ page }) => {
  await page.goto('/dm/stores');
  const form = page.locator('#stock-form');
  const input = form.getByRole('combobox', { name: 'Stock an item' });
  await input.pressSequentially('torc');
  await form.getByRole('listbox').getByRole('option', { name: /Torch/ }).click();
  await expect(input).toHaveValue('Torch');
  await expect(form.getByRole('listbox')).toBeHidden();
  await form.getByRole('button', { name: 'Add to shelf' }).click();
  await expect(page.locator('#stock-body tr', { hasText: 'Torch' })).toHaveCount(1);
});

test('the ▾ button lists the chosen category A–Z, and Category switches it', async ({ openPage, browserName }) => {
  test.skip(browserName === 'firefox', 'Firefox has no mobile emulation');
  const page = await openPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  await signInAsDm(page);
  await page.goto(`/dm/inventory/${BRYN}`);
  const form = giveForm(page);
  const list = suggestions(page);
  const category = form.locator('select[name="category_id"]');
  await expect(category).toHaveValue(await category.locator('option', { hasText: 'Weapon' }).getAttribute('value') ?? '');

  await form.getByRole('button', { name: 'Browse the catalog by category' }).tap();
  await expect(list).toBeVisible();
  await expect(list).toContainText('Weapon, A–Z');
  // The test config lists 4 at a time, and the demo has 8 weapons.
  await expect(list.getByRole('option', { name: /Battleaxe/ })).toBeVisible();
  await expect(list.getByRole('option', { name: /Longsword/ })).toHaveCount(0);
  await list.getByRole('option', { name: 'Show more Weapon items' }).tap();
  await expect(list.getByRole('option', { name: /Warhammer/ })).toBeVisible();
  await expect(list.getByRole('option', { name: /Show more/ })).toHaveCount(0);
  await expect(list).toBeVisible();
  const names = await list.getByRole('option').evaluateAll((els) => els.map((el) => (el as HTMLElement).dataset.value ?? ''));
  expect(names).toEqual(['Battleaxe', 'Dagger', 'Light crossbow', 'Longbow', 'Longsword', 'Quarterstaff', 'Shortbow', 'Warhammer']);
  // Browsing from a tap doesn't put the cursor in the field, so a phone keeps its keyboard down.
  await expect(itemInput(page)).not.toBeFocused();

  // Tapping Category closes the list (on a phone it covers the select); choosing one browses that instead.
  await category.selectOption({ label: 'Armor' });
  await expect(list).toContainText('Armor, A–Z');
  // Exactly a batch's worth: no "Show more".
  await expect(list.getByRole('option')).toHaveCount(4);
  await expect(list.getByRole('option', { name: /Longsword/ })).toHaveCount(0);
  await list.getByRole('option', { name: /Shield/ }).tap();
  await expect(itemInput(page)).toHaveValue('Shield');
  await expect(list).toBeHidden();
});

test('picking a browsed item sets its category, and ▾ closes the list again', async ({ page }) => {
  await page.goto(`/dm/inventory/${BRYN}`);
  const form = giveForm(page);
  const toggle = form.getByRole('button', { name: 'Browse the catalog by category' });
  await form.locator('select[name="category_id"]').selectOption({ label: 'Adventuring Gear' });
  await toggle.click();
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await toggle.click();
  await expect(suggestions(page)).toBeHidden();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  // Closed on purpose, so a later Category change leaves it closed.
  await form.locator('select[name="category_id"]').selectOption({ label: 'Armor' });
  await page.waitForTimeout(300);
  await expect(suggestions(page)).toBeHidden();

  // Down on the empty field browses too.
  await form.locator('select[name="category_id"]').selectOption({ label: 'Weapon' });
  await itemInput(page).focus();
  await itemInput(page).press('ArrowDown');
  await expect(suggestions(page)).toContainText('Weapon, A–Z');

  // Typing searches every category again.
  await itemInput(page).pressSequentially('torc');
  await expect(suggestions(page)).not.toContainText('A–Z');
  await suggestions(page).getByRole('option', { name: /Torch/ }).click();
  await expect(form.locator('select[name="category_id"]').locator('option:checked')).toHaveText('Adventuring Gear');
});

test('assets carry a version, so a deploy isn’t hidden by a cached app.js', async ({ page }) => {
  await page.goto(`/dm/inventory/${BRYN}`);
  await expect(page.locator('script[src*="app.js"]')).toHaveAttribute('src', /app\.js\?v=\d+$/);
  await expect(page.locator('link[rel="stylesheet"][href*="app.css"]')).toHaveAttribute('href', /app\.css\?v=\d+$/);
});

test('▾ from the keyboard moves on into the field, where the arrow keys pick', async ({ page }) => {
  await page.goto(`/dm/inventory/${BRYN}`);
  const form = giveForm(page);
  await form.locator('select[name="category_id"]').selectOption({ label: 'Armor' });
  await form.getByRole('button', { name: 'Browse the catalog by category' }).focus();
  await page.keyboard.press('Enter');
  await expect(suggestions(page)).toContainText('Armor, A–Z');
  await expect(itemInput(page)).toBeFocused();
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('Enter');
  await expect(itemInput(page)).toHaveValue('Chain mail');

  // Escape dismisses a browse list for good: changing Category doesn't bring it back.
  await itemInput(page).fill('');
  await itemInput(page).press('ArrowDown');
  await expect(suggestions(page)).toBeVisible();
  await itemInput(page).press('Escape');
  await form.locator('select[name="category_id"]').selectOption({ label: 'Weapon' });
  await page.waitForTimeout(300);
  await expect(suggestions(page)).toBeHidden();
});

test('browsing after the session ends goes to the sign-in page', async ({ page, context }) => {
  await page.goto(`/dm/inventory/${BRYN}`);
  await context.clearCookies();
  await giveForm(page).getByRole('button', { name: 'Browse the catalog by category' }).click();
  await expect(page).toHaveURL(/\/login$/);
});

test('% and _ in a search are matched as they are, not as wildcards', async ({ page }) => {
  for (const text of ['%', '_', 'Long_word', '!']) {
    const reply = await page.request.get(`/dm/catalog/suggest?item=${encodeURIComponent(text)}`);
    expect(await reply.text(), text).toBe('');
  }
  expect(await (await page.request.get('/dm/catalog/suggest?item=Longsword')).text()).toContain('Longsword');
});

test('a link followed after a deploy loads the page in full, with the new assets', async ({ page }) => {
  // Links to pages that don't redirect: a redirect loads the page in full anyway.
  await page.goto('/dm/carts');
  const nav = page.getByRole('navigation', { name: 'Main' });
  // Normally a link only swaps the body, so what this page set up is still there afterwards.
  await page.evaluate(() => { (window as unknown as { qmMarker: number }).qmMarker = 1; });
  await nav.getByRole('link', { name: 'Dashboard' }).click();
  await expect(page).toHaveURL(/\/dm$/);
  expect(await page.evaluate(() => (window as unknown as { qmMarker?: number }).qmMarker)).toBe(1);

  // Pretend this tab loaded older assets: the next link reloads in full.
  await page.evaluate(() => document.querySelector('meta[name="qm-assets"]')!.setAttribute('content', 'old'));
  await nav.getByRole('link', { name: 'Cart review' }).click();
  await expect(page).toHaveURL(/\/dm\/carts$/);
  await expect.poll(() => page.evaluate(() => (window as unknown as { qmMarker?: number }).qmMarker)).toBeUndefined();
  await expect(page.locator('meta[name="qm-assets"]')).not.toHaveAttribute('content', 'old');
});

test('"Show more" from the keyboard carries on at the first new item', async ({ page }) => {
  await page.goto(`/dm/inventory/${BRYN}`);
  const input = itemInput(page);
  await giveForm(page).locator('select[name="category_id"]').selectOption({ label: 'Weapon' });
  await input.focus();
  await input.press('ArrowDown');
  const more = suggestions(page).getByRole('option', { name: 'Show more Weapon items' });
  await expect(more).toBeVisible();
  // The test config lists 4 at a time: Battleaxe, Dagger, Light crossbow, Longbow, then "Show more".
  for (let i = 0; i < 5; i++) await input.press('ArrowDown');
  await expect(more).toHaveAttribute('aria-selected', 'true');
  await input.press('Enter');
  const longsword = suggestions(page).getByRole('option', { name: /Longsword/ });
  await expect(longsword).toHaveAttribute('aria-selected', 'true');
  await expect(suggestions(page).getByRole('option')).toHaveCount(8);
  await input.press('Enter');
  await expect(input).toHaveValue('Longsword');
});

test('▾ tapped straight after typing still browses', async ({ page }) => {
  // Hold the browse reply until the typed search has gone out, as on a slow connection.
  let searched = () => {};
  const searchSent = new Promise<void>((resolve) => { searched = resolve; });
  page.on('request', (req) => { if (/suggest\?item=sw$/.test(req.url())) searched(); });
  await page.route(/suggest\?browse=1/, async (route) => {
    await searchSent;
    await route.continue();
  });
  await page.goto(`/dm/inventory/${BRYN}`);
  const form = giveForm(page);
  await form.locator('select[name="category_id"]').selectOption({ label: 'Armor' });
  // Inside the search's 250 ms typing delay, so that search fires after the browse request is sent.
  await itemInput(page).pressSequentially('sw');
  await form.getByRole('button', { name: 'Browse the catalog by category' }).click();
  await expect(suggestions(page)).toContainText('Armor, A–Z');
  await page.waitForTimeout(400);
  await expect(suggestions(page)).toContainText('Armor, A–Z');
});

test('a search reply that lands after Escape doesn’t reopen the list', async ({ page }) => {
  let release = () => {};
  const held = new Promise<void>((resolve) => { release = resolve; });
  await page.route(/\/dm\/catalog\/suggest\?item=torc$/, async (route) => {
    await held;
    await route.continue();
  });
  await page.goto(`/dm/inventory/${BRYN}`);
  const input = itemInput(page);
  await input.pressSequentially('tor');
  await expect(suggestions(page).getByRole('option', { name: /Torch/ })).toBeVisible();
  const slow = page.waitForRequest(/item=torc$/);
  await input.press('c');
  await slow;
  await input.press('Escape');
  await expect(suggestions(page)).toBeHidden();
  const reply = page.waitForResponse(/item=torc$/);
  release();
  await reply;
  await page.waitForTimeout(300);
  await expect(suggestions(page)).toBeHidden();
  await expect(input).toBeFocused();
});
