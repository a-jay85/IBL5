import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Injuries — public page.
test.use({ storageState: publicStorageState() });

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

  test('days values are positive integers', async ({ page }) => {
    const daysCells = page.locator('.injuries-table tbody td.ibl-stat-highlight');
    const count = await daysCells.count();
    expect(count).toBeGreaterThanOrEqual(2);

    for (let i = 0; i < count; i++) {
      const text = await daysCells.nth(i).textContent();
      const value = parseInt(text?.trim() ?? '', 10);
      expect(value).toBeGreaterThan(0);
    }
  });

  test('days cells have tooltip with return date', async ({ page }) => {
    // CI seed has ibl_sim_dates with End Date — tooltips should render
    const tooltips = page.locator('.injuries-table td .ibl-tooltip');
    await expect(tooltips.first()).toBeVisible();

    const title = await tooltips.first().getAttribute('title');
    expect(title).toBeTruthy();
    expect(title).toContain('Returns:');
  });

  test('player name links navigate to player page', async ({ page }) => {
    const playerLinks = page.locator('.injuries-table a[href*="pid="]');
    await expect(playerLinks.first()).toBeVisible();

    const href = await playerLinks.first().getAttribute('href');
    expect(href).toContain('name=Player');

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on player page from Injuries');
    await expect(page.locator('h2, h3').first()).toBeVisible();
  });

  test('team name cells link to team pages', async ({ page }) => {
    const teamLinks = page.locator('.injuries-table a[href*="teamID="]');
    const count = await teamLinks.count();
    expect(count).toBeGreaterThanOrEqual(1);

    const href = await teamLinks.first().getAttribute('href');
    expect(href).toContain('name=Team');

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on team page from Injuries');
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Injuries page');
  });
});
