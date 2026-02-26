# CSS Architecture Reference

Auto-loads when editing `**/design/**/*.css` or `**/*View.php`.

## Layer Hierarchy

Tailwind 4 owns the layer order — only these four layers exist:

```
@layer theme, base, components, utilities;
```

| Layer | Priority | Contents | File(s) |
|-------|----------|----------|---------|
| `theme` | Lowest | Tailwind theme variables | Tailwind internal |
| `base` | Low | Tailwind preflight/reset, CSS reset, legacy PHP-Nuke styles | Tailwind internal + `base.css` |
| `components` | Normal | All IBL component CSS, Tailwind component classes, mobile overrides, card overrides | `components/*.css`, `tokens/tokens.css`, bottom of `base.css` |
| `utilities` | Highest | Tailwind utility classes (`text-center`, `bg-navy-800`, etc.) | Tailwind internal |

**Key rules:**
- A selector in `components` *always* beats `base` regardless of specificity (layer ordering).
- Within `components`, use higher specificity + later source order for overrides (e.g., `.player-stats-card table td` > `.ibl-data-table td`).
- Tailwind utility classes (`@layer utilities`) always win over everything — this is correct.
- **Never use custom layer names** (e.g., `reset`, `legacy`, `overrides`) — Tailwind 4 drops them from its layer order, causing them to implicitly land *after* `utilities`.

## Table Pattern Decision Tree

```
Need a data table?
├── Simple table, no scroll → <table class="ibl-data-table">
├── Team-colored table → <table class="ibl-data-table team-table">
├── Need horizontal scroll on mobile?
│   └── YES: Wrap in .table-scroll-wrapper > .table-scroll-container
│       └── Need sticky left column(s)?
│           ├── NO  → <table class="ibl-data-table">
│           └── YES → <table class="ibl-data-table responsive-table">
│               ├── 1 sticky col → td.sticky-col
│               ├── 2 sticky cols → td.sticky-col-1, td.sticky-col-2
│               └── 3 sticky cols → td.sticky-col-1, td.sticky-col-2, td.sticky-col-3
└── Need sticky header AND sticky column (grid/matrix)?
    └── .sticky-scroll-wrapper > .sticky-scroll-container
        └── <table class="ibl-data-table sticky-table">
            └── th.sticky-corner + td.sticky-col
```

### Three Sticky Table Patterns

| Pattern | Wrapper | Table Class | Use Case |
|---------|---------|-------------|----------|
| 1. Simple scroll | `.table-scroll-wrapper` > `.table-scroll-container` | `.ibl-data-table` | Wide tables that scroll horizontally |
| 2. Sticky columns | `.table-scroll-wrapper` > `.table-scroll-container` | `.ibl-data-table.responsive-table` | Mobile: left column(s) freeze while rest scrolls |
| 3. Sticky header+column | `.sticky-scroll-wrapper` > `.sticky-scroll-container` | `.ibl-data-table.sticky-table` | Both-axis scroll: header AND first column freeze |

## Overflow Rules

| Context | `overflow` Value | Why |
|---------|-----------------|-----|
| `.ibl-data-table` | `hidden` | Clip content for `border-radius` |
| `.ibl-data-table.responsive-table` (mobile) | `visible` | Required for `position: sticky` to work |
| `.sticky-table` | `visible` | Sticky positioning needs visible overflow |
| `.sticky-scroll-wrapper` | `auto` | This element provides the actual scroll viewport |
| `.table-scroll-container` | `auto` (desktop) / `scroll` (mobile) | Horizontal scroll container |
| `table, center` (legacy base.css) | `auto` | Prevent legacy layout overflow |

**Rule:** Never set `overflow: hidden` on any element containing `position: sticky` cells.

## Inline Style Policy

### Allowed inline styles
- **CSS custom properties** on containers: `style="--team-color-primary: #1a2e5a;"` (dynamic values from PHP)
- **Row-level dynamic styles**: `style="--team-row-hover-bg: ..."` (computed per-team)
- **Truly one-off layout**: `colspan`, unique padding on empty-state messages

### Redundant — use CSS classes instead
- `text-align: center` on `<td>` in `.ibl-data-table` (already set by `tables.css`)
- `font-family` on any element (set by `base.css` and component CSS)
- `font-size` on elements already styled by component classes
- `color` on links inside `.ibl-data-table` (set by `tables.css`)

## `white-space: nowrap` Locations

These CSS selectors set `white-space: nowrap` — check them before debugging text-wrapping issues:

| File | Selector | Purpose |
|------|----------|---------|
| `tables.css` | `.ibl-data-table th` | Header cells never wrap |
| `tables.css` | `.ibl-data-table td.player-cell` | Player names on one line |
| `tables.css` | `.responsive-table td` | All cells in scrollable tables |
| `navigation.css` | `.ibl-tab` | Tab labels |
| `navigation.css` | `.plr-nav__group-label` | Nav group labels |
| `navigation.css` | `.plr-nav__pill` | Nav pills |
| `cards.css` | `.ibl-card__meta` | Card metadata |
| `player-cards.css` | `.stats-grid a`, `th`, `td` | Stats card cells |
| `existing-components.css` | Various (7 selectors) | Legacy components |
| `sco-parser.css` | `.sco-play-text` | Play-by-play text |
| `saved-depth-charts.css` | `.saved-dc-table td` | Depth chart cells |

## Common Cell/Row Modifier Classes

| Class | Purpose | Applied to |
|-------|---------|------------|
| `.sep-team` | Team-colored vertical separator | `<td>`, `<th>` |
| `.sep-weak` | Gray vertical separator | `<td>`, `<th>` |
| `.salary` | Left-aligned salary column | `<td>`, `<th>` |
| `.sticky-col` | Sticky first column (single) | `<td>`, `<th>` |
| `.sticky-col-1/2/3` | Multi-column sticky | `<td>`, `<th>` |
| `.sticky-corner` | Top-left corner cell (sticky header+col) | `<th>` |
| `.ratings-highlight` | Team-colored highlight row | `<tr>` |
| `.ratings-separator` | Zero-padding divider row | `<tr>` |
| `.user-team-row` | Yellow highlight for user's team | `<tr>` |
| `.drafted` | Grayed-out drafted player row | `<tr>` |
| `.career-row` | Bold career totals row | `<tr>` |

## `!important` Policy

After `@layer` introduction, `!important` should only be used for:
1. **User-agent override necessity** — e.g., iOS Safari auto-detection of phone numbers
2. **JavaScript-set inline styles** that CSS must override

All other specificity battles are resolved by layer ordering + specificity within a layer. If you need a component to beat another component, use higher specificity selectors and/or later source order within `@layer components`.
