import { test, expect } from '../fixtures/auth-regular';
import { assertNoPhpErrors } from '../helpers/php-errors';

/**
 * The nav notification bell is gated to logged-in team owners. A logged-in user
 * with no franchise (the CI `ci-e2e-regular` user, provisioned with no
 * gm_username / team) must see no bell and no badge.
 *
 * Read-only: this spec never mutates gm_notifications, so it does not race the
 * serial notifications.spec.ts inbox tests.
 *
 * Verification matrix: #21.
 */
test.describe('notification bell visibility (non-owner)', () => {
  test('logged-in user with no team sees no notification bell', async ({ page }) => {
    test.skip(!process.env.IBL_TEST_USER_REGULAR, 'IBL_TEST_USER_REGULAR not configured');

    await page.goto('index.php');
    await assertNoPhpErrors(page, 'on index.php');

    await expect(page.locator('.notification-bell')).toHaveCount(0);
    await expect(page.locator('.notification-bell__badge')).toHaveCount(0);
  });
});
