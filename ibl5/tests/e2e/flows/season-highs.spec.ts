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
    // CI seed has box scores — grid should render
    const grid = page.locator('.ibl-grid');
    await expect(grid.first()).toBeVisible();

    const tables = page.locator('.stat-table, .ibl-data-table');
    await expect(tables.first()).toBeVisible();
  });

  test('multiple stat category tables visible', async ({ page }) => {
    // CI seed box scores produce points, rebounds, assists categories at minimum
    const tables = page.locator('.stat-table, .ibl-data-table');
    const count = await tables.count();
    expect(count).toBeGreaterThanOrEqual(3);
  });

  test('stat tables have header content', async ({ page }) => {
    const table = page.locator('.stat-table, .ibl-data-table').first();
    await expect(table).toBeVisible();
    const headerText = await table.locator('thead').textContent();
    expect(headerText!.length).toBeGreaterThan(0);
  });

  test('player links navigate to valid player pages', async ({ page }) => {
    const playerLinks = page.locator('.stat-table a[href*="pid="], .ibl-data-table a[href*="pid="]');
    await expect(playerLinks.first()).toBeVisible();

    const href = await playerLinks.first().getAttribute('href');
    expect(href).toContain('name=Player');

    // Navigate to player page and verify
    await page.goto(href!);
    await assertNoPhpErrors(page, 'on player page from Season Highs');
    await expect(page.locator('h2, h3').first()).toBeVisible();
  });

  test('stat values are numeric', async ({ page }) => {
    const valueCells = page.locator('.value-cell');
    const count = await valueCells.count();
    if (count > 0) {
      const text = await valueCells.first().textContent();
      expect(text?.trim()).toMatch(/^\d+(\.\d+)?$/);
    }
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Season Highs page');
  });
});
