import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Parameter edge cases — verify graceful handling of missing/invalid query parameters.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Parameter edge cases', () => {
  test('Team module without teamID shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=Team');
    await assertNoPhpErrors(page, 'on Team without teamID');
  });

  test('Player module without pid shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage');
    await assertNoPhpErrors(page, 'on Player without pid');
  });

  test('Player module with pid=0 shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=0');
    await assertNoPhpErrors(page, 'on Player with pid=0');
  });

  test('non-existent module shows not-active message', async ({ page }) => {
    await page.goto('modules.php?name=NonExistentModule');
    await assertNoPhpErrors(page, 'on non-existent module');

    // Should show a "doesn't exist" or similar error message
    const body = await page.locator('body').textContent();
    expect(body).toMatch(/doesn.t exist|not.*active|not.*found/i);
  });

  test('DraftHistory with invalid teamID shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory&teamID=999');
    await assertNoPhpErrors(page, 'on DraftHistory with invalid teamID');
    // Should either show empty state or all teams
  });

  test('Team module with out-of-range teamID shows error alert', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=-1');
    await assertNoPhpErrors(page, 'on Team with teamID=-1');

    // Should show error alert for invalid team
    const alert = page.locator('.ibl-alert--error');
    await expect(alert).toBeVisible();
    await expect(alert).toContainText(/not found/i);
  });

  test('Team module with string teamID shows error alert', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=abc');
    await assertNoPhpErrors(page, 'on Team with teamID=abc');

    // Should show error alert — "abc" is not a valid team ID
    const alert = page.locator('.ibl-alert--error');
    await expect(alert).toBeVisible();
    await expect(alert).toContainText(/not found/i);
  });

  test('Player with invalid PID shows graceful empty state', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=99999');
    await assertNoPhpErrors(page, 'on Player with invalid pid');

    // Should not show a trading card (player doesn't exist)
    const card = page.locator('.card-flip-container');
    expect(await card.count()).toBe(0);
  });

  test('Standings with unexpected parameters shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=Standings&foo=bar&teamID=999');
    await assertNoPhpErrors(page, 'on Standings with unexpected params');
    // Should still render standings normally
    const table = page.locator('.ibl-data-table');
    await expect(table.first()).toBeVisible();
  });

  test('SeasonLeaderboards with invalid season phase shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=SeasonLeaderboards&seasonPhase=InvalidPhase');
    await assertNoPhpErrors(page, 'on SeasonLeaderboards with invalid phase');
  });

  test('empty name parameter shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=');
    await assertNoPhpErrors(page, 'on modules.php with empty name');
  });

  test('missing name parameter shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php');
    await assertNoPhpErrors(page, 'on modules.php without name');
  });
});
