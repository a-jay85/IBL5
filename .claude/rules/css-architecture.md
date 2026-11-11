---
description: CSS architecture: all styles live in ibl5/design/components/; inline CSS is banned; daisyUI (d- prefix) is the base layer on converted pages. Includes extraction gotchas, orphan-CSS audit, and mobile responsive hiding. Long-form sections live in css-architecture-detail.md (Read on demand).
paths:
  - "**/design/**/*.css"
  - "**/*View.php"
last_verified: 2026-11-11
---

# CSS Architecture Reference

## Layer Hierarchy

Tailwind 4 owns the layer order — only four layers exist: `@layer theme, base, components, utilities;`

| Layer | Priority | Contents |
|-------|----------|----------|
| `theme` | lowest | Tailwind theme variables (internal) |
| `base` | low | Tailwind preflight/reset, CSS reset, legacy PHP-Nuke styles (`base.css`) |
| `components` | normal | All IBL component CSS, mobile/card overrides (`components/*.css`, `tokens/tokens.css`, bottom of `base.css`) |
| `utilities` | highest | Tailwind utilities (`text-center`, `bg-navy-800`…) (internal); daisyUI component rules in nested sublayers; the daisyUI override block at the end of `design/input.css` |

**Rules:**
- A `components` selector *always* beats `base` regardless of specificity (layer order).
- Within `components`, override via higher specificity + later source order (e.g. `.player-stats-card table td` > `.ibl-data-table td`).
- Utilities always win over everything — correct.
- **Never use custom layer names** (`reset`, `legacy`, `overrides`) — Tailwind 4 drops them from its order, so they implicitly land *after* `utilities`.

## daisyUI Components

Moved to `.claude/rules/css-architecture-detail.md` § daisyUI Components. daisyUI 5 is the base layer for shared UI on converted pages, and every daisyUI class carries the `d-` prefix (never unprefix it).

## Token Naming

`design/input.css` declares 62 `--color-*` variables in a `@theme` block; `design/tokens/tokens.css` re-exports each as a bare alias (`--navy-900: var(--color-navy-900);`).

**Rule:** component CSS under `design/components/` must reference the **bare aliases** (`--navy-900`), never `--color-*` directly. Tailwind controls the `--color-*` namespace; the aliases are the stable contract and don't break on version bumps.

Font weights and stacking levels follow the same rule. Write `var(--font-weight-semibold)` or `var(--z-sticky)` in place of a numeric `font-weight` or `z-index`; the `--font-weight-*` and `--z-*` tokens live in `design/tokens/tokens.css`. The keywords `bold` and `inherit` stay as written. A stylesheet that a page links without `themes/IBL/style/style.css` gets no `:root` tokens, so a bare `var(--z-modal)` there computes to `auto`. Such a file must use the fallback form, `var(--z-modal, 1000)`. Today that applies only to `design/components/block-fa-admin.css`, which `ibl5/block.php` links on its own.

## Table Patterns

Moved to `.claude/rules/css-architecture-detail.md` § Table Patterns. Pick a table pattern by need; every table uses `.ibl-data-table` plus variants, and cells take the sticky-col classes.

### Sticky Header Gotchas (read before implementing)

Moved to `.claude/rules/css-architecture-detail.md` § Sticky Header Gotchas (read before implementing). Read it before building a sticky header: any `overflow` other than `visible`/`clip` on any axis captures `position: sticky`.

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

Moved to `.claude/rules/css-architecture-detail.md` § `white-space: nowrap` Locations. Check these selectors before debugging text-wrapping.

## Cell/Row Modifier Classes

Moved to `.claude/rules/css-architecture-detail.md` § Cell/Row Modifier Classes. Cells and rows take the modifier classes listed there, such as `.sticky-col`, `.sep-team`, and `.user-team-row`.

## `!important` Policy

Only for (1) user-agent override necessity (e.g. iOS Safari phone-number auto-detection) and (2) JS-set inline styles CSS must override. All other specificity battles → layer ordering + specificity/source-order within `@layer components`.

## Frontend Anti-Patterns

Before writing CSS/HTML, read the relevant `ibl5/design/components/` file — the pattern you need likely exists. Avoid these AI tendencies that conflict with the design system:
1. Custom fonts/stacks — system fonts only (`base.css`).
2. Trendy aesthetics — no grain overlays, glassmorphism, gradient borders, decorative elements.
3. New table markup — always `.ibl-data-table` + variants (see Table Patterns).
4. Wrapper divs when Tailwind utilities on existing elements suffice.
5. Inventing CSS from scratch — read existing component files first.

## Extraction & Global-Selector Gotchas

Moved to `.claude/rules/css-architecture-detail.md` § Extraction & Global-Selector Gotchas. Never `@import` page-scoped CSS with unscoped global selectors into `design/input.css`.

## Orphan-CSS Audit

Moved to `.claude/rules/css-architecture-detail.md` § Orphan-CSS Audit. A `bin/check-orphan-css` miss is a worklist, not a dead verdict; run `--crawl` and open the emitting view before removing any candidate.

## Mobile-Only Scope

When removing a UI element "on mobile" or "to save space," default to **responsive CSS hiding** rather than deleting render code — desktop keeps what mobile drops. Add `@media (max-width: 1023px) { .element { display: none; } }` or a Tailwind `hidden lg:block` class on the existing element. Confirm scope explicitly when the user's phrasing is ambiguous (mobile only, or desktop too?).
