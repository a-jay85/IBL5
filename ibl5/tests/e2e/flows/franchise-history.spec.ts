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
    const table = page.locator('.sticky-table').first();
    await expect(table).toBeVisible();

    const headerText = await table.locator('thead').textContent();
    expect(headerText).toContain('Team');
  });

  test('all 10+ column headers present with key headers', async ({ page }) => {
    const headers = page.locator('.sticky-table thead th');
    expect(await headers.count()).toBeGreaterThanOrEqual(10);

    const headerTexts = await headers.allTextContents();
    const joined = headerTexts.join(' ');
    expect(joined).toContain('Team');
    // textContent() strips internal whitespace, so match without spaces
    expect(joined).toMatch(/All-Time\s*Record/);
    expect(joined).toMatch(/IBL\s*Titles/);
  });

  test('sticky corner cell has correct classes', async ({ page }) => {
    await expect(page.locator('thead th.sticky-col.sticky-corner')).toBeVisible();
  });

  test('has at least 28 team rows', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    expect(await teamRows.count()).toBeGreaterThanOrEqual(28);
  });

  test('record cells match wins-losses format', async ({ page }) => {
    const firstRow = page.locator('tr[data-team-id]').first();
    // All-Time Record is the 2nd column (index 1)
    const recordCell = firstRow.locator('td').nth(1);
    const text = (await recordCell.textContent())!.trim();
    expect(text).toMatch(/\d+-\d+/);
  });

  test('championship columns have numeric values', async ({ page }) => {
    const firstRow = page.locator('tr[data-team-id]').first();
    // Last 4 columns are championship-related counts
    const cells = firstRow.locator('td');
    const count = await cells.count();
    for (let i = count - 4; i < count; i++) {
      const text = (await cells.nth(i).textContent())!.trim();
      expect(text).toMatch(/^\d+$/);
    }
  });

  test('team cells link to Team module', async ({ page }) => {
    const firstRowLink = page.locator('tr[data-team-id] a[href*="name=Team"]').first();
    await expect(firstRowLink).toBeVisible();
  });

  test('sticky scroll wrapper is visible', async ({ page }) => {
    await expect(page.locator('.sticky-scroll-wrapper').first()).toBeVisible();
  });

  test('table is sortable', async ({ page }) => {
    const sortable = page.locator('.sortable');
    await expect(sortable.first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Franchise History page');
  });
});
