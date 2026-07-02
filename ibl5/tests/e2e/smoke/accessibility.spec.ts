import { test as publicTest } from '../fixtures/public';
import { test as authTest } from '../fixtures/auth';
import { assertNoA11yViolations, type A11yOptions } from '../helpers/accessibility';

// Site-wide exclusions — explicit a11y debt tracker.
// Each entry should have a comment explaining why it's excluded.
const SITE_WIDE_DISABLED_RULES: string[] = [];

// Per-rule allowlist of pages with known failures — shrinking backlog tracker.
// See ibl5/docs/a11y-backlog.md (non-contrast) and ibl5/docs/a11y-contrast-backlog.md (contrast).
// Each removal from a set = ratchet tightening (CI enforces that page/rule permanently).
// Keys are axe rule ids; values are sets of page names from the spec page lists.
const KNOWN_FAILING: Record<string, Set<string>> = {
  // PHP-Nuke legacy palette debt. See ibl5/docs/a11y-contrast-backlog.md.
  'color-contrast': new Set([
    // Public pages
    'homepage',
    'cap space',
    'player page',
    'activity tracker',
    'all-star appearances',
    'contract list',
    'draft pick locator',
    'franchise history',
    'franchise record book',
    'free agency preview',
    'injuries',
    'one on one game',
    'player movement',
    'projected draft order',
    'record holders',
    'schedule',
    'search',
    'season archive',
    'season highs',
    'series records',
    'team off/def stats',
    'team schedule',
    'topics',
    'news index',
    'news categories',
    'news article',
    // Pages with team-color contrast failures (ibl-team-cell--colored uses DB-configured team colors)
    'league starters',
    // SeasonLeaderboards renders team-color cells via TeamCellHelper; which low-contrast team
    // surfaces in the top-N depends on ORDER BY tie ordering, so the violation appeared
    // intermittently in CI (the dev-seed inventory missed it). Same team-color debt as above.
    'season leaderboards',
    // Auth pages
    'waivers',
    'trading',
    'depth chart entry',
    'gm contact list',
    'draft',
    'next sim',
    // Legacy admin page — PHP-Nuke palette debt. See ibl5/docs/a11y-backlog.md.
    'league control panel',
  ]),

  // No <h1> on page — most module pages use <h2 class="ibl-title">. See ibl5/docs/a11y-backlog.md.
  // Burn-down: a11y-2-heading-one-single-title (single-title views), supervised backlog (rest).
  'page-has-heading-one': new Set([
    // Seeded empirically — see plan a11y-1-ratchet-best-practice
    'season leaderboards',
    'career leaderboards',
    'award history',
    'player database',
    'team page',
    // Auth pages
    'your account',
  ]),

  // Heading-level skip (h4 after h2, no h3). See ibl5/docs/a11y-backlog.md.
  // Burn-down: a11y-2-heading-one-single-title (record holders view, same render pass).
  'heading-order': new Set([
  ]),

  // Links with no discernible text. Remaining entry is the homepage last-sim-recap
  // team links (data-dependent — out of scope; News-template links carry aria-labels).
  // See ibl5/docs/a11y-backlog.md §link-name.
  'link-name': new Set([
    'homepage',
  ]),

  // Touch targets < 24×24px. Seed-dependent for small-count hits. See ibl5/docs/a11y-backlog.md.
  // Empirically clean on the CI seed (axe target-size: 0 violations on topics/homepage/news article) — ratchet tightened.
  'target-size': new Set([
  ]),

  // Multiple landmarks share role+name. See ibl5/docs/a11y-backlog.md §landmark-unique.
  'landmark-unique': new Set([
  ]),

  // No <main> landmark — legacy root page bypasses PageLayout. See ibl5/docs/a11y-backlog.md §landmark-one-main.
  'landmark-one-main': new Set([
    'league control panel',
  ]),

  // Content outside landmark regions — same root-page bypass. See ibl5/docs/a11y-backlog.md §region.
  'region': new Set([
    'league control panel',
  ]),
};

function getA11yOptions(pageName: string): A11yOptions {
  const disableRules = [...SITE_WIDE_DISABLED_RULES];
  for (const [rule, pages] of Object.entries(KNOWN_FAILING)) {
    if (pages.has(pageName)) {
      disableRules.push(rule);
    }
  }
  return { disableRules };
}

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
  { name: 'news categories', url: 'modules.php?name=News&file=categories&op=newindex&catid=15' },
  { name: 'news article', url: 'modules.php?name=News&file=article&sid=1' },
  { name: 'team schedule', url: 'modules.php?name=Schedule&teamid=1' },
];

publicTest.describe('Public page accessibility', () => {
  publicTest.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  for (const { name, url } of publicPages) {
    publicTest(`${name} has no accessibility violations`, async ({ page }) => {
      await page.goto(url);
      await assertNoA11yViolations(page, `on ${url}`, getA11yOptions(name));
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
  { name: 'training camp ratings diff', url: 'modules.php?name=TrainingCampRatingsDiff' },
  {
    name: 'league control panel',
    url: 'leagueControlPanel.php',
    state: { 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' },
  },
];

authTest.describe('Authenticated page accessibility', () => {
  for (const { name, url, state } of authPages) {
    authTest(`${name} has no accessibility violations`, async ({ appState, page }) => {
      if (state) {
        await appState(state);
      }
      await page.goto(url);
      await assertNoA11yViolations(page, `on ${url}`, getA11yOptions(name));
    });
  }
});
