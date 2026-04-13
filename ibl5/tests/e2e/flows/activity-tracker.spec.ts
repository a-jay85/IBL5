import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Activity Tracker — public page.
test.use({ storageState: publicStorageState() });

test.describe('Activity Tracker flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=ActivityTracker');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Activity Tracker/i);
  });

  test('activity table is visible with expected columns', async ({ page }) => {
    const table = page.locator('.ibl-data-table').first();
    await expect(table).toBeVisible();

    const headerText = await table.locator('thead').textContent();
    expect(headerText).toContain('Team');
  });

  test('all 5 column headers present', async ({ page }) => {
    const headers = page.locator('.ibl-data-table thead th');
    await expect(headers).toHaveCount(5);

    const expectedHeaders = ['Team', 'Sim Depth Chart', 'Last Depth Chart', 'ASG Ballot', 'EOY Ballot'];
    for (let i = 0; i < expectedHeaders.length; i++) {
      await expect(headers.nth(i)).toContainText(expectedHeaders[i]);
    }
  });

  test('has at least 28 team rows', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    expect(await teamRows.count()).toBeGreaterThanOrEqual(28);
  });

  test('team cells link to Team module', async ({ page }) => {
    const firstRowLink = page.locator('tr[data-team-id] a[href*="name=Team"]').first();
    await expect(firstRowLink).toBeVisible();
  });

  test('ballot columns contain dates, dashes, or No Vote', async ({ page }) => {
    const firstRow = page.locator('tr[data-team-id]').first();
    // ASG Ballot is the 4th column (index 3)
    const asgCell = firstRow.locator('td').nth(3);
    const text = (await asgCell.textContent())!.trim();
    expect(text).toMatch(/^\d{4}-\d{2}-\d{2}$|^-$|^No Vote$/);
  });

  test('team cells have colored backgrounds', async ({ page }) => {
    const firstTeamCell = page.locator('tr[data-team-id]').first().locator('td').first();
    const style = await firstTeamCell.getAttribute('style');
    expect(style).toContain('background-color');
  });

  test('table is sortable', async ({ page }) => {
    const sortable = page.locator('.sortable');
    await expect(sortable.first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Activity Tracker page');
  });
});
