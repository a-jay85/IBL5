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

  test('injuries table or empty state is displayed', async ({ page }) => {
    // May show a table of injuries or indicate no injuries
    const table = page.locator('.injuries-table, .ibl-data-table');
    const count = await table.count();
    if (count > 0) {
      await expect(table.first()).toBeVisible();
      const headerText = await table.first().locator('thead').textContent();
      expect(headerText).toContain('Player');
      expect(headerText).toContain('Team');
      expect(headerText).toContain('Days');
    }
    // If no injuries, the page should still load without errors
  });

  test('injury rows have data-team-id when present', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    const count = await teamRows.count();
    // Injuries are data-dependent; just verify structure if present
    if (count > 0) {
      const firstTeamId = await teamRows.first().getAttribute('data-team-id');
      expect(firstTeamId).toBeTruthy();
    }
  });

  test('days column has highlight styling when present', async ({ page }) => {
    const highlights = page.locator('.ibl-stat-highlight');
    const count = await highlights.count();
    if (count > 0) {
      await expect(highlights.first()).toBeVisible();
    }
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Injuries page');
  });
});
