---
description: HTML View class standards: output buffering, HtmlSanitizer::e(), and structural conventions.
paths: "**/*View.php"
last_verified: 2026-04-11
---

# View Rendering Rules

## Canonical View Examples
Reference these before building new Views:
- `FreeAgency/FreeAgencyView.php` — complex tables with sticky columns, team colors, footer rows
- `Player/Views/PlayerSeasonStatsView.php` — cards, stats grids, tabbed layouts
- `Voting/VotingSubmissionView.php` — confirmation/error pages with CSS classes

## Mechanical enforcement (PHPStan)

These rules fire in CI and PostToolUse — violating them blocks the PR before review:

| Rule | Identifier | Catches |
|---|---|---|
| `RequireEscapedOutputRule` | `ibl.unescapedOutput` | `echo`/`<?=` in `*View.php` without `HtmlSanitizer::e()` wrap or safe-value exception (cast, literal, whitelisted helper) |
| `BanInlineCssRule` | `ibl.inlineCss` | `<style>` blocks or `style="..."` attributes in PHP string literals (exception: `style="--..."` CSS custom properties) |
| `BanDeprecatedHtmlTagsRule` | `ibl.deprecatedHtmlTag` | `<b>`, `<i>`, `<center>`, `<font>`, `<u>` in PHP string literals |

Use semantic replacements: `<strong>`, `<em>`, `text-align: center` via CSS class, CSS font properties. When you need a new visual pattern, create a CSS class under `ibl5/design/components/` — do NOT work around the rule with a whitelist exception unless the helper is genuinely HTML-safe (see `RequireEscapedOutputRule::SAFE_STATIC_CALLS`).

For dynamic per-element values (team colors, widths), use CSS custom properties on a container: `style="--team-color-primary: #$color1;"` — the `style="--` prefix is whitelisted.

## View Class Structure

All View classes use output buffering with `ob_start()` / `ob_get_clean()`:

```php
public function renderSection(array $data): string
{
    ob_start();
    ?>
    <div class="existing-component-class">
        <h2><?= HtmlSanitizer::e($data['title']) ?></h2>
    </div>
    <?php
    return (string) ob_get_clean();
}
```

Delegate to UI helpers instead of building markup inline:
- `UI\TableStyles` — team-colored styling (row backgrounds, hover effects, CSS custom properties)
- `UI\TeamCellHelper` — team name cells with consistent formatting

## CSS reuse

**Before writing new CSS**, check if a style already exists in `ibl5/design/`. Key files:
- `components/tables.css`, `components/cards.css`, `components/existing-components.css`, `tokens/colors.css`
- Reuse existing classes (`.ibl-card`, `.ibl-stat-highlight`, `.ibl-title`, `.ibl-data-table`) instead of creating duplicates
- Module-specific table overrides go in `tables.css` as new sections (pattern: `.allstar-table`, `.contact-table`, `.record-table`)

## Statistics Display
Use `BasketballStats\StatsFormatter` — see `php-classes.md` for method list.
