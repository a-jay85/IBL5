import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// YourAccount flow tests — registration, forgot password, activation errors,
// reset password. Login/logout is covered in login-logout.spec.ts.

test.describe('Registration page', () => {
  test('registration form renders with expected fields', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=new_user');

    await expect(page.locator('#register-username')).toBeVisible();
    await expect(page.locator('#register-email')).toBeVisible();
    await expect(page.locator('#register-password')).toBeVisible();
    await expect(page.locator('#register-password2')).toBeVisible();

    const submitBtn = page.locator('button[type="submit"]').filter({
      hasText: /create account/i,
    });
    await expect(submitBtn.first()).toBeVisible();
  });

  test('form has CSRF token', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=new_user');

    const form = page.locator('#site-content form');
    await expect(
      form.locator('input[name="op"][value="finish"]'),
    ).toBeAttached();
    await expect(
      form.locator('input[type="hidden"][name*="csrf"]'),
    ).toBeAttached();
  });

  test('has sign-in link for existing users', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=new_user');

    await expect(
      page.getByRole('link', { name: /already have an account/i }),
    ).toBeVisible();
  });

  test('no PHP errors on registration page', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=new_user');
    await assertNoPhpErrors(page, 'on registration page');
  });
});

test.describe('Registration validation errors', () => {
  test('password mismatch shows error', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=new_user');

    await page.locator('#register-username').fill('e2etest_mismatch');
    await page.locator('#register-email').fill('mismatch@test.example');
    await page.locator('#register-password').fill('pass123');
    await page.locator('#register-password2').fill('pass456');

    const submitBtn = page.locator('button[type="submit"]').filter({
      hasText: /create account/i,
    });

    await Promise.all([
      page.waitForNavigation(),
      submitBtn.click(),
    ]);

    await expect(page.locator('.ibl-alert--error')).toContainText(
      /passwords you entered do not match/i,
    );
  });

  test('short password shows error', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=new_user');

    await page.locator('#register-username').fill('e2etest_short');
    await page.locator('#register-email').fill('short@test.example');
    await page.locator('#register-password').fill('ab');
    await page.locator('#register-password2').fill('ab');

    const submitBtn = page.locator('button[type="submit"]').filter({
      hasText: /create account/i,
    });

    await Promise.all([
      page.waitForNavigation(),
      submitBtn.click(),
    ]);

    await expect(page.locator('.ibl-alert--error')).toContainText(
      /password must be at least/i,
    );
  });

  test('invalid username shows error', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=new_user');

    await page.locator('#register-username').fill('bad user!');
    await page.locator('#register-email').fill('invalid@test.example');
    await page.locator('#register-password').fill('pass12345');
    await page.locator('#register-password2').fill('pass12345');

    const submitBtn = page.locator('button[type="submit"]').filter({
      hasText: /create account/i,
    });

    await Promise.all([
      page.waitForNavigation(),
      submitBtn.click(),
    ]);

    // Global username guard (index.php line 23) fires before form handler,
    // rendering plain text "Illegal username..." via die()
    const body = await page.locator('body').textContent();
    expect(body).toMatch(/illegal username/i);
  });
});

test.describe('Registration: duplicate username', () => {
  test('existing username shows error', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=new_user');

    // Use a username known to exist in the database (test user from .env.test)
    const testUser = process.env.IBL_TEST_USER || 'A-Jay';
    await page.locator('#register-username').fill(testUser);
    await page.locator('#register-email').fill('duplicate@test.example');
    await page.locator('#register-password').fill('testpass12345');
    await page.locator('#register-password2').fill('testpass12345');

    const submitBtn = page.locator('button[type="submit"]').filter({
      hasText: /create account/i,
    });

    await Promise.all([
      page.waitForNavigation(),
      submitBtn.click(),
    ]);

    // Should show error about username already being taken
    const body = await page.locator('body').textContent();
    expect(body).toMatch(/already|taken|exists|in use/i);
  });
});

test.describe('Forgot password page', () => {
  test('forgot password form renders', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=pass_lost');

    await expect(page.locator('#reset-email')).toBeVisible();

    const submitBtn = page.locator('button[type="submit"]').filter({
      hasText: /send reset link/i,
    });
    await expect(submitBtn.first()).toBeVisible();
  });

  test('has sign-in and register links', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=pass_lost');

    await expect(
      page.getByRole('link', { name: /sign in/i }),
    ).toBeVisible();
    await expect(
      page.getByRole('link', { name: /need an account/i }),
    ).toBeVisible();
  });

  test('no PHP errors on forgot password page', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=pass_lost');
    await assertNoPhpErrors(page, 'on forgot password page');
  });
});

test.describe('Forgot password submission', () => {
  test('submitting email shows generic success page', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=pass_lost');

    await page.locator('#reset-email').fill('nonexistent@test.example');

    const submitBtn = page.locator('button[type="submit"]').filter({
      hasText: /send reset link/i,
    });

    await Promise.all([
      page.waitForNavigation(),
      submitBtn.click(),
    ]);

    await expect(page.locator('.auth-status__title')).toContainText(
      /check your email/i,
    );
  });
});

test.describe('Email activation error pages', () => {
  test('missing activation params shows mismatch error', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=confirm_email');

    await expect(page.locator('.auth-status__title')).toContainText(
      /activation error/i,
    );
    const body = await page.locator('body').textContent();
    expect(body).toMatch(/activation code does not match/i);
  });

  test('expired activation link shows expired error', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount&op=activate');

    await expect(page.locator('.auth-status__title')).toContainText(
      /activation error/i,
    );
    const body = await page.locator('body').textContent();
    expect(body).toMatch(/expired or is invalid/i);
  });
});

test.describe('Reset password form', () => {
  test('reset password form renders with token fields', async ({ page }) => {
    await page.goto(
      'modules.php?name=YourAccount&op=reset_password&selector=test_sel&token=test_tok',
    );

    await expect(page.locator('#reset-new-password')).toBeVisible();
    await expect(page.locator('#reset-confirm-password')).toBeVisible();

    const selectorInput = page.locator('input[name="selector"]');
    await expect(selectorInput).toBeAttached();
    await expect(selectorInput).toHaveValue('test_sel');

    const tokenInput = page.locator('input[name="token"]');
    await expect(tokenInput).toBeAttached();
    await expect(tokenInput).toHaveValue('test_tok');
  });

  test('no PHP errors on reset password form', async ({ page }) => {
    await page.goto(
      'modules.php?name=YourAccount&op=reset_password&selector=test_sel&token=test_tok',
    );
    await assertNoPhpErrors(page, 'on reset password form');
  });
});
