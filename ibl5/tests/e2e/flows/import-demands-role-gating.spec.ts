import { test, expect } from '../fixtures/auth-regular';

test.skip(
  !process.env.IBL_TEST_USER_REGULAR || !process.env.IBL_TEST_PASS_REGULAR,
  'IBL_TEST_USER_REGULAR / IBL_TEST_PASS_REGULAR not set — regular.json is not freshly authenticated',
);

test.describe('import-demands.php — role gating', () => {
  test('non-admin user gets 403', async ({ page }) => {
    const response = await page.goto('import-demands.php');
    expect(response?.status()).toBe(403);
  });
});
