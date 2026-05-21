/**
 * Verifies that every visual-regression anchor selector resolves to a visible
 * element on its URL. If a module fails to render its primary content, this
 * test surfaces the broken anchor before the VR screenshot diff can silently
 * pass with a blank baseline.
 */
import { test as publicTest } from '../fixtures/public';
import { test as authTest } from '../fixtures/auth';
import { expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';

type AnchorRow = {
  name: string;
  url: string;
  anchor: string;
  state?: Record<string, string>;
};

const PUBLIC_ANCHORS: AnchorRow[] = [
  { name: 'index', url: 'index.php', anchor: 'article' },
  { name: 'activity-tracker', url: 'modules.php?name=ActivityTracker', anchor: '.ibl-data-table' },
  { name: 'all-star-appearances', url: 'modules.php?name=AllStarAppearances', anchor: '.ibl-data-table' },
  { name: 'award-history', url: 'modules.php?name=AwardHistory', anchor: '.ibl-data-table' },
  { name: 'career-leaderboards', url: 'modules.php?name=CareerLeaderboards', anchor: 'form[name="CareerLeaderboards"]' },
  { name: 'compare-players', url: 'modules.php?name=ComparePlayers', anchor: 'form[action*="ComparePlayers"]' },
  { name: 'contract-list', url: 'modules.php?name=ContractList', anchor: '.totals-row' },
  { name: 'draft-history', url: 'modules.php?name=DraftHistory', anchor: '.ibl-data-table' },
  { name: 'draft-pick-locator', url: 'modules.php?name=DraftPickLocator', anchor: '.draft-pick-locator-container' },
  { name: 'franchise-history', url: 'modules.php?name=FranchiseHistory&teamid=1', anchor: '.ibl-data-table' },
  { name: 'franchise-record-book', url: 'modules.php?name=FranchiseRecordBook&teamid=1', anchor: '.ibl-data-table' },
  { name: 'free-agency-preview', url: 'modules.php?name=FreeAgencyPreview', anchor: 'th.fa-preview-pos-col' },
  { name: 'gm-contact-list', url: 'modules.php?name=GMContactList', anchor: '.ibl-data-table' },
  { name: 'injuries', url: 'modules.php?name=Injuries', anchor: '.ibl-data-table' },
  { name: 'league-starters', url: 'modules.php?name=LeagueStarters', anchor: '#league-starters-tables' },
  { name: 'news', url: 'modules.php?name=News', anchor: 'article' },
  { name: 'player', url: 'modules.php?name=Player&pa=showpage&pid=1', anchor: '.stats-grid' },
  { name: 'player-database', url: 'modules.php?name=PlayerDatabase', anchor: 'form[action*="PlayerDatabase"]' },
  { name: 'player-movement', url: 'modules.php?name=PlayerMovement', anchor: '.ibl-data-table' },
  { name: 'projected-draft-order', url: 'modules.php?name=ProjectedDraftOrder', anchor: '.ibl-data-table' },
  { name: 'record-holders', url: 'modules.php?name=RecordHolders', anchor: '.record-section' },
  { name: 'schedule', url: 'modules.php?name=Schedule', anchor: '.schedule-header' },
  { name: 'search', url: 'modules.php?name=Search', anchor: '.search-page' },
  { name: 'season-archive', url: 'modules.php?name=SeasonArchive', anchor: '.ibl-data-table' },
  { name: 'season-highs', url: 'modules.php?name=SeasonHighs', anchor: '.ibl-data-table' },
  { name: 'season-leaderboards', url: 'modules.php?name=SeasonLeaderboards', anchor: '.ibl-data-table' },
  { name: 'series-records', url: 'modules.php?name=SeriesRecords', anchor: '.ibl-data-table' },
  { name: 'standings', url: 'modules.php?name=Standings', anchor: '.ibl-data-table' },
  { name: 'team', url: 'modules.php?name=Team&op=team&teamid=1', anchor: '.team-page-layout' },
  { name: 'team-off-def-stats', url: 'modules.php?name=TeamOffDefStats', anchor: '.ibl-data-table' },
  { name: 'topics', url: 'modules.php?name=Topics', anchor: '.topics-page' },
  { name: 'transaction-history', url: 'modules.php?name=TransactionHistory', anchor: '.ibl-data-table' },
  { name: 'voting-results', url: 'modules.php?name=VotingResults', anchor: 'table.voting-results-table' },
  { name: 'your-account', url: 'modules.php?name=YourAccount', anchor: '.auth-page' },
];

const AUTH_ANCHORS: AnchorRow[] = [
  { name: 'api-keys', url: 'modules.php?name=ApiKeys', anchor: 'form[action*="ApiKeys"]' },
  { name: 'cap-space', url: 'modules.php?name=CapSpace&teamid=1', anchor: '.ibl-data-table' },
  { name: 'depth-chart-entry', url: 'modules.php?name=DepthChartEntry', anchor: 'form[name="DepthChartEntry"]' },
  { name: 'draft', url: 'modules.php?name=Draft', anchor: '.draft-container',
    state: { 'Show Draft Link': 'Yes' } },
  { name: 'free-agency', url: 'modules.php?name=FreeAgency', anchor: '.fa-table',
    state: { 'Current Season Phase': 'Free Agency' } },
  { name: 'next-sim', url: 'modules.php?name=NextSim', anchor: '.next-sim-container' },
  { name: 'one-on-one-game', url: 'modules.php?name=OneOnOneGame', anchor: 'form[name="OneOnOneGame"]' },
  { name: 'player-export-guide', url: 'modules.php?name=PlayerExportGuide', anchor: '.ibl-code-block' },
  { name: 'trading', url: 'modules.php?name=Trading', anchor: '.trading-team-select',
    state: { 'Allow Trades': 'Yes' } },
  { name: 'training-camp-ratings-diff', url: 'modules.php?name=TrainingCampRatingsDiff', anchor: '.ratings-diff-page' },
  { name: 'voting', url: 'modules.php?name=Voting', anchor: '.voting-form-container' },
  { name: 'waivers', url: 'modules.php?name=Waivers', anchor: '.waivers-page' },
];

publicTest.describe('VR anchor discrimination — public pages', () => {
  publicTest.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  for (const row of PUBLIC_ANCHORS) {
    publicTest(`${row.name} anchor resolves`, async ({ appState, page }) => {
      if (row.state) {
        await appState(row.state);
      }
      await page.goto(row.url);
      await assertNoPhpErrors(page, `on ${row.url}`);
      await expect(page.locator(row.anchor).first()).toBeVisible();
    });
  }
});

authTest.describe('VR anchor discrimination — authenticated pages', () => {
  for (const row of AUTH_ANCHORS) {
    authTest(`${row.name} anchor resolves`, async ({ appState, page }) => {
      if (row.state) {
        await appState(row.state);
      }
      await page.goto(row.url);
      await assertNoPhpErrors(page, `on ${row.url}`);
      await expect(page.locator(row.anchor).first()).toBeVisible();
    });
  }
});
