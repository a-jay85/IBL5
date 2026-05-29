import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';

// Public pages — no authentication required.
// Uses the public fixture with appState for automatic state restore.
//
// Consolidated from the former public-pages.spec.ts + public-pages-extended.spec.ts.
// Every page is one PAGES row (`goto + assertNoPhpErrors + anchor-visible
// (+ optional rowCount)`). The `>= 28` team-row counts and `>= 1` row checks are
// the only assertions the manifest-driven VR gate does not already cover, so they
// are preserved here. The homepage title check is special-cased below.

type PageRow = {
  name: string;
  url: string;
  anchor: string;
  rowCount?: { selector: string; minimum: number };
};

const PAGES: PageRow[] = [
  // ── From the former base public-pages.spec.ts ──
  { name: 'standings', url: 'modules.php?name=Standings', anchor: '.ibl-data-table',
    rowCount: { selector: 'tr[data-team-id]', minimum: 28 } },
  { name: 'player', url: 'modules.php?name=Player&pa=showpage&pid=1', anchor: '.stats-grid' },
  { name: 'team', url: 'modules.php?name=Team&op=team&teamid=1', anchor: '.team-page-layout' },
  { name: 'season leaderboards', url: 'modules.php?name=SeasonLeaderboards', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
  { name: 'career leaderboards', url: 'modules.php?name=CareerLeaderboards',
    anchor: 'form[name="CareerLeaderboards"]' },
  { name: 'draft history', url: 'modules.php?name=DraftHistory', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
  { name: 'cap space', url: 'modules.php?name=CapSpace', anchor: 'tr[data-team-id]',
    rowCount: { selector: 'tr[data-team-id]', minimum: 28 } },
  { name: 'topics', url: 'modules.php?name=Topics', anchor: '.topics-page' },

  // ── From the former public-pages-extended.spec.ts ──
  { name: 'schedule', url: 'modules.php?name=Schedule', anchor: '.schedule-header' },
  { name: 'injuries', url: 'modules.php?name=Injuries', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
  { name: 'player database', url: 'modules.php?name=PlayerDatabase', anchor: 'form[action*="PlayerDatabase"]' },
  { name: 'projected draft order', url: 'modules.php?name=ProjectedDraftOrder', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
  { name: 'draft pick locator', url: 'modules.php?name=DraftPickLocator', anchor: '.draft-pick-locator-container' },
  { name: 'free agency preview', url: 'modules.php?name=FreeAgencyPreview', anchor: 'th.fa-preview-pos-col' },
  { name: 'contract list', url: 'modules.php?name=ContractList', anchor: '.totals-row' },
  { name: 'player movement', url: 'modules.php?name=PlayerMovement', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
  { name: 'league starters', url: 'modules.php?name=LeagueStarters', anchor: '#league-starters-tables' },
  { name: 'compare players', url: 'modules.php?name=ComparePlayers', anchor: 'form[action*="ComparePlayers"]' },
  { name: 'season highs', url: 'modules.php?name=SeasonHighs', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
  { name: 'series records', url: 'modules.php?name=SeriesRecords', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
  { name: 'franchise history', url: 'modules.php?name=FranchiseHistory', anchor: '.ibl-data-table',
    rowCount: { selector: 'tr[data-team-id]', minimum: 28 } },
  { name: 'activity tracker', url: 'modules.php?name=ActivityTracker', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
];

test.describe('Public page smoke tests', () => {
  // Ensure trivia mode is off so modules render normally.
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  test('homepage loads', async ({ page }) => {
    await page.goto('index.php');
    await assertNoPhpErrors(page, 'on index.php');
    await expect(page).toHaveTitle(/IBL/i);
  });

  for (const { name, url, anchor, rowCount } of PAGES) {
    test(`${name} loads`, async ({ page }) => {
      test.setTimeout(60_000);
      await gotoWithRetry(page, url);
      await assertNoPhpErrors(page, `on ${url}`);
      await expect(page.locator(anchor).first()).toBeVisible();
      if (rowCount) {
        const rows = page.locator(rowCount.selector);
        if (rowCount.minimum === 1) {
          await expect(rows.first()).toBeVisible();
        } else {
          const count = await rows.count();
          expect(count, `${name}: expected >= ${rowCount.minimum} rows`).toBeGreaterThanOrEqual(rowCount.minimum);
        }
      }
    });
  }
});
