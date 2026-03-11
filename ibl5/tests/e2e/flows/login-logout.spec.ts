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

    if (!username || !password) {
      test.skip(true, 'Missing IBL_TEST_USER or IBL_TEST_PASS');
    }

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

    await page.waitForLoadState('domcontentloaded');

    // Should stay on login page or show error
    const body = await page.locator('body').textContent();
    const hasError =
      body?.match(/incorrect|invalid|error|failed/i) ||
      page.url().includes('YourAccount');

    expect(hasError).toBeTruthy();
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

    // Should see login form or redirect to login
    const loginForm = page.locator('#login-username');
    const signInText = page.getByText('Sign In');

    // Either the login form is shown inline or we're redirected to YourAccount
    const hasLoginForm = (await loginForm.count()) > 0;
    const hasSignInText = (await signInText.count()) > 0;
    const isOnLoginPage = page.url().includes('YourAccount');

    expect(hasLoginForm || hasSignInText || isOnLoginPage).toBeTruthy();
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

    if (!username || !password) {
      test.skip(true, 'Missing IBL_TEST_USER or IBL_TEST_PASS');
    }

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
