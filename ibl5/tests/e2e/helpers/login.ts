import type { Page } from '@playwright/test';

/**
 * Navigate to YourAccount, fill the login form, submit, and await the
 * post-login redirect. Works for any credentials that produce a redirect
 * (i.e., valid credentials). Do NOT call this for invalid-credential tests
 * that expect to remain on YourAccount — the waitForURL will timeout.
 */
export async function loginViaForm(
  page: Page,
  username: string,
  password: string,
): Promise<void> {
  await page.goto('modules.php?name=YourAccount');
  const loginForm = page.locator('form', { has: page.locator('#login-username') });
  await loginForm.locator('#login-username').fill(username);
  await loginForm.locator('#login-password').fill(password);
  await Promise.all([
    page.waitForURL((url) => !url.href.includes('name=YourAccount'), {
      timeout: 20_000,
    }),
    loginForm.locator('button[type="submit"]').click(),
  ]);
}
