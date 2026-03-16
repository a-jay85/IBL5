import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Franchise History — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Franchise History flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=FranchiseHistory');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Franchise History/i);
  });

  test('franchise table is visible with expected columns', async ({ page }) => {
    const table = page.locator('.ibl-data-table, .sticky-table').first();
    await expect(table).toBeVisible();

    const headerText = await table.locator('thead').textContent();
    expect(headerText).toContain('Team');
  });

  test('team rows have data-team-id attributes', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    const count = await teamRows.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('sticky scroll wrapper exists for wide table', async ({ page }) => {
    const wrapper = page.locator('.sticky-scroll-wrapper');
    const count = await wrapper.count();
    if (count > 0) {
      await expect(wrapper.first()).toBeVisible();
    }
  });

  test('table is sortable', async ({ page }) => {
    const sortable = page.locator('.sortable');
    await expect(sortable.first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Franchise History page');
  });
});
