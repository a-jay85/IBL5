import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Series Records — public page.
test.use({ storageState: publicStorageState() });

test.describe('Series Records flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=SeriesRecords');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Series Records/i);
  });

  test('series records matrix table is visible', async ({ page }) => {
    await expect(page.locator('.sticky-table').first()).toBeVisible();
  });

  test('grid has header cells matching team count plus corner', async ({ page }) => {
    const headerCells = page.locator('.sticky-table thead tr th');
    // CI seed has ~3 teams with series data; production has 28+
    // At minimum: corner cell + at least 2 team columns
    expect(await headerCells.count()).toBeGreaterThanOrEqual(3);
  });

  test('header row contains team logo images', async ({ page }) => {
    const logos = page.locator('thead th img[src*="logo/new"]');
    // CI seed has ~3 teams; production has 28
    expect(await logos.count()).toBeGreaterThanOrEqual(2);
  });

  test('diagonal cells contain x', async ({ page }) => {
    // First team row — self-vs-self cell should contain "x"
    const firstRow = page.locator('.sticky-table tbody tr').first();
    // Cell at index 1 is the self-vs-self diagonal (index 0 is the team name)
    const diagonalCell = firstRow.locator('td').nth(1);
    await expect(diagonalCell).toContainText('x');
  });

  test('record cells have background-color styles', async ({ page }) => {
    const firstRow = page.locator('.sticky-table tbody tr').first();
    // Non-diagonal cell (index 2) should have --series-cell-bg custom property
    const recordCell = firstRow.locator('td').nth(2);
    const style = await recordCell.getAttribute('style');
    expect(style).toBeTruthy();
    expect(style!).toContain('--series-cell-bg');
  });

  test('table uses page-sticky wrapper', async ({ page }) => {
    await expect(page.locator('.sticky-scroll-wrapper.page-sticky')).toBeVisible();
  });

  test('table has team rows', async ({ page }) => {
    const rows = page.locator('.sticky-table tbody tr');
    // CI seed has ~3 teams; production has 28
    expect(await rows.count()).toBeGreaterThanOrEqual(2);
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Series Records page');
  });
});
