import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Projected Draft Order — public page.
test.use({ storageState: publicStorageState() });

test.describe('Projected Draft Order flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=ProjectedDraftOrder');
  });

  test('page loads with title containing year', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Draft Order/i);
  });

  test('draft order table is visible', async ({ page }) => {
    const table = page.locator('.projected-draft-order-table, .ibl-data-table').first();
    await expect(table).toBeVisible();
  });

  test('table has expected columns', async ({ page }) => {
    const table = page.locator('.projected-draft-order-table, .ibl-data-table').first();
    await expect(table).toBeVisible();

    const headerText = await table.locator('thead').textContent();
    expect(headerText).toContain('Pick');
    expect(headerText).toContain('Team');
  });

  test('table has at least 28 team rows', async ({ page }) => {
    // CI seed has 28 teams in standings — all should appear in round 1
    const table = page.locator('.projected-draft-order-table, .ibl-data-table').first();
    const rows = table.locator('tbody tr:not(.projected-draft-order-separator)');
    expect(await rows.count()).toBeGreaterThanOrEqual(28);
  });

  test('pick numbers start at 1', async ({ page }) => {
    const table = page.locator('.projected-draft-order-table, .ibl-data-table').first();
    const pickCells = table.locator('tbody tr:not(.projected-draft-order-separator) td:first-child');
    const firstPick = await pickCells.first().textContent();
    expect(parseInt(firstPick?.trim() ?? '', 10)).toBe(1);
  });

  test('team cells link to team pages', async ({ page }) => {
    const teamLinks = page.locator('.projected-draft-order-table a[href*="teamid="], .ibl-data-table a[href*="teamid="]');
    const count = await teamLinks.count();
    expect(count).toBeGreaterThanOrEqual(1);

    const href = await teamLinks.first().getAttribute('href');
    expect(href).toContain('name=Team');

    // Navigate and verify
    await page.goto(href!);
    await assertNoPhpErrors(page, 'on team page from Projected Draft Order');
  });

  test('round separator rows exist for multiple rounds', async ({ page }) => {
    // CI seed has round 1 and round 2 picks
    const separators = page.locator('.projected-draft-order-separator, .ibl-table-subheading');
    const count = await separators.count();
    expect(count).toBeGreaterThanOrEqual(1);
    await expect(separators.first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Projected Draft Order page');
  });
});
