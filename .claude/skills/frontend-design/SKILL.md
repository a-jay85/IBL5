---
name: frontend-design
description: Create and modify IBL5 frontend interfaces using the project's CSS architecture, View patterns, and component system. Use when building UI components, styling tables, or modifying View classes.
---

# IBL5 Frontend Design

This project has a mature CSS architecture and design system. Always work within it — never invent new patterns.

Reference: `css-architecture.md` (layers, table patterns, sticky/overflow, modifier classes) and `view-rendering.md` (View class structure, XSS, CSS centralization) auto-load when editing CSS or View files.

## Before Writing Any CSS or HTML

1. **Read the relevant component CSS** in `ibl5/design/components/` (17 files: tables, cards, forms, navigation, player views, etc.)
2. **Read an existing View class** similar to what you're building — canonical examples:
   - `FreeAgency/FreeAgencyView.php` — complex tables with sticky columns, team colors, footer rows
   - `PlayerInfo/PlayerInfoView.php` — cards, stats grids, tabbed layouts
   - `ScoParser/ScoParserView.php` — custom component with dedicated CSS
3. **Check if a utility class already exists** — Tailwind 4 utilities and existing component classes handle most needs

## Anti-Patterns

These counter common AI tendencies that conflict with this project's design system:

1. **Custom fonts or font stacks** — system fonts only, set in `base.css`
2. **Generic/trendy aesthetics** — no grain overlays, glassmorphism, gradient borders, or decorative elements foreign to the design system
3. **New table markup patterns** — always use `.ibl-data-table` and its variants (see `css-architecture.md` decision tree)
4. **Creating wrapper divs** when Tailwind utilities on existing elements suffice
5. **Inventing CSS from scratch** — read existing component files first; the pattern you need likely already exists
