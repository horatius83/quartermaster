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
  await form.getByRole('listbox', { name: 'Catalog items' }).getByRole('option', { name: /Torch/ }).click();
  await expect(input).toHaveValue('Torch');
  await expect(form.getByRole('listbox', { name: 'Catalog items' })).toBeHidden();
  await form.getByRole('button', { name: 'Add to shelf' }).click();
  await expect(page.locator('#stock-body tr', { hasText: 'Torch' })).toHaveCount(1);
});

test('picking an item to stock fills in its price and weight and shows its description', async ({ page }) => {
  await page.goto('/dm/stores');
  const form = page.locator('#stock-form');
  const input = form.getByRole('combobox', { name: 'Stock an item' });
  const price = form.getByLabel('Price');
  const weight = form.getByLabel('Weight');
  const category = form.locator('select[name="category_id"]');
  const description = form.getByLabel('Description');
  await expect(description).toHaveAttribute('readonly', '');
  await expect(description).toHaveValue('');

  await input.pressSequentially('longs');
  await form.getByRole('listbox', { name: 'Catalog items' }).getByRole('option', { name: /Longsword/ }).click();
  await expect(description).toHaveValue('Martial melee weapon. 1d8 slashing, versatile (1d10).');
  await expect(price).toHaveValue('15 gp');
  await expect(weight).toHaveValue('3');
  await expect(category.locator('option:checked')).toHaveText('Weapon');

  // Another catalog item replaces them, category and all.
  await input.fill('');
  await input.pressSequentially('potion of');
  await form.getByRole('listbox', { name: 'Catalog items' }).getByRole('option', { name: /Potion of Healing/ }).click();
  await expect(description).toHaveValue(/restores 2d4 \+ 2 hit points/);
  await expect(price).toHaveValue('50 gp');
  await expect(weight).toHaveValue('0.5');
  await expect(category.locator('option:checked')).toHaveText('Consumable');

  // A name that's new to the catalog has no description, and what was filled in goes back, but what the DM
  // changed stays.
  await weight.fill('2');
  await input.fill('Potion of Gumption');
  await expect(price).toHaveValue('');
  await expect(description).toHaveAttribute('placeholder', /Not in the catalog yet/);
  await expect(description).toHaveValue('');
  await expect(weight).toHaveValue('2');

  // Typing a catalog item's name in full fills in what the DM hasn't changed: Price, but not their Weight.
  await input.fill('torch');
  await expect(description).toHaveValue(/Burns for 1 hour/);
  await expect(price).toHaveValue('1 cp');
  await expect(weight).toHaveValue('2');

  // A price changed after the fill is the DM's, so typing the name again keeps it.
  await price.fill('3 cp');
  const redrawn = page.waitForResponse(/\/dm\/catalog\/details\?item=Torch/);
  await input.fill('Torc');
  await input.fill('Torch');
  await redrawn;
  await expect(description).toHaveValue(/Burns for 1 hour/);
  await expect(price).toHaveValue('3 cp');
  await form.getByRole('button', { name: 'Add to shelf' }).click();
  await expect(page.getByLabel('Price of Torch')).toHaveValue('3 cp');
  // The form empties for the next item, description too.
  await expect(input).toHaveValue('');
  await expect(price).toHaveValue('');
  await expect(description).toHaveValue('');
  await expect(description).toHaveAttribute('placeholder', 'Pick an item to read its description.');

  // A pick fills Price over one the DM typed first, since that's what picking is for.
  await price.fill('9 gp');
  await input.pressSequentially('longs');
  await form.getByRole('listbox', { name: 'Catalog items' }).getByRole('option', { name: /Longsword/ }).click();
  await expect(price).toHaveValue('15 gp');
});

test('stocking straight after retyping the item doesn’t use the old item’s price', async ({ page }) => {
  await page.goto('/dm/stores');
  const form = page.locator('#stock-form');
  const input = form.getByRole('combobox', { name: 'Stock an item' });
  await input.pressSequentially('longs');
  await form.getByRole('listbox', { name: 'Catalog items' }).getByRole('option', { name: /Longsword/ }).click();
  await expect(form.getByLabel('Price')).toHaveValue('15 gp');
  // The description box never catches up before Enter.
  await page.route(/\/dm\/catalog\/details/, () => {});
  await input.fill('Torch');
  await expect(form.getByLabel('Price')).toHaveValue('');
  await input.press('Enter');
  await expect(page.getByLabel('Price of Torch')).toHaveValue('1 cp');
});

test('a slow catalog reply doesn’t overwrite a price typed while it was on its way', async ({ page }) => {
  let release = () => {};
  const held = new Promise<void>((resolve) => { release = resolve; });
  await page.route(/\/dm\/catalog\/details\?item=Torch/, async (route) => {
    await held;
    await route.continue();
  });
  await page.goto('/dm/stores');
  const form = page.locator('#stock-form');
  await form.getByRole('combobox', { name: 'Stock an item' }).pressSequentially('torc');
  await form.getByRole('listbox', { name: 'Catalog items' }).getByRole('option', { name: /Torch/ }).click();
  await form.getByLabel('Price').fill('3 cp');
  const reply = page.waitForResponse(/\/dm\/catalog\/details\?item=Torch/);
  release();
  await reply;
  await expect(form.getByLabel('Description')).toHaveValue(/Burns for 1 hour/);
  await expect(form.getByLabel('Price')).toHaveValue('3 cp');
  // Weight wasn't touched meanwhile, so it's filled in.
  await expect(form.getByLabel('Weight')).toHaveValue('1');
});

test('giving an item shows its description and fills in its value and weight too', async ({ page }) => {
  await page.goto(`/dm/inventory/${BRYN}`);
  const form = giveForm(page);
  const description = form.getByLabel('Description');
  const category = form.locator('select[name="category_id"]');
  await expect(description).toHaveAttribute('readonly', '');
  await expect(description).toHaveAttribute('placeholder', 'Pick an item to read its description.');

  await itemInput(page).pressSequentially('potion of');
  await suggestions(page).getByRole('option', { name: /Potion of Healing/ }).click();
  await expect(description).toHaveValue(/restores 2d4 \+ 2 hit points/);
  await expect(form.getByLabel('Value')).toHaveValue('50 gp');
  await expect(form.getByLabel('Weight')).toHaveValue('0.5');
  await expect(category.locator('option:checked')).toHaveText('Consumable');

  // A new name is added to the catalog when it's given, so the box says so in the give form's words.
  await itemInput(page).fill('Potion of Gumption');
  await expect(description).toHaveAttribute('placeholder', 'Not in the catalog yet. Giving it adds it, with this category, weight and value.');
  await expect(form.getByLabel('Value')).toHaveValue('');
  await expect(form.getByLabel('Weight')).toHaveValue('0');

  await itemInput(page).fill('torch');
  await expect(description).toHaveValue(/Burns for 1 hour/);
  await form.getByRole('button', { name: 'Add to pack' }).click();
  await expect(page.locator('#inv-body tr', { hasText: 'Torch' })).not.toHaveCount(0);
  // The form empties for the next item, description too.
  await expect(itemInput(page)).toHaveValue('');
  await expect(description).toHaveValue('');
  await expect(description).toHaveAttribute('placeholder', 'Pick an item to read its description.');
});

test('an item with no description says so', async ({ page }) => {
  await page.goto('/dm/stores');
  const form = page.locator('#stock-form');
  await form.getByRole('combobox', { name: 'Stock an item' }).pressSequentially('quive');
  await form.getByRole('listbox', { name: 'Catalog items' }).getByRole('option', { name: /Quiver/ }).click();
  await expect(form.getByLabel('Description')).toHaveAttribute('placeholder', 'The catalog has no description of Quiver.');
  await expect(form.getByLabel('Price')).toHaveValue('1 gp');
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

test('a form sent after a deploy still shows its error and keeps its input', async ({ page }) => {
  await page.goto('/dm/party');
  await page.evaluate(() => document.querySelector('meta[name="qm-assets"]')!.setAttribute('content', 'old'));
  await page.getByLabel('Character name').fill('Ilse Varn');
  await page.getByLabel('Player username').fill('ilse');
  await page.getByLabel('Temporary password').evaluate((el) => el.removeAttribute('minlength'));
  await page.getByLabel('Temporary password').fill('tiny1');
  await page.getByRole('button', { name: 'Add to party' }).click();

  // Neither the form's reply nor the page it redirects to is fetched a second time, which would use up the
  // error and the kept input.
  await expect(page.getByText('Player passwords need at least 6 characters.')).toBeVisible();
  await expect(page.getByLabel('Character name')).toHaveValue('Ilse Varn');
  await expect(page.getByLabel('Player username')).toHaveValue('ilse');
});

test('with focus on ▾, Escape closes the list and the arrow keys move through it', async ({ page }) => {
  await page.goto(`/dm/inventory/${BRYN}`);
  const input = itemInput(page);
  const toggle = giveForm(page).getByRole('button', { name: 'Browse the catalog by category' });
  await input.pressSequentially('torc');
  await expect(suggestions(page)).toBeVisible();
  await page.keyboard.press('Tab');
  await expect(toggle).toBeFocused();
  await expect(suggestions(page)).toBeVisible();
  await page.keyboard.press('ArrowDown');
  await expect(input).toBeFocused();
  await expect(suggestions(page).getByRole('option').first()).toHaveAttribute('aria-selected', 'true');

  await page.keyboard.press('Tab');
  await expect(toggle).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(suggestions(page)).toBeHidden();
});

test('a key pressed on ▾ earlier doesn’t make a later click count as the keyboard', async ({ page }) => {
  await page.goto(`/dm/inventory/${BRYN}`);
  const toggle = giveForm(page).getByRole('button', { name: 'Browse the catalog by category' });
  // Enter goes down on ▾ but nothing clicks it (as when a key is let go elsewhere), then a pointer click.
  await toggle.dispatchEvent('keydown', { key: 'Enter', bubbles: true });
  await toggle.click();
  await expect(suggestions(page)).toContainText('Weapon, A–Z');
  await expect(itemInput(page)).not.toBeFocused();
  await toggle.click();
  await expect(suggestions(page)).toBeHidden();

  // The same with the key let go, then a click with no pointer first, as a screen reader's double-tap sends.
  await toggle.dispatchEvent('keydown', { key: ' ', bubbles: true });
  await toggle.dispatchEvent('keyup', { key: ' ', bubbles: true });
  await page.waitForTimeout(50);
  await toggle.dispatchEvent('click', { detail: 0 });
  await expect(suggestions(page)).toContainText('Weapon, A–Z');
  await expect(itemInput(page)).not.toBeFocused();
});

test('Down on ▾ with the list closed browses and moves into the field', async ({ page }) => {
  await page.goto(`/dm/inventory/${BRYN}`);
  const toggle = giveForm(page).getByRole('button', { name: 'Browse the catalog by category' });
  await toggle.focus();
  await page.keyboard.press('ArrowDown');
  await expect(suggestions(page)).toContainText('Weapon, A–Z');
  await expect(itemInput(page)).toBeFocused();
});

test('Escape inside the typing delay keeps the list closed', async ({ page }) => {
  await page.goto(`/dm/inventory/${BRYN}`);
  const input = itemInput(page);
  await input.pressSequentially('tor');
  await expect(suggestions(page).getByRole('option', { name: /Torch/ })).toBeVisible();
  // The search for "torc" is still waiting out its 250 ms when Escape closes the list.
  const sent = page.waitForRequest(/item=torc$/);
  await input.press('c');
  await input.press('Escape');
  await expect(suggestions(page)).toBeHidden();
  await sent;
  await page.waitForTimeout(500);
  await expect(suggestions(page)).toBeHidden();
  await expect(input).toBeFocused();
});

test('Back to a page htmx no longer has cached loads it in full when the assets changed', async ({ page }) => {
  await page.goto('/dm/carts');
  await page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Dashboard' }).click();
  await expect(page).toHaveURL(/\/dm$/);
  await page.evaluate(() => {
    localStorage.removeItem('htmx-history-cache');
    document.querySelector('meta[name="qm-assets"]')!.setAttribute('content', 'old');
    (window as unknown as { qmMarker: number }).qmMarker = 1;
  });
  await page.goBack();
  await expect(page).toHaveURL(/\/dm\/carts$/);
  await expect.poll(() => page.evaluate(() => (window as unknown as { qmMarker?: number }).qmMarker)).toBeUndefined();
  await expect(page.locator('meta[name="qm-assets"]')).not.toHaveAttribute('content', 'old');
});

test('the stock form suggests spells a scroll of its level can hold for its notes', async ({ page }) => {
  await page.goto('/dm/stores');
  const form = page.locator('#stock-form');
  const notes = form.getByRole('combobox', { name: 'Notes' });
  const spells = form.getByRole('listbox', { name: 'Spells' });
  await form.getByLabel('Stock an item').fill('Spell scroll (3rd level)');
  await notes.pressSequentially('fire');
  // Its own level first, then lower ones cast at it; no cantrips (Fire Bolt) or higher spells (Fire Shield).
  await expect(spells.getByRole('option')).toHaveText([/Fireball\s*3rd level/, /Faerie Fire\s*1st level, cast at 3rd/]);
  await notes.press('ArrowDown');
  await notes.press('Enter');
  await expect(notes).toHaveValue('Fireball');
  await expect(spells).toBeHidden();
  await form.getByRole('button', { name: 'Add to shelf' }).click();
  await expect(page.locator('#stock-body input[name="notes"][value="Fireball"]')).toHaveCount(1);

  // Changing the item drops the list it had and searches again: a cantrip scroll offers only cantrips, and a
  // "Level 1 scroll" only 1st-level spells.
  const backToNotes = async () => {
    await notes.focus();
    await notes.press('ArrowDown');
  };
  await form.getByLabel('Stock an item').fill('Spell scroll (3rd level)');
  await notes.pressSequentially('fire');
  await expect(spells.getByRole('option')).toHaveCount(2);
  await notes.press('Escape');
  await form.getByLabel('Stock an item').fill('Spell scroll (cantrip)');
  await backToNotes();
  await expect(spells.getByRole('option')).toHaveText([/Fire Bolt\s*Cantrip/]);
  await notes.press('Escape');
  await form.getByLabel('Stock an item').fill('Level 1 scroll');
  await backToNotes();
  await expect(spells.getByRole('option')).toHaveText([/Faerie Fire\s*1st level/]);

  // A long list says how many more there are.
  await notes.fill('a');
  await form.getByLabel('Stock an item').fill('Spell scroll (9th level)');
  await backToNotes();
  await expect(spells.getByRole('option')).toHaveCount(12);
  await expect(spells).toContainText(/\d+ more fit\. Type more of the name to find it\./);
  await notes.press('Escape');

  // A picked spell is saved with a straight apostrophe, so the same spell typed on a phone (curly) is the same line.
  await form.getByLabel('Stock an item').fill('Spell scroll (1st level)');
  await notes.fill("hunter's");
  await spells.getByRole('option', { name: /Hunter’s Mark/ }).click();
  await expect(notes).toHaveValue("Hunter's Mark");
  await form.getByRole('button', { name: 'Add to shelf' }).click();
  await expect(page.locator('#stock-body input[name="notes"][value="Hunter\'s Mark"]')).toHaveCount(1);
  await form.getByLabel('Stock an item').fill('Spell scroll (1st level)');
  await notes.fill('Hunter’s Mark');
  await notes.press('Escape');
  await form.getByLabel('In stock').fill('3');
  await form.getByRole('button', { name: 'Add to shelf' }).click();
  await expect(page.getByLabel("Stock of Spell scroll (1st level) (Hunter's Mark)")).toHaveValue('3');
  await expect(page.locator('#stock-body input[name="notes"][value^="Hunter"]')).toHaveCount(1);

  // An item that isn't a scroll offers nothing.
  await form.getByLabel('Stock an item').fill('Rope');
  const reply = page.waitForResponse(/\/dm\/stock\/spells\?/);
  await notes.pressSequentially('fire');
  expect(await (await reply).text()).toBe('');
  await expect(spells).toBeHidden();
});
