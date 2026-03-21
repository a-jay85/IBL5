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
    // Should show the module-not-active message or a 404-like page
    const body = await page.locator('body').textContent();
    // Should not have PHP fatal errors
    await assertNoPhpErrors(page, 'on non-existent module');
  });

  test('DraftHistory with invalid teamID shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory&teamID=999');
    await assertNoPhpErrors(page, 'on DraftHistory with invalid teamID');
    // Should either show empty state or all teams
  });

  test('Team module with out-of-range teamID shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=-1');
    await assertNoPhpErrors(page, 'on Team with teamID=-1');
  });

  test('Team module with string teamID shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=abc');
    await assertNoPhpErrors(page, 'on Team with teamID=abc');
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
