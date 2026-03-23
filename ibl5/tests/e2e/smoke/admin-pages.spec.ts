import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Admin-only page smoke tests — require roles_mask = 1 (ADMIN) on the test user.

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

    expect(status, 'Test user must have admin privileges — ensure roles_mask=1').not.toBe(403);

    // The page renders an Initialization section on success
    await expect(page.getByText('Initialization')).toBeVisible();
  });

  test('updateAllTheThings pipeline renders summary', async ({ page }) => {
    const response = await page.goto('scripts/updateAllTheThings.php', {
      timeout: 60_000,
    });
    const status = response?.status() ?? 0;

    expect(status, 'Test user must have admin privileges — ensure roles_mask=1').not.toBe(403);

    // Pipeline renders a summary: "X steps completed" or "X succeeded"
    await expect(
      page.getByText(/\d+\s+(steps?\s+completed|succeeded)/i),
    ).toBeVisible({ timeout: 60_000 });
  });

  test('block.php loads for admin', async ({ page }) => {
    const response = await page.goto('block.php');
    const status = response?.status() ?? 0;

    expect(status, 'Test user must have admin privileges — ensure roles_mask=1').not.toBe(403);

    // block.php is the Free Agency admin page — it should render without crashing
    // Content depends on season phase, so just verify no access denial
    expect(status).toBeLessThan(500);
  });

  test('no PHP errors on admin pages', async ({ page }) => {
    for (const url of ADMIN_URLS) {
      const response = await page.goto(url, { timeout: 60_000 });
      const status = response?.status() ?? 0;

      expect(status, 'Test user must have admin privileges — ensure roles_mask=1').not.toBe(403);

      await assertNoPhpErrors(page, `on ${url}`);
    }
  });
});
