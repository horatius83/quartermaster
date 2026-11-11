import type { Page } from '@playwright/test';
import { test, expect, loadSrd, signIn, signInAsAdmin, signInAsDm, toasts } from './fixtures';
import { checkSrdData, openCampaignSystem, upgradeToRulesets } from './php';

const rows = (page: Page) => page.locator('#catalog-body tr');
const rowOf = (page: Page, name: string) =>
  page.locator('#catalog-body tr').filter({ has: page.locator(`input[name="name"][value="${name}"]`) });

/** Start a campaign of this system and go to its catalog. */
async function startCampaign(page: Page, name: string, system: string): Promise<void> {
  await page.goto('/dm/campaigns');
  await page.getByLabel('New campaign').fill(name);
  await page.locator('form', { has: page.getByLabel('New campaign') }).getByLabel(/^System/).selectOption({ label: system });
  await page.getByRole('button', { name: 'Start campaign' }).click();
  await expect(page).toHaveURL(/\/dm\/party$/);
  await page.goto('/dm/catalog');
}

/** Go back to another campaign and to its catalog. */
async function switchToCampaign(page: Page, name: string): Promise<void> {
  await page.goto('/dm/campaigns');
  await page.locator('tbody tr', { has: page.locator(`input[value="${name}"]`) }).getByRole('button', { name: 'Switch to' }).click();
  await expect(page).toHaveURL(/\/dm$/);
  await page.goto('/dm/catalog');
}

async function addItem(page: Page, name: string): Promise<void> {
  const form = page.locator('#catalog-form');
  await form.getByLabel('New item').fill(name);
  await form.getByRole('button', { name: 'Add item' }).click();
  await expect(toasts(page)).toContainText(`Added ${name} to the catalog.`);
}

test('the bundled SRD data is all usable', () => {
  expect(checkSrdData()).toEqual({ problems: [] });
});

test('the upgrade keeps items, tags and everything pointing at them, and frees names per system', () => {
  expect(upgradeToRulesets()).toEqual({
    campaignRulesets: ['5e'],
    campaignsOpen: [1],
    newCampaignOpen: 0,
    items: [{ id: 7, name: 'Old sword', ruleset: '5e', description: 'Well used.' }],
    tags: ['martial', 'melee'],
    inventoryItems: [7],
    foreignKeyProblems: 0,
    nextItemId: 10, // id 9 was used and deleted before the upgrade, so it isn't handed out again
    otherEditionAllowed: true,
    sameEditionRejected: true,
  });
});

test.describe('a DM with campaigns in two systems', () => {
  test.beforeEach(async ({ page }) => {
    await signInAsDm(page);
  });

  test('a campaign sees only its own system’s items and tags, wherever it looks', async ({ page }) => {
    await startCampaign(page, 'Old School', '3.5e');
    await addItem(page, 'Old lantern');

    await expect(rowOf(page, 'Longsword')).toHaveCount(0);
    await expect(rowOf(page, 'Old lantern')).toHaveCount(1);
    await expect(page.locator('#tag-filter')).not.toContainText('martial');
    expect(await (await page.request.get('/dm/catalog/suggest?item=Longsword')).text()).toBe('');

    // There's no way to ask for the others, in the page or its address.
    await expect(page.getByLabel('System')).toHaveCount(0);
    await expect(page.getByLabel('Edition')).toHaveCount(0);
    await page.goto('/dm/catalog?ruleset=all');
    await expect(rowOf(page, 'Longsword')).toHaveCount(0);

    await switchToCampaign(page, 'The Sunken Crown');
    await expect(rowOf(page, 'Longsword')).toHaveCount(1);
    await expect(rowOf(page, 'Old lantern')).toHaveCount(0);
    await expect(page.locator('#tag-filter')).toContainText('martial');
    expect(await (await page.request.get('/dm/catalog/suggest?item=Old%20lantern')).text()).toBe('');
  });

  test('a campaign’s system is fixed once it starts', async ({ page }) => {
    await page.goto('/dm/campaigns');
    const row = page.locator('tbody tr', { has: page.locator('input[value="The Sunken Crown"]') });
    await expect(row.locator('td').nth(1)).toHaveText('5e');
    await expect(row.locator('select')).toHaveCount(0);
    await expect(row.getByRole('button', { name: 'Change' })).toHaveCount(0);

    const token = await page.locator('input[name="_csrf"]').first().getAttribute('value');
    await page.request.post('/dm/campaigns/1/ruleset', { form: { ruleset: '3.5e', _csrf: token! }, maxRedirects: 0 });
    await page.reload();
    await expect(toasts(page)).toContainText('The Sunken Crown’s system is already set, and can’t be changed.');
    await expect(row.locator('td').nth(1)).toHaveText('5e');
  });

  test.describe('and an older campaign that really plays 3.5e', () => {
    const SUNKEN = 'The Sunken Crown';
    const systemOf = (page: Page) => page.getByLabel(`System of ${SUNKEN}`);
    const setSystem = async (page: Page, system: string) => {
      await page.goto('/dm/campaigns');
      await systemOf(page).selectOption({ label: system });
      await page.getByRole('button', { name: 'Set once' }).click();
      await expect(toasts(page)).toContainText('that’s now fixed');
      await expect(systemOf(page)).toHaveCount(0);
    };

    test.beforeEach(() => openCampaignSystem(SUNKEN));

    test('setting it moves what the campaign holds, and leaves the 5e items alone', async ({ page }) => {
      await page.goto('/dm/catalog');
      const description = await rowOf(page, 'Longsword').getByLabel('Description').inputValue();
      const tags = await rowOf(page, 'Longsword').getByLabel('Tags').inputValue();
      // In the shared 5e catalog but in nothing of this campaign's, like another DM's items.
      await addItem(page, 'Unused trinket');

      await setSystem(page, '3.5e');
      await expect(page.locator('tbody tr', { has: page.locator(`input[value="${SUNKEN}"]`) }).locator('td').nth(1)).toHaveText('3.5e');

      // The catalog now shows the copies, in use by this campaign's packs and shelves, and only those.
      await page.goto('/dm/catalog');
      await expect(rowOf(page, 'Unused trinket')).toHaveCount(0);
      const inUse = await rows(page).evaluateAll((trs) => trs.map((tr) => tr.children[tr.children.length - 2].textContent!.trim()));
      expect(inUse.length).toBeGreaterThan(0);
      expect(inUse).not.toContain('—');
      await expect(rowOf(page, 'Longsword').getByLabel('Description')).toHaveValue(description);
      await expect(rowOf(page, 'Longsword').getByLabel('Tags')).toHaveValue(tags);
      await expect(rowOf(page, 'Longsword').locator('td').nth(-2)).not.toHaveText('—');

      await page.goto('/dm/inventory/1');
      await expect(page.getByRole('cell', { name: /^Longsword \+1/ })).toBeVisible();
      await page.goto('/dm/history/1');
      await expect(page.locator('main')).toContainText('Longsword');

      // Editing the copy, to check below that the 5e original doesn't follow.
      await page.goto('/dm/catalog');
      await rowOf(page, 'Longsword').getByLabel('Description').fill('The 3.5e one.');
      await rowOf(page, 'Longsword').getByLabel('Description').blur();
      await expect(toasts(page)).toContainText('Saved Longsword.');

      // The other campaign is still 5e, and its items didn't change with the copy.
      await switchToCampaign(page, 'Winter’s Teeth');
      await expect(rowOf(page, 'Longsword').getByLabel('Description')).toHaveValue(description);
    });

    test('an item in a player’s open cart moves too, and adding it again joins the same line', async ({ page }) => {
      await signIn(page, 'bryn');
      await page.goto('/shop/1');
      await page.getByRole('button', { name: 'Add Longsword to cart' }).click();
      await expect(page.locator('#cart .cart-line', { hasText: 'Longsword' })).toBeVisible();

      await signInAsDm(page);
      await setSystem(page, '3.5e');

      await signIn(page, 'bryn');
      await page.goto('/shop/1');
      await expect(page.locator('#cart .cart-line', { hasText: 'Longsword' })).toBeVisible();
      await page.getByRole('button', { name: 'Add Longsword to cart' }).click();
      await expect(page.locator('#cart .cart-line', { hasText: 'Longsword' })).toHaveCount(1);
    });

    test('a name the chosen system already has means that item', async ({ page }) => {
      await loadSrd(page, '3.5e');

      await setSystem(page, '3.5e');
      await page.goto('/dm/catalog?q=Longsword');
      await expect(rows(page)).toHaveCount(1);
      await expect(rowOf(page, 'Longsword').getByLabel('Weight')).toHaveValue('4');
    });

    test('confirming 5e copies nothing and locks it', async ({ page }) => {
      await setSystem(page, '5e');
      await expect(toasts(page)).toContainText('plays 5e, and that’s now fixed.');
      await startCampaign(page, 'Old School', '3.5e');
      await expect(rowOf(page, 'Longsword')).toHaveCount(0);
    });

    test('a made-up system is refused, and it can still be set', async ({ page }) => {
      await page.goto('/dm/campaigns');
      const token = await page.locator('input[name="_csrf"]').first().getAttribute('value');
      await page.request.post('/dm/campaigns/1/ruleset', { form: { ruleset: 'bogus', _csrf: token! }, maxRedirects: 0 });
      await page.reload();
      await expect(toasts(page)).toContainText('Pick one of the systems in the list.');
      await expect(systemOf(page)).toHaveValue('5e');
    });
  });

  test('a mistaken system can be fixed by starting again and deleting the empty campaign', async ({ page }) => {
    await startCampaign(page, 'Oops', '3.5e');
    await page.goto('/dm/campaigns');
    await page.locator('tbody tr', { has: page.locator('input[value="Oops"]') }).getByRole('button', { name: 'Delete' }).click();
    await expect(toasts(page)).toContainText('Deleted Oops.');
  });

  test('two systems can each have a Longsword, but one system can’t have two', async ({ page }) => {
    await startCampaign(page, 'Old School', '3.5e');
    const form = page.locator('#catalog-form');
    await form.getByLabel('New item').fill('Longsword');
    await form.getByLabel('Weight (lb)').fill('4');
    await form.getByRole('button', { name: 'Add item' }).click();
    await expect(toasts(page)).toContainText('Added Longsword to the catalog.');
    await expect(rowOf(page, 'Longsword').getByLabel('Weight')).toHaveValue('4');

    await form.getByLabel('New item').fill('longsword');
    await form.getByRole('button', { name: 'Add item' }).click();
    await expect(toasts(page)).toContainText('“longsword” is already in the catalog for 3.5e.');

    // Renaming onto a name the system already has is refused too.
    await addItem(page, 'Shortsword');
    await rowOf(page, 'Shortsword').getByLabel('Name').fill('Longsword');
    await rowOf(page, 'Shortsword').getByLabel('Name').blur();
    await expect(toasts(page)).toContainText('Another 3.5e item is already called “Longsword”.');

    // The 5e one is untouched.
    await switchToCampaign(page, 'The Sunken Crown');
    await expect(rowOf(page, 'Longsword').getByLabel('Weight')).not.toHaveValue('4');
  });

  test('a tab left open on another system can’t change that system’s items', async ({ page, context }) => {
    await page.goto('/dm/catalog');
    const description = rowOf(page, 'Torch').getByLabel('Description');
    const before = await description.inputValue();

    const other = await context.newPage();
    await startCampaign(other, 'Old School', '3.5e');
    await other.close();

    await description.fill('Changed from a stale tab.');
    await description.blur();
    await expect(toasts(page)).toContainText('No such item.');

    await switchToCampaign(page, 'The Sunken Crown');
    await expect(rowOf(page, 'Torch').getByLabel('Description')).toHaveValue(before);
  });

  test('Pathfinder campaigns start with no items to load, and DMs add their own', async ({ page }) => {
    await startCampaign(page, 'Rise of Runelords', 'Pathfinder 1e');
    await expect(page.locator('main')).not.toContainText('SRD');
    await expect(page.locator('#catalog-body')).toContainText('No items match.');
    await expect(page.locator('main header')).toContainText('Pathfinder 1e');

    await addItem(page, 'Longsword');
    await expect(rowOf(page, 'Longsword')).toHaveCount(1);
    await page.locator('#catalog-form').getByLabel('New item').fill('longsword');
    await page.locator('#catalog-form').getByRole('button', { name: 'Add item' }).click();
    await expect(toasts(page)).toContainText('“longsword” is already in the catalog for Pathfinder 1e.');

    // 1e and 2e are separate systems.
    await startCampaign(page, 'Age of Ashes', 'Pathfinder 2e');
    await expect(rowOf(page, 'Longsword')).toHaveCount(0);

    await page.goto('/dm/campaigns');
    await expect(page.locator('tbody tr', { has: page.locator('input[value="Age of Ashes"]') }).locator('td').nth(1)).toHaveText('Pathfinder 2e');
  });

  test('loading the SRD adds each system’s own items and leaves the DM’s alone', async ({ page }) => {
    await page.goto('/dm/catalog');
    const longsword = rowOf(page, 'Longsword');
    await longsword.getByLabel('Description').fill('The smith’s own.');
    await longsword.getByLabel('Description').blur();
    await expect(toasts(page)).toContainText('Saved Longsword.');

    const load = async (system: string) => {
      await page.goto('/admin');
      await page.getByRole('button', { name: `Load ${system} SRD gear` }).click();
    };
    await signInAsAdmin(page);
    await load('5e');
    await expect(toasts(page)).toContainText(/Added \d+ 5e SRD gear items and skipped \d+ already in the catalog\./);
    await load('3.5e');
    await expect(toasts(page)).toContainText(/Added \d+ 3\.5e SRD gear items\./);
    // Once everything's in, the button says so rather than offering to do nothing.
    await expect(page.getByRole('button', { name: 'Load 3.5e SRD gear' })).toBeDisabled();
    await expect(page.getByRole('article', { name: '3.5e SRD' }).getByRole('region', { name: 'Weapons, armor and gear' })).toContainText('Loaded');

    await signInAsDm(page);
    await page.goto('/dm/catalog');
    await expect(longsword.getByLabel('Description')).toHaveValue('The smith’s own.');
    await expect(rowOf(page, 'Greatsword').getByLabel('Description')).toHaveValue('2d6 slashing. Heavy, two-handed.');
    await expect(rowOf(page, 'Greatsword').getByLabel('Tags')).toHaveValue('heavy, martial, melee, two-handed');
    await expect(page.locator('#tag-filter')).toContainText('two-handed');

    await startCampaign(page, 'Old School', '3.5e');
    await expect(rowOf(page, 'Longsword').getByLabel('Weight')).toHaveValue('4');
    await expect(rowOf(page, 'Longsword').getByLabel('Description')).toContainText('Critical 19-20/×2');
    await expect(page.locator('main')).not.toContainText('aren’t in this catalog yet');
  });

  test('stocking a shelf by name finds the campaign’s own system', async ({ page }) => {
    await loadSrd(page, '3.5e');
    await startCampaign(page, 'Old School', '3.5e');

    const suggestions = await page.request.get('/dm/catalog/suggest?item=Longsword');
    expect(await suggestions.text()).toContain('4 lb');

    await page.goto('/dm/stores');
    await page.getByLabel('New store name').fill('Old Forge');
    await page.getByRole('button', { name: '+ Open a new store' }).click();
    await page.getByLabel('Stock an item').fill('Longsword');
    await page.getByRole('button', { name: 'Add to shelf' }).click();
    await expect(page.locator('#stock-body tr', { hasText: 'Longsword' })).toHaveCount(1);

    // Only the 3.5e Longsword is in use now, not the 5e one the demo's stores and packs hold.
    await page.goto('/dm/catalog');
    await expect(rowOf(page, 'Longsword').locator('td').nth(-2)).toHaveText('1');
  });

  test('an existing name with a straight apostrophe isn’t loaded again', async ({ page }) => {
    await page.goto('/dm/catalog');
    const form = page.locator('#catalog-form');
    await form.getByLabel('New item').fill("Tinker's tools");
    await form.getByRole('button', { name: 'Add item' }).click();
    await expect(toasts(page)).toContainText('Added Tinker');

    await loadSrd(page, '5e');
    await page.goto('/dm/catalog?q=Tinker');
    await expect(rows(page)).toHaveCount(1);
  });

  test('a new name typed into the stock form joins the campaign’s system', async ({ page }) => {
    await startCampaign(page, 'Old School', '3.5e');
    await page.goto('/dm/stores');
    await page.getByLabel('New store name').fill('Old Forge');
    await page.getByRole('button', { name: '+ Open a new store' }).click();
    await page.getByLabel('Stock an item').fill('Glowing Rock');
    await page.getByRole('button', { name: 'Add to shelf' }).click();
    await expect(page.locator('#stock-body tr', { hasText: 'Glowing Rock' })).toHaveCount(1);

    await page.goto('/dm/catalog');
    await expect(rowOf(page, 'Glowing Rock')).toHaveCount(1);
    await switchToCampaign(page, 'The Sunken Crown');
    await expect(rowOf(page, 'Glowing Rock')).toHaveCount(0);
  });

  test('the licence notices are served, and DMs and players can reach them', async ({ page }) => {
    await page.goto('/dm');
    await expect(page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'SRD licences' })).toBeVisible();
    await signIn(page, 'bryn');
    await page.goto('/pack');
    const link = page.getByRole('link', { name: 'SRD licences' });
    await expect(link).toBeVisible();
    await expect(link).toHaveAttribute('download', 'srd-notices.txt');
    const notices = await (await page.request.get((await link.getAttribute('href'))!)).text();
    expect(notices).toContain('OPEN GAME LICENSE Version 1.0a');
    expect(notices).toContain('Commons Attribution 4.0 International License');
  });
});
