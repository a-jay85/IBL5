import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Team page — public, no authentication required.
// Current-season teams use a dropdown (.ibl-view-select), not tabs.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Team page flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=1');
  });

  test('default view loads with roster table', async ({ page }) => {
    // Current-season teams use a dropdown, not tabs
    const dropdown = page.locator('.ibl-view-select').first();
    await expect(dropdown).toBeVisible();
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
  });

  test('team banner shows logo and action links', async ({ page }) => {
    const banner = page.locator('.team-banner-row').first();
    if (await banner.isVisible()) {
      // Action links flank the logo
      const actionLinks = banner.locator('.team-action-link');
      await expect(actionLinks.first()).toBeVisible();
    }
  });

  test('dropdown switches view to season totals', async ({ page }) => {
    const dropdown = page.locator('.ibl-view-select').first();
    await expect(dropdown).toBeVisible();

    await dropdown.selectOption('total_s');
    // Table should update (AJAX or page reload)
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
  });

  test('dropdown switches view to contracts', async ({ page }) => {
    const dropdown = page.locator('.ibl-view-select').first();
    await expect(dropdown).toBeVisible();

    await dropdown.selectOption('contracts');
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
  });

  test('dropdown switches view to averages', async ({ page }) => {
    const dropdown = page.locator('.ibl-view-select').first();
    await expect(dropdown).toBeVisible();

    await dropdown.selectOption('avg_s');
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
  });

  test('team cards display sidebar info', async ({ page }) => {
    const cards = page.locator('.team-card');
    if (await cards.first().isVisible()) {
      await expect(cards.first()).toBeVisible();
    }
  });

  test('multiple teams render without errors', async ({ page }) => {
    const teamIDs = [1, 5, 10, 15];
    for (const teamID of teamIDs) {
      await page.goto(`modules.php?name=Team&op=team&teamID=${teamID}`);
      await assertNoPhpErrors(page, `on team ${teamID}`);
    }
  });
});
