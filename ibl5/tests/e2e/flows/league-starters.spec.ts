import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';

// League Starters — public page showing starting lineups by position.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('League Starters flow', () => {
  test.beforeEach(async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=LeagueStarters');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/League Starters/i);
  });

  test('displays all five position tables', async ({ page }) => {
    const positionLabels = [
      'Point Guards',
      'Shooting Guards',
      'Small Forwards',
      'Power Forwards',
      'Centers',
    ];

    for (const label of positionLabels) {
      await expect(page.getByText(label, { exact: false }).first()).toBeVisible();
    }
  });

  test('each position has a data table', async ({ page }) => {
    const tables = page.locator('.ibl-data-table');
    const count = await tables.count();
    // At least 5 tables (one per position)
    expect(count).toBeGreaterThanOrEqual(5);
  });

  test('tables have expected stat columns for ratings view', async ({ page }) => {
    const firstTable = page.locator('.ibl-data-table').first();
    await expect(firstTable).toBeVisible();

    const headerText = await firstTable.locator('thead').textContent();
    expect(headerText).toContain('Player');
    expect(headerText).toContain('Team');
  });

  test('player links are present in tables', async ({ page }) => {
    // Player links use modules.php?name=Player&...
    const playerLinks = page.locator('.ibl-data-table a[href*="Player"]');
    const count = await playerLinks.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('team rows have data-team-id attributes', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    const count = await teamRows.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('view switcher tabs are present', async ({ page }) => {
    // LeagueStarters has tabs: Ratings, Season Totals, Season Averages, Per 36 Minutes
    const tabs = page.locator('.ibl-tab, [role="tab"]');
    const count = await tabs.count();
    expect(count).toBeGreaterThanOrEqual(4);
  });

  test('switching to Season Totals view works', async ({ page }) => {
    const totalsLink = page.locator('a[href*="display=total_s"]').first();
    if (await totalsLink.count() > 0) {
      const href = await totalsLink.getAttribute('href');
      if (href) {
        await page.goto(href);
        await expect(page.locator('.ibl-data-table').first()).toBeVisible();
      }
    }
  });

  test('switching to Season Averages view works', async ({ page }) => {
    const avgLink = page.locator('a[href*="display=avg_s"]').first();
    if (await avgLink.count() > 0) {
      const href = await avgLink.getAttribute('href');
      if (href) {
        await page.goto(href);
        await expect(page.locator('.ibl-data-table').first()).toBeVisible();
      }
    }
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on League Starters page');
  });

  test('no PHP errors on alternate display modes', async ({ page }) => {
    const displays = ['total_s', 'avg_s', 'per36mins'];
    for (const display of displays) {
      await page.goto(`modules.php?name=LeagueStarters&display=${display}`);
      await assertNoPhpErrors(page, `on League Starters ${display} view`);
    }
  });
});
