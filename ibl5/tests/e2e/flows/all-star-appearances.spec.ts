import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// All-Star Appearances — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('All-Star Appearances flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=AllStarAppearances');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/All-Star Appearances/i);
  });

  test('appearances table is visible when data exists', async ({ page }) => {
    const table = page.locator('.ibl-data-table');
    const count = await table.count();
    if (count > 0) {
      await expect(table.first()).toBeVisible();

      const headerText = await table.first().locator('thead').textContent();
      expect(headerText).toContain('Player');
      expect(headerText).toContain('Appearances');
    }
  });

  test('player links exist when data present', async ({ page }) => {
    const playerLinks = page.locator('.ibl-data-table a[href*="pid="]');
    const count = await playerLinks.count();
    if (count > 0) {
      const href = await playerLinks.first().getAttribute('href');
      expect(href).toContain('name=Player');
    }
  });

  test('appearances count cells have highlight styling', async ({ page }) => {
    const highlights = page.locator('.ibl-stat-highlight');
    const count = await highlights.count();
    if (count > 0) {
      await expect(highlights.first()).toBeVisible();
    }
  });

  test('table is sortable', async ({ page }) => {
    const sortable = page.locator('.sortable');
    const count = await sortable.count();
    if (count > 0) {
      await expect(sortable.first()).toBeVisible();
    }
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on All-Star Appearances page');
  });
});
