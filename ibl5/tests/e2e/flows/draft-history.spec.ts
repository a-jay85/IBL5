import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Draft History — public page.
test.use({ storageState: { cookies: [], origins: [] } });

/** Extract the first numeric year value from the draft year dropdown. */
async function getFirstDraftYear(page: Page): Promise<string> {
  const options = page.locator('#draft-year-select option');
  const optionCount = await options.count();
  for (let i = 0; i < optionCount; i++) {
    const val = await options.nth(i).getAttribute('value');
    if (val && /^\d{4}$/.test(val)) {
      return val;
    }
  }
  return '';
}

/**
 * Navigate to a draft year that has data (player picks).
 * Tries each year from the dropdown until a draft table appears.
 */
async function navigateToDraftYearWithData(page: Page): Promise<boolean> {
  const options = page.locator('#draft-year-select option');
  const optionCount = await options.count();
  for (let i = 0; i < optionCount; i++) {
    const val = await options.nth(i).getAttribute('value');
    if (val && /^\d{4}$/.test(val)) {
      await page.goto(`modules.php?name=DraftHistory&year=${val}`);
      const table = page.locator('.draft-history-table');
      if ((await table.count()) > 0) {
        return true;
      }
    }
  }
  return false;
}

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
    const noData = page.locator('.draft-no-data');
    const table = page.locator('.draft-history-table');
    const noDataCount = await noData.count();
    const tableCount = await table.count();
    expect(noDataCount + tableCount).toBeGreaterThan(0);
  });

  test('selecting a year with data shows draft picks table', async ({ page }) => {
    const found = await navigateToDraftYearWithData(page);
    expect(found).toBe(true);
    await assertNoPhpErrors(page, 'on DraftHistory with data');

    const table = page.locator('.draft-history-table');
    await expect(table.first()).toBeVisible();
  });

  test('draft table has responsive-table class', async ({ page }) => {
    const found = await navigateToDraftYearWithData(page);
    expect(found).toBe(true);

    const table = page.locator('.draft-history-table.responsive-table');
    await expect(table.first()).toBeVisible();
  });

  test('draft picks table has expected column headers', async ({ page }) => {
    const found = await navigateToDraftYearWithData(page);
    expect(found).toBe(true);

    const table = page.locator('.draft-history-table').first();
    const headerText = await table.locator('thead').textContent();
    expect(headerText).toContain('Rd');
    expect(headerText).toContain('Pick');
    expect(headerText).toContain('Player');
    expect(headerText).toContain('Team');
  });

  test('team history page loads without errors', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory&op=team&teamID=1');
    await assertNoPhpErrors(page, 'on DraftHistory team view');
    await expect(page.locator('.team-logo-banner').first()).toBeVisible();
  });

  test('player links exist in draft table', async ({ page }) => {
    const found = await navigateToDraftYearWithData(page);
    expect(found).toBe(true);

    const playerLinks = page.locator('.draft-history-table a[href*="pid="]');
    await expect(playerLinks.first()).toBeVisible();

    const href = await playerLinks.first().getAttribute('href');
    expect(href).toContain('name=Player');
  });

  test('player link navigates to player page', async ({ page }) => {
    const found = await navigateToDraftYearWithData(page);
    expect(found).toBe(true);

    const playerLink = page.locator('.draft-history-table a[href*="pid="]').first();
    const href = await playerLink.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on player page from Draft History link');
  });

  test('year dropdown navigates correctly', async ({ page }) => {
    const yearValue = await getFirstDraftYear(page);
    expect(yearValue).toBeTruthy();

    await page.goto(`modules.php?name=DraftHistory&year=${yearValue}`);
    await assertNoPhpErrors(page, `on DraftHistory year=${yearValue}`);
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Draft History page');
  });
});
