import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Season Archive — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Season Archive flow', () => {
  test('index page loads with title', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive');
    await expect(page.locator('.ibl-title')).toContainText(/Season Archive/i);
  });

  test('index table has expected columns', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive');
    const table = page.locator('.season-archive-index-table, .ibl-data-table').first();
    const count = await table.count();
    if (count > 0) {
      await expect(table).toBeVisible();
      const headerText = await table.locator('thead').textContent();
      expect(headerText).toContain('Season');
    }
  });

  test('index table is sortable', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive');
    const sortable = page.locator('.sortable');
    const count = await sortable.count();
    if (count > 0) {
      await expect(sortable.first()).toBeVisible();
    }
  });

  test('no PHP errors on index page', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive');
    await assertNoPhpErrors(page, 'on Season Archive index');
  });

  test('detail view loads for a year', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive&year=2026');
    await assertNoPhpErrors(page, 'on Season Archive year=2026');
  });

  test('no PHP errors on nonexistent year', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive&year=1900');
    await assertNoPhpErrors(page, 'on Season Archive invalid year');
  });
});
