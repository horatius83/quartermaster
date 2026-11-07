import type { Locator, Page } from '@playwright/test';
import { test, expect, signIn, signInAsDm, toasts } from './fixtures';

const rowOf = (page: Page, name: string) =>
  page.locator('#catalog-body tr').filter({ has: page.locator(`input[name="name"][value="${name}"]`) });
const catalogFilter = (page: Page) => page.getByRole('group', { name: 'Filter by tag' });
const catalogChip = (page: Page, tag: string) => catalogFilter(page).locator('label', { hasText: new RegExp(`^\\s*${tag}\\s*$`) });
const shopFilter = (page: Page) => page.getByRole('navigation', { name: 'Filter by tag' });
const wares = (page: Page) => page.locator('#wares article h2');
// htmx finishes a swap about 20ms after new rows appear. An edit made inside that window, far quicker than anyone
// could type, may not save, so a test editing a row it just filtered to waits for this first.
const swapFinished = (page: Page) => expect(page.locator('.htmx-added, .htmx-settling')).toHaveCount(0);
const pick = async (nav: Locator, name: string) => {
  await swapFinished(nav.page());
  await nav.getByRole('link', { name }).click();
};

test.describe('DM', () => {
  test.beforeEach(async ({ page }) => {
    await signInAsDm(page);
    await page.goto('/dm/catalog');
  });

  test('adds an item with tags, tidied up, and can filter by them straight away', async ({ page }) => {
    const form = page.locator('#catalog-form');
    await form.getByLabel('New item').fill('Sling');
    await form.getByLabel('Tags').fill(' Thrown,  SIMPLE , thrown,, Two   Handed ');
    await form.getByRole('button', { name: 'Add item' }).click();
    await expect(toasts(page)).toContainText('Added Sling to the catalog.');
    await expect(form.getByLabel('Tags')).toHaveValue('');
    await expect(rowOf(page, 'Sling').getByLabel('Tags')).toHaveValue('simple, thrown, two handed');
    await expect(catalogChip(page, 'thrown')).toBeVisible();

    await swapFinished(page);
    await catalogChip(page, 'thrown').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(1);
    await expect(rowOf(page, 'Sling')).toBeVisible();
  });

  test('edits and clears tags, and the filter keeps up without a reload', async ({ page }) => {
    await rowOf(page, 'Torch').getByLabel('Tags').fill('Light Source');
    await rowOf(page, 'Torch').getByLabel('Tags').blur();
    await expect(toasts(page)).toContainText('Saved Torch.');
    await expect(rowOf(page, 'Torch').getByLabel('Tags')).toHaveValue('light source');
    await expect(catalogChip(page, 'light source')).toBeVisible();

    await page.reload();
    await expect(rowOf(page, 'Torch').getByLabel('Tags')).toHaveValue('light source');

    await rowOf(page, 'Torch').getByLabel('Tags').fill('');
    await rowOf(page, 'Torch').getByLabel('Tags').blur();
    await expect(catalogChip(page, 'light source')).toHaveCount(0);
    await page.reload();
    await expect(rowOf(page, 'Torch').getByLabel('Tags')).toHaveValue('');
  });

  test('editing another field keeps the tags', async ({ page }) => {
    await rowOf(page, 'Longbow').getByLabel('Price').fill('60 gp');
    await rowOf(page, 'Longbow').getByLabel('Price').blur();
    await expect(toasts(page)).toContainText('Saved Longbow.');
    await page.reload();
    await expect(rowOf(page, 'Longbow').getByLabel('Tags')).toHaveValue('martial, ranged');
  });

  test('a tag that is too long is refused and the old tags stay', async ({ page }) => {
    await rowOf(page, 'Longbow').getByLabel('Tags').fill('x'.repeat(31));
    await rowOf(page, 'Longbow').getByLabel('Tags').blur();
    await expect(toasts(page)).toContainText('Keep each tag to 30 characters.');
    await page.reload();
    await expect(rowOf(page, 'Longbow').getByLabel('Tags')).toHaveValue('martial, ranged');
  });

  test('tags are shown as text, not run as markup', async ({ page }) => {
    await rowOf(page, 'Torch').getByLabel('Tags').fill('<img src=x>');
    await rowOf(page, 'Torch').getByLabel('Tags').blur();
    await expect(catalogFilter(page).locator('label', { hasText: '<img src=x>' })).toBeVisible();
    await expect(page.locator('#tag-filter img')).toHaveCount(0);
  });

  test('filters the catalog by several tags, along with search and category, and survives a reload', async ({ page }) => {
    await swapFinished(page);
    await catalogChip(page, 'ranged').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(3); // Shortbow, Longbow, Light crossbow
    await swapFinished(page);
    await catalogChip(page, 'simple').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(2);
    await expect(rowOf(page, 'Longbow')).toHaveCount(0);

    await page.getByRole('searchbox', { name: 'Search' }).fill('short');
    await expect(page.locator('#catalog-body tr')).toHaveCount(1);
    await expect(rowOf(page, 'Shortbow')).toBeVisible();

    await page.reload();
    await expect(page.locator('#catalog-body tr')).toHaveCount(1);
    await expect(catalogChip(page, 'simple').getByRole('checkbox')).toBeChecked();
    await expect(page.getByRole('searchbox', { name: 'Search' })).toHaveValue('short');

    await page.getByRole('searchbox', { name: 'Search' }).fill('');
    await expect(page.locator('#catalog-body tr')).toHaveCount(2);
    await page.locator('#cat-filter').selectOption({ label: 'Armor' });
    await expect(page.locator('#catalog-body')).toContainText('No items match.');
    await page.locator('#cat-filter').selectOption({ label: 'All categories' });
    await swapFinished(page);
    await catalogChip(page, 'simple').click();
    await swapFinished(page);
    await catalogChip(page, 'ranged').click();
    await expect(rowOf(page, 'Torch')).toBeVisible();
  });

  test('Back and Forward put the filters back in step with the rows', async ({ page }) => {
    const search = page.getByRole('searchbox', { name: 'Search' });
    const ticked = (tag: string) => catalogChip(page, tag).getByRole('checkbox');
    await swapFinished(page);
    await catalogChip(page, 'ranged').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(3);
    await swapFinished(page);
    await catalogChip(page, 'simple').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(2);
    await search.fill('short');
    await expect(page.locator('#catalog-body tr')).toHaveCount(1);

    await page.goBack();
    await expect(page.locator('#catalog-body tr')).toHaveCount(2);
    await expect(search).toHaveValue('');
    await expect(ticked('simple')).toBeChecked();

    await page.goBack();
    await expect(page.locator('#catalog-body tr')).toHaveCount(3);
    await expect(ticked('ranged')).toBeChecked();
    await expect(ticked('simple')).not.toBeChecked();

    // The next filter starts from what's showing, so "ranged" isn't dropped.
    await swapFinished(page);
    await catalogChip(page, 'martial').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(1);
    await expect(rowOf(page, 'Longbow')).toBeVisible();

    await page.goBack();
    await page.goBack();
    await expect(page.locator('#catalog-body tr')).toHaveCount(28);
    await expect(ticked('ranged')).not.toBeChecked();

    await page.goForward();
    await expect(page.locator('#catalog-body tr')).toHaveCount(3);
    await expect(ticked('ranged')).toBeChecked();
  });

  test('Back shows what was saved since, so editing there can’t undo it', async ({ page }) => {
    await swapFinished(page);
    await catalogChip(page, 'ranged').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(3);
    await swapFinished(page);
    await rowOf(page, 'Longbow').getByLabel('Tags').fill('ranged, two handed');
    await rowOf(page, 'Longbow').getByLabel('Tags').blur();
    await expect(rowOf(page, 'Longbow').getByLabel('Tags')).toHaveValue('ranged, two handed');
    await swapFinished(page);
    await catalogChip(page, 'simple').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(2);

    await page.goBack();
    await expect(page.locator('#catalog-body tr')).toHaveCount(3);
    await expect(rowOf(page, 'Longbow').getByLabel('Tags')).toHaveValue('ranged, two handed');
    await swapFinished(page);
    await rowOf(page, 'Longbow').getByLabel('Price').fill('60 gp');
    await rowOf(page, 'Longbow').getByLabel('Price').blur();
    await expect(toasts(page)).toContainText('Saved Longbow.');
    await page.reload();
    await expect(rowOf(page, 'Longbow').getByLabel('Tags')).toHaveValue('ranged, two handed');
  });

  test('Back honours a filter address however it was written', async ({ page }) => {
    await page.goto('/dm/catalog?tag[]=Ranged');
    await expect(page.locator('#catalog-body tr')).toHaveCount(3);
    await swapFinished(page);
    await catalogChip(page, 'simple').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(2);
    await page.goBack();
    await expect(page.locator('#catalog-body tr')).toHaveCount(3);
    await expect(catalogChip(page, 'ranged').getByRole('checkbox')).toBeChecked();
    await expect(catalogChip(page, 'simple').getByRole('checkbox')).not.toBeChecked();
  });

  test('an edit that takes a row out of the filters hides it and says so', async ({ page }) => {
    await swapFinished(page);
    await catalogChip(page, 'heavy').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(1);
    await swapFinished(page);
    await rowOf(page, 'Chain mail').getByLabel('Tags').fill('');
    await rowOf(page, 'Chain mail').getByLabel('Tags').blur();
    await expect(toasts(page)).toContainText('Saved Chain mail. It no longer matches the filters');
    await expect(page.locator('#catalog-body')).toContainText('No items match.');
    await expect(catalogChip(page, 'heavy').getByRole('checkbox')).toBeChecked();

    await swapFinished(page);
    await catalogChip(page, 'heavy').click();
    await expect(rowOf(page, 'Chain mail').getByLabel('Tags')).toHaveValue('');
    // No item has the tag any more, so once it's unticked it leaves the filter.
    await expect(catalogChip(page, 'heavy')).toHaveCount(0);
    await expect(page).not.toHaveURL(/heavy/);
  });

  // The test server handles one request at a time, so these slow requests down in the browser to make them overlap.
  const slowRowSaves = (page: Page) => page.route(/\/dm\/catalog\/\d+$/, async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 600));
    await route.continue();
  });

  test('clicking a chip during a slow save saves first, then filters', async ({ page }) => {
    await slowRowSaves(page);
    await rowOf(page, 'Torch').getByLabel('Tags').fill('light');
    await catalogChip(page, 'light').click();
    await expect(toasts(page)).toContainText('Saved Torch.');
    await expect(page.locator('#catalog-body tr')).toHaveCount(2); // Leather armor and the Torch
    await expect(rowOf(page, 'Torch').getByLabel('Tags')).toHaveValue('light');
    await expect(catalogChip(page, 'light').getByRole('checkbox')).toBeChecked();
  });

  test('a row locks during a slow save, and the next field’s edit isn’t lost', async ({ page }) => {
    await slowRowSaves(page);
    await rowOf(page, 'Torch').getByLabel('Price').fill('9 gp');
    await rowOf(page, 'Torch').getByLabel('Price').blur();
    await expect(rowOf(page, 'Torch').getByLabel('Description')).toBeDisabled();
    await expect(rowOf(page, 'Torch').getByLabel('Price')).toHaveValue('9 gp');
    await expect(rowOf(page, 'Torch').getByLabel('Description')).toBeEnabled();
    await swapFinished(page);

    await rowOf(page, 'Torch').getByLabel('Description').fill('Burns for six hours.');
    await rowOf(page, 'Torch').getByLabel('Description').blur();
    await expect(rowOf(page, 'Torch').getByLabel('Description')).toBeDisabled();
    await expect(rowOf(page, 'Torch').getByLabel('Description')).toBeEnabled();
    await page.reload();
    await expect(rowOf(page, 'Torch').getByLabel('Price')).toHaveValue('9 gp');
    await expect(rowOf(page, 'Torch').getByLabel('Description')).toHaveValue('Burns for six hours.');
  });

  test('saves in two rows at once both land', async ({ page }) => {
    await slowRowSaves(page);
    await rowOf(page, 'Torch').getByLabel('Price').fill('9 gp');
    await rowOf(page, 'Torch').getByLabel('Price').blur();
    await rowOf(page, 'Longbow').getByLabel('Price').fill('60 gp');
    await rowOf(page, 'Longbow').getByLabel('Price').blur();
    await expect(rowOf(page, 'Longbow').getByLabel('Description')).toBeDisabled();
    await expect(rowOf(page, 'Longbow').getByLabel('Description')).toBeEnabled();
    await expect(rowOf(page, 'Torch').getByLabel('Description')).toBeEnabled();
    await page.reload();
    await expect(rowOf(page, 'Torch').getByLabel('Price')).toHaveValue('9 gp');
    await expect(rowOf(page, 'Longbow').getByLabel('Price')).toHaveValue('60 gp');
  });

  test('rows lock while the table redraws, then unlock', async ({ page }) => {
    await page.route(/\/dm\/catalog\?/, async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 600));
      await route.continue();
    });
    await catalogChip(page, 'ranged').click();
    await expect(rowOf(page, 'Longbow').getByLabel('Tags')).toBeDisabled();
    await expect(page.locator('#catalog-body tr')).toHaveCount(3);
    await expect(rowOf(page, 'Longbow').getByLabel('Tags')).toBeEnabled();
  });

  test('a failed save unlocks the row and later redraws still run', async ({ page }) => {
    await slowRowSaves(page);
    await rowOf(page, 'Torch').getByLabel('Price').fill('not a price');
    await rowOf(page, 'Torch').getByLabel('Price').blur();
    await expect(rowOf(page, 'Torch').getByLabel('Description')).toBeDisabled();
    await expect(toasts(page)).toContainText('Write prices like');
    await expect(rowOf(page, 'Torch').getByLabel('Description')).toBeEnabled();
    await catalogChip(page, 'heavy').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(1);
  });

  test('a row saved without a tags field keeps its tags', async ({ page }) => {
    await rowOf(page, 'Longbow').getByLabel('Tags').evaluate((el) => el.remove());
    await rowOf(page, 'Longbow').getByLabel('Price').fill('60 gp');
    await rowOf(page, 'Longbow').getByLabel('Price').blur();
    await expect(toasts(page)).toContainText('Saved Longbow.');
    await page.reload();
    await expect(rowOf(page, 'Longbow').getByLabel('Tags')).toHaveValue('martial, ranged');
  });

  test('the table warns that players see tags', async ({ page }) => {
    await expect(page.locator('thead')).toContainText('players see tags');
  });

  test('adding a name already taken leaves the existing item’s tags alone', async ({ page }) => {
    const form = page.locator('#catalog-form');
    await form.getByLabel('New item').fill('longbow');
    await form.getByLabel('Tags').fill('stolen');
    await form.getByRole('button', { name: 'Add item' }).click();
    await expect(toasts(page)).toContainText('is already in the catalog.');
    await page.reload();
    await expect(rowOf(page, 'Longbow').getByLabel('Tags')).toHaveValue('martial, ranged');
  });

  test('adding an item keeps the table matching the filters', async ({ page }) => {
    const form = page.locator('#catalog-form');
    await swapFinished(page);
    await catalogChip(page, 'ranged').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(3);

    await form.getByLabel('New item').fill('Plain Sack');
    await form.getByRole('button', { name: 'Add item' }).click();
    await expect(toasts(page)).toContainText('Added Plain Sack to the catalog.');
    await expect(form.getByLabel('New item')).toHaveValue('');
    await expect(page.locator('#catalog-body tr')).toHaveCount(3);
    await expect(catalogChip(page, 'ranged').getByRole('checkbox')).toBeChecked();

    await form.getByLabel('New item').fill('Sling');
    await form.getByLabel('Tags').fill('ranged, thrown');
    await form.getByRole('button', { name: 'Add item' }).click();
    await expect(rowOf(page, 'Sling')).toBeVisible();
    await expect(page.locator('#catalog-body tr')).toHaveCount(4);
    await expect(catalogChip(page, 'thrown')).toBeVisible();
    await expect(catalogChip(page, 'ranged').getByRole('checkbox')).toBeChecked();
  });

  test('a chip stays ticked when editing tags refreshes the filter', async ({ page }) => {
    await swapFinished(page);
    await catalogChip(page, 'ranged').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(3);
    await swapFinished(page);

    await rowOf(page, 'Longbow').getByLabel('Tags').fill('martial, ranged, two handed');
    await rowOf(page, 'Longbow').getByLabel('Tags').blur();
    await expect(catalogChip(page, 'two handed')).toBeVisible();
    await expect(catalogChip(page, 'ranged').getByRole('checkbox')).toBeChecked();

    await swapFinished(page);
    await catalogChip(page, 'two handed').click();
    await expect(page.locator('#catalog-body tr')).toHaveCount(1);
    await expect(rowOf(page, 'Longbow')).toBeVisible();
  });

  test('tags that look like numbers keep the order the database gives them', async ({ page }) => {
    await rowOf(page, 'Torch').getByLabel('Tags').fill('9, 10');
    await rowOf(page, 'Torch').getByLabel('Tags').blur();
    await expect(rowOf(page, 'Torch').getByLabel('Tags')).toHaveValue('10, 9');
    await expect(catalogFilter(page).locator('label').filter({ hasText: /^\s*\d+\s*$/ })).toHaveText([/10/, /9/]);
  });

  test('deleting the last item with a tag takes the tag off the filter', async ({ page }) => {
    const form = page.locator('#catalog-form');
    await form.getByLabel('New item').fill('Oddity');
    await form.getByLabel('Tags').fill('one of a kind');
    await form.getByRole('button', { name: 'Add item' }).click();
    await expect(catalogChip(page, 'one of a kind')).toBeVisible();

    await rowOf(page, 'Oddity').getByRole('button', { name: 'Delete' }).click();
    await expect(rowOf(page, 'Oddity')).toHaveCount(0);
    await expect(catalogChip(page, 'one of a kind')).toHaveCount(0);
  });
});

test.describe('Player', () => {
  test('filters a shop by tags, narrowing with each one, and can undo it', async ({ page }) => {
    await signIn(page, 'bryn');
    await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
    const allAnvil = ['Longsword', 'Battleaxe', 'Warhammer', 'Chain mail', 'Scale mail', 'Shield', 'Smith’s tools'];
    await expect(wares(page)).toHaveCount(allAnvil.length);
    await expect(page.locator('article', { hasText: 'Chain mail' }).locator('.item-tag')).toHaveText(['heavy']);

    await pick(shopFilter(page), 'heavy');
    await expect(wares(page)).toHaveText(['Chain mail']);
    await expect(shopFilter(page).getByRole('link', { name: 'heavy' })).toHaveAttribute('aria-current', 'true');

    // Only tags on the wares showing are offered, so no pick leads nowhere.
    await expect(shopFilter(page).getByRole('link')).toHaveText(['heavy']);

    await pick(shopFilter(page), 'heavy');
    await expect(wares(page)).toHaveCount(allAnvil.length);
    await pick(shopFilter(page), 'melee');
    await expect(wares(page)).toHaveText(['Battleaxe', 'Longsword', 'Warhammer']);
    await expect(shopFilter(page).getByRole('link')).toHaveText(['martial', 'melee']);
    await pick(shopFilter(page), 'melee');
    await expect(wares(page)).toHaveCount(allAnvil.length);
  });

  test('a link to tags nothing has together says so and can be undone', async ({ page }) => {
    await signIn(page, 'bryn');
    await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
    await expect(page.getByRole('heading', { name: 'The Rusty Anvil' })).toBeVisible();
    await page.goto(`${page.url().split('?')[0]}?tag[]=heavy&tag[]=melee`);
    await expect(wares(page)).toHaveCount(0);
    await expect(page.locator('#wares')).toContainText('Nothing here has all of those tags.');
    await pick(shopFilter(page), 'melee');
    await expect(wares(page)).toHaveText(['Chain mail']);
  });

  test('a filtered address works on its own, and buying still works from it', async ({ page }) => {
    await signIn(page, 'bryn');
    await page.getByRole('link', { name: 'Fletcher’s Rest' }).click();
    await pick(shopFilter(page), 'ranged');
    await expect(shopFilter(page).getByRole('link', { name: 'ranged' })).toHaveAttribute('aria-current', 'true');
    await pick(shopFilter(page), 'martial');
    await expect(wares(page)).toHaveText(['Longbow']);

    await page.reload();
    await expect(wares(page)).toHaveText(['Longbow']);
    await expect(shopFilter(page).getByRole('link', { name: 'martial' })).toHaveAttribute('aria-current', 'true');

    await page.getByRole('button', { name: 'Add Longbow to cart' }).click();
    await expect(page.locator('#cart .cart-line', { hasText: 'Longbow' })).toBeVisible();
  });

  test('tag and category filters combine, and tags follow the category', async ({ page }) => {
    await signIn(page, 'bryn');
    await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
    await pick(page.getByRole('navigation', { name: 'Filter by category' }), 'Armor');
    await expect(shopFilter(page).getByRole('link')).toHaveText(['heavy', 'medium']);

    await pick(shopFilter(page), 'medium');
    await expect(wares(page)).toHaveText(['Scale mail']);

    // Only categories with wares carrying the tag are offered, so no pick leads nowhere.
    const categories = page.getByRole('navigation', { name: 'Filter by category' });
    await expect(categories.getByRole('link')).toHaveText(['All', 'Armor']);

    // A link can still ask for a category without the tag: the tag and category stay listed, to be undone.
    await page.goto(`${page.url().split('?')[0]}?cat=weapon&tag[]=medium`);
    await expect(wares(page)).toHaveCount(0);
    await expect(categories.getByRole('link', { name: 'Weapon' })).toHaveAttribute('aria-current', 'true');
    await expect(shopFilter(page).getByRole('link', { name: 'medium' })).toHaveAttribute('aria-current', 'true');
    await pick(shopFilter(page), 'medium');
    await expect(wares(page)).toHaveCount(3);
  });

  test('narrowing to one category still lets a player leave it', async ({ page }) => {
    await signIn(page, 'bryn');
    await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
    const categories = page.getByRole('navigation', { name: 'Filter by category' });
    await pick(categories, 'Armor');
    await pick(shopFilter(page), 'heavy');
    await expect(categories.getByRole('link')).toHaveText(['All', 'Armor']);
    await pick(categories, 'All');
    await expect(wares(page)).toHaveText(['Chain mail']);
    // Only armor has this tag, so with no category chosen there's nothing to choose between.
    await expect(categories).toHaveCount(0);
  });

  test('a second chip click during a slow load is ignored, not mixed up', async ({ page }) => {
    await signIn(page, 'bryn');
    await page.getByRole('link', { name: 'Fletcher’s Rest' }).click();
    await expect(page.getByRole('heading', { name: 'Fletcher’s Rest' })).toBeVisible();
    await expect(shopFilter(page).getByRole('link', { name: 'martial' })).toBeVisible();
    await swapFinished(page);
    // The first click's reply is the slower one: if the second were sent too, its reply would win and "ranged" would be lost.
    await page.route(/\/shop\/\d+\?/, async (route) => {
      await new Promise((resolve) => setTimeout(resolve, route.request().url().includes('ranged') ? 900 : 100));
      await route.continue();
    });
    await shopFilter(page).getByRole('link', { name: 'ranged' }).click();
    await shopFilter(page).getByRole('link', { name: 'martial' }).click();

    await expect(shopFilter(page).getByRole('link', { name: 'ranged' })).toHaveAttribute('aria-current', 'true');
    await expect(wares(page)).toHaveText(['Longbow', 'Shortbow']);
    await expect(shopFilter(page).getByRole('link', { name: 'martial' })).not.toHaveAttribute('aria-current', 'true');

    await pick(shopFilter(page), 'martial');
    await expect(wares(page)).toHaveText(['Longbow']);
  });

  test('Back in a shop shows the cart as it is now', async ({ page }) => {
    await signIn(page, 'bryn');
    await page.getByRole('link', { name: 'The Rusty Anvil' }).click();
    await pick(shopFilter(page), 'melee');
    await expect(wares(page)).toHaveCount(3);
    await swapFinished(page);
    await page.getByRole('button', { name: 'Add Longsword to cart' }).click();
    await expect(page.locator('#cart .cart-line', { hasText: 'Longsword' })).toBeVisible();

    await page.goBack();
    await expect(wares(page)).toHaveCount(7);
    await expect(page.locator('#cart .cart-line', { hasText: 'Longsword' })).toBeVisible();
  });

  test('a shop with no tagged wares shows no tag filter', async ({ page }) => {
    await signIn(page, 'bryn');
    await page.getByRole('link', { name: 'Mossbottom Apothecary' }).click();
    await expect(wares(page)).not.toHaveCount(0);
    await expect(shopFilter(page)).toHaveCount(0);
  });
});
