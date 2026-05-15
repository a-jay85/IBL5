/**
 * Full-page visual regression matrix.
 *
 * One screenshot per module under `ibl5/modules/` (47 modules) plus the
 * homepage (`index.php`) and one mobile-only repeat of DepthChartEntry at
 * 375×812. All other shots use the desktop 1280×900 viewport from
 * `playwright.visual.config.ts`. Total: 49 baselines.
 *
 * - Public-fixture modules render without authentication.
 * - Auth-fixture modules use the CI test user (admin role, Metros GM).
 * - Each row declares its `anchor` selector (asserted visible before the
 *   screenshot) so a broken module renders an empty page instead of a
 *   silently-passing blank baseline.
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
  { name: 'career-leaderboards', url: 'modules.php?name=CareerLeaderboards', anchor: '#site-content' },
  { name: 'compare-players', url: 'modules.php?name=ComparePlayers', anchor: '#site-content' },
  { name: 'contract-list', url: 'modules.php?name=ContractList', anchor: '#site-content' },
  { name: 'draft-history', url: 'modules.php?name=DraftHistory', anchor: '.ibl-data-table' },
  { name: 'draft-pick-locator', url: 'modules.php?name=DraftPickLocator', anchor: '#site-content' },
  { name: 'franchise-history', url: 'modules.php?name=FranchiseHistory&teamid=1', anchor: '.ibl-data-table' },
  { name: 'franchise-record-book', url: 'modules.php?name=FranchiseRecordBook&teamid=1', anchor: '.ibl-data-table' },
  { name: 'free-agency-preview', url: 'modules.php?name=FreeAgencyPreview', anchor: '#site-content' },
  { name: 'gm-contact-list', url: 'modules.php?name=GMContactList', anchor: '.ibl-data-table' },
  { name: 'injuries', url: 'modules.php?name=Injuries', anchor: '.ibl-data-table' },
  { name: 'league-starters', url: 'modules.php?name=LeagueStarters', anchor: '#site-content' },
  { name: 'news', url: 'modules.php?name=News', anchor: 'article',
    extraMask: ['article time'] },
  { name: 'next-sim', url: 'modules.php?name=NextSim', anchor: '#site-content',
    extraMask: ['time.local-time'] },
  { name: 'player', url: 'modules.php?name=Player&pa=showpage&pid=1', anchor: '.stats-grid' },
  { name: 'player-database', url: 'modules.php?name=PlayerDatabase', anchor: '#site-content' },
  { name: 'player-movement', url: 'modules.php?name=PlayerMovement', anchor: '.ibl-data-table' },
  { name: 'projected-draft-order', url: 'modules.php?name=ProjectedDraftOrder', anchor: '.ibl-data-table' },
  { name: 'record-holders', url: 'modules.php?name=RecordHolders', anchor: '#site-content' },
  { name: 'schedule', url: 'modules.php?name=Schedule', anchor: '.schedule-header',
    extraMask: ['.schedule-today-highlight'] },
  { name: 'search', url: 'modules.php?name=Search', anchor: '#site-content' },
  { name: 'season-archive', url: 'modules.php?name=SeasonArchive', anchor: '.ibl-data-table' },
  { name: 'season-highs', url: 'modules.php?name=SeasonHighs', anchor: '.ibl-data-table' },
  { name: 'season-leaderboards', url: 'modules.php?name=SeasonLeaderboards', anchor: '.ibl-data-table',
    elementScreenshot: true },
  { name: 'series-records', url: 'modules.php?name=SeriesRecords', anchor: '.ibl-data-table' },
{ name: 'standings', url: 'modules.php?name=Standings', anchor: '.ibl-data-table' },
  { name: 'team', url: 'modules.php?name=Team&op=team&teamid=1', anchor: '#site-content' },
  { name: 'team-off-def-stats', url: 'modules.php?name=TeamOffDefStats', anchor: '.ibl-data-table' },
  { name: 'topics', url: 'modules.php?name=Topics', anchor: '#site-content' },
  { name: 'transaction-history', url: 'modules.php?name=TransactionHistory', anchor: '.ibl-data-table',
    extraMask: ['.transaction-row time'] },
  { name: 'voting-results', url: 'modules.php?name=VotingResults', anchor: '#site-content' },
  { name: 'your-account', url: 'modules.php?name=YourAccount', anchor: '#site-content' },
];

const AUTH_MODULES: ModuleSnapshot[] = [
  { name: 'api-keys', url: 'modules.php?name=ApiKeys', anchor: '#site-content' },
  { name: 'cap-space', url: 'modules.php?name=CapSpace&teamid=1', anchor: '.ibl-data-table' },
  { name: 'debug-menu', url: 'modules.php?name=DebugMenu', anchor: '#site-content',
    notes: 'Admin-only diagnostic page; renders link list under CI seed.' },
  { name: 'depth-chart-entry', url: 'modules.php?name=DepthChartEntry', anchor: '#site-content' },
  { name: 'depth-chart-entry-mobile', url: 'modules.php?name=DepthChartEntry',
    anchor: '#site-content', mobile: true },
  { name: 'draft', url: 'modules.php?name=Draft', anchor: '#site-content',
    state: { 'Show Draft Link': 'Yes' },
    notes: 'Outside Draft phase, requires Show Draft Link toggle to render.' },
  { name: 'free-agency', url: 'modules.php?name=FreeAgency', anchor: '#site-content',
    state: { 'Current Season Phase': 'Free Agency' } },
  { name: 'one-on-one-game', url: 'modules.php?name=OneOnOneGame', anchor: '#site-content',
    notes: 'Admin-only game-runner; baseline reflects empty state under CI seed.' },
  { name: 'player-export-guide', url: 'modules.php?name=PlayerExportGuide', anchor: '#site-content',
    notes: 'Admin-only documentation; static content.' },
  { name: 'trading', url: 'modules.php?name=Trading', anchor: '.trading-team-select',
    state: { 'Allow Trades': 'Yes' } },
  { name: 'training-camp-ratings-diff', url: 'modules.php?name=TrainingCampRatingsDiff', anchor: '#site-content',
    notes: 'Admin-only; renders empty state unless ratings snapshot exists.' },
  { name: 'voting', url: 'modules.php?name=Voting', anchor: '#site-content' },
  { name: 'waivers', url: 'modules.php?name=Waivers', anchor: '#site-content' },
];

async function captureSnapshot(page: Page, row: ModuleSnapshot): Promise<void> {
  await page.goto(row.url);
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

  publicTest('no PHP errors on visual regression pages', async ({ page }) => {
    for (const row of PUBLIC_MODULES) {
      await page.goto(row.url);
      await assertNoPhpErrors(page, `on ${row.url}`);
    }
  });
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
      if (row.mobile) {
        await page.setViewportSize({ width: 375, height: 812 });
      }
      if (row.notes) {
        console.log(`[visual-regression] ${row.name}: ${row.notes}`);
      }
      await captureSnapshot(page, row);
    });
  }

  authTest('no PHP errors on visual regression pages', async ({ appState, page }) => {
    await appState({
      'Allow Trades': 'Yes',
      'Current Season Phase': 'Free Agency',
      'Show Draft Link': 'Yes',
    });
    for (const row of AUTH_MODULES) {
      await page.goto(row.url);
      await assertNoPhpErrors(page, `on ${row.url}`);
    }
  });
});
