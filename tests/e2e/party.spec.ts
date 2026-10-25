import { test, expect, signInAsDm } from './fixtures';

test('adding a character with a taken username keeps the rest of the form', async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm/party');
  await page.getByLabel('Character name').fill('Ilse Varn');
  await page.getByLabel('Class & level').fill('Wizard 3');
  await page.getByLabel('Carry (lb)').fill('90');
  await page.getByLabel('Player username').fill('bryn');
  await page.getByLabel('Temporary password').fill('secret99');
  await page.getByRole('button', { name: 'Add to party' }).click();

  await expect(page.getByText('The username “bryn” is already taken.')).toBeVisible();
  await expect(page.getByLabel('Character name')).toHaveValue('Ilse Varn');
  await expect(page.getByLabel('Class & level')).toHaveValue('Wizard 3');
  await expect(page.getByLabel('Carry (lb)')).toHaveValue('90');
  await expect(page.getByLabel('Player username')).toHaveValue('');
  await expect(page.getByLabel('Temporary password')).toHaveValue('');

  // The kept values are used up: a fresh visit shows a blank form again.
  await page.goto('/dm/party');
  await expect(page.getByLabel('Character name')).toHaveValue('');
  await expect(page.getByLabel('Carry (lb)')).toHaveValue('150');
});

test('a too-short password keeps the username but never echoes the password', async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm/party');
  await page.getByLabel('Character name').fill('Ilse Varn');
  await page.getByLabel('Player username').fill('ilse');
  // Skip the browser's minlength check so the server's rule is what answers.
  await page.getByLabel('Temporary password').evaluate((el) => el.removeAttribute('minlength'));
  await page.getByLabel('Temporary password').fill('tiny1');
  await page.getByRole('button', { name: 'Add to party' }).click();

  await expect(page.getByText('Player passwords need at least 6 characters.')).toBeVisible();
  await expect(page.getByLabel('Character name')).toHaveValue('Ilse Varn');
  await expect(page.getByLabel('Player username')).toHaveValue('ilse');
  await expect(page.getByLabel('Temporary password')).toHaveValue('');
  expect(await page.content()).not.toContain('tiny1');
});

test('kept input waits for its own page, so another page in between can’t use it up', async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm/party');
  // Post the form without following the redirect, as if the DM looked at another tab before it loaded.
  const csrf = await page.locator('input[name="_csrf"]').first().inputValue();
  const response = await page.request.post('/dm/party', {
    form: { _csrf: csrf, name: 'Ilse Varn', class_label: 'Wizard 3', carry_capacity: '90', username: 'bryn', password: 'secret99' },
    maxRedirects: 0,
  });
  expect(response.status()).toBe(303);

  await page.goto('/dm');
  await page.goto('/dm/party');
  await expect(page.getByLabel('Character name')).toHaveValue('Ilse Varn');
  await expect(page.getByLabel('Carry (lb)')).toHaveValue('90');

  await page.reload();
  await expect(page.getByLabel('Character name')).toHaveValue('');
});

test('a new store without a name keeps the kind of shop and stays on the same store', async ({ page }) => {
  await signInAsDm(page);
  await page.goto('/dm/stores');
  // Open a store other than the first, which is where /dm/stores itself lands.
  const storeUrl = await page.getByRole('navigation', { name: 'Stores' }).getByRole('link').nth(1).getAttribute('href');
  await page.goto(storeUrl!);
  // Whitespace passes the browser's required check but trims to nothing on the server.
  await page.getByLabel('New store name').fill('   ');
  await page.getByLabel('Kind of shop').first().fill('Apothecary');
  await page.getByRole('button', { name: '+ Open a new store' }).click();

  await expect(page.getByText('Give the store a name.')).toBeVisible();
  await expect(page).toHaveURL(storeUrl);
  await expect(page.getByLabel('Kind of shop').first()).toHaveValue('Apothecary');
});
