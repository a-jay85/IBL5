# IBL5 Universal Coding Rules

**Use custom agents for task-specific workflows:** See `.github/agents/` for specialized agents (refactoring, testing, security, documentation, review) with handoffs between workflow stages.

---

## ⚠️ CRITICAL RULES (Always Apply)

### 1. Class Autoloading - NO Manual Requires
```php
// ✅ CORRECT - Just use the class name
$player = new Player($db);

// ❌ WRONG - NEVER do this
require_once 'classes/Player.php';
```
- All classes in `ibl5/classes/`
- Filename = class name (e.g., `Player.php`)

### 2. Type Hints Required
```php
public function getPlayer(int $playerId): ?Player
public function getTeamRoster(int $teamId): array
```
- `declare(strict_types=1);` in every file

### 3. Database Object Preference
**Always use the global `$mysqli_db` object (modern MySQLi)** whenever a class requires a database object.
**Avoid the legacy `$db` object** whenever possible.

```php
// ✅ CORRECT - Use the global mysqli_db
global $mysqli_db;
$stmt = $mysqli_db->prepare('SELECT * FROM ibl_plr WHERE pid = ?');
$stmt->bind_param('i', $playerId);
$stmt->execute();

// ❌ AVOID - Legacy $db object
// if (method_exists($this->db, 'sql_escape_string')) { ... }
```

**For Dual-Implementation (if needed):**
```php
if (method_exists($this->db, 'sql_escape_string')) {
    // LEGACY: sql_* methods + DatabaseService::escapeString()
} else {
    // MODERN: prepared statements (preferred)
}
```

### 4. Database Schema Verification
**CRITICAL: Always verify table/column names in `ibl5/schema.sql` before implementing database queries.**
- Reference `ibl5/schema.sql` for all table names, columns, and data types
- Never assume table or column names exist without verification
- Cross-check foreign key relationships in schema.sql
- Validate indexes and constraints before optimization
- This prevents hallucination of non-existent database structures

### 5. Basketball Statistics Formatting
**CRITICAL: Use `BasketballStats\StatsFormatter` for ALL statistic formatting, never use `number_format()` directly.**
```php
// ✅ CORRECT - Use StatsFormatter methods
use BasketballStats\StatsFormatter;
echo StatsFormatter::formatPercentage($made, $attempted);  // FG%, FT%, 3P%
echo StatsFormatter::formatPerGameAverage($total, $games); // PPG, APG, etc.
echo StatsFormatter::formatPer36Stat($total, $minutes);    // Per-36 stats
echo StatsFormatter::formatTotal($value);                  // Counting stats with commas
echo StatsFormatter::formatAverage($value);                // General averages

// ❌ WRONG - NEVER use number_format directly for stats
echo number_format($percentage, 3);
echo number_format($average, 2);
```
Available methods in `StatsFormatter`:
- `formatPercentage()` - Shooting percentages (3 decimals)
- `formatPerGameAverage()` - Per-game stats (1 decimal)
- `formatPer36Stat()` - Per-36-minute stats (1 decimal)
- `formatTotal()` - Totals with comma separators
- `formatAverage()` - General averages (2 decimals)
- `calculatePoints()` - Calculate point totals
- `safeDivide()` - Safe division with zero-division handling

Use `BasketballStats\StatsSanitizer` for input validation (sanitizeInt, sanitizeFloat, sanitizeString).

### 6. Security
- **SQL**: Prepared statements or escaped strings
- **XSS**: Use `Utilities\HtmlSanitizer::safeHtmlOutput()` on ALL output (handles multiple types, removes SQL escaping)
- **Validation**: Whitelist for enumerated values

### 7. Testing
- PHPUnit 12.4+ in `ibl5/tests/`
- Register in `ibl5/phpunit.xml`
- No `markTestSkipped()` - delete instead
- Zero warnings or failures
- **Mock objects**: Use PHPDoc annotations for IDE support
  ```php
  /** @var InterfaceName&\PHPUnit\Framework\MockObject\MockObject */
  private InterfaceName $mockObject;
  ```
- Use @see instead of {@inheritdoc} in PHPDoc

### 8. HTML & CSS Refactoring
**Convert deprecated styling tags into modern CSS patterns.**

**Step 1: Replace Deprecated Tags**
```html
<!-- ❌ WRONG - Deprecated tags -->
<font color="#ff0000">Error</font>
<center>Centered text</center>
<b>Bold</b> and <i>Italic</i>
<u>Underlined</u>

<!-- ✅ CORRECT - Use semantic HTML + inline styles -->
<span style="color: #ff0000;">Error</span>
<div style="text-align: center;">Centered text</div>
<strong style="font-weight: bold;">Bold</strong> and <em style="font-style: italic;">Italic</em>
<span style="text-decoration: underline;">Underlined</span>
```

**Step 2: Centralize Repeated Styles**
When the same inline styles appear 2+ times, extract them to a `<style>` block:
```html
<!-- BEFORE: Repetitive inline styles -->
<div style="color: #333; font-size: 14px; margin: 10px 0; padding: 8px;">Item 1</div>
<div style="color: #333; font-size: 14px; margin: 10px 0; padding: 8px;">Item 2</div>
<div style="color: #333; font-size: 14px; margin: 10px 0; padding: 8px;">Item 3</div>

<!-- AFTER: Centralized styles -->
<style>
  .list-item {
    color: #333;
    font-size: 14px;
    margin: 10px 0;
    padding: 8px;
  }
</style>
<div class="list-item">Item 1</div>
<div class="list-item">Item 2</div>
<div class="list-item">Item 3</div>
```

**Rules:**
- Convert `<b>`, `<i>`, `<u>`, `<font>`, `<center>`, etc. to semantic HTML + inline styles
- Use `<style>` blocks (inline or scoped) for styles applied 2+ times
- Prefer semantic HTML (`<strong>`, `<em>`, `<div>`) over presentation tags
- Use CSS classes for reusable styling patterns
- Keep `<style>` blocks near the top of the file for maintainability

### 9. Production Validation Harness
**CRITICAL: Use production (iblhoops.net) as verification during refactoring.**
- After completing refactoring work, compare localhost against iblhoops.net
- Verify that **text and data output exactly match** between both environments
- Check:
  - Page rendering and layout
  - Data values (stats, names, dates, calculations)
  - List ordering and sorting
  - Form submissions and responses
  - Error messages and validation
- If output does **NOT** match exactly, refactoring is incomplete
- Continue iterating until localhost output matches production exactly
- This ensures no functionality was broken or altered during refactoring

---

## Quick Reference

| Task | Command |
|------|---------|
| Run tests | `cd ibl5 && vendor/bin/phpunit` |
| Run specific | `vendor/bin/phpunit tests/Module/` |
| Schema | `ibl5/schema.sql` |
| Stats formatting | `BasketballStats\StatsFormatter` |

---

## Custom Agents Workflow

Select agents from the agent picker for task-specific workflows:

1. **IBL5 Refactoring** → Extract modules to Repository/Service/View
2. **IBL5 Testing** → Write PHPUnit behavior-focused tests  
3. **IBL5 Security** → Audit for SQL injection/XSS
4. **IBL5 Documentation** → Update docs during PRs
5. **IBL5 Review** → Final validation before merge

Each agent has **handoff buttons** to transition to the next workflow stage.

---

## Resources

- [DEVELOPMENT_GUIDE.md](../DEVELOPMENT_GUIDE.md) - Priorities & status
- [DATABASE_GUIDE.md](../DATABASE_GUIDE.md) - Schema reference
- [ibl5/docs/](../ibl5/docs/) - Architecture, testing, environment guides
