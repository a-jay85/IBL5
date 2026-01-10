# IBL5 Core Coding Rules (Always Loaded)

These rules apply to ALL code work in this project.

## Class Autoloading - NO Manual Requires
```php
// ✅ CORRECT - Just use the class name
$player = new Player($db);

// ❌ WRONG - NEVER do this
require_once 'classes/Player.php';
```
All classes in `ibl5/classes/`, filename = class name.

## Type Hints Required
```php
public function getPlayer(int $playerId): ?Player
```
- `declare(strict_types=1);` in every file

## Database Object Preference
**Use `$mysqli_db` (modern MySQLi)** over legacy `$db`:
```php
global $mysqli_db;
$stmt = $mysqli_db->prepare('SELECT * FROM ibl_plr WHERE pid = ?');
$stmt->bind_param('i', $playerId);
$stmt->execute();
```

## Schema Verification (CRITICAL)
**Always verify table/column names in `ibl5/schema.sql` before writing queries.**
Never assume database structures exist.

## XSS Protection (MANDATORY)
Use `Utilities\HtmlSanitizer::safeHtmlOutput()` on ALL output:
- Database query results
- User inputs (player names, form data)
- Play-by-play text, error messages

## HTML/CSS Modernization (MANDATORY)
Replace deprecated tags immediately:
- `<b>` → `<strong style="font-weight: bold;">`
- `<i>` → `<em style="font-style: italic;">`
- `<font>` → `<span style="...">`
- `<center>` → `<div style="text-align: center;">`
- `border=1` → `style="border: 1px solid #000; border-collapse: collapse;"`

## Quick Reference
| Task | Command |
|------|---------|
| Run tests | `cd ibl5 && vendor/bin/phpunit` |
| Schema | `ibl5/schema.sql` |
| Stats formatting | `BasketballStats\StatsFormatter` |

**Detailed specifications in `.github/skills/` (auto-loaded by task).**
