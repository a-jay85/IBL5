import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Draft History — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Draft History flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory');
  });

  test('page loads with title containing Draft', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Draft/i);
  });

  test('year selector dropdown is present', async ({ page }) => {
    await expect(page.locator('#draft-year-select')).toBeVisible();
  });

  test('year dropdown has multiple options', async ({ page }) => {
    const options = page.locator('#draft-year-select option');
    expect(await options.count()).toBeGreaterThanOrEqual(2);
  });

  test('default page shows no-data message or draft table', async ({ page }) => {
    // Default DraftHistory may show no-data or auto-load the latest year
    const noData = page.locator('.draft-no-data');
    const table = page.locator('.draft-history-table');
    const noDataCount = await noData.count();
    const tableCount = await table.count();
    expect(noDataCount + tableCount).toBeGreaterThan(0);
  });

  test('selecting a year shows draft picks table', async ({ page }) => {
    // Pick the first non-default option from the dropdown
    const select = page.locator('#draft-year-select');
    const options = select.locator('option');
    const optionCount = await options.count();
    // Find first option with a numeric year value
    let yearValue = '';
    for (let i = 0; i < optionCount; i++) {
      const val = await options.nth(i).getAttribute('value');
      if (val && /^\d{4}$/.test(val)) {
        yearValue = val;
        break;
      }
    }
    if (yearValue) {
      await page.goto(`modules.php?name=DraftHistory&year=${yearValue}`);
      await assertNoPhpErrors(page, `on DraftHistory year=${yearValue}`);
      const table = page.locator('.draft-history-table');
      const noData = page.locator('.draft-no-data');
      // Either data or no-data message should show
      const tableCount = await table.count();
      const noDataCount = await noData.count();
      expect(tableCount + noDataCount).toBeGreaterThan(0);
    }
  });

  test('table has responsive-table class when data exists', async ({ page }) => {
    // Navigate to a year that likely has data — use the dropdown's first year
    const options = page.locator('#draft-year-select option');
    const optionCount = await options.count();
    let yearValue = '';
    for (let i = 0; i < optionCount; i++) {
      const val = await options.nth(i).getAttribute('value');
      if (val && /^\d{4}$/.test(val)) {
        yearValue = val;
        break;
      }
    }
    if (yearValue) {
      await page.goto(`modules.php?name=DraftHistory&year=${yearValue}`);
      const table = page.locator('.draft-history-table.responsive-table');
      const tableCount = await table.count();
      if (tableCount > 0) {
        await expect(table.first()).toBeVisible();
      }
    }
  });

  test('draft picks table has expected column headers when data exists', async ({ page }) => {
    const options = page.locator('#draft-year-select option');
    const optionCount = await options.count();
    let yearValue = '';
    for (let i = 0; i < optionCount; i++) {
      const val = await options.nth(i).getAttribute('value');
      if (val && /^\d{4}$/.test(val)) {
        yearValue = val;
        break;
      }
    }
    if (yearValue) {
      await page.goto(`modules.php?name=DraftHistory&year=${yearValue}`);
      const table = page.locator('.draft-history-table').first();
      const tableCount = await table.count();
      if (tableCount > 0) {
        const headerText = await table.locator('thead').textContent();
        expect(headerText).toContain('Rd');
        expect(headerText).toContain('Pick');
        expect(headerText).toContain('Player');
        expect(headerText).toContain('Team');
      }
    }
  });

  test('team history page loads without errors', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory&op=team&teamID=1');
    await assertNoPhpErrors(page, 'on DraftHistory team view');
    await expect(page.locator('.team-logo-banner').first()).toBeVisible();
  });

  test('player links exist in draft table when data present', async ({ page }) => {
    const options = page.locator('#draft-year-select option');
    const optionCount = await options.count();
    let yearValue = '';
    for (let i = 0; i < optionCount; i++) {
      const val = await options.nth(i).getAttribute('value');
      if (val && /^\d{4}$/.test(val)) {
        yearValue = val;
        break;
      }
    }
    if (yearValue) {
      await page.goto(`modules.php?name=DraftHistory&year=${yearValue}`);
      const playerLinks = page.locator('.draft-history-table a[href*="pid="]');
      const count = await playerLinks.count();
      if (count > 0) {
        const href = await playerLinks.first().getAttribute('href');
        expect(href).toContain('name=Player');
      }
    }
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Draft History page');
  });
});
