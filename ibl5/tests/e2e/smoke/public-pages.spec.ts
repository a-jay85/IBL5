import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Public pages — no authentication required.
// Uses the public fixture with appState for automatic state restore.

test.describe('Public page smoke tests', () => {
  // Ensure trivia mode is off so modules render normally
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  test('homepage loads', async ({ page }) => {
    await page.goto('index.php');
    await assertNoPhpErrors(page, 'on index.php');
    await expect(page).toHaveTitle(/IBL/i);
  });

  test('standings page loads', async ({ page }) => {
    await page.goto('modules.php?name=Standings');
    await assertNoPhpErrors(page, 'on modules.php?name=Standings');
    await expect(page.locator('.ibl-title').first()).toBeVisible();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const teamRows = page.locator('tr[data-team-id]');
    const count = await teamRows.count();
    expect(count).toBeGreaterThanOrEqual(28);
  });

  test('player page loads', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1');
    await assertNoPhpErrors(page, 'on modules.php?name=Player&pa=showpage&pid=1');
    await expect(page.locator('.stats-grid').first()).toBeVisible();
  });

  test('team page loads', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamid=1');
    await assertNoPhpErrors(page, 'on modules.php?name=Team&op=team&teamid=1');
    await expect(page.locator('.team-page-layout').first()).toBeVisible();
  });

  test('season leaderboards loads', async ({ page }) => {
    await page.goto('modules.php?name=SeasonLeaderboards');
    await assertNoPhpErrors(page, 'on modules.php?name=SeasonLeaderboards');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const rows = page.locator('.ibl-data-table').first().locator('tbody tr');
    await expect(rows.first()).toBeVisible();
  });

  test('career leaderboards loads', async ({ page }) => {
    await page.goto('modules.php?name=CareerLeaderboards');
    await assertNoPhpErrors(page, 'on modules.php?name=CareerLeaderboards');
    await expect(page.getByRole('button', { name: /display/i })).toBeVisible();
  });

  test('draft history loads', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory');
    await assertNoPhpErrors(page, 'on modules.php?name=DraftHistory');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const rows = page.locator('.ibl-data-table').first().locator('tbody tr');
    await expect(rows.first()).toBeVisible();
  });

  test('cap space loads', async ({ page }) => {
    await page.goto('modules.php?name=CapSpace');
    await assertNoPhpErrors(page, 'on modules.php?name=CapSpace');
    const teamRows = page.locator('tr[data-team-id]');
    const count = await teamRows.count();
    expect(count).toBeGreaterThanOrEqual(28);
  });

  test('topics page loads', async ({ page }) => {
    await page.goto('modules.php?name=Topics');
    await assertNoPhpErrors(page, 'on modules.php?name=Topics');
    await expect(page.locator('.topics-page').first()).toBeVisible();
  });
});
