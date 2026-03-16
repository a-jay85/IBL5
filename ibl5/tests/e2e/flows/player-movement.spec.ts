import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Player Movement — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Player Movement flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=PlayerMovement');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Player Movement/i);
  });

  test('player movement table is visible when data exists', async ({ page }) => {
    const table = page.locator('.player-movement-table, .ibl-data-table');
    const count = await table.count();
    if (count > 0) {
      await expect(table.first()).toBeVisible();

      const headerText = await table.first().locator('thead').textContent();
      expect(headerText).toContain('Player');
      expect(headerText).toContain('Old');
      expect(headerText).toContain('New');
    }
  });

  test('player links exist when data present', async ({ page }) => {
    const playerLinks = page.locator('.player-movement-table a[href*="pid="], .ibl-data-table a[href*="pid="]');
    const count = await playerLinks.count();
    if (count > 0) {
      const href = await playerLinks.first().getAttribute('href');
      expect(href).toContain('name=Player');
    }
  });

  test('rows have data-team-ids attribute when present', async ({ page }) => {
    const rows = page.locator('tr[data-team-ids]');
    const count = await rows.count();
    if (count > 0) {
      const teamIds = await rows.first().getAttribute('data-team-ids');
      expect(teamIds).toBeTruthy();
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
    await assertNoPhpErrors(page, 'on Player Movement page');
  });
});
