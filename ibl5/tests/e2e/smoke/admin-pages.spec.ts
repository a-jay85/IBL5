import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Admin-only page smoke tests — require roles_mask = 1 (ADMIN) on the test user.
// If the authenticated user is not an admin, tests skip gracefully.

const ADMIN_URLS = [
  'scripts/updateAllTheThings.php',
  'block.php',
];

test.describe('Admin page smoke tests', () => {
  test('updateAllTheThings page loads for admin', async ({ page }) => {
    const response = await page.goto('scripts/updateAllTheThings.php', {
      timeout: 60_000,
    });
    const status = response?.status() ?? 0;
    const body = await page.locator('body').textContent();

    if (status === 403 || body?.includes('Access denied')) {
      test.skip(true, 'Test user does not have admin privileges');
    }

    // The page renders an Initialization section on success
    await expect(page.getByText('Initialization')).toBeVisible();
  });

  test('updateAllTheThings pipeline renders summary', async ({ page }) => {
    const response = await page.goto('scripts/updateAllTheThings.php', {
      timeout: 60_000,
    });
    const status = response?.status() ?? 0;
    const body = await page.locator('body').textContent();

    if (status === 403 || body?.includes('Access denied')) {
      test.skip(true, 'Test user does not have admin privileges');
    }

    // Pipeline renders a summary: "X steps completed" or "X succeeded"
    await expect(
      page.getByText(/\d+\s+(steps?\s+completed|succeeded)/i),
    ).toBeVisible({ timeout: 60_000 });
  });

  test('block.php loads for admin', async ({ page }) => {
    const response = await page.goto('block.php');
    const status = response?.status() ?? 0;
    const body = await page.locator('body').textContent();

    if (status === 403 || body?.includes('Access denied')) {
      test.skip(true, 'Test user does not have admin privileges');
    }

    // block.php is the Free Agency admin page — it should render without crashing
    // Content depends on season phase, so just verify no access denial
    expect(status).toBeLessThan(500);
  });

  test('no PHP errors on admin pages', async ({ page }) => {
    for (const url of ADMIN_URLS) {
      const response = await page.goto(url, { timeout: 60_000 });
      const status = response?.status() ?? 0;
      const body = await page.locator('body').textContent();

      if (status === 403 || body?.includes('Access denied')) {
        // Non-admin user — skip the whole check
        test.skip(true, 'Test user does not have admin privileges');
      }

      await assertNoPhpErrors(page, `on ${url}`);
    }
  });
});
