# CSS Table Architecture

## Inheritance Hierarchy

```
.ibl-data-table  (base — used by 37+ views)
├── .team-table  (adds team colors via CSS custom properties)
├── .responsive-table  (adds mobile sticky columns)
├── .sticky-table  (adds sticky header + first column)
├── .league-stats-table  (league-wide stat styling)
├── .compare-players-table  (player comparison)
├── .depth-chart-table  (depth chart form)
├── .contact-table  (contact list, max-width: 800px)
├── .allstar-table  (all-star history, max-width: 500px)
├── .voting-results-table  (vote counts, max-width: 420px)
├── .voting-form-table  (ballot with checkboxes/radios)
├── .draft-table  (draft selection form)
├── .draft-pick-table  (draft pick locator matrix)
├── .draft-history-table  (mobile column constraints)
└── .injury-table  (card layout on mobile)
```

## Heading Classes

| Class | Element | Purpose | Used On |
|-------|---------|---------|---------|
| `.ibl-title` | `<h1>` or `<h2>` | Page-level heading | Single-table pages (Contracts, Injuries, Leaderboards, etc.) and multi-table page titles (League Stats) |
| `.ibl-table-title` | `<h2>` | Section heading above a table | Multi-table pages (Record Holders, Trading, Season Highs, League Starters, League Stats) |

## Wrapper / Layout Classes

| Wrapper | Purpose | Paired With |
|---------|---------|-------------|
| `.table-scroll-wrapper` + `.table-scroll-container` | Horizontal scroll on mobile, scroll shadow | `.ibl-data-table`, `.responsive-table` |
| `.sticky-scroll-wrapper` + `.sticky-scroll-container` | Both-axis scroll with sticky header+column | `.ibl-data-table.sticky-table` |
| `.trading-layout` | Trade module grid layout | `.trading-roster`, `.trading-team-select`, etc. |
| `.ibl-grid` / `.ibl-grid--2col` / `.ibl-grid--3col` | Multi-column card grids | `.stat-table` (mini tables inside) |

## Impact Analysis: What Changes When You Edit a Selector

### `.ibl-data-table` — Highest impact (37+ views)

Affects ALL data tables site-wide:

- Standings, League Stats, Season Leaders, Draft History
- Contact List, Compare Players, Contract List, Injuries
- All-Star Appearances, Leaderboards, Player Search
- Transaction History, Series Records, Franchise History
- Free Agency, Free Agency Preview, Record Holders, Player Awards
- Cap Info, Draft Pick Locator, Depth Chart, Voting
- Trading rosters, One-on-One, Team page tables
- All `UI/Tables/*` components (Ratings, SeasonTotals, SeasonAverages, Per36Minutes, Contracts, PeriodAverages)

### `.team-table` — Medium impact (7 views)

- FreeAgencyView (4 tables)
- UI/Tables/Ratings
- UI/Tables/SeasonTotals
- UI/Tables/SeasonAverages
- UI/Tables/Per36Minutes
- UI/Tables/Contracts
- UI/Tables/PeriodAverages

### `.responsive-table` + `.sticky-col*` — Medium impact (mobile only)

- Standings, Draft History, Contract List
- Compare Players, Leaderboards, Season Leaders
- Player Awards, Transaction History
- One-on-One, Team page, Depth Chart

### `.sticky-table` — Low impact (5 views)

- DraftPickLocatorView
- CapInfoView
- SeriesRecordsView
- FranchiseHistoryView
- FreeAgencyPreviewView

### Specialized tables — Single-view impact each

| Selector | View |
|----------|------|
| `.injury-table` | InjuriesView |
| `.league-stats-table` | LeagueStatsView |
| `.depth-chart-table` | DepthChartView |
| `.draft-pick-table` | DraftPickLocatorView |
| `.contact-table` | ContactListView |
| `.voting-form-table` | Voting views |
| `.trading-*` | Trading/TradingView |

## Cell / Helper Classes

These are used inside multiple table types.

| Class | What It Styles | Where Used |
|-------|---------------|------------|
| `.ibl-team-cell` / `--colored` | Team logo + name flex cell | Standings, Free Agency, all team columns |
| `.ibl-player-cell` | Player photo + name flex cell | Rosters, draft, leaderboards |
| `.ibl-stat-value` / `--highlight` / `--positive` / `--negative` | Right-aligned stat numbers | All stat display tables |
| `.rank-cell` | Bold navy rank number | Leaderboards, standings |
| `.date-cell` | No-wrap date display | Transaction history, schedules |
| `.divider` | 3px navy column separator | Multi-section tables |
| `.totals-row` | Orange accent totals row | Team stat tables |
| `.highlight` | Orange accent row | Various highlight rows |
| `.drafted` | 50% opacity + strikethrough | Draft views |
| `.injury-days-tooltip` | CSS tooltip for return dates | InjuriesView |

## Row Variant Classes

| Class | Effect |
|-------|--------|
| `.ibl-table-row--highlight` | Orange accent background |
| `.ibl-table-row--user-team` | Light yellow (#ffffcc) |
| `.ibl-table-row--winner` | Bold weight, orange color |
| `tr.ratings-highlight` | Team color highlight (15% mix, `.team-table` only) |
| `tr.ratings-separator` | Zero-padding divider row (`.team-table` only) |

## HTML Structural Patterns

### Pattern 1: Standard Data Table

```html
<table class="ibl-data-table">
  <thead>...</thead>
  <tbody>...</tbody>
</table>
```

### Pattern 2: Responsive Table (Mobile Sticky Columns)

```html
<div class="table-scroll-wrapper">
  <div class="table-scroll-container">
    <table class="ibl-data-table responsive-table">
      <thead><tr>
        <th class="sticky-col">Name</th>
        <th>Col2</th>
      </tr></thead>
    </table>
  </div>
</div>
```

### Pattern 3: Team-Colored Table

```html
<table class="ibl-data-table team-table"
       style="--team-color-primary: #1e3a5f; --team-color-secondary: #D4AF37;">
  ...
</table>
```

### Pattern 4: Grid/Matrix Table (Sticky Header + Column)

```html
<div class="sticky-scroll-wrapper">
  <div class="sticky-scroll-container">
    <table class="ibl-data-table sticky-table">
      <thead><tr>
        <th class="sticky-col sticky-corner">Team</th>
        ...
      </tr></thead>
    </table>
  </div>
</div>
```

## Key Gotcha

Never set `overflow: hidden` on a table that uses `position: sticky` cells. The `.responsive-table` class is explicitly excluded from overflow clipping via `.ibl-data-table:not(.responsive-table)`.
