import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// League Starters — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('League Starters flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=LeagueStarters');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/League Starters/i);
  });

  test('starters tables are visible', async ({ page }) => {
    const tables = page.locator('.ibl-data-table, table');
    await expect(tables.first()).toBeVisible();
  });

  test('position sections are displayed', async ({ page }) => {
    // League Starters shows tables per position group
    const body = await page.locator('body').textContent();
    // Should contain position labels
    const hasPositions = body!.includes('Point Guard') || body!.includes('PG') ||
      body!.includes('Guard') || body!.includes('Forward') || body!.includes('Center');
    expect(hasPositions).toBe(true);
  });

  test('display mode parameter changes view', async ({ page }) => {
    // Default is ratings view
    await page.goto('modules.php?name=LeagueStarters&display=total_s');
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on League Starters total_s view');
  });

  test('averages view loads', async ({ page }) => {
    await page.goto('modules.php?name=LeagueStarters&display=avg_s');
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on League Starters avg_s view');
  });

  test('per 36 minutes view loads', async ({ page }) => {
    await page.goto('modules.php?name=LeagueStarters&display=per36mins');
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on League Starters per36mins view');
  });

  test('no PHP errors on default view', async ({ page }) => {
    await assertNoPhpErrors(page, 'on League Starters page');
  });
});
