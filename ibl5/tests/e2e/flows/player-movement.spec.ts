import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Player Movement — public page.
test.use({ storageState: publicStorageState() });

test.describe('Player Movement flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=PlayerMovement');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Player Movement/i);
  });

  // CI seed guarantees movement: pid=4 played for Metros (tid=1) in 2025,
  // now on Stars (tid=2). Query: ibl_hist.year=2025 AND teamid != plr.tid.

  test('player movement table is visible with expected headers', async ({ page }) => {
    const table = page.locator('.player-movement-table, .ibl-data-table');
    await expect(table.first()).toBeVisible();

    const headerText = await table.first().locator('thead').textContent();
    expect(headerText).toContain('Player');
    expect(headerText).toContain('Old');
    expect(headerText).toContain('New');
  });

  test('player links navigate to player page', async ({ page }) => {
    const playerLinks = page.locator('.player-movement-table a[href*="pid="], .ibl-data-table a[href*="pid="]');
    await expect(playerLinks.first()).toBeVisible();

    const href = await playerLinks.first().getAttribute('href');
    expect(href).toContain('name=Player');

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on player page from Player Movement link');
  });

  test('rows have data-team-ids attribute', async ({ page }) => {
    const rows = page.locator('tr[data-team-ids]');
    await expect(rows.first()).toBeVisible();

    const teamIds = await rows.first().getAttribute('data-team-ids');
    expect(teamIds).toBeTruthy();
  });

  test('table is sortable', async ({ page }) => {
    const sortable = page.locator('.sortable');
    await expect(sortable.first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Player Movement page');
  });
});
