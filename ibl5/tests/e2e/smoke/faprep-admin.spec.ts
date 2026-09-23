import { test, expect } from '../fixtures/auth';
import { publicStorageState } from '../helpers/public-storage-state';

// faprep.php is admin-gated (is_admin() → 403 for non-admins). The admin
// storage state from fixtures/auth (roles_mask=1) passes the gate.

test('faprep.php renders free-agency prep for admin', async ({ page }) => {
  const response = await page.goto('faprep.php');
  expect(response?.status()).toBe(200);
  // Grounded in ci-seed.sql: ibl_plr pid=1 'Test Player', retired=0 → passes faprep's WHERE p.retired = 0.
  await expect(page.locator('table')).toContainText('Test Player');
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
});

test.describe('faprep.php anonymous', () => {
  test.use({ storageState: publicStorageState() });

  test('returns 403 for an unauthenticated visitor', async ({ page }) => {
    const response = await page.goto('faprep.php');
    expect(response?.status()).toBe(403);
    await expect(page.locator('body')).toContainText('Forbidden');
    await expect(page.locator('table')).toHaveCount(0);
  });
});
