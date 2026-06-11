---
description: CSS architecture: all styles live in ibl5/design/components/; inline CSS is banned.
paths:
  - "**/design/**/*.css"
  - "**/*View.php"
last_verified: 2026-06-11
---

# CSS Architecture Reference

## Layer Hierarchy

Tailwind 4 owns the layer order — only four layers exist: `@layer theme, base, components, utilities;`

| Layer | Priority | Contents |
|-------|----------|----------|
| `theme` | lowest | Tailwind theme variables (internal) |
| `base` | low | Tailwind preflight/reset, CSS reset, legacy PHP-Nuke styles (`base.css`) |
| `components` | normal | All IBL component CSS, mobile/card overrides (`components/*.css`, `tokens/tokens.css`, bottom of `base.css`) |
| `utilities` | highest | Tailwind utilities (`text-center`, `bg-navy-800`…) (internal) |

**Rules:**
- A `components` selector *always* beats `base` regardless of specificity (layer order).
- Within `components`, override via higher specificity + later source order (e.g. `.player-stats-card table td` > `.ibl-data-table td`).
- Utilities always win over everything — correct.
- **Never use custom layer names** (`reset`, `legacy`, `overrides`) — Tailwind 4 drops them from its order, so they implicitly land *after* `utilities`.

## Token Naming

`design/input.css` declares 62 `--color-*` variables in a `@theme` block; `design/tokens/tokens.css` re-exports each as a bare alias (`--navy-900: var(--color-navy-900);`).

**Rule:** component CSS under `design/components/` must reference the **bare aliases** (`--navy-900`), never `--color-*` directly. Tailwind controls the `--color-*` namespace; the aliases are the stable contract and don't break on version bumps.

## Table Patterns

Pick by need (cells get sticky-col classes — see Cell/Row Modifiers below):

| Pattern | Wrapper | Table class | Use case |
|---------|---------|-------------|----------|
| 1. Simple scroll | `.table-scroll-wrapper` > `.table-scroll-container` | `.ibl-data-table` | Wide tables scrolling horizontally |
| 2. Sticky columns | `.table-scroll-wrapper` > `.table-scroll-container` | `.ibl-data-table.responsive-table` | Mobile: left col(s) freeze, rest scrolls |
| 3. Sticky header+column | `.sticky-scroll-wrapper` > `.sticky-scroll-container` | `.ibl-data-table.sticky-table` | Both-axis: header AND first col freeze (`th.sticky-corner` + `td.sticky-col`) |
| 3a. ...+viewport | `.sticky-scroll-wrapper.page-sticky` > `.sticky-scroll-container` | `.ibl-data-table.sticky-table` | Same as 3, header sticks to viewport on desktop via JS (`sticky-page-header.js`) |
| 4. Sticky header (viewport) | none | `.ibl-data-table.sticky-header` | Desktop: header sticks to viewport on scroll (pure CSS, no horizontal scroll) |

Simple non-scrolling table → `.ibl-data-table`; team-colored → add `.team-table`.

### Sticky Header Gotchas (read before implementing)

- **Fixed nav offset:** nav is 72px tall — all sticky `top` values must be `72px`, not `0`.
- **`overflow` captures sticky:** any element with `overflow` other than `visible`/`clip` on ANY axis becomes a scroll container and captures `position: sticky` (per spec, no workaround). And `overflow-x: auto; overflow-y: visible` → the browser promotes `visible` to `auto`. `overflow-y: clip` is NOT promoted, but the element is still a scroll container if the other axis is `auto`/`scroll` (sticky is captured per-element, not per-axis).
- **Consequence:** pure CSS can't do page-level sticky headers inside a horizontal scroll container. Pattern 3a uses JS (`sticky-page-header.js`) to clone the thead into a fixed overlay.
- **`base.css` scopes `overflow-x: auto` to `table:not(.ibl-data-table)`** — modern tables unaffected. Sticky variants still set `overflow: visible` to override `.ibl-data-table`'s `overflow: hidden` (border-radius clipping).
- **Rounded corners need `overflow: hidden`** to clip, which breaks sticky. Fix: `overflow: visible` on the table + `border-radius` on corner cells. Pattern 4 corner `th` cells also need `box-shadow` in `var(--page-bg, #eeeeee)` to mask rows scrolling behind the rounded corners.
- **`--page-bg`** is set on `<body>` by `theme.php` from `$bgcolor1` (dev `#BBBBBB`, prod `#EEEEEE`).
- **`css:watch` may not rebuild.** After editing CSS, verify: `grep -c "your-class" themes/IBL/style/style.css`. If 0, manually rebuild (sanctioned recovery — see `.claude/rules/css-auto-rebuild.md`).

## Overflow Rules

| Context | `overflow` | Why |
|---------|-----------|-----|
| `.ibl-data-table` | `hidden` | clip for `border-radius` |
| `.ibl-data-table.sticky-header` / `.responsive-table` (mobile) / `.sticky-table` | `visible` | required for `position: sticky` |
| `.sticky-scroll-wrapper` (incl. `.page-sticky`) | `auto` | the actual scroll viewport (JS handles desktop viewport-sticky) |
| `.table-scroll-container` | `auto` desktop / `scroll` mobile | horizontal scroll container |
| `table:not(.ibl-data-table), center` (legacy base.css) | `auto` | prevent legacy layout overflow |

- **Never** set `overflow: hidden` on any element containing `position: sticky` cells.
- All `.sticky-table` consumers must be inside `.sticky-scroll-wrapper` — `responsive-tables.js` skips tables there to avoid injecting conflicting wrappers.

## Inline Style Policy

Enforced by `BanInlineCssRule` (`ibl.inlineCss`): allows `style="--..."` custom properties (dynamic per-element values like team colors), bans everything else. If no class exists, create one in `ibl5/design/components/` — never work around with a literal `style="color: ..."`.

**Card modifiers** (use instead of inline styles): `.team-card__body--flush` (zero padding), `--tight` (no bottom padding), `--bordered` (top border), `.team-card__section-label` (uppercase sub-heading), `.team-card__footer--bold` (bold totals).

## `white-space: nowrap` Locations

Check these before debugging text-wrapping:

| File | Selector |
|------|----------|
| `tables.css` | `.ibl-data-table th`, `td.player-cell`, `.responsive-table td` |
| `navigation.css` | `.ibl-tab`, `.plr-nav__group-label`, `.plr-nav__pill` |
| `cards.css` | `.ibl-card__meta` |
| `player-cards.css` | `.stats-grid a`, `th`, `td` |
| `existing-components.css` | various (7 legacy selectors) |

## Cell/Row Modifier Classes

| Class | Purpose | On |
|-------|---------|-----|
| `.sep-team` / `.sep-weak` | team-colored / gray vertical separator | `td`,`th` |
| `.salary` | left-aligned salary column | `td`,`th` |
| `.sticky-col` / `.sticky-col-1/2/3` | single / multi sticky column | `td`,`th` |
| `.sticky-corner` | top-left corner cell (sticky header+col) | `th` |
| `.ratings-separator` | zero-padding divider row | `tr` |
| `.user-team-row` | yellow highlight for user's team | `tr` |
| `.drafted` | grayed-out drafted player | `tr` |
| `.career-row` | bold career totals | `tr` |

## `!important` Policy

Only for (1) user-agent override necessity (e.g. iOS Safari phone-number auto-detection) and (2) JS-set inline styles CSS must override. All other specificity battles → layer ordering + specificity/source-order within `@layer components`.

## Frontend Anti-Patterns

Before writing CSS/HTML, read the relevant `ibl5/design/components/` file — the pattern you need likely exists. Avoid these AI tendencies that conflict with the design system:
1. Custom fonts/stacks — system fonts only (`base.css`).
2. Trendy aesthetics — no grain overlays, glassmorphism, gradient borders, decorative elements.
3. New table markup — always `.ibl-data-table` + variants (see Table Patterns).
4. Wrapper divs when Tailwind utilities on existing elements suffice.
5. Inventing CSS from scratch — read existing component files first.
