import type { Page } from '@playwright/test';
import { test, expect } from '../fixtures/base';

/**
 * Verifies the CI-provisioned non-admin user actually exists in auth_users
 * with non-admin privileges. Independent of auth-regular.setup.ts so a
 * setup-file regression doesn't mask a provisioning regression.
 *
 * Test classification: API-test (Tier 2 plan, verification row 3).
 * Uses the base fixture (no storageState) and manually sets `_no_auto_login`
 * to opt out of DevAutoLogin so the login form renders.
 */

test.use({ storageState: { cookies: [], origins: [] } });

async function loginViaForm(page: Page, username: string, password: string): Promise<void> {
  await page.goto('modules.php?name=YourAccount');
  const loginForm = page.locator('form', { has: page.locator('#login-username') });
  await loginForm.locator('#login-username').fill(username);
  await loginForm.locator('#login-password').fill(password);
  await Promise.all([
    page.waitForURL((url) => !url.href.includes('name=YourAccount'), { timeout: 20_000 }),
    loginForm.locator('button[type="submit"]').click(),
  ]);
}

test.describe('Regular (non-admin) test user provisioning', () => {
  test.beforeEach(async ({ page, request }) => {
    const baseUrl = process.env.BASE_URL ?? 'http://main.localhost/ibl5/';
    await page.context().addCookies([
      { name: '_no_auto_login', value: '1', domain: new URL(baseUrl).hostname, path: '/' },
    ]);
    await request.delete('test-state.php?action=clear-throttle');
  });

  test('regular user can authenticate via login form POST', async ({ page }) => {
    const username = process.env.IBL_TEST_USER_REGULAR;
    const password = process.env.IBL_TEST_PASS_REGULAR;
    test.skip(!username || !password, 'IBL_TEST_USER_REGULAR/PASS not configured');
    await loginViaForm(page, username!, password!);
    expect(page.url()).not.toContain('name=YourAccount');
  });

  test('regular user is denied admin pages (roles_mask=0)', async ({ page }) => {
    const username = process.env.IBL_TEST_USER_REGULAR;
    const password = process.env.IBL_TEST_PASS_REGULAR;
    test.skip(!username || !password, 'IBL_TEST_USER_REGULAR/PASS not configured');
    await loginViaForm(page, username!, password!);
    const response = await page.goto('scripts/updateAllTheThings.php');
    expect(response?.status(), 'regular user must NOT have admin privileges').toBe(403);
  });
});
