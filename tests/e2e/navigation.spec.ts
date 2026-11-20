import type { Page } from '@playwright/test';
import { test, expect, signIn, signInAsDm, BRYN } from './fixtures';

/**
 * Click a sidebar or top-bar link and say whether the page was swapped in place (as boosted links are) rather than
 * loaded afresh. A fresh load restyles the whole page, which shows as the layout popping and resizing.
 */
async function swappedInPlace(page: Page, link: string, lands: RegExp): Promise<boolean> {
  await page.evaluate(() => ((window as unknown as { qmSamePage: boolean }).qmSamePage = true));
  await page.getByRole('link', { name: link, exact: true }).click();
  // Wait for where the link ends up, so a fresh load has happened by the time the flag is read.
  await expect(page).toHaveURL(lands);
  await page.waitForLoadState();
  return page.evaluate(() => (window as unknown as { qmSamePage?: boolean }).qmSamePage === true);
}

test('links that redirect still swap the page in place', async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm');
  // Player inventories goes to /dm/inventory, which redirects to the first character's pack.
  expect(await swappedInPlace(page, 'Player inventories', new RegExp(`/dm/inventory/${BRYN}$`))).toBe(true);
  await expect(page.getByRole('heading', { name: 'Bryn Ashvale’s Pack' })).toBeVisible();
  await expect(page.locator('nav.sidebar')).toHaveCount(1);
  // Back returns to the dashboard, not to the address that redirected.
  await page.goBack();
  await expect(page).toHaveURL(/\/dm$/);

  // A player's Shops link redirects to their first shop the same way.
  await signIn(page, 'bryn');
  await page.goto('/pack');
  expect(await swappedInPlace(page, 'Shops', /\/shop\/\d+$/)).toBe(true);
  await expect(page.locator('#cart')).toBeVisible();
});

test('a redirect for a form still loads the page in full, with its message', async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm/campaigns');
  await page.locator('tbody tr', { hasText: 'Running now' }).getByRole('button', { name: 'Rename' }).click();
  await expect(page.locator('#toasts')).toContainText('Renamed');
});
