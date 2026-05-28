import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { seedResetUser, type SeedResetUser } from '../helpers/test-state';
import { deleteTestUser } from '../helpers/cleanup';

// Password reset flow — uses public (unauthenticated) fixture.
// Full end-to-end reset not covered: intercepting the password-reset email
// is not possible in CI.

test.describe('Password reset flow', () => {
  test('forgot password page renders form and CSRF token', async ({
    page,
  }) => {
    await page.goto('modules.php?name=YourAccount&op=pass_lost');
    await assertNoPhpErrors(page, 'on forgot password page');

    await expect(page.locator('input[name="user_email"]')).toBeAttached();
    await expect(
      page.locator('input[name="op"][value="mailpasswd"]'),
    ).toBeAttached();
    await expect(
      page.locator('form:has(input[name="user_email"]) input[name="_csrf_token"]'),
    ).toBeAttached();
  });

  test('POST mailpasswd with valid email returns generic status page', async ({
    page,
  }) => {
    await page.goto('modules.php?name=YourAccount&op=pass_lost');
    await page.locator('input[name="user_email"]').fill('ci-test@example.com');
    await page
      .locator('form:has(input[name="user_email"]) button[type="submit"]')
      .click();

    await expect(page.locator('body')).toContainText('Check Your Email');
    await assertNoPhpErrors(page, 'after valid email submission');
  });

  test('POST mailpasswd with nonexistent email returns same generic status page', async ({
    page,
  }) => {
    await page.goto('modules.php?name=YourAccount&op=pass_lost');
    await page
      .locator('input[name="user_email"]')
      .fill('no-such-user-99999@example.com');
    await page
      .locator('form:has(input[name="user_email"]) button[type="submit"]')
      .click();

    await expect(page.locator('body')).toContainText('Check Your Email');
    await assertNoPhpErrors(page, 'after nonexistent email submission');
  });

  test('POST mailpasswd with empty email returns validation error', async ({
    page,
  }) => {
    await page.goto('modules.php?name=YourAccount&op=pass_lost');
    await page
      .locator('input[name="user_email"]')
      .evaluate((el) => el.removeAttribute('required'));
    await page
      .locator('form:has(input[name="user_email"]) button[type="submit"]')
      .click();

    await expect(page.locator('body')).toContainText(
      'Please enter your email address.',
    );
    await assertNoPhpErrors(page, 'after empty email submission');
  });

  test('GET reset_password without selector/token redirects to pass_lost', async ({
    page,
  }) => {
    await page.goto('modules.php?name=YourAccount&op=reset_password');
    await expect(page).toHaveURL(/op=pass_lost/);
    await assertNoPhpErrors(page, 'after redirect to pass_lost');
  });

  test('GET reset_password with garbage selector/token renders reset form', async ({
    page,
  }) => {
    await page.goto(
      'modules.php?name=YourAccount&op=reset_password&selector=garbage123&token=invalid456',
    );
    await assertNoPhpErrors(page, 'on reset password form with garbage tokens');

    await expect(page.locator('input[name="new_password"]')).toBeAttached();
    await expect(page.locator('input[name="new_password2"]')).toBeAttached();
    await expect(
      page.locator('input[name="op"][value="do_reset_password"]'),
    ).toBeAttached();
  });
});

// Full do_reset_password round-trip against a real, library-minted reset token.
// A dedicated e2e_reset_* user (seeded VERIFIED so forgotPassword can issue a
// token) keeps the shared IBL_TEST_USER password untouched. Serial: the three
// tests share one seeded account whose password Test 1 changes.
test.describe('Password reset round-trip', () => {
  test.describe.configure({ mode: 'serial' });

  const NEW_PASSWORD = 'ResetNewPass456!';
  let seeded: SeedResetUser;

  test.beforeAll(async ({ request }) => {
    seeded = await seedResetUser(request);
  });

  test.afterAll(async ({ request }) => {
    await deleteTestUser(request, seeded.username);
  });

  test('do_reset_password with valid token sets the new password', async ({
    page,
  }) => {
    await page.goto(
      `modules.php?name=YourAccount&op=reset_password&selector=${seeded.selector}&token=${seeded.token}`,
    );
    // Driving the form submits the reset form's own CSRF token; the layout also
    // renders login forms with their own tokens, so manual extraction would
    // grab the wrong one.
    await page.locator('#reset-new-password').fill(NEW_PASSWORD);
    await page.locator('#reset-confirm-password').fill(NEW_PASSWORD);
    await page
      .locator('form:has(input[name="op"][value="do_reset_password"]) button[type="submit"]')
      .click();

    await expect(page.locator('.auth-status__title')).toContainText(
      /password changed/i,
    );
    await expect(page.locator('body')).toContainText(/password has been reset/i);
    await assertNoPhpErrors(page, 'after do_reset_password');
  });

  test('new password logs in successfully', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount');
    const loginForm = page.locator('form', {
      has: page.locator('#login-username'),
    });
    await loginForm.locator('#login-username').fill(seeded.username);
    await loginForm.locator('#login-password').fill(NEW_PASSWORD);
    await loginForm.locator('button[type="submit"]').click();

    await page.waitForURL((url) => !url.href.includes('name=YourAccount'), {
      timeout: 10_000,
    });
    expect(page.url()).not.toContain('name=YourAccount');
  });

  test('old password no longer logs in', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount');
    const loginForm = page.locator('form', {
      has: page.locator('#login-username'),
    });
    await loginForm.locator('#login-username').fill(seeded.username);
    await loginForm.locator('#login-password').fill(seeded.oldPassword);
    await loginForm.locator('button[type="submit"]').click();

    await expect(page.getByText(/login was incorrect/i)).toBeVisible();
    expect(page.url()).toContain('name=YourAccount');
  });
});
