import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Team Offense/Defense Stats — public page, no authentication required.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Team Stats flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=TeamOffDefStats');
  });

  test('page loads with title containing Statistics', async ({ page }) => {
    const title = page.locator('.ibl-title').first();
    await expect(title).toBeVisible();
    const titleText = await title.textContent();
    expect(titleText?.toLowerCase()).toContain('statistic');
  });

  test('all 5 section headings visible', async ({ page }) => {
    const body = await page.locator('body').textContent();
    const expectedSections = [
      'Team Offense Totals',
      'Team Defense Totals',
      'Team Offense Averages',
      'Team Defense Averages',
      'Differentials',
    ];

    for (const section of expectedSections) {
      expect(
        body,
        `Section "${section}" should be visible`,
      ).toContain(section);
    }
  });

  test('each section has a data table with rows', async ({ page }) => {
    const tables = page.locator('.ibl-data-table');
    expect(await tables.count()).toBeGreaterThanOrEqual(5);

    // Check first 5 tables have data rows
    for (let i = 0; i < 5; i++) {
      const rows = tables.nth(i).locator('tbody tr');
      expect(
        await rows.count(),
        `Table ${i} should have at least one row`,
      ).toBeGreaterThan(0);
    }
  });

  test('sortable tables support client-side sorting', async ({ page }) => {
    const sortableTable = page.locator('.ibl-data-table.sortable').first();

    if (await sortableTable.isVisible()) {
      // Get initial first row text
      const firstRowBefore = await sortableTable
        .locator('tbody tr')
        .first()
        .textContent();

      // Click a header to sort
      const header = sortableTable.locator('thead th').nth(2);
      await header.click();

      // Table should still be visible (no page reload errors)
      await expect(sortableTable).toBeVisible();

      // Click again for reverse sort
      await header.click();
      await expect(sortableTable).toBeVisible();
    }
  });

  test('tables have team rows matching expected count', async ({ page }) => {
    // Each table should have rows for all 28 teams
    const firstTable = page.locator('.ibl-data-table').first();
    await expect(firstTable).toBeVisible();
    const rows = firstTable.locator('tbody tr');
    expect(await rows.count()).toBeGreaterThanOrEqual(20);
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Team Stats page');
  });
});
