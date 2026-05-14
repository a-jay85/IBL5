import { test, expect } from '../fixtures/auth';
import { setState, setEoyVotes } from '../helpers/test-state';

// E2E tests for the updater awards steps:
//   - GenerateSeasonAwardsStep (Season awards card)
//   - EndOfSeasonImportStep Finals MVP card
//
// The updater script queries ibl_settings via $lcpRepo->getSetting()
// (direct DB read), so cookie-based appState overrides don't work here.
// All state mutations use setState() (DB-level) with afterEach cleanup.
//
// The CI seed has:
//   - Phase = 'Regular Season', so non-Playoffs skip is the default
//   - No ibl_jsb_history rows with won_championship=1 for year 2026,
//     so EndOfSeasonImportStep is skipped ("No champion determined yet")
//   - No Leaders.htm at $basePath/Leaders.htm, so the Generate button
//     is gated behind "Leaders.htm not found" even when votes ≥ 75%
//   - No 'Most Valuable Player (1st)' award for 2026, so
//     awardsAlreadyGenerated = false (no short-circuit)
//
// Tests are serial because they mutate shared DB rows.
test.describe.configure({ mode: 'serial' });

test.describe('Updater awards — GenerateSeasonAwardsStep', () => {
  test.afterEach(async ({ request }) => {
    await setEoyVotes(request, 0);
    await setState(request, { 'Current Season Phase': 'Regular Season' });
  });

  test('phase not Playoffs: shows "Only available during Playoffs phase"', async ({
    page,
  }) => {
    // Seed default is 'Regular Season' — no setup needed
    await page.goto('scripts/updateAllTheThings.php', { timeout: 60_000 });

    await expect(
      page.getByText('Only available during Playoffs phase'),
    ).toBeVisible({ timeout: 60_000 });
  });

  test('Playoffs + votes < 75%: shows "Voting not yet complete"', async ({
    page,
    request,
  }) => {
    await setState(request, { 'Current Season Phase': 'Playoffs' });
    await setEoyVotes(request, 15);
    await page.goto('scripts/updateAllTheThings.php', { timeout: 60_000 });

    await expect(
      page.getByText(/Voting not yet complete/),
    ).toBeVisible({ timeout: 60_000 });
  });

  test('Playoffs + votes ≥ 75% + no Leaders.htm: shows upload prompt', async ({
    page,
    request,
  }) => {
    // 21/28 = 75% threshold met, but Leaders.htm is not present in the
    // test environment — the step shows the missing-file message instead
    // of the Generate button.
    await setState(request, { 'Current Season Phase': 'Playoffs' });
    await setEoyVotes(request, 21);
    await page.goto('scripts/updateAllTheThings.php', { timeout: 60_000 });

    await expect(
      page.getByText('Leaders.htm not found'),
    ).toBeVisible({ timeout: 60_000 });
  });
});

test.describe('Updater awards — EndOfSeasonImportStep (no champion in seed)', () => {
  test.afterEach(async ({ request }) => {
    await setState(request, { 'Current Season Phase': 'Regular Season' });
  });

  test('no champion: Finals MVP UI is not shown', async ({ page, request }) => {
    // CI seed has no won_championship=1 row for season year 2026, so
    // EndOfSeasonImportStep returns skipped and "IBL Finals MVP" is never rendered.
    await setState(request, { 'Current Season Phase': 'Playoffs' });
    await page.goto('scripts/updateAllTheThings.php', { timeout: 60_000 });

    // Anchor: confirm the pipeline ran to completion before checking negative
    await expect(
      page.getByText(/\d+\s+(steps?\s+completed|succeeded)/i),
    ).toBeVisible({ timeout: 60_000 });

    await expect(page.getByText('IBL Finals MVP')).not.toBeVisible();
  });
});
