/**
 * Full-page visual regression matrix.
 *
 * One screenshot per module under `ibl5/modules/` (47 modules) plus the
 * homepage (`index.php`) and thirteen mobile-only repeats at 375×812 (Standings,
 * Player, Team, Schedule, FreeAgency, Trading, DepthChartEntry, News, Search,
 * Draft, Voting, ProjectedDraftOrder, DraftHistory). All other shots use the
 * desktop 1280×900 viewport from `playwright.visual.config.ts`.
 * Total: 59 baselines.
 *
 * - Public-fixture modules render without authentication.
 * - Auth-fixture modules use the CI test user (admin role, Metros GM).
 * - Each row anchors on a content-specific selector inside the module's
 *   render block; if the module fails to render its primary content,
 *   `anchor.waitFor()` times out before any screenshot diff runs.
 * - `GLOBAL_MASK_SELECTORS` covers known volatile regions (timestamps,
 *   live-time text); per-row `extraMask` adds module-specific noise areas.
 * - Per-row `extraMaxDiffPixelRatio` overrides the global 0.005 threshold
 *   where structural rendering noise demands it.
 *
 * The 13 element-crop baselines from the previous spec are intentionally
 * dropped: full-page coverage subsumes them. CSS regressions previously
 * limited to one element are now caught wherever the element appears.
 */
import type { Locator, Page } from '@playwright/test';
import { test as publicTest } from '../fixtures/public';
import { test as authTest } from '../fixtures/auth';
import { expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';

type ModuleSnapshot = {
  /** Slug used in the test title and snapshot filename. */
  name: string;
  /** Path under `BASE_URL`, e.g. `modules.php?name=Standings`. */
  url: string;
  /** Selector that must be visible before the screenshot is captured. */
  anchor: string;
  /** Optional cookie-state overrides (forwarded to `appState`). */
  state?: Record<string, string>;
  /** Module-specific masks layered on top of `GLOBAL_MASK_SELECTORS`. */
  extraMask?: string[];
  /** Per-row override for `maxDiffPixelRatio`. */
  extraMaxDiffPixelRatio?: number;
  /** Screenshot the anchor element instead of the full page. */
  elementScreenshot?: boolean;
  /** Mobile viewport (375×812) instead of desktop 1280×900. */
  mobile?: boolean;
  /**
   * Optional comment for future readers: e.g. "renders empty under CI seed —
   * any non-blank diff is real." Surfaces in `console.log` during runs.
   */
  notes?: string;
};

/**
 * Selectors masked on every screenshot. Volatile regions that would otherwise
 * cause flake from clock drift, request-count changes, or post-render JS
 * rewrites.
 *
 * `time.local-time` — `jslib/local-time.js` rewrites text post-DOMContentLoaded.
 * `.news-article__meta` — News article timestamps and read counters.
 * `[data-volatile="timestamp"]` — convention for opt-in masking of future
 *    volatile markup; harmless if no element carries the attribute.
 */
const GLOBAL_MASK_SELECTORS: string[] = [
  'time.local-time',
  '.news-article__meta',
  '[data-volatile="timestamp"]',
];

function buildMasks(page: Page, extraMask: string[] = []): Locator[] {
  return [...GLOBAL_MASK_SELECTORS, ...extraMask].map((sel) => page.locator(sel));
}

const PUBLIC_MODULES: ModuleSnapshot[] = [
  { name: 'index', url: 'index.php', anchor: 'article' },
  { name: 'activity-tracker', url: 'modules.php?name=ActivityTracker', anchor: '.ibl-data-table',
    extraMask: ['.activity-row time'] },
  { name: 'all-star-appearances', url: 'modules.php?name=AllStarAppearances', anchor: '.ibl-data-table' },
  { name: 'award-history', url: 'modules.php?name=AwardHistory', anchor: '.ibl-data-table' },
  { name: 'career-leaderboards', url: 'modules.php?name=CareerLeaderboards', anchor: 'form[name="CareerLeaderboards"]' },
  { name: 'compare-players', url: 'modules.php?name=ComparePlayers', anchor: 'form[action*="ComparePlayers"]' },
  { name: 'contract-list', url: 'modules.php?name=ContractList', anchor: '.totals-row' },
  { name: 'draft-history', url: 'modules.php?name=DraftHistory', anchor: '.ibl-data-table' },
  { name: 'draft-history-mobile', url: 'modules.php?name=DraftHistory',
    anchor: '.ibl-data-table', mobile: true },
  { name: 'draft-pick-locator', url: 'modules.php?name=DraftPickLocator', anchor: '.draft-pick-locator-container' },
  { name: 'franchise-history', url: 'modules.php?name=FranchiseHistory&teamid=1', anchor: '.ibl-data-table' },
  { name: 'franchise-record-book', url: 'modules.php?name=FranchiseRecordBook&teamid=1', anchor: '.ibl-data-table' },
  { name: 'free-agency-preview', url: 'modules.php?name=FreeAgencyPreview', anchor: 'th.fa-preview-pos-col' },
  { name: 'gm-contact-list', url: 'modules.php?name=GMContactList', anchor: '.ibl-data-table' },
  { name: 'injuries', url: 'modules.php?name=Injuries', anchor: '.ibl-data-table' },
  { name: 'league-starters', url: 'modules.php?name=LeagueStarters', anchor: '#league-starters-tables' },
  { name: 'news', url: 'modules.php?name=News', anchor: 'article',
    extraMask: ['article time'] },
  { name: 'news-mobile', url: 'modules.php?name=News', anchor: 'article',
    extraMask: ['article time'], mobile: true },
  { name: 'player', url: 'modules.php?name=Player&pa=showpage&pid=1', anchor: '.stats-grid' },
  { name: 'player-mobile', url: 'modules.php?name=Player&pa=showpage&pid=1', anchor: '.stats-grid', mobile: true },
  { name: 'player-database', url: 'modules.php?name=PlayerDatabase', anchor: 'form[action*="PlayerDatabase"]' },
  { name: 'player-movement', url: 'modules.php?name=PlayerMovement', anchor: '.ibl-data-table' },
  { name: 'projected-draft-order', url: 'modules.php?name=ProjectedDraftOrder', anchor: '.ibl-data-table' },
  { name: 'projected-draft-order-mobile', url: 'modules.php?name=ProjectedDraftOrder',
    anchor: '.ibl-data-table', mobile: true },
  { name: 'record-holders', url: 'modules.php?name=RecordHolders', anchor: '.record-section' },
  { name: 'schedule', url: 'modules.php?name=Schedule', anchor: '.schedule-header',
    extraMask: ['.schedule-today-highlight'] },
  { name: 'league-schedule-mobile', url: 'modules.php?name=Schedule', anchor: '.schedule-header',
    extraMask: ['.schedule-today-highlight'], mobile: true },
  { name: 'search', url: 'modules.php?name=Search', anchor: '.search-page' },
  { name: 'search-mobile', url: 'modules.php?name=Search', anchor: '.search-page', mobile: true },
  { name: 'season-archive', url: 'modules.php?name=SeasonArchive', anchor: '.ibl-data-table' },
  { name: 'season-highs', url: 'modules.php?name=SeasonHighs', anchor: '.ibl-data-table' },
  { name: 'season-leaderboards', url: 'modules.php?name=SeasonLeaderboards', anchor: '.ibl-data-table',
    elementScreenshot: true },
  { name: 'series-records', url: 'modules.php?name=SeriesRecords', anchor: '.ibl-data-table' },
  { name: 'standings', url: 'modules.php?name=Standings', anchor: '.ibl-data-table' },
  { name: 'standings-mobile', url: 'modules.php?name=Standings', anchor: '.ibl-data-table', mobile: true },
  { name: 'team', url: 'modules.php?name=Team&op=team&teamid=1', anchor: '.team-page-layout' },
  { name: 'team-mobile', url: 'modules.php?name=Team&op=team&teamid=1', anchor: '.team-page-layout', mobile: true },
  { name: 'team-off-def-stats', url: 'modules.php?name=TeamOffDefStats', anchor: '.ibl-data-table' },
  { name: 'topics', url: 'modules.php?name=Topics', anchor: '.topics-page' },
  { name: 'transaction-history', url: 'modules.php?name=TransactionHistory', anchor: '.ibl-data-table',
    extraMask: ['.transaction-row time'] },
  { name: 'voting-results', url: 'modules.php?name=VotingResults', anchor: 'table.voting-results-table' },
  { name: 'your-account', url: 'modules.php?name=YourAccount', anchor: '.auth-page' },
];

const AUTH_MODULES: ModuleSnapshot[] = [
  { name: 'api-keys', url: 'modules.php?name=ApiKeys', anchor: 'form[action*="ApiKeys"]' },
  { name: 'cap-space', url: 'modules.php?name=CapSpace&teamid=1', anchor: '.ibl-data-table' },
  { name: 'depth-chart-entry', url: 'modules.php?name=DepthChartEntry', anchor: 'form[name="DepthChartEntry"]' },
  { name: 'depth-chart-entry-mobile', url: 'modules.php?name=DepthChartEntry',
    anchor: 'form[name="DepthChartEntry"]', mobile: true },
  { name: 'draft', url: 'modules.php?name=Draft', anchor: '.draft-container',
    state: { 'Show Draft Link': 'Yes' },
    notes: 'Outside Draft phase, requires Show Draft Link toggle to render.' },
  { name: 'draft-mobile', url: 'modules.php?name=Draft', anchor: '.draft-container',
    state: { 'Show Draft Link': 'Yes' }, mobile: true,
    notes: 'Outside Draft phase, requires Show Draft Link toggle to render.' },
  { name: 'free-agency', url: 'modules.php?name=FreeAgency', anchor: '.fa-table',
    state: { 'Current Season Phase': 'Free Agency' } },
  { name: 'free-agency-mobile', url: 'modules.php?name=FreeAgency', anchor: '.fa-table',
    state: { 'Current Season Phase': 'Free Agency' }, mobile: true },
  { name: 'next-sim', url: 'modules.php?name=NextSim', anchor: '.next-sim-container',
    extraMask: ['time.local-time'] },
  { name: 'one-on-one-game', url: 'modules.php?name=OneOnOneGame', anchor: 'form[name="OneOnOneGame"]',
    notes: 'Admin-only game-runner; baseline reflects empty state under CI seed.' },
  { name: 'player-export-guide', url: 'modules.php?name=PlayerExportGuide', anchor: '.ibl-code-block',
    notes: 'Admin-only documentation; static content.' },
  { name: 'trading', url: 'modules.php?name=Trading', anchor: '.trading-team-select',
    state: { 'Allow Trades': 'Yes' } },
  { name: 'trading-mobile', url: 'modules.php?name=Trading', anchor: '.trading-team-select',
    state: { 'Allow Trades': 'Yes' }, mobile: true },
  { name: 'training-camp-ratings-diff', url: 'modules.php?name=TrainingCampRatingsDiff', anchor: '.ratings-diff-page',
    notes: 'Admin-only; renders empty state unless ratings snapshot exists.' },
  { name: 'voting', url: 'modules.php?name=Voting', anchor: '.voting-form-container' },
  { name: 'voting-mobile', url: 'modules.php?name=Voting',
    anchor: '.voting-form-container', mobile: true },
  { name: 'waivers', url: 'modules.php?name=Waivers', anchor: '.waivers-page' },
];

async function captureSnapshot(page: Page, row: ModuleSnapshot): Promise<void> {
  if (row.mobile) {
    await page.setViewportSize({ width: 375, height: 812 });
  }
  await page.goto(row.url);
  await assertNoPhpErrors(page, `on ${row.url}`);
  await page.waitForLoadState('networkidle');
  const anchor = page.locator(row.anchor).first();
  await anchor.waitFor({ state: 'visible' });
  const screenshotOpts = {
    animations: 'disabled' as const,
    mask: buildMasks(page, row.extraMask),
    ...(row.extraMaxDiffPixelRatio !== undefined
      ? { maxDiffPixelRatio: row.extraMaxDiffPixelRatio }
      : {}),
  };
  if (row.elementScreenshot) {
    await expect(anchor).toHaveScreenshot(`${row.name}.png`, screenshotOpts);
  } else {
    await expect(page).toHaveScreenshot(`${row.name}.png`, {
      fullPage: true,
      ...screenshotOpts,
    });
  }
}

// ============================================================
// Public visual regression — no authentication required
// ============================================================

publicTest.describe('Visual regression — public pages (full-page)', () => {
  publicTest.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  for (const row of PUBLIC_MODULES) {
    publicTest(`${row.name}`, async ({ appState, page }) => {
      if (row.state) {
        await appState(row.state);
      }
      if (row.notes) {
        console.log(`[visual-regression] ${row.name}: ${row.notes}`);
      }
      await captureSnapshot(page, row);
    });
  }

});

// ============================================================
// Authenticated visual regression — requires test user
// ============================================================

authTest.describe('Visual regression — authenticated pages (full-page)', () => {
  for (const row of AUTH_MODULES) {
    authTest(`${row.name}`, async ({ appState, page }) => {
      if (row.state) {
        await appState(row.state);
      }
      if (row.notes) {
        console.log(`[visual-regression] ${row.name}: ${row.notes}`);
      }
      await captureSnapshot(page, row);
    });
  }

});
