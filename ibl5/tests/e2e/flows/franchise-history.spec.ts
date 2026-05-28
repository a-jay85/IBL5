import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Franchise History — public page.
test.use({ storageState: publicStorageState() });

test.describe('Franchise History flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=FranchiseHistory');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Franchise History/i);
  });

  test('has at least 28 team rows', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    expect(await teamRows.count()).toBeGreaterThanOrEqual(28);
  });

  test('team cells link to Team module', async ({ page }) => {
    const firstRowLink = page.locator('tr[data-team-id] a[href*="name=Team"]').first();
    await expect(firstRowLink).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Franchise History page');
  });
});
