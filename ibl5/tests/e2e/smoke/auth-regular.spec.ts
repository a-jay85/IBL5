import { test, expect } from '../fixtures/auth-regular';

/**
 * Verifies playwright/.auth/regular.json was produced by auth-regular.setup.ts
 * and that the chromium project picks it up when the auth-regular fixture is
 * imported. Together with the existing admin smoke tests, this confirms both
 * storage states coexist (verification row 4 of the Tier 2 plan).
 */

test.describe('Regular user smoke', () => {
  test('non-admin storage state authenticates a normal page', async ({ page }) => {
    await page.goto('index.php');
    // Authenticated users never see the sign-in CTA in the navbar.
    await expect(page.getByText('Sign In')).toBeHidden();
  });

  test('non-admin user gets 403 on admin entry point', async ({ page }) => {
    const response = await page.goto('scripts/updateAllTheThings.php');
    expect(response?.status()).toBe(403);
  });
});
