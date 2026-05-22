import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

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
