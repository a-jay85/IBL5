import { test as authTest } from './auth';
import type { SetStateFn } from '../helpers/test-state';

export type SeasonPhase = 'Regular Season' | 'Playoffs' | 'Draft' | 'Free Agency' | 'Preseason' | 'HEAT';

// Invariant: CONTRACT_YEAR_PHASES ∪ IN_SEASON_PHASES = SEASON_PHASES, intersection empty.
// These mirror Season::advancesContractYears() (Season.php:197). If that method's phase
// list changes, this file changes with it.
export const SEASON_PHASES: readonly SeasonPhase[] = [
  'Regular Season', 'Playoffs', 'Draft', 'Free Agency', 'Preseason', 'HEAT',
];

export const CONTRACT_YEAR_PHASES: readonly SeasonPhase[] = ['Playoffs', 'Draft', 'Free Agency'];

export const IN_SEASON_PHASES: readonly SeasonPhase[] = ['Regular Season', 'Preseason', 'HEAT'];

// One phase per side of Season::advancesContractYears(). Use for cap/salary/contract specs.
export const CONTRACT_BOUNDARY_PHASES: readonly SeasonPhase[] = ['Regular Season', 'Free Agency'];

/**
 * Minimal structural shape of a Playwright test object carrying `appState`.
 * Method syntax (not arrow properties) gives parameter bivariance, so BOTH
 * `test` (fixtures/auth) and `publicTest` (fixtures/public) satisfy it.
 */
type PhaseTest = {
  describe(title: string, body: () => void): void;
  beforeEach(inner: (args: { appState: SetStateFn }) => Promise<void>): void;
};

export interface WithPhasesOptions {
  /** Test object to build the describes on. Defaults to `test` from fixtures/auth. */
  test?: PhaseTest;
  /** Extra allowlisted settings merged into every beforeEach appState call. */
  extraState?: Record<string, string>;
}

export function withPhases(
  phases: readonly SeasonPhase[],
  fn: (phase: SeasonPhase) => void,
  opts: WithPhasesOptions = {},
): void {
  const t = opts.test ?? (authTest as unknown as PhaseTest);
  const extraState = opts.extraState ?? {};

  for (const phase of phases) {
    t.describe(`phase: ${phase}`, () => {
      t.beforeEach(async ({ appState }) => {
        await appState({
          'Current Season Phase': phase,
          'Current Season Ending Year': '2026',
          ...extraState,
        });
      });
      fn(phase);
    });
  }
}
