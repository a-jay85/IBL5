import { test as setup, expect } from '@playwright/test';

const authFile = 'playwright/.auth/user.json';

setup('authenticate', async ({ page }) => {
  const username = process.env.IBL_TEST_USER;
  const password = process.env.IBL_TEST_PASS;

  if (!username || !password) {
    throw new Error(
      'Missing IBL_TEST_USER or IBL_TEST_PASS. ' +
      'Copy .env.test.example to .env.test and fill in your credentials.'
    );
  }

  await page.goto('modules.php?name=YourAccount');
  await page.locator('#login-username').fill(username);
  await page.locator('#login-password').fill(password);
  await page.locator('button[type="submit"]').click();

  // Wait for login to complete — nav should show "Logout"
  await expect(page.getByText('Logout')).toBeVisible({ timeout: 10_000 });

  await page.context().storageState({ path: authFile });
});
