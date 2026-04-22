import { test as publicTest } from '../fixtures/public';
import { test as authTest } from '../fixtures/auth';
import { assertNoA11yViolations, type A11yOptions } from '../helpers/accessibility';

// Site-wide exclusions — explicit a11y debt tracker.
// Each entry should have a comment explaining why it's excluded.
const SITE_WIDE_DISABLED_RULES: string[] = [
  'color-contrast', // PHP-Nuke legacy palette — nearly every page affected
];

const A11Y_OPTIONS: A11yOptions = { disableRules: SITE_WIDE_DISABLED_RULES };

// --- Public pages ---

const publicPages: Array<{ name: string; url: string }> = [
  { name: 'homepage', url: 'index.php' },
  { name: 'standings', url: 'modules.php?name=Standings' },
  { name: 'season leaderboards', url: 'modules.php?name=SeasonLeaderboards' },
  { name: 'career leaderboards', url: 'modules.php?name=CareerLeaderboards' },
  { name: 'draft history', url: 'modules.php?name=DraftHistory' },
  { name: 'cap space', url: 'modules.php?name=CapSpace' },
  { name: 'player page', url: 'modules.php?name=Player&pa=showpage&pid=1' },
  { name: 'team page', url: 'modules.php?name=Team&op=team&teamid=1' },
  { name: 'activity tracker', url: 'modules.php?name=ActivityTracker' },
  { name: 'all-star appearances', url: 'modules.php?name=AllStarAppearances' },
  { name: 'award history', url: 'modules.php?name=AwardHistory' },
  { name: 'contract list', url: 'modules.php?name=ContractList' },
  { name: 'draft pick locator', url: 'modules.php?name=DraftPickLocator' },
  { name: 'franchise history', url: 'modules.php?name=FranchiseHistory' },
  { name: 'franchise record book', url: 'modules.php?name=FranchiseRecordBook' },
  { name: 'free agency preview', url: 'modules.php?name=FreeAgencyPreview' },
  { name: 'injuries', url: 'modules.php?name=Injuries' },
  { name: 'league starters', url: 'modules.php?name=LeagueStarters' },
  { name: 'one on one game', url: 'modules.php?name=OneOnOneGame' },
  { name: 'player database', url: 'modules.php?name=PlayerDatabase' },
  { name: 'player movement', url: 'modules.php?name=PlayerMovement' },
  { name: 'projected draft order', url: 'modules.php?name=ProjectedDraftOrder' },
  { name: 'record holders', url: 'modules.php?name=RecordHolders' },
  { name: 'schedule', url: 'modules.php?name=Schedule' },
  { name: 'season archive', url: 'modules.php?name=SeasonArchive' },
  { name: 'season highs', url: 'modules.php?name=SeasonHighs' },
  { name: 'series records', url: 'modules.php?name=SeriesRecords' },
  { name: 'team off/def stats', url: 'modules.php?name=TeamOffDefStats' },
  { name: 'transaction history', url: 'modules.php?name=TransactionHistory' },
  { name: 'search', url: 'modules.php?name=Search' },
  { name: 'topics', url: 'modules.php?name=Topics' },
  { name: 'voting results', url: 'modules.php?name=VotingResults' },
  { name: 'news index', url: 'modules.php?name=News' },
  { name: 'news categories', url: 'modules.php?name=News&file=categories' },
  { name: 'news article', url: 'modules.php?name=News&file=article&sid=1' },
  { name: 'team schedule', url: 'modules.php?name=Schedule&teamid=1' },
];

publicTest.describe('Public page accessibility', () => {
  publicTest.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  for (const { name, url } of publicPages) {
    publicTest(`${name} has no WCAG 2.1 AA violations`, async ({ page }) => {
      await page.goto(url);
      await assertNoA11yViolations(page, `on ${url}`, A11Y_OPTIONS);
    });
  }
});

// --- Authenticated pages ---

const authPages: Array<{
  name: string;
  url: string;
  state?: Record<string, string>;
}> = [
  { name: 'trading', url: 'modules.php?name=Trading', state: { 'Allow Trades': 'Yes' } },
  {
    name: 'free agency',
    url: 'modules.php?name=FreeAgency',
    state: { 'Current Season Phase': 'Free Agency' },
  },
  { name: 'depth chart entry', url: 'modules.php?name=DepthChartEntry' },
  { name: 'waivers', url: 'modules.php?name=Waivers' },
  { name: 'gm contact list', url: 'modules.php?name=GMContactList' },
  { name: 'compare players', url: 'modules.php?name=ComparePlayers' },
  { name: 'draft', url: 'modules.php?name=Draft', state: { 'Show Draft Link': 'Yes' } },
  { name: 'next sim', url: 'modules.php?name=NextSim' },
  { name: 'your account', url: 'modules.php?name=YourAccount' },
  {
    name: 'voting ASG ballot',
    url: 'modules.php?name=Voting',
    state: { 'Current Season Phase': 'Regular Season', 'ASG Voting': 'Yes' },
  },
  {
    name: 'voting EOY ballot',
    url: 'modules.php?name=Voting',
    state: { 'Current Season Phase': 'Free Agency', 'EOY Voting': 'Yes' },
  },
];

authTest.describe('Authenticated page accessibility', () => {
  for (const { name, url, state } of authPages) {
    authTest(`${name} has no WCAG 2.1 AA violations`, async ({ appState, page }) => {
      if (state) {
        await appState(state);
      }
      await page.goto(url);
      await assertNoA11yViolations(page, `on ${url}`, A11Y_OPTIONS);
    });
  }
});
