import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Login/Logout flow — uses public (unauthenticated) fixture throughout.
// IMPORTANT: Do NOT use the auth fixture here. The logout test destroys the
// server-side session, which would poison all parallel tests sharing the
// same stored auth state.

test.describe('Login page', () => {
  test('login page renders with form elements', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount');

    await expect(page.locator('#login-username')).toBeVisible();
    await expect(page.locator('#login-password')).toBeVisible();

    const submitBtn = page.locator('button[type="submit"]').filter({
      hasText: /sign in/i,
    });
    await expect(submitBtn.first()).toBeVisible();
  });

  test('login form has remember me checkbox', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount');

    const rememberMe = page.locator('input[name="remember_me"]');
    await expect(rememberMe.first()).toBeAttached();
  });

  test('login page has forgot password and create account links', async ({
    page,
  }) => {
    await page.goto('modules.php?name=YourAccount');

    await expect(
      page.getByRole('link', { name: /forgot password/i }),
    ).toBeVisible();
    await expect(
      page.getByRole('link', { name: /create an account/i }),
    ).toBeVisible();
  });

  test('successful login redirects away from login page', async ({
    page,
  }) => {
    const username = process.env.IBL_TEST_USER;
    const password = process.env.IBL_TEST_PASS;

    expect(username, 'IBL_TEST_USER must be set in .env.test').toBeTruthy();
    expect(password, 'IBL_TEST_PASS must be set in .env.test').toBeTruthy();

    await page.goto('modules.php?name=YourAccount');

    const loginForm = page.locator('form', {
      has: page.locator('#login-username'),
    });
    await loginForm.locator('#login-username').fill(username!);
    await loginForm.locator('#login-password').fill(password!);
    await loginForm.locator('button[type="submit"]').click();

    // Successful login redirects away from YourAccount
    await page.waitForURL(
      (url) => !url.href.includes('name=YourAccount'),
      { timeout: 10000 },
    );

    // Should be on a different page now
    expect(page.url()).not.toContain('name=YourAccount');
  });

  test('invalid credentials show error', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount');

    const loginForm = page.locator('form', {
      has: page.locator('#login-username'),
    });
    await loginForm.locator('#login-username').fill('nonexistent_user_xyz');
    await loginForm.locator('#login-password').fill('wrong_password_123');
    await loginForm.locator('button[type="submit"]').click();

    // Deterministic: the rendered login error must be visible AND we must
    // remain on YourAccount. (No `|| url.includes('YourAccount')` escape — that
    // clause was always true and made the test green regardless of the error.)
    await expect(page.getByText(/login was incorrect/i)).toBeVisible();
    expect(page.url()).toContain('name=YourAccount');
  });

  test('no PHP errors on login page', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount');
    await assertNoPhpErrors(page, 'on login page');
  });
});

test.describe('Login required redirect', () => {
  test('auth-only page redirects to login when unauthenticated', async ({
    page,
  }) => {
    await page.goto('modules.php?name=DepthChartEntry');

    // loginbox() emits a JS redirect to YourAccount for unauthenticated users.
    // Deterministic: wait for that redirect, then confirm the login form
    // rendered. (No three-way OR with an always-true URL clause.)
    await page.waitForURL(/name=YourAccount/, { timeout: 10_000 });
    await expect(page.locator('#login-username')).toBeVisible();
  });
});

// ============================================================
// Remember me — drives the login form with the checkbox on/off and inspects
// the resulting cookies. delight-auth's persistent cookie name is a hash of
// the session name (not the literal `auth_remember`), so we assert on the
// observable discriminator: a long-lived cookie (90-day expiry) appears only
// when remember-me is checked. Public fixture (fresh context per test).
// ============================================================

test.describe('Remember me cookie', () => {
  const username = process.env.IBL_TEST_USER;
  const password = process.env.IBL_TEST_PASS;
  // delight-auth names its persistent cookie `remember_<hash-of-session-name>`,
  // not the literal `auth_remember`. Matching the prefix isolates it from the
  // always-present `lang` / `PHPSESSID` cookies (which are persistent too).
  const REMEMBER_PREFIX = /^remember_/;
  // 30 days: above a session cookie, below the 90-day remember lifetime.
  const PERSISTENT_THRESHOLD_S = 30 * 24 * 60 * 60;

  test.beforeAll(() => {
    expect(username, 'IBL_TEST_USER must be set').toBeTruthy();
    expect(password, 'IBL_TEST_PASS must be set').toBeTruthy();
  });

  async function loginWithRemember(
    page: import('@playwright/test').Page,
    remember: boolean,
  ): Promise<void> {
    await page.goto('modules.php?name=YourAccount');
    const loginForm = page.locator('form', {
      has: page.locator('#login-username'),
    });
    await loginForm.locator('#login-username').fill(username!);
    await loginForm.locator('#login-password').fill(password!);
    if (remember) {
      await loginForm.locator('input[name="remember_me"]').check();
    }
    await loginForm.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.href.includes('name=YourAccount'), {
      timeout: 10_000,
    });
  }

  test('checked remember-me sets a long-lived remember cookie', async ({
    page,
  }) => {
    await loginWithRemember(page, true);

    const cookies = await page.context().cookies();
    const nowS = Date.now() / 1000;
    const remember = cookies.filter((c) => REMEMBER_PREFIX.test(c.name));
    expect(
      remember,
      'a remember_ cookie should be set when remember-me is checked',
    ).toHaveLength(1);
    expect(
      remember[0].expires,
      'the remember cookie should be long-lived, not session-scoped',
    ).toBeGreaterThan(nowS + PERSISTENT_THRESHOLD_S);
  });

  test('unchecked remember-me sets no remember cookie', async ({ page }) => {
    await loginWithRemember(page, false);

    const cookies = await page.context().cookies();
    const remember = cookies.filter((c) => REMEMBER_PREFIX.test(c.name));
    expect(
      remember,
      'no remember_ cookie should be set without remember-me',
    ).toHaveLength(0);
  });
});

// ============================================================
// Logout — uses public fixture with manual login/logout cycle.
// Does NOT use the auth fixture to avoid destroying the shared
// server-side session used by parallel tests.
// ============================================================

test.describe('Logout flow', () => {
  test('logout clears session and shows login option', async ({ page }) => {
    const username = process.env.IBL_TEST_USER;
    const password = process.env.IBL_TEST_PASS;

    expect(username, 'IBL_TEST_USER must be set in .env.test').toBeTruthy();
    expect(password, 'IBL_TEST_PASS must be set in .env.test').toBeTruthy();

    // Log in manually with a fresh session
    await page.goto('modules.php?name=YourAccount');
    const loginForm = page.locator('form', {
      has: page.locator('#login-username'),
    });
    await loginForm.locator('#login-username').fill(username!);
    await loginForm.locator('#login-password').fill(password!);
    await loginForm.locator('button[type="submit"]').click();

    await page.waitForURL(
      (url) => !url.href.includes('name=YourAccount'),
      { timeout: 10000 },
    );

    // Verify we're logged in — can access a protected page
    await page.goto('modules.php?name=DepthChartEntry');
    expect(page.url()).not.toContain('YourAccount');

    // Perform logout
    await page.goto('modules.php?name=YourAccount&op=logout');
    await page.waitForLoadState('domcontentloaded');

    // After logout, try accessing a protected page — should get login prompt
    await page.goto('modules.php?name=DepthChartEntry');

    const body = await page.locator('body').textContent();
    const hasLoginPrompt =
      body?.match(/sign in|log in|login/i) ||
      page.url().includes('YourAccount');

    expect(hasLoginPrompt).toBeTruthy();
  });
});
