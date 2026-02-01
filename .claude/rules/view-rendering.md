---
paths: "**/*View.php"
---

# View Rendering Rules

## XSS Protection (MANDATORY)
ALL dynamic content must use `Utilities\HtmlSanitizer::safeHtmlOutput()`:

```php
// ✅ CORRECT
<?= \Utilities\HtmlSanitizer::safeHtmlOutput($playerName) ?>
<?= \Utilities\HtmlSanitizer::safeHtmlOutput($row['name']) ?>

// ❌ VULNERABLE
<?= $playerName ?>
<?= $row['name'] ?>
echo $username;
```

## HTML Modernization
Replace deprecated tags:

| Deprecated | Modern Replacement |
|------------|-------------------|
| `<b>text</b>` | `<strong style="font-weight: bold;">text</strong>` |
| `<i>text</i>` | `<em style="font-style: italic;">text</em>` |
| `<u>text</u>` | `<span style="text-decoration: underline;">text</span>` |
| `<font color="red">` | `<span style="color: red;">` |
| `<center>` | `<div style="text-align: center;">` |
| `border=1` | `style="border: 1px solid #000; border-collapse: collapse;"` |

## CSS Centralization
When inline styles repeat 2+ times, extract to `<style>` block:

```html
<style>
.data-cell { border: 1px solid #000; padding: 4px; }
.player-name { font-weight: bold; color: #333; }
</style>

<td class="data-cell"><span class="player-name">...</span></td>
```

## Statistics Display
Use `BasketballStats\StatsFormatter` — see `php-classes.md` for method list.
