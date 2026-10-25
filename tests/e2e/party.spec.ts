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
