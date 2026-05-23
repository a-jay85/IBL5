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
  { name: 'index', auth: 'public', url: 'index.php', anchor: 'article' },
  { name: 'activity-tracker', auth: 'public', url: 'modules.php?name=ActivityTracker',
    anchor: '.ibl-data-table', extraMask: ['.activity-row time'] },
  { name: 'all-star-appearances', auth: 'public', url: 'modules.php?name=AllStarAppearances',
    anchor: '.ibl-data-table' },
  { name: 'award-history', auth: 'public', url: 'modules.php?name=AwardHistory',
    anchor: '.ibl-data-table' },
  { name: 'career-leaderboards', auth: 'public', url: 'modules.php?name=CareerLeaderboards',
    anchor: 'form[name="CareerLeaderboards"]' },
  { name: 'compare-players', auth: 'public', url: 'modules.php?name=ComparePlayers',
    anchor: 'form[action*="ComparePlayers"]' },
  { name: 'contract-list', auth: 'public', url: 'modules.php?name=ContractList',
    anchor: '.totals-row' },
  { name: 'draft-history', auth: 'public', url: 'modules.php?name=DraftHistory',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'draft-pick-locator', auth: 'public', url: 'modules.php?name=DraftPickLocator',
    anchor: '.draft-pick-locator-container' },
  { name: 'franchise-history', auth: 'public', url: 'modules.php?name=FranchiseHistory&teamid=1',
    anchor: '.ibl-data-table' },
  { name: 'franchise-record-book', auth: 'public', url: 'modules.php?name=FranchiseRecordBook&teamid=1',
    anchor: '.ibl-data-table' },
  { name: 'free-agency-preview', auth: 'public', url: 'modules.php?name=FreeAgencyPreview',
    anchor: 'th.fa-preview-pos-col' },
  { name: 'gm-contact-list', auth: 'public', url: 'modules.php?name=GMContactList',
    anchor: '.ibl-data-table' },
  { name: 'injuries', auth: 'public', url: 'modules.php?name=Injuries',
    anchor: '.ibl-data-table' },
  { name: 'league-starters', auth: 'public', url: 'modules.php?name=LeagueStarters',
    anchor: '#league-starters-tables' },
  { name: 'news', auth: 'public', url: 'modules.php?name=News', anchor: 'article',
    extraMask: ['article time'], viewports: ['desktop', 'mobile'] },
  { name: 'player', auth: 'public', url: 'modules.php?name=Player&pa=showpage&pid=1',
    anchor: '.stats-grid', viewports: ['desktop', 'mobile'] },
  { name: 'player-database', auth: 'public', url: 'modules.php?name=PlayerDatabase',
    anchor: 'form[action*="PlayerDatabase"]' },
  { name: 'player-movement', auth: 'public', url: 'modules.php?name=PlayerMovement',
    anchor: '.ibl-data-table',
    states: [
      { name: 'default', appState: {} },
      { name: 'empty', appState: { 'Current Season Ending Year': '1900' } },
    ],
    dataDriven: true,
    notes: 'empty state forces previousSeasonEndingYear=1899 → zero rows; locks empty-state render.' },
  { name: 'projected-draft-order', auth: 'public', url: 'modules.php?name=ProjectedDraftOrder',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'record-holders', auth: 'public', url: 'modules.php?name=RecordHolders',
    anchor: '.record-section' },
  { name: 'schedule', auth: 'public', url: 'modules.php?name=Schedule',
    anchor: '.schedule-header', extraMask: ['.schedule-today-highlight'],
    viewports: ['desktop', 'mobile'] },
  { name: 'search', auth: 'public', url: 'modules.php?name=Search',
    anchor: '.search-page', viewports: ['desktop', 'mobile'] },
  { name: 'season-archive', auth: 'public', url: 'modules.php?name=SeasonArchive',
    anchor: '.ibl-data-table' },
  { name: 'season-highs', auth: 'public', url: 'modules.php?name=SeasonHighs',
    anchor: '.ibl-data-table' },
  { name: 'season-leaderboards', auth: 'public', url: 'modules.php?name=SeasonLeaderboards',
    anchor: '.ibl-data-table', elementScreenshot: true },
  { name: 'series-records', auth: 'public', url: 'modules.php?name=SeriesRecords',
    anchor: '.ibl-data-table' },
  { name: 'standings', auth: 'public', url: 'modules.php?name=Standings',
    anchor: '.ibl-data-table', viewports: ['desktop', 'mobile'] },
  { name: 'team', auth: 'public', url: 'modules.php?name=Team&op=team&teamid=1',
    anchor: '.team-page-layout', viewports: ['desktop', 'mobile'] },
  { name: 'team-off-def-stats', auth: 'public', url: 'modules.php?name=TeamOffDefStats',
    anchor: '.ibl-data-table' },
  { name: 'topics', auth: 'public', url: 'modules.php?name=Topics',
    anchor: '.topics-page' },
  { name: 'transaction-history', auth: 'public', url: 'modules.php?name=TransactionHistory',
    anchor: '.ibl-data-table', extraMask: ['.transaction-row time'] },
  { name: 'voting-results', auth: 'public', url: 'modules.php?name=VotingResults',
    anchor: 'table.voting-results-table' },
  { name: 'your-account', auth: 'public', url: 'modules.php?name=YourAccount',
    anchor: '.auth-page' },

  // ── Auth modules ────────────────────────────────────────────
  { name: 'api-keys', auth: 'auth', url: 'modules.php?name=ApiKeys',
    anchor: 'form[action*="ApiKeys"]' },
  { name: 'cap-space', auth: 'auth', url: 'modules.php?name=CapSpace&teamid=1',
    anchor: '.ibl-data-table' },
  { name: 'depth-chart-entry', auth: 'auth', url: 'modules.php?name=DepthChartEntry',
    anchor: 'form[name="DepthChartEntry"]', viewports: ['desktop', 'mobile'] },
  { name: 'draft', auth: 'auth', url: 'modules.php?name=Draft',
    anchor: '.draft-container',
    states: [{ name: 'default', appState: { 'Show Draft Link': 'Yes' } }],
    viewports: ['desktop', 'mobile'],
    notes: 'Outside Draft phase, requires Show Draft Link toggle to render.' },
  { name: 'free-agency', auth: 'auth', url: 'modules.php?name=FreeAgency',
    anchor: '.fa-table',
    states: [{ name: 'default', appState: { 'Current Season Phase': 'Free Agency' } }],
    viewports: ['desktop', 'mobile'] },
  { name: 'next-sim', auth: 'auth', url: 'modules.php?name=NextSim',
    anchor: '.next-sim-container', extraMask: ['time.local-time'] },
  { name: 'one-on-one-game', auth: 'auth', url: 'modules.php?name=OneOnOneGame',
    anchor: 'form[name="OneOnOneGame"]',
    notes: 'Admin-only game-runner; baseline reflects empty state under CI seed.' },
  { name: 'player-export-guide', auth: 'auth', url: 'modules.php?name=PlayerExportGuide',
    anchor: '.ibl-code-block',
    notes: 'Admin-only documentation; static content.' },
  { name: 'trading', auth: 'auth', url: 'modules.php?name=Trading',
    anchor: '.trading-team-select',
    states: [{ name: 'default', appState: { 'Allow Trades': 'Yes' } }],
    viewports: ['desktop', 'mobile'] },
  { name: 'training-camp-ratings-diff', auth: 'auth', url: 'modules.php?name=TrainingCampRatingsDiff',
    anchor: '.ratings-diff-page',
    notes: 'Admin-only; renders empty state unless ratings snapshot exists.' },
  { name: 'voting', auth: 'auth', url: 'modules.php?name=Voting',
    anchor: '.voting-form-container', viewports: ['desktop', 'mobile'] },
  { name: 'waivers', auth: 'auth', url: 'modules.php?name=Waivers',
    anchor: '.waivers-page' },

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
