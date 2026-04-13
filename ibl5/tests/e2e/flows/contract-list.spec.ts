import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Contract List — public page.
test.use({ storageState: publicStorageState() });

test.describe('Contract List flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=ContractList');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Contract List/i);
  });

  test('contract table is visible with expected columns', async ({ page }) => {
    const table = page.locator('.ibl-data-table').first();
    await expect(table).toBeVisible();

    const headerText = await table.locator('thead').textContent();
    expect(headerText).toContain('Player');
    expect(headerText).toContain('Pos');
    expect(headerText).toContain('Team');
  });

  test('player links point to player pages', async ({ page }) => {
    const playerLinks = page.locator('.ibl-data-table a[href*="pid="]');
    await expect(playerLinks.first()).toBeVisible();
    const href = await playerLinks.first().getAttribute('href');
    expect(href).toContain('name=Player');
  });

  test('table has responsive scroll wrapper', async ({ page }) => {
    // Contract list uses responsive-table pattern
    const table = page.locator('.responsive-table, .ibl-data-table').first();
    await expect(table).toBeVisible();
  });

  test('table has sticky column for player names', async ({ page }) => {
    // responsive-table should have sticky columns
    const stickyCols = page.locator('.sticky-col');
    await expect(stickyCols.first()).toBeVisible();
  });

  test('totals row exists', async ({ page }) => {
    const totalsRow = page.locator('.totals-row, tr.totals-row');
    await expect(totalsRow.first()).toBeVisible();
  });

  test('table is sortable', async ({ page }) => {
    const sortable = page.locator('.sortable');
    await expect(sortable.first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Contract List page');
  });
});
