# CSS Table Architecture

## Inheritance Hierarchy

```
.ibl-data-table  (base — used by 37+ views)
├── .team-table  (adds team colors via CSS custom properties)
├── .responsive-table  (adds mobile sticky columns)
├── .sticky-table  (adds sticky header + first column)
├── .league-stats-table  (league-wide stat styling)
├── .depth-chart-table  (depth chart form)
├── .contact-table  (contact list, max-width: 800px)
├── .voting-results-table  (vote counts, max-width: 420px)
├── .voting-form-table  (ballot with checkboxes/radios)
├── .draft-table  (draft selection form)
├── .draft-pick-table  (draft pick locator matrix, styled to match ibl-data-table)
└── .draft-history-table  (mobile column constraints)
```

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
- BasketballStats/Tables/SeasonTotals
- BasketballStats/Tables/SeasonAverages
- BasketballStats/Tables/Per36Minutes
- UI/Tables/Contracts
- BasketballStats/Tables/PeriodAverages

### `.responsive-table` + `.sticky-col*` — Medium impact (mobile only)

- Standings, Draft History, Contract List
- Compare Players, Leaderboards, Season Leaders
- Player Awards, Transaction History
- One-on-One, Team page, Depth Chart

### `.sticky-table` — Low impact (5 views)

- DraftPickLocatorView
- CapSpaceView
- SeriesRecordsView
- FranchiseHistoryView
- FreeAgencyPreviewView

### Specialized tables — Single-view impact each

| Selector | View |
|----------|------|
| `.league-stats-table` | TeamOffDefStatsView |
| `.depth-chart-table` | DepthChartEntryView |
| `.draft-pick-table` | DraftPickLocatorView (styled to match `.ibl-data-table`, uses `.sticky-table`) |
| `.contact-table` | GMContactListView |
| `.voting-form-table` | Voting views |
| `.trading-*` | Trading/TradingView |

## Cell / Helper Classes

These are used inside multiple table types.

| Class | What It Styles | Where Used |
|-------|---------------|------------|
| `.ibl-team-cell__name` / `__city` / `__logo` / `__text` / `--colored` | Team logo + name flex cell | Standings, Free Agency, all team columns |
| `.ibl-player-cell` | Player photo + name flex cell | Rosters, draft, leaderboards |
| `.ibl-stat-value` / `--highlight` / `--positive` / `--negative` | Right-aligned stat numbers | All stat display tables |
| `.rank-cell` | Bold navy rank number | Leaderboards, standings |
| `.date-cell` | No-wrap date display | Transaction history, schedules |
| `.divider` | 3px navy column separator | Multi-section tables |
| `.totals-row` | Orange accent totals row | Team stat tables |
| `.highlight` | Orange accent row | Various highlight rows |
| `.drafted` | 50% opacity + strikethrough | Draft views |
| `.ibl-tooltip` | CSS tooltip on hover/focus | InjuriesView, Ratings, block-Chunk_Leaders |

## Row Variant Classes

| Class | Effect |
|-------|--------|
| `.ibl-table-row--highlight` | Orange accent background |
| `.ibl-table-row--user-team` | Light yellow (#ffffcc) |
| `.ibl-table-row--winner` | Bold weight, orange color |
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

## Team-Color CSS Custom Properties

Canonical pair: `--team-color-primary` / `--team-color-secondary`

All team-colored elements must emit these via `TableStyles::inlineTeamVars($color1, $color2)`, which sanitizes through a hex allow-list (`sanitizeColor()`). Do NOT emit team-color CSS variables directly — always route through `inlineTeamVars()`.

### Alias mapping (defined in `tokens/tokens.css`)

Legacy variable names are mapped to the canonical pair via CSS aliases on any element with `style*="--team-color-primary"`:

| Legacy name | Maps to | Consumed by |
|---|---|---|
| `--team-tab-bg-color` | `var(--team-color-primary)` | `navigation.css`, `team-splits.css` |
| `--team-tab-active-color` | `var(--team-color-secondary)` | `navigation.css`, `team-splits.css` |
| `--team-primary` | `var(--team-color-primary)` | `schedule.css` |
| `--team-secondary` | `var(--team-color-secondary)` | `schedule.css` |
| `--banner-primary` | `var(--team-color-primary)` | `banners.css` |
| `--banner-secondary` | `var(--team-color-secondary)` | `banners.css` |

### Cell-scope variables (intentionally separate)

`--team-cell-bg` / `--team-cell-color` are scoped to individual `<td>` cells via `TeamCellHelper::renderTeamCell()`. They use `TableStyles::sanitizeColor()` directly and remain separate from the container-level canonical pair because they target a different element scope.

## Per-Feature CSS Partials

Module-specific table styles live in `design/components/tables/<feature>.css`. Each file wraps its rules in `@layer components { ... }` and is imported via `design/input.css` immediately after `tables.css`.

| File | Covers |
|------|--------|
| `tables/season-highs.css` | `.stat-table`, `.season-highs-discrepancy-panel` |
| `tables/voting.css` | `.voting-results-table`, `.voting-form-table`, `.voting-submission-feedback` |
| `tables/trading.css` | Trading module: `.ibl-data-table` rosters, `.trade-offer-card*`, `.trading-*` layout/roster selectors |
| `tables/draft-history.css` | `.draft-history-table` |
| `tables/league-stats.css` | `.league-stats-table` |
| `tables/contact-list.css` | `.contact-table` |
| `tables/transaction-history.css` | `.txn-table` (on `.ibl-data-table`) |
| `tables/depth-chart.css` | Depth Chart table mobile rules extracted from `tables.css` |
| `tables/draft-pick-locator.css` | `.draft-pick-table` |
| `tables/record-holders.css` | `.record-table` / `--*col*` variants, `.record-category*`, `.record-section*` |
| `tables/projected-draft-order.css` | `.projected-draft-order-table` |
| `tables/player-movement.css` | `.player-movement-table` |
| `tables/franchise-record-book.css` | `.record-book-section-title`, `.record-book-team-selector`, `.record-book-retired-cell` |
| `tables/free-agency.css` | `.fa-table`, `.fa-*` selectors |

### When to add a new module file

If a new module needs table styling beyond `.ibl-data-table` base:
1. Create `design/components/tables/<module>.css` with `@layer components { ... }`.
2. Add `@import './components/tables/<module>.css';` in `design/input.css` after the existing `tables/` import block.
3. Add a row to this table.

## Key Gotchas

### Sticky positioning and overflow

Never set `overflow: hidden` on a table that uses `position: sticky` cells. The `.responsive-table` class is explicitly excluded from overflow clipping via `.ibl-data-table:not(.responsive-table)`.

### `responsive-tables.js` and sticky-scroll tables

`responsive-tables.js` auto-wraps overflowing `.ibl-data-table` elements in `.table-scroll-wrapper > .table-scroll-container` and sets inline `overflow: hidden` on the wrapper. This **breaks** `.sticky-table` tables that rely on `.sticky-scroll-wrapper` for both-axis scrolling.

**Fix:** `responsive-tables.js` skips any table inside `.sticky-scroll-wrapper` (via `table.closest(".sticky-scroll-wrapper")`). All `.sticky-table` consumers **must** be wrapped in `.sticky-scroll-wrapper` to be protected.

**Root cause:** The JS `constrainWrapper()` sets `wrapper.style.overflow = "hidden"` as an inline style, which overrides the CSS rule `.sticky-scroll-wrapper .table-scroll-wrapper { overflow: visible }`. Skipping these tables entirely avoids the conflict.
