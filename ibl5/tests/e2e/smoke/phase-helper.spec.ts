// Phase restore is BrowserContext disposal — not an afterEach hook. Playwright disposes
// the context after each test identically on pass and on fail. Block C proves this.
import { test as publicTest, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';
import {
  withPhases,
  SEASON_PHASES,
  CONTRACT_YEAR_PHASES,
  IN_SEASON_PHASES,
  CONTRACT_BOUNDARY_PHASES,
} from '../fixtures/phase';

// Block A — helper applies the phase (ALLOW path)
// Mutation caught: delete the appState call from withPhases' beforeEach and the
// Draft module is denied (phase stays at seed-default Free Agency), table never appears.
withPhases(['Draft'], (phase) => {
  publicTest('Draft module renders when phase is set (appState cookie drives access)', async ({ page }) => {
    expect(phase).toBe('Draft');
    await page.goto('modules.php?name=Draft');
    await expect(page.locator('#site-content')).toBeVisible();
    await expect(page.locator('table.draft-table').first()).toBeVisible();
    await assertNoPhpErrors(page, `Draft module in phase: ${phase}`);
  });
}, { test: publicTest, extraState: { 'Show Draft Link': 'Off' } });

// Block B — other phases deny the Draft module (DENY path)
// Mutation caught: hard-coding beforeEach to always send Draft — Block A still passes,
// Block B fails on both phases.
withPhases(CONTRACT_BOUNDARY_PHASES, (phase) => {
  publicTest(`Draft module is absent in non-Draft phase (${phase})`, async ({ page }) => {
    expect(phase).not.toBe('Draft');
    await page.goto('modules.php?name=Draft');
    // #site-content must be present — a PHP crash must not read as "module absent"
    await expect(page.locator('#site-content')).toBeVisible();
    await expect(page.locator('table.draft-table')).toHaveCount(0);
  });
}, { test: publicTest, extraState: { 'Show Draft Link': 'Off' } });

// Block C — restore holds on the failure path
// Mutation caught: promoting the cookie to worker scope, or using createAppStateFixture
// (DB-writing) — the probe then sees Draft leaking and table.draft-table is non-zero.
publicTest.describe('withPhases: phase does not leak past a failing test', () => {
  publicTest.describe.configure({ mode: 'serial' });

  withPhases(['Draft'], () => {
    publicTest('deliberate throw to verify phase does not survive a failing test', async ({ page }) => {
      // test.fail() makes this throw expected — Playwright reports failure if it stops throwing.
      // Without it, serial mode would skip the Block C probe when this test fails.
      publicTest.fail();
      await page.goto('modules.php?name=Draft');
      await expect(page.locator('table.draft-table').first()).toBeVisible();
      throw new Error('deliberate failure — proves the phase does not survive a failing test');
    });
  }, { test: publicTest, extraState: { 'Show Draft Link': 'Off' } });

  // NOT wrapped in withPhases — runs at seed default (Free Agency).
  publicTest('probe: Draft module absent after failing test (phase did not leak)', async ({ page }) => {
    await page.goto('modules.php?name=Draft');
    await expect(page.locator('#site-content')).toBeVisible();
    await expect(page.locator('table.draft-table')).toHaveCount(0);
    // Positive half: FreeAgency module is allowed in seed-default Free Agency phase.
    await page.goto('modules.php?name=FreeAgency');
    await expect(page.locator('#site-content')).toBeVisible();
    await assertNoPhpErrors(page, 'FreeAgency module in seed-default phase after failed test');
  });
});

// Block D — constants still mirror Season::advancesContractYears()
// Mutation caught: adding a seventh phase without classifying it, or moving Playoffs
// into IN_SEASON_PHASES (plausible — isOffseasonPhase() excludes it, advancesContractYears() doesn't).
publicTest('phase constants partition the phase space (mirror Season::advancesContractYears)', () => {
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
