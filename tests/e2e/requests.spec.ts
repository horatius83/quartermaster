import { test, expect, signIn, signInAsDm, toasts, SECOND_CAMPAIGN } from './fixtures';
import type { Page } from '@playwright/test';

/** As the signed-in player, send the DM a request from the Requests page. */
async function sendRequest(page: Page, text: string): Promise<void> {
  await page.goto('/requests');
  await page.getByLabel('Your request or comment').fill(text);
  await page.getByRole('button', { name: 'Send to the DM' }).click();
  await expect(toasts(page)).toContainText('Sent to your DM.');
}

const requestCard = (page: Page, from = 'Bryn Ashvale') => page.locator('article.request-card', { hasText: from });

test('a player sends a request and reads the DM’s reply', async ({ page }) => {
  await signIn(page, 'bryn');
  await sendRequest(page, 'Could the smith make me a silvered longsword?\nI can pay half up front.');
  await expect(page.locator('#requests .status')).toHaveText('Waiting on DM');
  await expect(page.getByLabel('Your request or comment')).toHaveValue('');

  await signInAsDm(page);
  await expect(page.locator('#request-badge')).toContainText('1');
  await expect(page.locator('#requests-h + a')).toContainText('Could the smith make me a silvered longsword? I can pay half');
  await page.goto('/dm/requests');
  const card = requestCard(page);
  // The line break the player typed is kept.
  await expect(card.locator('.request-body')).toBeVisible();
  expect(await card.locator('.request-body').innerText()).toBe('Could the smith make me a silvered longsword?\nI can pay half up front.');
  await card.getByLabel(/^Reply/).fill('She can have one ready by next session, 110 gp.');
  await card.getByRole('button', { name: 'Send reply' }).click();
  await expect(card.locator('.status')).toHaveText('Answered');
  await expect(card).toContainText('You: She can have one ready by next session, 110 gp.');
  await expect(page.locator('#request-badge')).toHaveText('');
  await expect(toasts(page)).toContainText('Replied to Bryn Ashvale.');

  await signIn(page, 'bryn');
  await expect(page.locator('#reply-badge')).toContainText('1');
  await page.goto('/requests');
  await expect(page.locator('#requests .status')).toHaveText('Answered');
  await expect(page.locator('#requests')).toContainText('DM: She can have one ready by next session, 110 gp.');
  await expect(page.locator('#requests')).toContainText('New');
  // Seeing the reply clears the badge, and it's no longer new next time.
  await expect(page.locator('#reply-badge')).toHaveText('');
  await page.reload();
  await expect(page.locator('#requests')).not.toContainText('New');
});

test('the DM closes a request without a reply', async ({ page }) => {
  await signIn(page, 'bryn');
  await sendRequest(page, 'Thanks for running tonight!');

  await signInAsDm(page);
  await page.goto('/dm/requests');
  const card = requestCard(page);
  // A reply typed but not sent isn't saved by closing.
  await card.getByLabel(/^Reply/).fill('Half-written');
  await card.getByRole('button', { name: 'Close without reply' }).click();
  await expect(card.locator('.status')).toHaveText('Closed');
  await expect(card).not.toContainText('Half-written');
  await expect(page.locator('#request-badge')).toHaveText('');

  await signIn(page, 'bryn');
  await expect(page.locator('#reply-badge')).toHaveText('');
  await page.goto('/requests');
  await expect(page.locator('#requests .status')).toHaveText('Closed');
  await expect(page.locator('#requests')).not.toContainText('DM:');
});

test('a reply has to say something', async ({ page }) => {
  await signIn(page, 'bryn');
  await sendRequest(page, 'Any news from the guild?');

  await signInAsDm(page);
  await page.goto('/dm/requests');
  await requestCard(page).getByRole('button', { name: 'Send reply' }).click();
  await expect(toasts(page)).toContainText('Write a reply first');
  await expect(requestCard(page).locator('.status')).toHaveText('Open');
});

test('a player withdraws a request, and the DM can’t answer it afterwards', async ({ page, openPage }) => {
  await signIn(page, 'bryn');
  await sendRequest(page, 'Never mind this one.');

  const dm = await openPage();
  await signInAsDm(dm);
  await dm.goto('/dm/requests');
  await expect(requestCard(dm)).toContainText('Never mind this one.');

  await page.locator('#requests').getByRole('button', { name: 'Withdraw request' }).click();
  await expect(toasts(page)).toContainText('Request withdrawn.');
  await expect(page.locator('#requests')).toHaveCount(0);

  await requestCard(dm).getByLabel(/^Reply/).fill('Too late?');
  await requestCard(dm).getByRole('button', { name: 'Send reply' }).click();
  await expect(toasts(dm)).toContainText('already been closed or withdrawn');
  await dm.goto('/dm/requests');
  await expect(requestCard(dm)).toHaveCount(0);
  await expect(dm.locator('#request-badge')).toHaveText('');
});

test('each campaign’s DM page shows only its own players’ requests', async ({ page }) => {
  await signIn(page, 'sela');
  await sendRequest(page, 'Is the pass open yet?');
  await signIn(page, 'tamsin');
  await sendRequest(page, 'More arrows, please.');

  await signInAsDm(page);
  await page.goto('/dm/requests');
  await expect(page.locator('article.request-card')).toHaveCount(1);
  await expect(requestCard(page, 'Tamsin')).toContainText('More arrows, please.');
  await expect(page.locator('#request-badge')).toContainText('1');
});

test('a reply sent after switching campaigns in another tab says where the request is', async ({ page }) => {
  await signIn(page, 'bryn');
  await sendRequest(page, 'Can I keep the troll’s club?');

  await signInAsDm(page);
  await page.goto('/dm/requests');
  const other = await page.context().newPage();
  await other.goto('/dm');
  await other.locator('#campaign-switch').selectOption({ label: SECOND_CAMPAIGN });
  await expect(other.locator('.campaign-switch option:checked')).toHaveText(SECOND_CAMPAIGN);

  await requestCard(page).getByLabel(/^Reply/).fill('Only if you can lift it.');
  await requestCard(page).getByRole('button', { name: 'Send reply' }).click();
  await expect(toasts(page)).toContainText(`you’ve since switched to ${SECOND_CAMPAIGN}. Switch back to answer it.`);
  await expect(requestCard(page).locator('.status')).toHaveText('Open');
});

test('the dashboard lists the first few open requests and links to the rest', async ({ page }) => {
  await signIn(page, 'bryn');
  for (let i = 1; i <= 7; i++) {
    await sendRequest(page, `Dashboard request ${i}`);
  }
  await signInAsDm(page);
  await expect(page.locator('#requests-h ~ a.card')).toHaveCount(5);
  await page.getByRole('link', { name: 'and 2 more →' }).click();
  await expect(page.locator('article.request-card')).toHaveCount(7);
});

test('a player can have only so many requests waiting', async ({ page }) => {
  await signIn(page, 'bryn');
  for (let i = 1; i <= 20; i++) {
    await sendRequest(page, `Request ${i}`);
  }
  await expect(page.getByLabel('Your request or comment')).toHaveCount(0);
  await expect(page.locator('main')).toContainText('You have 20 requests waiting on your DM.');

  await page.locator('#requests li', { hasText: 'Request 1' }).first().getByRole('button', { name: 'Withdraw request' }).click();
  await expect(page.getByLabel('Your request or comment')).toBeVisible();
});
