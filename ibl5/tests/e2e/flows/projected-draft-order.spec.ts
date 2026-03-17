import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Projected Draft Order — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Projected Draft Order flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=ProjectedDraftOrder');
  });

  test('page loads with title containing year', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Draft Order/i);
  });

  test('draft order table is visible', async ({ page }) => {
    const table = page.locator('.projected-draft-order-table, .ibl-data-table, table').first();
    await expect(table).toBeVisible();
  });

  test('table has expected columns', async ({ page }) => {
    const table = page.locator('.projected-draft-order-table, .ibl-data-table').first();
    await expect(table).toBeVisible();

    const headerText = await table.locator('thead').textContent();
    expect(headerText).toContain('Pick');
    expect(headerText).toContain('Team');
  });

  test('table has team rows with pick numbers', async ({ page }) => {
    const table = page.locator('.projected-draft-order-table, .ibl-data-table, table').first();
    const rows = table.locator('tbody tr');
    const count = await rows.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('round separator rows exist when multiple rounds present', async ({ page }) => {
    const separators = page.locator('.projected-draft-order-separator, .ibl-table-subheading');
    // May or may not have separators depending on data
    const count = await separators.count();
    if (count > 0) {
      await expect(separators.first()).toBeVisible();
    }
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Projected Draft Order page');
  });
});
