import type { Page } from '@playwright/test';
import { test, expect, loadSrd, signInAsDm, toasts } from './fixtures';

const rows = (page: Page) => page.locator('#catalog-body tr');
const rowOf = (page: Page, name: string) =>
  rows(page).filter({ has: page.locator(`input[name="name"][value="${name}"]`) });
const pager = (page: Page) => page.getByRole('navigation', { name: 'Catalog pages' });
const button = (page: Page, name: 'Previous' | 'Next') => pager(page).getByRole('button', { name });
const firstName = (page: Page) => rows(page).first().getByLabel('Name');
/** The total in "Showing 1–50 of 190 · page 1 of 4". */
const total = async (page: Page) => Number((await pager(page).locator('span').innerText()).match(/of (\d+) ·/)![1]);

test.beforeEach(async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm/catalog');
});

test('a short catalog has no pager', async ({ page }) => {
  await expect(rows(page)).toHaveCount(28);
  await expect(pager(page)).toHaveCount(0);
});

/** Add an item nothing uses, so it has a Delete button (everything in the demo is in use). */
async function addUnused(page: Page, name: string): Promise<void> {
  const form = page.locator('#catalog-form');
  await form.getByLabel('New item').fill(name);
  await form.getByRole('button', { name: 'Add item' }).click();
  await expect(rowOf(page, name)).toHaveCount(1);
}

/** Make row saves slow, so whatever is clicked next has to wait for them. */
const holdSaves = (page: Page) =>
  page.route('**/dm/catalog/*', async (route) => {
    if (route.request().method() === 'POST' && !route.request().url().endsWith('/delete')) {
      await new Promise((done) => setTimeout(done, 800));
    }
    await route.continue();
  });

test('the whole table fits a laptop screen without sideways scrolling', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  const wrap = page.locator('.table-wrap');
  const overflow = await wrap.evaluate((el) => el.scrollWidth - el.clientWidth);
  expect(overflow).toBeLessThanOrEqual(0);
  await expect(rows(page).first().getByRole('button', { name: 'Delete' }).or(rows(page).first().getByLabel('Price'))).toBeVisible();
});

test('deleting a row right after editing another keeps the edit', async ({ page }) => {
  await addUnused(page, 'Keeper');
  await addUnused(page, 'Goner');
  // If the delete's redraw overtook the slow save, it would show the old description.
  await holdSaves(page);

  await rowOf(page, 'Keeper').getByLabel('Description').fill('Saved before the redraw.');
  await rowOf(page, 'Goner').getByRole('button', { name: 'Delete' }).click();
  await expect(toasts(page)).toContainText('Saved Keeper.');
  await expect(toasts(page)).toContainText('Deleted Goner.');
  await expect(rowOf(page, 'Goner')).toHaveCount(0);
  await expect(rowOf(page, 'Keeper').getByLabel('Description')).toHaveValue('Saved before the redraw.');
});

test('deleting a row right after editing that same row still deletes it', async ({ page }) => {
  await addUnused(page, 'Goner');
  await holdSaves(page);

  // The save replaces the row, and with it the button whose delete is waiting.
  await rowOf(page, 'Goner').getByLabel('Description').fill('About to go.');
  await rowOf(page, 'Goner').getByRole('button', { name: 'Delete' }).click();
  await expect(toasts(page)).toContainText('Deleted Goner.');
  await expect(rowOf(page, 'Goner')).toHaveCount(0);
});

test.describe('with the SRD items loaded', () => {
  test.beforeEach(async ({ page }) => {
    await loadSrd(page, '5e');
    await page.goto('/dm/catalog');
    await expect(pager(page)).toContainText('page 1 of');
  });

  test('moves between pages, and the address keeps the place', async ({ page }) => {
    await expect(rows(page)).toHaveCount(50);
    await expect(pager(page)).toContainText('Showing 1–50 of');
    await expect(button(page, 'Previous')).toBeDisabled();
    const firstOnPageOne = await firstName(page).inputValue();

    await button(page, 'Next').click();
    await expect(pager(page)).toContainText('Showing 51–100 of');
    await expect(rows(page)).toHaveCount(50);
    await expect(page).toHaveURL(/page=2/);
    await expect(button(page, 'Previous')).toBeEnabled();
    await expect(firstName(page)).not.toHaveValue(firstOnPageOne);

    await page.reload();
    await expect(pager(page)).toContainText('Showing 51–100 of');

    await button(page, 'Previous').click();
    await expect(firstName(page)).toHaveValue(firstOnPageOne);
  });

  test('the last page stops, and a page past the end shows the last one', async ({ page }) => {
    await page.goto('/dm/catalog?page=999');
    await expect(button(page, 'Next')).toBeDisabled();
    await expect(pager(page)).toContainText(`of ${await total(page)} · page`);
    expect(await rows(page).count()).toBeLessThanOrEqual(50);
    expect(await rows(page).count()).toBeGreaterThan(0);
  });

  test('changing a filter goes back to page 1, and paging keeps the filter', async ({ page }) => {
    await button(page, 'Next').click();
    await expect(page).toHaveURL(/page=2/);

    await page.locator('#cat-filter').selectOption('gear');
    await expect(page).not.toHaveURL(/page=2/);
    await expect(pager(page)).toContainText('page 1 of');
    await expect(rows(page).first().getByLabel('Category')).toHaveValue(/.+/);

    await button(page, 'Next').click();
    await expect(pager(page)).toContainText('page 2 of');
    await expect(page).toHaveURL(/cat=gear/);
    const category = rows(page).first().getByLabel('Category').locator('option:checked');
    await expect(category).toHaveText('Adventuring Gear');

    // A search narrow enough for one page takes the pager away.
    await page.getByLabel('Search').fill('Lantern');
    await expect(pager(page)).toHaveCount(0);
    await expect(rows(page)).toHaveCount(2);
  });

  test('saving and deleting on a later page stays on that page', async ({ page }) => {
    await button(page, 'Next').click();
    await expect(pager(page)).toContainText('page 2 of');
    const before = await total(page);

    const row = rows(page).nth(1);
    const name = await row.getByLabel('Name').inputValue();
    await row.getByLabel('Description').fill('Edited on page two.');
    await row.getByLabel('Description').blur();
    await expect(toasts(page)).toContainText(`Saved ${name}.`);
    await expect(pager(page)).toContainText('page 2 of');

    // Delete: the page fills back up from the next one, and the count drops.
    const unused = rows(page).filter({ has: page.getByRole('button', { name: 'Delete' }) }).first();
    const gone = await unused.getByLabel('Name').inputValue();
    await unused.getByRole('button', { name: 'Delete' }).click();
    await expect(toasts(page)).toContainText(`Deleted ${gone}.`);
    await expect(pager(page)).toContainText(`of ${before - 1} · page 2 of`);
    await expect(rows(page)).toHaveCount(50);

    // An edit that takes a row out of the filters redraws the same page, and the count drops.
    await page.locator('#cat-filter').selectOption('gear');
    await expect(pager(page)).toContainText('page 1 of');
    await button(page, 'Next').click();
    await expect(pager(page)).toContainText('page 2 of');
    const gear = await total(page);
    await rows(page).first().getByLabel('Category').selectOption({ label: 'Tool' });
    await expect(toasts(page)).toContainText('It no longer matches the filters');
    await expect(pager(page)).toContainText(`of ${gear - 1} · page 2 of`);
  });

  test('a page change clicked during a save that redraws the table still happens', async ({ page }) => {
    const all = await total(page);
    await page.locator('#cat-filter').selectOption('gear');
    // "page 1 of" shows before the filter too, so wait for the filtered count.
    await expect.poll(() => total(page)).toBeLessThan(all);
    const gear = await total(page);
    await holdSaves(page);
    // This save takes the row out of the filter, so its answer redraws the table and the pager with it, replacing
    // the Next button whose click is waiting for the save.
    await rows(page).first().getByLabel('Category').selectOption({ label: 'Tool' });
    // Clicked straight away, while the save is held. (A plain click() would wait for the save's toast to stop covering
    // the button, and so come after the save.)
    await button(page, 'Next').dispatchEvent('click');
    // Both land: the save (one fewer gear item) and then the page change.
    await expect(pager(page)).toContainText(`of ${gear - 1} · page 2 of`);
  });

  test('adding an item stays on the page and updates the count', async ({ page }) => {
    await button(page, 'Next').click();
    await expect(pager(page)).toContainText('page 2 of');
    const before = await total(page);

    const form = page.locator('#catalog-form');
    await form.getByLabel('New item').fill('Folding Boat');
    await form.getByRole('button', { name: 'Add item' }).click();
    await expect(toasts(page)).toContainText('Added Folding Boat to the catalog.');
    await expect(pager(page)).toContainText(`of ${before + 1} · page 2 of`);
  });
});
