import { test as setup } from '@playwright/test';

const authFile = 'playwright/.auth/user.json';

setup('authenticate', async ({ page, request }) => {
  const username = process.env.IBL_TEST_USER;
  const password = process.env.IBL_TEST_PASS;

  if (!username || !password) {
    throw new Error(
      'Missing IBL_TEST_USER or IBL_TEST_PASS. ' +
      'Copy .env.test.example to .env.test and fill in your credentials.'
    );
  }

  // Clear auth throttling before login — repeated test runs accumulate
  // failed attempts that trigger "Too many login attempts" and block auth.
  const throttleResp = await request.delete('test-state.php?action=clear-throttle');
  if (!throttleResp.ok()) {
    console.warn(`Failed to clear auth throttle (HTTP ${throttleResp.status()}) — login may fail if throttled`);
  }

  await page.goto('modules.php?name=YourAccount');

  // Check if already authenticated (e.g., DEV_AUTO_LOGIN redirected away from YourAccount)
  if (!page.url().includes('name=YourAccount')) {
    // Already logged in via dev auto-login or previous redirect
    await page.context().storageState({ path: authFile });
    return;
  }

  const loginForm = page.locator('form', { has: page.locator('#login-username') });
  await loginForm.locator('#login-username').fill(username);
  await loginForm.locator('#login-password').fill(password);
  await loginForm.locator('button[type="submit"]').click();

  // Wait for login redirect — successful login redirects away from YourAccount.
  // "Logout" is inside a collapsed dropdown so we can't check for it directly.
  await page.waitForURL((url) => !url.href.includes('name=YourAccount'));

  await page.context().storageState({ path: authFile });
});
