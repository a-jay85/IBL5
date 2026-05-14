import { test, expect } from '../fixtures/auth';
import { setEoyVotes } from '../helpers/test-state';

// E2E tests for the updater awards steps:
//   - GenerateSeasonAwardsStep (Season awards card)
//   - EndOfSeasonImportStep Finals MVP card
//
// The CI seed has:
//   - No ibl_jsb_history rows with won_championship=1 for year 2026,
//     so EndOfSeasonImportStep is skipped ("No champion determined yet")
//   - No Leaders.htm at $basePath/Leaders.htm, so the Generate button
//     is gated behind "Leaders.htm not found" even when votes ≥ 75%
//   - No 'Most Valuable Player (1st)' award for 2026, so
//     awardsAlreadyGenerated = false (no short-circuit)
//
// Tests are serial because they mutate shared ibl_team_info.eoy_vote rows
// via setEoyVotes, and the updater page is slow (~60s timeout).
test.describe.configure({ mode: 'serial' });

test.describe('Updater awards — GenerateSeasonAwardsStep', () => {
  test.afterEach(async ({ request }) => {
    // Reset eoy_vote back to 0 to avoid leaking state across tests
    await setEoyVotes(request, 0);
  });

  test('phase not Playoffs: shows "Only available during Playoffs phase"', async ({
    appState,
    page,
  }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto('scripts/updateAllTheThings.php', { timeout: 60_000 });

    await expect(
      page.getByText('Only available during Playoffs phase'),
    ).toBeVisible({ timeout: 60_000 });
  });

  test('Playoffs + votes < 75%: shows "Voting not yet complete"', async ({
    appState,
    page,
    request,
  }) => {
    await appState({ 'Current Season Phase': 'Playoffs' });
    await setEoyVotes(request, 15);
    await page.goto('scripts/updateAllTheThings.php', { timeout: 60_000 });

    await expect(
      page.getByText(/Voting not yet complete/),
    ).toBeVisible({ timeout: 60_000 });
  });

  test('Playoffs + votes ≥ 75% + no Leaders.htm: shows upload prompt', async ({
    appState,
    page,
    request,
  }) => {
    // 21/28 = 75% threshold met, but Leaders.htm is not present in the
    // test environment — the step shows the missing-file message instead
    // of the Generate button.
    await appState({ 'Current Season Phase': 'Playoffs' });
    await setEoyVotes(request, 21);
    await page.goto('scripts/updateAllTheThings.php', { timeout: 60_000 });

    await expect(
      page.getByText('Leaders.htm not found'),
    ).toBeVisible({ timeout: 60_000 });
  });
});

test.describe('Updater awards — EndOfSeasonImportStep (no champion in seed)', () => {
  test('no champion: Finals MVP UI is not shown', async ({ appState, page }) => {
    // CI seed has no won_championship=1 row for season year 2026, so
    // EndOfSeasonImportStep returns skipped and "IBL Finals MVP" is never rendered.
    await appState({ 'Current Season Phase': 'Playoffs' });
    await page.goto('scripts/updateAllTheThings.php', { timeout: 60_000 });

    // Anchor: confirm the pipeline ran to completion before checking negative
    await expect(
      page.getByText(/\d+\s+(steps?\s+completed|succeeded)/i),
    ).toBeVisible({ timeout: 60_000 });

    await expect(page.getByText('IBL Finals MVP')).not.toBeVisible();
  });
});
