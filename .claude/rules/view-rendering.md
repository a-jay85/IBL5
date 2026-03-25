---
paths: "**/*View.php"
---

# View Rendering Rules

## Canonical View Examples
Reference these before building new Views:
- `FreeAgency/FreeAgencyView.php` — complex tables with sticky columns, team colors, footer rows
- `Player/Views/PlayerSeasonStatsView.php` — cards, stats grids, tabbed layouts
- `Voting/VotingSubmissionView.php` — confirmation/error pages with CSS classes

## XSS Protection (MANDATORY)
ALL dynamic content must use `HtmlSanitizer::e()` (short alias) or `HtmlSanitizer::safeHtmlOutput()`:

```php
// ✅ CORRECT (prefer e() for brevity in templates)
<?= \Utilities\HtmlSanitizer::e($playerName) ?>
<?= \Utilities\HtmlSanitizer::e($row['name']) ?>

// ✅ ALSO CORRECT (full method name)
<?= \Utilities\HtmlSanitizer::safeHtmlOutput($playerName) ?>

// ❌ VULNERABLE
<?= $playerName ?>
<?= $row['name'] ?>
echo $username;
```

## HTML Modernization
Replace deprecated tags with semantic HTML + CSS classes (not inline styles):

| Deprecated | Modern Replacement |
|------------|-------------------|
| `<b>text</b>` | `<strong>text</strong>` |
| `<i>text</i>` | `<em>text</em>` |
| `<u>text</u>` | `<span class="...">text</span>` (add CSS class) |
| `<font color="red">` | `<span class="...">` (add CSS class) |
| `<center>` | Use `text-align: center` via CSS class |
| `border=1` | Use `.ibl-data-table` or add CSS class |

## No Inline Styles (MANDATORY)
**Never write `style="..."` attributes in PHP view files** except for CSS custom properties on containers (e.g., `style="--team-color-primary: #..."` for dynamic team colors). All visual styling must go in CSS files under `ibl5/design/components/`.

Inline style anti-patterns to avoid:
```php
// ❌ WRONG — layout via inline style
"<div style=\"text-align: center; margin-bottom: 1rem;\">"
// ✅ CORRECT — use a CSS class
"<div class=\"team-logo-fallback\">"

// ❌ WRONG — typography via inline style
"<strong style=\"font-weight: 700; font-size: 0.875rem; ...\">"
// ✅ CORRECT — use a CSS class
"<strong class=\"team-card__section-label\">"

// ✅ ALLOWED — CSS custom properties for dynamic values
"<div class=\"team-card\" style=\"--team-color-primary: #$color1;\">"
```

When you need a new visual pattern, create a CSS class in the appropriate file:
- Card modifiers → `cards.css` (e.g., `.team-card__body--tight`, `.team-card__footer--bold`)
- Layout wrappers → `existing-components.css` (e.g., `.franchise-history-wrapper`)
- Navigation elements → `navigation.css`

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

## CSS Centralization
Never write `<style>` blocks in PHP view files — all CSS must be centralized. For dynamic team colors, use CSS custom properties set via inline `style` attributes on container elements, with the corresponding rules in centralized CSS files.

**Before writing new CSS**, check if a style already exists in `ibl5/design/`. Key files:
- `components/tables.css`, `components/cards.css`, `components/existing-components.css`, `tokens/colors.css`
- Reuse existing classes (`.ibl-card`, `.ibl-stat-highlight`, `.ibl-title`, `.ibl-data-table`) instead of creating duplicates
- Module-specific table overrides go in `tables.css` as new sections (pattern: `.allstar-table`, `.contact-table`, `.record-table`)

## Statistics Display
Use `BasketballStats\StatsFormatter` — see `php-classes.md` for method list.
