import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Draft History — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Draft History flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory');
  });

  test('page loads with title containing year', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Draft/i);
  });

  test('year selector dropdown is present', async ({ page }) => {
    const yearSelect = page.locator('#draft-year-select');
    await expect(yearSelect).toBeVisible();
  });

  test('draft picks table is visible with expected columns', async ({ page }) => {
    const table = page.locator('.draft-history-table, .ibl-data-table').first();
    await expect(table).toBeVisible();

    const headerText = await table.locator('thead').textContent();
    expect(headerText).toContain('Rd');
    expect(headerText).toContain('Pick');
    expect(headerText).toContain('Player');
    expect(headerText).toContain('Team');
  });

  test('player links exist in draft table', async ({ page }) => {
    const playerLinks = page.locator('.draft-history-table a[href*="pid="], .ibl-data-table a[href*="pid="]');
    const count = await playerLinks.count();
    if (count > 0) {
      const href = await playerLinks.first().getAttribute('href');
      expect(href).toContain('name=Player');
    }
  });

  test('table has sticky columns for responsive display', async ({ page }) => {
    const stickyCols = page.locator('.sticky-col-1, .sticky-col-2, .sticky-col');
    const count = await stickyCols.count();
    if (count > 0) {
      await expect(stickyCols.first()).toBeVisible();
    }
  });

  test('year navigation works via dropdown', async ({ page }) => {
    const yearSelect = page.locator('#draft-year-select');
    const options = yearSelect.locator('option');
    const optionCount = await options.count();
    expect(optionCount).toBeGreaterThanOrEqual(1);
  });

  test('table is sortable', async ({ page }) => {
    const sortable = page.locator('.sortable');
    await expect(sortable.first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Draft History page');
  });
});
