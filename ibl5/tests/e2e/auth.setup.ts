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
  const loginForm = page.locator('form', { has: page.locator('#login-username') });
  await loginForm.locator('#login-username').fill(username);
  await loginForm.locator('#login-password').fill(password);
  await loginForm.locator('button[type="submit"]').click();

  // Wait for login redirect — successful login redirects away from YourAccount.
  // "Logout" is inside a collapsed dropdown so we can't check for it directly.
  await page.waitForURL((url) => !url.href.includes('name=YourAccount'), { timeout: 10_000 });

  await page.context().storageState({ path: authFile });
});
