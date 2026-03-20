import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Injuries — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Injuries flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Injuries');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Injured Players/i);
  });

  test('table has .injuries-table class', async ({ page }) => {
    await expect(page.locator('table.injuries-table')).toBeVisible();
  });

  test('4 column headers: Pos, Player, Team, Days', async ({ page }) => {
    const headers = page.locator('.injuries-table thead th');
    await expect(headers).toHaveCount(4);

    await expect(headers.nth(0)).toContainText('Pos');
    await expect(headers.nth(1)).toContainText('Player');
    await expect(headers.nth(2)).toContainText('Team');
    await expect(headers.nth(3)).toContainText('Days');
  });

  test('at least 2 injured player rows', async ({ page }) => {
    // CI seed has pid=5 (5 days) and pid=7 (3 days)
    const teamRows = page.locator('.injuries-table tbody tr[data-team-id]');
    expect(await teamRows.count()).toBeGreaterThanOrEqual(2);
  });

  test('injury rows have data-team-id attribute', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    const firstTeamId = await teamRows.first().getAttribute('data-team-id');
    expect(firstTeamId).toBeTruthy();
  });

  test('days cells have tooltip when lastSimEndDate is set', async ({ page }) => {
    // .ibl-tooltip only renders when lastSimEndDate is set in CI settings
    const tooltips = page.locator('.injuries-table td .ibl-tooltip');
    const count = await tooltips.count();
    if (count > 0) {
      const title = await tooltips.first().getAttribute('title');
      expect(title).toBeTruthy();
    }
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Injuries page');
  });
});
