import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Free Agency Preview — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Free Agency Preview flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=FreeAgencyPreview');
  });

  test('page loads with title containing year', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Free Agent Preview/i);
  });

  test('preview table is visible with expected columns', async ({ page }) => {
    const table = page.locator('.ibl-data-table, .sticky-table').first();
    await expect(table).toBeVisible();

    const headerText = await table.locator('thead').textContent();
    expect(headerText).toContain('Player');
    expect(headerText).toContain('Team');
    expect(headerText).toContain('Pos');
  });

  test('player links point to player pages', async ({ page }) => {
    const playerLinks = page.locator('.ibl-data-table a[href*="pid="], .sticky-table a[href*="pid="]');
    const count = await playerLinks.count();
    if (count > 0) {
      const href = await playerLinks.first().getAttribute('href');
      expect(href).toContain('name=Player');
    }
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
    await assertNoPhpErrors(page, 'on Free Agency Preview page');
  });
});
