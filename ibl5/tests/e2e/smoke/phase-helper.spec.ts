// Meta-spec for `withPhases` (../fixtures/phase).
//
// Choosing the observable: NOT a module access gate. `modules.php` skips
// ModuleAccessControl for admins and the auth fixture's user is an admin, so a
// gated module is always reachable there; and every gated module checks
// `is_user()` before the gate, so the public fixture always renders loginBox()
// on the ALLOW side. Neither fixture can show both sides of an access gate.
//
// Instead this spec uses a phase-driven *content* observable: the Team ratings
// page marks expiring-contract rows with `player-fa-expiring-row` only when
// Season::isOffseasonPhase() holds (TeamTableService.php:65 — phase only, no
// login or ownership term). The page itself is public-readable.
//
// The CI seed's default phase is 'Free Agency' (fixtures/ci-seed.sql:46), which
// DOES fade. So the mutation-catching direction is 'Regular Season': a helper
// that never sends the phase leaves the seed default in place and the rows stay
// faded. Block A is that direction; Block B is its twin.
//
// Phase restore is BrowserContext disposal — not an afterEach hook. Playwright
// disposes the context after each test identically on pass and on fail. Block C
// proves this.
import { test, expect } from '../fixtures/auth';
import { test as publicTest } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';
import {
  withPhases,
  SEASON_PHASES,
  CONTRACT_YEAR_PHASES,
  IN_SEASON_PHASES,
  CONTRACT_BOUNDARY_PHASES,
} from '../fixtures/phase';

const TEAM_RATINGS_URL = 'modules.php?name=Team&op=team&teamid=1&display=ratings';
const FADED_ROWS = 'table.team-table tbody tr.player-fa-expiring-row';

// Block A — the helper overrides the seed default (the mutation-catching direction)
// Mutation caught: delete the appState call from withPhases' beforeEach. The phase
// then stays at the seed default 'Free Agency', which fades, and this fails.
withPhases(['Regular Season'], (phase) => {
  test('helper overrides the seed default phase (expiring rows unfaded)', async ({ page }) => {
    expect(phase).toBe('Regular Season');
    await page.goto(TEAM_RATINGS_URL);
    // Positive half — a blank PHP crash must not read as "not faded".
    await expect(page.locator('table.team-table')).toBeVisible();
    await expect(page.locator(FADED_ROWS)).toHaveCount(0);
    await assertNoPhpErrors(page, `Team ratings in phase: ${phase}`);
  });
});

// Block B — each generated describe receives its own phase (the ALLOW twin)
// Mutation caught: hard-coding beforeEach to always send 'Regular Season'. Block A
// still passes; both phases here fail.
withPhases(['Draft', 'Free Agency'], (phase) => {
  test(`offseason phase fades expiring rows (${phase})`, async ({ page }) => {
    await page.goto(TEAM_RATINGS_URL);
    await expect(page.locator('table.team-table')).toBeVisible();
    await expect(page.locator(FADED_ROWS).first()).toBeVisible();
    await assertNoPhpErrors(page, `Team ratings in phase: ${phase}`);
  });
});

// Block C — restore holds on the failure path
// Mutation caught: promoting the cookie to worker scope, or using
// createAppStateFixture (DB-writing) — the probe then sees 'Regular Season'
// leaking and the seed-default fade is gone.
test.describe('withPhases: phase does not leak past a failing test', () => {
  test.describe.configure({ mode: 'serial' });

  withPhases(['Regular Season'], () => {
    test('deliberate throw to verify phase does not survive a failing test', async ({ page }) => {
      // test.fail() makes this throw expected — Playwright reports it as a failure
      // if it ever stops throwing. Without it, serial mode would skip the probe.
      test.fail();
      await page.goto(TEAM_RATINGS_URL);
      await expect(page.locator(FADED_ROWS)).toHaveCount(0);
      throw new Error('deliberate failure — proves the phase does not survive a failing test');
    });
  });

  // NOT wrapped in withPhases — runs at the seed default ('Free Agency'), which fades.
  test('probe: seed-default phase restored after failing test (no leak)', async ({ page }) => {
    await page.goto(TEAM_RATINGS_URL);
    await expect(page.locator('table.team-table')).toBeVisible();
    await expect(page.locator(FADED_ROWS).first()).toBeVisible();
    await assertNoPhpErrors(page, 'Team ratings at seed default after failed test');
  });
});

// Block D — the `test` and `extraState` options both reach PHP
// Mutation caught: dropping `opts.test` (the block would build on the admin auth
// fixture, and the Trivia gate — which admins bypass — would stop denying), or
// dropping `extraState` from the appState payload (Player stays accessible).
withPhases(['Draft'], (phase) => {
  publicTest('public fixture carries phase, and extraState reaches PHP', async ({ page }) => {
    // Phase half: the Team page is public-readable and fades on the offseason side.
    await page.goto(TEAM_RATINGS_URL);
    await expect(page.locator('table.team-table')).toBeVisible();
    await expect(page.locator(FADED_ROWS).first()).toBeVisible();

    // extraState half: Trivia Mode hides Player from a non-admin
    // (ModuleAccessControl::TRIVIA_HIDDEN_MODULES).
    await page.goto('modules.php?name=Player&pa=showpage&pid=1');
    await expect(page.locator('#site-content')).toContainText("Sorry, this Module isn't active!");
    await assertNoPhpErrors(page, `Player module under Trivia Mode in phase: ${phase}`);
  });
}, { test: publicTest, extraState: { 'Trivia Mode': 'On' } });

// Block E — constants still mirror Season::advancesContractYears()
// Mutation caught: adding a seventh phase without classifying it, or moving Playoffs
// into IN_SEASON_PHASES (plausible — isOffseasonPhase() excludes it, advancesContractYears() doesn't).
test('phase constants partition the phase space (mirror Season::advancesContractYears)', () => {
  const sorted = (arr: readonly string[]) => [...arr].sort();

  expect(sorted(CONTRACT_YEAR_PHASES)).toEqual(['Draft', 'Free Agency', 'Playoffs']);
  expect(sorted(IN_SEASON_PHASES)).toEqual(['HEAT', 'Preseason', 'Regular Season']);

  const combined = [...CONTRACT_YEAR_PHASES, ...IN_SEASON_PHASES];
  expect(sorted(combined)).toEqual(sorted(SEASON_PHASES));

  const intersection = CONTRACT_YEAR_PHASES.filter(p => IN_SEASON_PHASES.includes(p));
  expect(intersection).toHaveLength(0);

  // CONTRACT_BOUNDARY_PHASES has exactly one member from each side
  const fromContractYear = CONTRACT_BOUNDARY_PHASES.filter(p => CONTRACT_YEAR_PHASES.includes(p));
  const fromInSeason = CONTRACT_BOUNDARY_PHASES.filter(p => IN_SEASON_PHASES.includes(p));
  expect(fromContractYear).toHaveLength(1);
  expect(fromInSeason).toHaveLength(1);
});
