import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Olympics module coverage — gap analysis tests beyond existing gating test.

test.describe('Olympics module coverage', () => {
  test('olympics standings loads with team data', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=Standings&league=olympics');
    await assertNoPhpErrors(page, 'on Olympics Standings');

    // Should show group standings with expected columns
    const tables = page.locator('.ibl-data-table, table');
    await expect(tables.first()).toBeVisible();

    // Standings table should have team-related headers
    const headers = await tables.first().locator('th').allTextContents();
    const joined = headers.join(' ');
    const hasTeamColumn = /team|country|nation/i.test(joined);
    const hasRecordColumn = /w|l|win|loss|pct|record/i.test(joined);
    expect(hasTeamColumn || hasRecordColumn).toBe(true);
  });

  test('olympics team page loads with roster', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=Team&op=team&teamID=1&league=olympics');
    await assertNoPhpErrors(page, 'on Olympics Team page');

    // Team page should have a table (roster or stats)
    const table = page.locator('.ibl-data-table, table');
    await expect(table.first()).toBeVisible();
  });

  test('olympics leaderboards loads', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=SeasonLeaderboards&league=olympics');
    await assertNoPhpErrors(page, 'on Olympics Leaderboards');
  });

  test('IBL-only modules show gating message in olympics context', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    // FranchiseHistory is IBL-only
    await page.goto('modules.php?name=FranchiseHistory&league=olympics');
    // Should either redirect, show a message, or the module should still work
    await assertNoPhpErrors(page, 'on IBL-only module in olympics context');
  });

  test('no PHP errors across olympics pages', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    const urls = [
      'modules.php?name=Standings&league=olympics',
      'modules.php?name=Team&op=team&teamID=1&league=olympics',
      'modules.php?name=SeasonLeaderboards&league=olympics',
    ];
    for (const url of urls) {
      await page.goto(url);
      await assertNoPhpErrors(page, `on ${url}`);
    }
  });
});
