import { test as setup } from '@playwright/test';

// Setup file — runs before any fixture is established, so the direct
// `@playwright/test` import (instead of fixtures/base) is the documented
// exception per eslint.config.js. See auth.setup.ts for the admin equivalent.

const authFile = 'playwright/.auth/regular.json';

setup('authenticate regular (non-admin) user', async ({ page, request }) => {
  const username = process.env.IBL_TEST_USER_REGULAR;
  const password = process.env.IBL_TEST_PASS_REGULAR;

  // Skip (don't fail) when the non-admin credentials are unset so the admin
  // setup project still publishes user.json and the admin suite stays green
  // for local devs who haven't opted into the role-gating tests. Specs that
  // import fixtures/auth-regular will fail loudly when regular.json is absent.
  setup.skip(!username || !password, 'IBL_TEST_USER_REGULAR / IBL_TEST_PASS_REGULAR not set');

  const throttleResp = await request.delete('test-state.php?action=clear-throttle');
  if (!throttleResp.ok()) {
    console.warn(`Failed to clear auth throttle (HTTP ${throttleResp.status()}) — login may fail if throttled`);
  }

  // Auto-login is now opt-in (_auto_login=1 cookie) — the login form renders by
  // default on localhost without any opt-out cookie. Navigate directly.
  await page.goto('modules.php?name=YourAccount');

  const loginForm = page.locator('form', { has: page.locator('#login-username') });
  await loginForm.locator('#login-username').fill(username);
  await loginForm.locator('#login-password').fill(password);

  // Submit and wait for the redirect off YourAccount atomically. Without
  // the Promise.all, fast PHP responses can complete the navigation before
  // the waiter installs and the test then times out on the resolved URL.
  await Promise.all([
    page.waitForURL((url) => !url.href.includes('name=YourAccount'), { timeout: 20_000 }),
    loginForm.locator('button[type="submit"]').click(),
  ]);

  await page.context().storageState({ path: authFile });
});
