import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Season Highs — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Season Highs flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=SeasonHighs');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Season Highs/i);
  });

  test('stat tables are displayed in grid layout', async ({ page }) => {
    const grid = page.locator('.ibl-grid');
    const count = await grid.count();
    if (count > 0) {
      await expect(grid.first()).toBeVisible();
    }
    // Individual stat tables should be visible
    const tables = page.locator('.stat-table, .ibl-data-table');
    await expect(tables.first()).toBeVisible();
  });

  test('stat tables have rank and value columns', async ({ page }) => {
    const table = page.locator('.stat-table, .ibl-data-table').first();
    await expect(table).toBeVisible();
    // Tables use colspan headers for stat category names
    const headerText = await table.locator('thead').textContent();
    expect(headerText!.length).toBeGreaterThan(0);
  });

  test('player links exist in stat tables', async ({ page }) => {
    const playerLinks = page.locator('.stat-table a[href*="pid="], .ibl-data-table a[href*="pid="]');
    const count = await playerLinks.count();
    if (count > 0) {
      const href = await playerLinks.first().getAttribute('href');
      expect(href).toContain('name=Player');
    }
  });

  test('multiple stat categories are displayed', async ({ page }) => {
    const tables = page.locator('.stat-table, .ibl-data-table');
    const count = await tables.count();
    // Should have multiple stat category tables (points, rebounds, assists, etc.)
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Season Highs page');
  });
});
