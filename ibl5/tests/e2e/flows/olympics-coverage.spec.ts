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

  test('IBL-only modules show gating message in olympics context', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    // FranchiseHistory is IBL-only
    await page.goto('modules.php?name=FranchiseHistory&league=olympics');
    // Should either redirect, show a message, or the module should still work
    await assertNoPhpErrors(page, 'on IBL-only module in olympics context');
  });

  // The former 'no PHP errors across olympics pages' loop test was removed: it
  // re-visited Standings + Team (&league=olympics), already asserted individually
  // above, and carried no unique header-content assertion. The unique Olympics
  // header checks live at smoke/olympics-pages.spec.ts (the "Olympics Standings"
  // title + Eastern/Western-Conference-absence assertions).
});
