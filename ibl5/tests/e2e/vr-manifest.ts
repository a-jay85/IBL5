// TODO: migrate vr-anchors-discriminate.spec.ts to consume this manifest to prevent drift.

export type AuthMode = 'public' | 'auth' | 'auth-regular';
export type Viewport = 'desktop' | 'mobile';

export type StateVariant = {
  name: string;
  appState: Record<string, string>;
};

export type HtmxTab = {
  key: string;
  trigger: string;
  swapTarget: string;
};

export type VrRow = {
  name: string;
  auth: AuthMode;
  url: string;
  anchor: string;
  viewports?: Viewport[];
  states?: StateVariant[];
  htmxTabs?: HtmxTab[];
  extraMask?: string[];
  extraMaxDiffPixelRatio?: number;
  elementScreenshot?: boolean;
  dataDriven?: boolean;
  notes?: string;
};

const DEFAULT_STATE: StateVariant = { name: 'default', appState: {} };

export const VR_MANIFEST: VrRow[] = [
  // ── Public modules ──────────────────────────────────────────
  { name: 'index', auth: 'public', url: 'index.php', anchor: 'article',
    extraMask: ['div.news-article__body'] },
  { name: 'activity-tracker', auth: 'public', url: 'modules.php?name=ActivityTracker',
    anchor: '.ibl-data-table', extraMask: ['.activity-row time'],
    viewports: ['desktop', 'mobile'] },
  { name: 'all-star-appearances', auth: 'public', url: 'modules.php?name=AllStarAppearances',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'award-history', auth: 'public', url: 'modules.php?name=AwardHistory',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'career-leaderboards', auth: 'public', url: 'modules.php?name=CareerLeaderboards',
    anchor: 'form[name="CareerLeaderboards"]', viewports: ['desktop', 'mobile'] },
  { name: 'compare-players', auth: 'public', url: 'modules.php?name=ComparePlayers',
    anchor: 'form[action*="ComparePlayers"]', viewports: ['desktop', 'mobile'] },
  { name: 'contract-list', auth: 'public', url: 'modules.php?name=ContractList',
    anchor: '.totals-row', viewports: ['desktop', 'mobile'] },
  { name: 'draft-history', auth: 'public', url: 'modules.php?name=DraftHistory',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'draft-pick-locator', auth: 'public', url: 'modules.php?name=DraftPickLocator',
    anchor: '.draft-pick-locator-container', viewports: ['desktop', 'mobile'] },
  { name: 'franchise-history', auth: 'public', url: 'modules.php?name=FranchiseHistory&teamid=1',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'franchise-record-book', auth: 'public', url: 'modules.php?name=FranchiseRecordBook&teamid=1',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'free-agency-preview', auth: 'public', url: 'modules.php?name=FreeAgencyPreview',
    anchor: 'th.fa-preview-pos-col', viewports: ['desktop', 'mobile'] },
  { name: 'gm-contact-list', auth: 'public', url: 'modules.php?name=GMContactList',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'injuries', auth: 'public', url: 'modules.php?name=Injuries',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'league-starters', auth: 'public', url: 'modules.php?name=LeagueStarters',
    anchor: '#league-starters-tables', viewports: ['desktop', 'mobile'],
    htmxTabs: [
      { key: 'total-s', trigger: 'a.ibl-tab[data-display="total_s"]', swapTarget: '#league-starters-tables' },
      { key: 'avg-s', trigger: 'a.ibl-tab[data-display="avg_s"]', swapTarget: '#league-starters-tables' },
      { key: 'per36mins', trigger: 'a.ibl-tab[data-display="per36mins"]', swapTarget: '#league-starters-tables' },
    ] },
  { name: 'news', auth: 'public', url: 'modules.php?name=News', anchor: 'article',
    extraMask: ['article time'], viewports: ['desktop', 'mobile'] },
  { name: 'player', auth: 'public', url: 'modules.php?name=Player&pa=showpage&pid=1',
    anchor: '.stats-grid', viewports: ['desktop', 'mobile'] },
  { name: 'player-database', auth: 'public', url: 'modules.php?name=PlayerDatabase',
    anchor: 'form[action*="PlayerDatabase"]', viewports: ['desktop', 'mobile'] },
  { name: 'player-movement', auth: 'public', url: 'modules.php?name=PlayerMovement',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'],
    states: [
      { name: 'default', appState: {} },
      { name: 'empty', appState: { 'Current Season Ending Year': '1900' } },
    ],
    dataDriven: true,
    notes: 'empty state forces previousSeasonEndingYear=1899 → zero rows; locks empty-state render.' },
  { name: 'projected-draft-order', auth: 'public', url: 'modules.php?name=ProjectedDraftOrder',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'record-holders', auth: 'public', url: 'modules.php?name=RecordHolders',
    anchor: '.record-section', viewports: ['desktop', 'mobile'] },
  { name: 'schedule', auth: 'public', url: 'modules.php?name=Schedule',
    anchor: '.schedule-header', extraMask: ['.schedule-today-highlight'],
    viewports: ['desktop', 'mobile'] },
  { name: 'search', auth: 'public', url: 'modules.php?name=Search',
    anchor: '.search-page', viewports: ['desktop', 'mobile'] },
  { name: 'season-archive', auth: 'public', url: 'modules.php?name=SeasonArchive',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'season-highs', auth: 'public', url: 'modules.php?name=SeasonHighs',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'season-leaderboards', auth: 'public', url: 'modules.php?name=SeasonLeaderboards',
    anchor: '.ibl-data-table', elementScreenshot: true, viewports: ['desktop', 'mobile'] },
  { name: 'series-records', auth: 'public', url: 'modules.php?name=SeriesRecords',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'standings', auth: 'public', url: 'modules.php?name=Standings',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'team', auth: 'public', url: 'modules.php?name=Team&op=team&teamid=1',
    anchor: '.team-page-layout', viewports: ['desktop', 'mobile'],
    htmxTabs: [
      { key: 'total-s', trigger: 'a.ibl-tab[data-display="total_s"]', swapTarget: '.table-scroll-container' },
      { key: 'avg-s', trigger: 'a.ibl-tab[data-display="avg_s"]', swapTarget: '.table-scroll-container' },
      { key: 'per36mins', trigger: 'a.ibl-tab[data-display="per36mins"]', swapTarget: '.table-scroll-container' },
      { key: 'chunk', trigger: 'a.ibl-tab[data-display="chunk"]', swapTarget: '.table-scroll-container' },
      { key: 'contracts', trigger: 'a.ibl-tab[data-display="contracts"]', swapTarget: '.table-scroll-container' },
    ] },
  { name: 'team-off-def-stats', auth: 'public', url: 'modules.php?name=TeamOffDefStats',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'topics', auth: 'public', url: 'modules.php?name=Topics',
    anchor: '.topics-page', viewports: ['desktop', 'mobile'] },
  { name: 'transaction-history', auth: 'public', url: 'modules.php?name=TransactionHistory',
    anchor: '.ibl-data-table', extraMask: ['.transaction-row time'],
    viewports: ['desktop', 'mobile'] },
  { name: 'voting-results', auth: 'public', url: 'modules.php?name=VotingResults',
    anchor: 'table.voting-results-table' },
  { name: 'your-account', auth: 'public', url: 'modules.php?name=YourAccount',
    anchor: '.auth-page', viewports: ['desktop', 'mobile'] },

  // ── Error pages ──────────────────────────────────────────────
  { name: 'error-invalid-module', auth: 'public', url: 'modules.php?name=NonExistentModule',
    anchor: 'article',
    notes: 'Invalid module name redirects to index.php; captures post-redirect homepage render.' },
  { name: 'error-nonexistent-file', auth: 'public',
    url: 'modules.php?name=Standings&file=nonexistent', anchor: 'body',
    notes: 'Nonexistent file renders error message within page layout.' },

  // ── Auth modules ────────────────────────────────────────────
  { name: 'api-keys', auth: 'auth', url: 'modules.php?name=ApiKeys',
    anchor: 'form[action*="ApiKeys"]', viewports: ['desktop', 'mobile'] },
  { name: 'cap-space', auth: 'auth', url: 'modules.php?name=CapSpace&teamid=1',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'depth-chart-entry', auth: 'auth', url: 'modules.php?name=DepthChartEntry',
    anchor: 'form[name="DepthChartEntry"]', viewports: ['desktop', 'mobile'] },
  { name: 'draft', auth: 'auth', url: 'modules.php?name=Draft',
    anchor: '.draft-container',
    states: [
      { name: 'default', appState: { 'Show Draft Link': 'Yes' } },
      { name: 'in-phase', appState: { 'Current Season Phase': 'Draft' } },
    ],
    viewports: ['desktop', 'mobile'],
    dataDriven: true,
    notes: 'Default state requires Show Draft Link toggle; in-phase renders natively without it.' },
  { name: 'free-agency', auth: 'auth', url: 'modules.php?name=FreeAgency',
    anchor: '.fa-table',
    states: [
      { name: 'default', appState: { 'Current Season Phase': 'Free Agency' } },
      { name: 'out-of-phase', appState: { 'Current Season Phase': 'Regular Season' } },
    ],
    viewports: ['desktop', 'mobile'],
    dataDriven: true },
  { name: 'next-sim', auth: 'auth', url: 'modules.php?name=NextSim',
    anchor: '.next-sim-container', extraMask: ['time.local-time'],
    viewports: ['desktop', 'mobile'],
    htmxTabs: [
      { key: 'SG', trigger: 'a.ibl-tab[data-display="SG"]', swapTarget: '.nextsim-tab-container' },
      { key: 'SF', trigger: 'a.ibl-tab[data-display="SF"]', swapTarget: '.nextsim-tab-container' },
      { key: 'PF', trigger: 'a.ibl-tab[data-display="PF"]', swapTarget: '.nextsim-tab-container' },
      { key: 'C', trigger: 'a.ibl-tab[data-display="C"]', swapTarget: '.nextsim-tab-container' },
    ] },
  { name: 'one-on-one-game', auth: 'auth', url: 'modules.php?name=OneOnOneGame',
    anchor: 'form[name="OneOnOneGame"]', viewports: ['desktop', 'mobile'],
    notes: 'Admin-only game-runner; baseline reflects empty state under CI seed.' },
  { name: 'player-export-guide', auth: 'auth', url: 'modules.php?name=PlayerExportGuide',
    anchor: '.ibl-code-block',
    notes: 'Admin-only documentation; static content.' },
  { name: 'trading', auth: 'auth', url: 'modules.php?name=Trading',
    anchor: '.trading-team-select',
    states: [{ name: 'default', appState: { 'Allow Trades': 'Yes' } }],
    viewports: ['desktop', 'mobile'] },
  { name: 'training-camp-ratings-diff', auth: 'auth', url: 'modules.php?name=TrainingCampRatingsDiff',
    anchor: '.ratings-diff-page', viewports: ['desktop', 'mobile'],
    notes: 'Admin-only; renders empty state unless ratings snapshot exists.' },
  { name: 'voting', auth: 'auth', url: 'modules.php?name=Voting',
    anchor: '.voting-form-container', viewports: ['desktop', 'mobile'],
    states: [
      { name: 'default', appState: {} },
      { name: 'eoy-playoffs', appState: { 'Current Season Phase': 'Playoffs', 'EOY Voting': 'On' } },
      { name: 'eoy-draft', appState: { 'Current Season Phase': 'Draft', 'EOY Voting': 'On' } },
      { name: 'eoy-fa', appState: { 'Current Season Phase': 'Free Agency', 'EOY Voting': 'On' } },
    ],
    dataDriven: true },
  { name: 'waivers', auth: 'auth', url: 'modules.php?name=Waivers',
    anchor: '.waivers-page', viewports: ['desktop', 'mobile'],
    states: [
      { name: 'default', appState: {} },
      { name: 'empty', appState: { 'Allow Waiver Moves': 'No' } },
    ],
    dataDriven: true },

  // ── Auth-regular modules ────────────────────────────────────
  { name: 'team-non-admin', auth: 'auth-regular', url: 'modules.php?name=Team&op=team&teamid=1',
    anchor: '.team-page-layout',
    notes: 'Authenticated non-admin viewing another team — exercises nav/personalization deltas vs. public baseline.' },
  { name: 'next-sim-non-admin', auth: 'auth-regular', url: 'modules.php?name=NextSim',
    anchor: '.next-sim-container',
    notes: 'Free-Agents fallback team path. If PHP errors, assertNoPhpErrors fails loudly in first CI run.' },
];

export function snapshotFilename(
  row: VrRow,
  state: StateVariant,
  viewport: Viewport,
  tab?: HtmxTab,
): string {
  const parts: string[] = [row.name];
  if (state.name !== 'default') parts.push(state.name);
  if (tab) parts.push('tab', tab.key);
  if (viewport === 'mobile') parts.push('mobile');
  return parts.join('-') + '.png';
}

export { DEFAULT_STATE };
