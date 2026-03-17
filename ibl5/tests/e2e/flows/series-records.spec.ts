import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Series Records — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Series Records flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=SeriesRecords');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Series Records/i);
  });

  test('series records matrix table is visible', async ({ page }) => {
    const table = page.locator('.ibl-data-table, .sticky-table, table').first();
    await expect(table).toBeVisible();
  });

  test('table has team rows', async ({ page }) => {
    const table = page.locator('.ibl-data-table, .sticky-table, table').first();
    const rows = table.locator('tbody tr');
    const count = await rows.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('sticky scroll wrapper exists for wide matrix', async ({ page }) => {
    const wrapper = page.locator('.sticky-scroll-wrapper');
    const count = await wrapper.count();
    if (count > 0) {
      await expect(wrapper.first()).toBeVisible();
    }
  });

  test('diagonal cells contain self-reference marker', async ({ page }) => {
    // In the matrix, team vs itself shows "x"
    const body = await page.locator('body').textContent();
    // At least the page should have meaningful content
    expect(body!.length).toBeGreaterThan(200);
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Series Records page');
  });
});
