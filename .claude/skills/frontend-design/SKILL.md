---
name: frontend-design
description: Create and modify IBL5 frontend interfaces using the project's CSS architecture, View patterns, and component system. Use when building UI components, styling tables, or modifying View classes.
---

# IBL5 Frontend Design

Build and modify IBL5 frontend interfaces within the established design system. This project has a mature CSS architecture — always work within it.

## Before Writing Any CSS or HTML

1. **Read the relevant component CSS** in `ibl5/design/components/` — there are 17 files covering tables, cards, forms, navigation, player views, and more
2. **Read `css-architecture.md`** (auto-loads for CSS/View files) for the layer system, table patterns, sticky gotchas, and overflow rules
3. **Read an existing View class** similar to what you're building — canonical examples:
   - `FreeAgency/FreeAgencyView.php` — complex tables with sticky columns, team colors, footer rows
   - `PlayerInfo/PlayerInfoView.php` — cards, stats grids, tabbed layouts
   - `ScoParser/ScoParserView.php` — custom component with dedicated CSS
4. **Check if a utility class already exists** — Tailwind 4 utilities (`text-center`, `bg-navy-800`, etc.) and existing component classes handle most needs

## View Class Pattern

All View classes follow this structure:

```php
declare(strict_types=1);

namespace IBL5\ModuleName;

use IBL5\Utilities\HtmlSanitizer;

final class ModuleView
{
    // Typed properties for injected dependencies
    public function __construct(
        private readonly SomeService $service,
    ) {}

    public function renderSection(array $data): string
    {
        ob_start();
        // HTML with HtmlSanitizer::e() on ALL dynamic output
        ?>
        <div class="existing-component-class">
            <h2><?= HtmlSanitizer::e($data['title']) ?></h2>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
```

**Rules:**
- Use `ob_start()` / `ob_get_clean()` for HTML generation
- Apply `HtmlSanitizer::e()` (short alias) on every dynamic value
- Delegate to UI helpers: `UI\TableStyles` for team-colored styling, `UI\TeamCellHelper` for team cells
- Never put `<style>` blocks or CSS-generating methods in PHP files
- All CSS goes in `ibl5/design/components/` files

## CSS Rules

### Layer System (Tailwind 4)

Four layers only: `theme < base < components < utilities`. Components beat base; utilities beat everything.

- **All IBL component CSS** lives in `@layer components` inside files in `design/components/`
- **Never create custom `@layer` names** — Tailwind 4 drops them, causing them to land after utilities
- **Never use `!important`** unless overriding user-agent styles or JS-set inline styles
- **Resolve specificity within components** using higher specificity selectors and later source order

### Where CSS Goes

| What | Where | NOT |
|------|-------|----|
| Component styles | `design/components/<name>.css` | `<style>` in PHP |
| One-off layout | Tailwind utility classes in HTML | Inline `style=""` |
| Dynamic values | CSS custom properties: `style="--team-color: #1a2e5a"` | Inline color/bg styles |
| New component | New file in `design/components/`, `@import` in `design/input.css` | Anywhere else |

### Inline Style Policy

**Allowed:** CSS custom properties (`--team-color-primary`, `--team-row-hover-bg`), `colspan`, truly unique one-off padding.

**Not allowed:** `text-align`, `font-family`, `font-size`, `color` on links — these are handled by component CSS or Tailwind utilities.

## Table Pattern Quick-Reference

| Need | Pattern | Classes/Wrappers |
|------|---------|-----------------|
| Simple data table | Basic | `<table class="ibl-data-table">` |
| Team-colored table | Basic + team | `<table class="ibl-data-table team-table">` |
| Horizontal scroll (mobile) | Scroll wrapper | `.table-scroll-wrapper` > `.table-scroll-container` > `table.ibl-data-table` |
| Sticky left column(s) | Responsive | Above wrapper + `table.ibl-data-table.responsive-table` + `td.sticky-col` |
| Sticky header (desktop, no h-scroll) | Sticky header | `<table class="ibl-data-table sticky-header">` (pure CSS) |
| Sticky header + sticky column | Sticky table | `.sticky-scroll-wrapper` > `.sticky-scroll-container` > `table.ibl-data-table.sticky-table` |
| Above + header sticks to viewport | Page sticky | Add `.page-sticky` to wrapper (requires `sticky-page-header.js`) |

Read `css-architecture.md` "Table Pattern Decision Tree" for the full decision tree and sticky gotchas.

## Sticky / Overflow Gotchas

- **Nav offset:** Sticky `top` must be `72px` (nav height), not `0`
- **`overflow: auto` captures sticky:** Any non-`visible`/`clip` overflow on ANY axis makes a scroll container, breaking sticky
- **`overflow-y: visible` gets promoted** to `auto` if the other axis is `auto`/`scroll`/`hidden`
- **`base.css` sets `table { overflow-x: auto }`** — Pattern 4 (sticky-header) overrides to `visible`
- **Never set `overflow: hidden`** on elements with `position: sticky` cells
- **Rounded corners + sticky** — use `border-radius` on corner cells directly, not `overflow: hidden` on the table

## Common Cell/Row Classes

Use existing modifier classes instead of creating new ones:

- `.sep-team` / `.sep-weak` — vertical separators
- `.salary` — left-aligned salary column
- `.sticky-col`, `.sticky-col-1/2/3` — sticky columns
- `.user-team-row` — yellow highlight for user's team
- `.drafted` — grayed-out row
- `.career-row` — bold career totals
- `.ratings-highlight` / `.ratings-separator` — team-colored highlights

## Anti-Patterns (Never Do These)

1. **Custom fonts or font stacks** — the project uses system fonts via `base.css`
2. **`<style>` blocks in PHP** — all CSS in `design/components/`
3. **Custom `@layer` names** — only `theme`, `base`, `components`, `utilities`
4. **`!important` for specificity** — use layer ordering and selector specificity
5. **Generic/trendy aesthetics** — no grain overlays, glassmorphism, or decorative elements foreign to the design system
6. **Inline styles for things CSS classes handle** — check existing classes first
7. **New table markup patterns** — use `.ibl-data-table` and its variants
8. **`number_format()` for stats** — use `BasketballStats\StatsFormatter` (enforced by PHPStan)
9. **Forgetting `HtmlSanitizer::e()`** on any dynamic output
10. **Creating wrapper divs** when Tailwind utilities on existing elements suffice
