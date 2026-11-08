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
  await expect(suggestions(page).getByRole('option')).not.toHaveCount(0);
  await input.fill('');
  await expect(suggestions(page)).toBeHidden();
  await input.press('ArrowDown');
  await expect(suggestions(page)).toBeHidden();
  await expect(suggestions(page).getByRole('option')).toHaveCount(0);
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
