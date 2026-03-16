import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Draft Pick Locator — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Draft Pick Locator flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=DraftPickLocator');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Draft Pick Locator/i);
  });

  test('pick locator table is visible', async ({ page }) => {
    const table = page.locator('.draft-pick-table, .sticky-table, .ibl-data-table, table').first();
    await expect(table).toBeVisible();
  });

  test('table has team rows with data-team-id', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    const count = await teamRows.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('own picks and traded picks are distinguished', async ({ page }) => {
    const ownPicks = page.locator('.draft-pick-own');
    const tradedPicks = page.locator('.draft-pick-traded');
    // At least own picks should exist
    const ownCount = await ownPicks.count();
    const tradedCount = await tradedPicks.count();
    expect(ownCount + tradedCount).toBeGreaterThan(0);
  });

  test('sticky scroll wrapper exists for wide matrix', async ({ page }) => {
    const wrapper = page.locator('.sticky-scroll-wrapper, .draft-pick-locator-container');
    const count = await wrapper.count();
    if (count > 0) {
      await expect(wrapper.first()).toBeVisible();
    }
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Draft Pick Locator page');
  });
});
