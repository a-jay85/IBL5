import { test, expect } from '../fixtures/auth-regular';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { desktopNav } from '../helpers/navigation';

/**
 * Tier 2 role-gating coverage: exercises the production gate paths that
 * fixtures/auth.ts (admin) bypasses by virtue of roles_mask=1.
 *
 * Skips entirely when IBL_TEST_USER_REGULAR is unset — auth-regular.setup.ts
 * also skips in that case, so playwright/.auth/regular.json is absent or
 * stale and these assertions would run against an unauthenticated session.
 *
 * The blocks below cover every code-level admin gate we currently enforce.
 * Surfaces that gate only by `is_user()` (or have no gate at all) are
 * listed in the plan's "Out of scope" section and intentionally not tested
 * here — a future security-hardening PR would add gates AND tests together.
 */

test.skip(
  !process.env.IBL_TEST_USER_REGULAR || !process.env.IBL_TEST_PASS_REGULAR,
  'IBL_TEST_USER_REGULAR / IBL_TEST_PASS_REGULAR not set — regular.json is not freshly authenticated',
);

// _MODULENOTACTIVE message rendered by modules.php when a non-admin requests
// a gated module. Source: ibl5/language/lang-english.php define _MODULENOTACTIVE.
const MODULE_NOT_ACTIVE = "Sorry, this Module isn't active!";

// ---------------------------------------------------------------------------
// Block A — admin entry-point scripts return 403 for an authenticated non-admin
// ---------------------------------------------------------------------------

test.describe('Admin entry-point scripts: non-admin gets 403', () => {
  // scripts/updateAllTheThings.php is also smoke-tested in smoke/auth-regular.spec.ts;
  // duplicated here so the full role-gating matrix lives in one file.
  test('scripts/updateAllTheThings.php returns 403', async ({ page }) => {
    const response = await page.goto('scripts/updateAllTheThings.php');
    expect(response?.status()).toBe(403);
  });

  test('scripts/allStarRename.php returns 403 JSON', async ({ request }) => {
    const response = await request.get('scripts/allStarRename.php');
    expect(response.status()).toBe(403);
    const body = (await response.json()) as { success: boolean };
    expect(body.success).toBe(false);
  });

  test('block.php returns 403', async ({ page }) => {
    const response = await page.goto('block.php');
    expect(response?.status()).toBe(403);
  });

  test('simSummaries.php returns 403', async ({ page }) => {
    const response = await page.goto('simSummaries.php');
    expect(response?.status()).toBe(403);
  });

  test('simSummaries.php?sim=689&format=txt returns 403 without leaking the recap', async ({
    request,
  }) => {
    const response = await request.get('simSummaries.php?sim=689&format=txt');
    expect(response.status()).toBe(403);
    // Seeded body of sim 689 (tests/e2e/fixtures/ci-seed.sql) — a 403 that still
    // wrote the payload would pass a status-only assertion.
    expect(await response.text()).not.toContain('the Cannons erased a nine-point');
  });

  test('leagueControlPanel.php returns 403', async ({ page }) => {
    const response = await page.goto('leagueControlPanel.php');
    expect(response?.status()).toBe(403);
  });

  test('faprep.php returns 403', async ({ page }) => {
    const response = await page.goto('faprep.php');
    expect(response?.status()).toBe(403);
  });
});

// ---------------------------------------------------------------------------
// Block B — save_order handler JSON 403 for non-admin
// ---------------------------------------------------------------------------

test.describe('ProjectedDraftOrder save_order: non-admin gets 403 JSON', () => {
  test('POST save_order without admin returns 403 success:false', async ({
    request,
  }) => {
    const response = await request.post(
      'modules.php?name=ProjectedDraftOrder&op=save_order',
      {
        data: { order: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] },
        headers: { 'Content-Type': 'application/json' },
      },
    );
    expect(response.status()).toBe(403);
    const body = (await response.json()) as { success: boolean };
    expect(body.success).toBe(false);
  });
});

// ---------------------------------------------------------------------------
// Block C — ProjectedDraftOrder index renders for non-admin but hides admin JS
// ---------------------------------------------------------------------------

test.describe('ProjectedDraftOrder index: admin controls hidden for non-admin', () => {
  test('non-admin sees the draft order table without admin drag JS', async ({
    page,
  }) => {
    const response = await page.goto('modules.php?name=ProjectedDraftOrder');
    expect(response?.status()).toBe(200);

    const table = page
      .locator('.projected-draft-order-table, .ibl-data-table')
      .first();
    await expect(table).toBeVisible();

    // The drag handler ships only when both isAdmin and !isDraftStarted.
    // For a roles_mask=0 user it must never be on the page.
    const html = await page.content();
    expect(html).not.toContain('draft-order-drag.js');

    await assertNoPhpErrors(page, 'on ProjectedDraftOrder as non-admin');
  });
});

// ---------------------------------------------------------------------------
// Block D — phase-restricted modules hide for non-admin when phase is wrong
// ---------------------------------------------------------------------------

test.describe('Phase-restricted modules: hidden for non-admin', () => {
  test('Draft module shows _MODULENOTACTIVE when phase != Draft', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Show Draft Link': 'Off',
    });
    await page.goto('modules.php?name=Draft');

    const body = await page.locator('body').textContent();
    expect(body).toContain(MODULE_NOT_ACTIVE);
  });

  test('FreeAgency module shows _MODULENOTACTIVE outside Free Agency phase', async ({
    appState,
    page,
  }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto('modules.php?name=FreeAgency');

    const body = await page.locator('body').textContent();
    expect(body).toContain(MODULE_NOT_ACTIVE);
  });

  test('Waivers module shows _MODULENOTACTIVE when waivers disallowed', async ({
    appState,
    page,
  }) => {
    // Waivers is always-on during HEAT / Regular Season / Playoffs, so the
    // "Allow Waiver Moves" setting only takes effect during Free Agency or
    // Preseason. Use Preseason here — Free Agency would let the gate run
    // but adds the FA module's own phase noise to the picture.
    await appState({
      'Current Season Phase': 'Preseason',
      'Allow Waiver Moves': 'No',
    });
    await page.goto('modules.php?name=Waivers');

    const body = await page.locator('body').textContent();
    expect(body).toContain(MODULE_NOT_ACTIVE);
  });
});

// ---------------------------------------------------------------------------
// Block E — Trivia-hidden modules for non-admin when Trivia Mode is on
// ---------------------------------------------------------------------------

test.describe('Trivia-mode hidden modules: non-admin sees notice', () => {
  test('Player module hidden when Trivia Mode is On', async ({
    appState,
    page,
  }) => {
    await appState({ 'Trivia Mode': 'On' });
    await page.goto('modules.php?name=Player');

    const body = await page.locator('body').textContent();
    expect(body).toContain(MODULE_NOT_ACTIVE);
  });

  test('CareerLeaderboards hidden when Trivia Mode is On', async ({
    appState,
    page,
  }) => {
    await appState({ 'Trivia Mode': 'On' });
    await page.goto('modules.php?name=CareerLeaderboards');

    const body = await page.locator('body').textContent();
    expect(body).toContain(MODULE_NOT_ACTIVE);
  });

  test('SeasonLeaderboards hidden when Trivia Mode is On', async ({
    appState,
    page,
  }) => {
    await appState({ 'Trivia Mode': 'On' });
    await page.goto('modules.php?name=SeasonLeaderboards');

    const body = await page.locator('body').textContent();
    expect(body).toContain(MODULE_NOT_ACTIVE);
  });
});

// ---------------------------------------------------------------------------
// Block F — GM-only pages: authenticated user with no team assignment
//
// These pages don't enforce admin or team-membership in code today; the plan
// intentionally exercises the "no team" branch to document current behavior.
// Assertions are intentionally permissive (HTTP 200 + no PHP fatal) — Phase 3
// of the plan calls these out as exploratory; tighten assertions in a
// follow-up PR once the response shape is observed in CI artifacts.
// ---------------------------------------------------------------------------

test.describe('GM-only pages: non-admin / no-team behavior', () => {
  test('Trading renders without PHP errors for a user with no team', async ({
    appState,
    page,
  }) => {
    await appState({
      'Allow Trades': 'Yes',
      'Current Season Phase': 'Regular Season',
    });
    const response = await page.goto('modules.php?name=Trading');
    expect(response?.status()).toBe(200);
    await assertNoPhpErrors(page, 'on Trading as non-admin');
  });

  test('FreeAgency negotiate renders without PHP errors for a user with no team', async ({
    appState,
    page,
  }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    const response = await page.goto(
      'modules.php?name=FreeAgency&pa=negotiate&pid=11',
    );
    expect(response?.status()).toBe(200);
    await assertNoPhpErrors(page, 'on FreeAgency negotiate as non-admin');
  });

  test('Player negotiate renders without PHP errors for a user with no team', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    const response = await page.goto(
      'modules.php?name=Player&pa=negotiate&pid=30',
    );
    expect(response?.status()).toBe(200);
    await assertNoPhpErrors(page, 'on Player negotiate as non-admin');
  });

  test('DepthChartEntry renders without PHP errors for a user with no team', async ({
    page,
  }) => {
    const response = await page.goto('modules.php?name=DepthChartEntry');
    expect(response?.status()).toBe(200);
    await assertNoPhpErrors(page, 'on DepthChartEntry as non-admin');
  });
});

// ---------------------------------------------------------------------------
// Block G — nav: admin-only "Voting Results" link is never shown to a non-admin
//
// The auth-regular user is roles_mask=0 with no ibl_team_info row
// (ci-seed.sql:1929), so getMyTeamMenu() returns null and the My Team menu
// does not render at all — the link is absent because no admin session ever
// emits it. The precise "menu present, Voting shown, Voting Results hidden"
// isolation is carried by NavigationMenuBuilderTest (admin=false + teamId set).
// ---------------------------------------------------------------------------

test.describe('Nav: Voting Results link hidden for non-admin', () => {
  test('rendered desktop nav has no Voting Results link', async ({ page }) => {
    await page.goto('index.php');
    await expect(
      desktopNav(page).getByRole('link', { name: 'Voting Results' }),
    ).toHaveCount(0);
  });
});
