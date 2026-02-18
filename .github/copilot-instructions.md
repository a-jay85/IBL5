# IBL5 Universal Coding Rules

**Progressive loading enabled:** Detailed specifications are in `.claude/rules/` and `.github/skills/`. See [SKILLS_GUIDE.md](SKILLS_GUIDE.md) for architecture details.

---

## 🔍 ALWAYS VERIFY (Mandatory Code Review)

**CRITICAL: Regardless of task focus, ALWAYS check for and fix these violations:**

### PR Implementation (Rule #0)
- Review ALL comments on the PR, not just highlighted lines
- Search across ALL files for similar issues where comments were NOT left
- Fix every instance of identified patterns, not just commented lines

### XSS Protection (MANDATORY)
- Use `Utilities\HtmlSanitizer::safeHtmlOutput()` on ALL dynamic content
- Check: database results, form inputs, player names, error messages
- **Never skip this check** - applies during ANY code work

### HTML/CSS Modernization (MANDATORY)
Replace deprecated tags immediately:
- `<b>` → `<strong style="font-weight: bold;">`
- `<i>` → `<em style="font-style: italic;">`
- `<font>` → `<span style="...">`
- `<center>` → `<div style="text-align: center;">`
- `border=1` → `style="border: 1px solid #000; border-collapse: collapse;"`

---

## ⚠️ CRITICAL RULES (Always Apply)

### 1. Class Autoloading
```php
// ✅ Just use the class name
$player = new Player($db);

// ❌ NEVER require classes manually
```
Classes in `ibl5/classes/`, filename = class name.

### 2. Type Hints Required
```php
public function getPlayer(int $playerId): ?Player
```
`declare(strict_types=1);` in every file.

### 3. Database Object Preference
Use `$mysqli_db` (modern MySQLi) over legacy `$db`.

### 4. Schema Verification
**Always verify table/column names in `ibl5/schema.sql`** before writing queries. Never assume structures exist.

### 5. Statistics Formatting
Use `BasketballStats\StatsFormatter` for ALL stats formatting (never `number_format()` directly).

### 6. No Unused Methods
Only implement methods with active callers. Dead code increases maintenance burden.

### 7. Production Validation
After refactoring, compare localhost against iblhoops.net. Output must match exactly.

---

## Quick Reference

| Task | Command |
|------|---------|
| Run tests | `cd ibl5 && vendor/bin/phpunit` |
| Schema | `ibl5/schema.sql` |
| Stats formatting | `BasketballStats\StatsFormatter` |
| Skills guide | `.github/SKILLS_GUIDE.md` |

---

## Resources

- [DEVELOPMENT_GUIDE.md](../ibl5/docs/DEVELOPMENT_GUIDE.md) - Status & priorities
- [DATABASE_GUIDE.md](../ibl5/docs/DATABASE_GUIDE.md) - Schema reference
- [SKILLS_GUIDE.md](SKILLS_GUIDE.md) - Progressive loading architecture
- `.claude/rules/` - Path-conditional rules (auto-load by file)
- `.github/skills/` - Task-discovery skills (auto-load by intent)
