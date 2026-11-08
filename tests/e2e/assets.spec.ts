import type { Page } from '@playwright/test';
import { test, expect, signIn, signInAsDm } from './fixtures';

/** Watch for anything the page fetches from another site. Everything it needs should come from public/assets. */
function externalRequests(page: Page): string[] {
  const seen: string[] = [];
  page.on('request', (request) => {
    const url = new URL(request.url());
    if (url.protocol.startsWith('http') && url.hostname !== '127.0.0.1') seen.push(request.url());
  });
  return seen;
}

test('every kind of page loads its script and fonts from this site', async ({ page }) => {
  const external = externalRequests(page);
  const failed: string[] = [];
  page.on('response', (response) => {
    if (response.url().includes('/assets/') && !response.ok()) failed.push(response.url());
  });

  await signInAsDm(page); // starts on the signed-out login page
  await page.goto('/dm/catalog');
  await signIn(page, 'bryn');
  await page.goto('/shop');

  expect(await page.evaluate(() => (window as unknown as { htmx: { version: string } }).htmx.version)).toBe('2.0.4');
  for (const font of ['500 20px Caveat', '700 20px Caveat', '400 20px "Patrick Hand"']) {
    const faces = await page.evaluate((f) => document.fonts.load(f, 'Quartermaster').then((all) => all.length), font);
    expect(faces, font).toBeGreaterThan(0);
  }
  expect(failed).toEqual([]);
  expect(external).toEqual([]);
});
