import { test, expect } from '../fixtures/auth';
import { test as nonAdminTest } from '../fixtures/auth-regular';
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

  // The former 'no PHP errors across olympics pages' loop test was removed: it
  // re-visited Standings + Team (&league=olympics), already asserted individually
  // above, and carried no unique header-content assertion. The unique Olympics
  // header checks live at smoke/olympics-pages.spec.ts (the "Olympics Standings"
  // title + Eastern/Western-Conference-absence assertions).
});

// Gating must be asserted as a NON-ADMIN: modules.php:91 is
// `if (!$isModuleAccessible && !is_admin())`, so the admin fixture bypasses
// ModuleAccessControl entirely and would never see the gating message.
nonAdminTest.describe('Olympics module coverage — non-admin gating', () => {
  nonAdminTest.skip(
    !process.env.IBL_TEST_USER_REGULAR || !process.env.IBL_TEST_PASS_REGULAR,
    'IBL_TEST_USER_REGULAR / IBL_TEST_PASS_REGULAR not set — regular.json is not freshly authenticated',
  );

  nonAdminTest('IBL-only modules show gating message in olympics context', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    // FranchiseHistory is IBL-only
    await page.goto('modules.php?name=FranchiseHistory&league=olympics');
    await assertNoPhpErrors(page, 'on IBL-only module in olympics context');

    // FranchiseHistory is IBL-only, so the olympics context must gate it for an
    // AUTHENTICATED user too — olympics-module-gating.spec.ts:34 covers only the
    // public path, and gating that leaks once logged in is the failure this guards.
    const body = await page.locator('body').textContent();
    expect(
      body,
      'IBL-only module should show the gating message in olympics context',
    ).toContain("Module isn't active");
  });
});
