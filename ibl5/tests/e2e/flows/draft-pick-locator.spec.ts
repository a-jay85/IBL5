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

  test('container has .draft-pick-locator-container', async ({ page }) => {
    await expect(page.locator('.draft-pick-locator-container')).toBeVisible();
  });

  test('pick locator table is visible', async ({ page }) => {
    await expect(page.locator('.draft-pick-table').first()).toBeVisible();
  });

  test('thead has two header rows', async ({ page }) => {
    await expect(page.locator('.draft-pick-table thead tr')).toHaveCount(2);
  });

  test('has at least 28 team rows', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    expect(await teamRows.count()).toBeGreaterThanOrEqual(28);
  });

  test('year spans in first header row', async ({ page }) => {
    const yearSpans = page.locator('.draft-pick-table thead tr:first-child th[colspan="2"]');
    expect(await yearSpans.count()).toBeGreaterThanOrEqual(6);

    const firstYearText = (await yearSpans.first().textContent())!.trim();
    expect(firstYearText).toMatch(/\d{4}/);
  });

  test('first column cells have sticky-col class', async ({ page }) => {
    await expect(
      page.locator('tbody tr[data-team-id]:first-child td.sticky-col').first()
    ).toBeVisible();
  });

  test('own picks and traded picks are distinguished', async ({ page }) => {
    const ownPicks = page.locator('.draft-pick-own');
    const tradedPicks = page.locator('.draft-pick-traded');
    const ownCount = await ownPicks.count();
    const tradedCount = await tradedPicks.count();
    expect(ownCount + tradedCount).toBeGreaterThan(0);
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Draft Pick Locator page');
  });
});
