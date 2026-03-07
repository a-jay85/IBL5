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
├── Need desktop sticky header (no horizontal scroll)?
│   └── <table class="ibl-data-table sticky-header">
│       (Pattern 4 — pure CSS, header sticks to viewport on page scroll)
├── Need sticky header AND sticky column (grid/matrix)?
│   └── .sticky-scroll-wrapper > .sticky-scroll-container
│       └── <table class="ibl-data-table sticky-table">
│           └── th.sticky-corner + td.sticky-col
│       └── Also need header to stick to VIEWPORT on desktop?
│           └── Add .page-sticky to wrapper (Pattern 3a — requires JS)
```

### Sticky Table Patterns

| Pattern | Wrapper | Table Class | Use Case |
|---------|---------|-------------|----------|
| 1. Simple scroll | `.table-scroll-wrapper` > `.table-scroll-container` | `.ibl-data-table` | Wide tables that scroll horizontally |
| 2. Sticky columns | `.table-scroll-wrapper` > `.table-scroll-container` | `.ibl-data-table.responsive-table` | Mobile: left column(s) freeze while rest scrolls |
| 3. Sticky header+column | `.sticky-scroll-wrapper` > `.sticky-scroll-container` | `.ibl-data-table.sticky-table` | Both-axis scroll: header AND first column freeze |
| 3a. Sticky header+column (viewport) | `.sticky-scroll-wrapper.page-sticky` > `.sticky-scroll-container` | `.ibl-data-table.sticky-table` | Same as 3, but header sticks to viewport on desktop via JS (`sticky-page-header.js`) |
| 4. Sticky header (viewport) | None | `.ibl-data-table.sticky-header` | Desktop: header sticks to viewport on page scroll (pure CSS, no horizontal scroll) |

### Sticky Header Gotchas (IMPORTANT — read before implementing)

- **Fixed nav offset:** The nav is 72px tall. All sticky `top` values must be `72px`, not `0`.
- **`overflow: auto` captures sticky:** Any element with `overflow` other than `visible` or `clip` on ANY axis becomes a scroll container, which captures `position: sticky`. This is per CSS spec and cannot be worked around.
- **`overflow-y: visible` gets promoted:** If one axis is `auto`/`scroll`/`hidden` and the other is `visible`, the `visible` is promoted to `auto` by the browser. So `overflow-x: auto; overflow-y: visible` actually becomes `overflow-x: auto; overflow-y: auto`.
- **`overflow-y: clip` does NOT get promoted** but the element is still a scroll container if the other axis is `auto`/`scroll`. Sticky is captured per-element, not per-axis.
- **Consequence:** Pure CSS cannot do page-level sticky headers inside a horizontal scroll container. Pattern 3a uses JavaScript (`sticky-page-header.js`) to clone the thead into a fixed overlay.
- **`base.css` sets `table { overflow-x: auto }`** in `@layer base`. This makes every `<table>` a scroll container and breaks sticky. Pattern 4 overrides this with `overflow: visible` (components layer beats base layer).
- **Rounded corners require `overflow: hidden`** to clip, but that breaks sticky. Solution: set `overflow: visible` on the table and apply `border-radius` directly to corner cells. For Pattern 4, corner `th` cells also need `box-shadow` in `var(--page-bg, #eeeeee)` to mask rows scrolling behind the rounded corners.
- **`--page-bg` CSS variable** is set on `<body>` by `theme.php` from `$bgcolor1` (dev: `#BBBBBB`, prod: `#EEEEEE`).
- **`css:watch` may not rebuild.** After editing CSS, verify the compiled output contains your new rules: `grep -c "your-class" themes/IBL/style/style.css`. If 0, manually rebuild.

## Overflow Rules

| Context | `overflow` Value | Why |
|---------|-----------------|-----|
| `.ibl-data-table` | `hidden` | Clip content for `border-radius` |
| `.ibl-data-table.sticky-header` | `visible` | Required for `position: sticky` to work |
| `.ibl-data-table.responsive-table` (mobile) | `visible` | Required for `position: sticky` to work |
| `.sticky-table` | `visible` | Sticky positioning needs visible overflow |
| `.sticky-scroll-wrapper` | `auto` | This element provides the actual scroll viewport |
| `.sticky-scroll-wrapper.page-sticky` (desktop) | `auto` (unchanged) | JS handles viewport sticky; wrapper still scrolls horizontally |
| `.table-scroll-container` | `auto` (desktop) / `scroll` (mobile) | Horizontal scroll container |
| `table, center` (legacy base.css) | `auto` | Prevent legacy layout overflow |

**Rule:** Never set `overflow: hidden` on any element containing `position: sticky` cells.

**Rule:** All `.sticky-table` consumers must be inside `.sticky-scroll-wrapper`. The `responsive-tables.js` script skips tables inside `.sticky-scroll-wrapper` to avoid injecting conflicting scroll wrappers with inline `overflow: hidden`.

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
