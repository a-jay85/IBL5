# Copilot Coding Agent Instructions for IBL5

## Overview
This repository uses the Copilot coding agent to automate code changes, improvements, and maintenance. Follow these instructions for effective automation.

**📚 Detailed Guides (reference as needed):**
- [ARCHITECTURE_PATTERNS.md](../ibl5/docs/ARCHITECTURE_PATTERNS.md) - Interface-driven architecture, database patterns
- [TESTING_STANDARDS.md](../ibl5/docs/TESTING_STANDARDS.md) - PHPUnit guidelines, test quality principles
- [DEVELOPMENT_ENVIRONMENT.md](../ibl5/docs/DEVELOPMENT_ENVIRONMENT.md) - Setup, dependencies, database connection
- [DOCUMENTATION_STANDARDS.md](../ibl5/docs/DOCUMENTATION_STANDARDS.md) - Documentation organization and lifecycle

---

## Engineering Philosophy

### Operate as an Architect-Level Engineer
- Approach every change with architectural thinking and long-term maintainability
- Write code that is clear, readable, maintainable, and extensible
- Prioritize code quality over speed of delivery
- **Criticize design decisions constructively** - evaluate trade-offs and recommend alternatives

### Pull Request Standards
- **Clarity**: PRs must be easy to understand at a glance
- **Readability**: Follow consistent patterns and naming conventions
- **Maintainability**: Avoid clever code; prefer explicit, self-documenting approaches
- **Completeness**: All tests must pass without warnings or failures

---

## Codebase Architecture

### Technology Stack
- **Current**: PHP-Nuke legacy framework, MySQL
- **Migration Target**: Laravel and/or TypeScript/Svelte/Vite, PostgreSQL
- Write new code with modern PHP practices (namespaces, type hints, etc.)

### Directory Structure
```
ibl5/
├── classes/          # All class files (PSR-4 autoloaded)
├── modules/          # Feature modules (PHP-Nuke style)
├── tests/            # PHPUnit test suite (v12.4+)
└── mainfile.php      # Bootstrap file with class autoloader
```

---

## ⚠️ CRITICAL RULES

### 1. Class Autoloading - NO Manual Requires

**The codebase has a functional class autoloader - DO NOT write `require()` or `require_once()` statements for classes!**

```php
// ✅ CORRECT - Just use the class name
$player = new Player($db);
$team = Team::findById($teamId);

// ❌ WRONG - NEVER do this
require_once 'classes/Player.php';
$player = new Player($db);
```

**Rules:**
- All classes MUST be in `ibl5/classes/` directory
- Class filenames MUST match the class name (e.g., `Player.php` for `Player` class)
- Use proper namespacing for new classes (PSR-4)

### 2. Interface-Driven Architecture

All refactored modules follow the interface contract pattern. **See [ARCHITECTURE_PATTERNS.md](../ibl5/docs/ARCHITECTURE_PATTERNS.md) for complete details.**

**Quick reference:**
```
Module/
├── Contracts/           # Interface definitions
│   └── ModuleRepositoryInterface.php
├── ModuleRepository.php # implements ModuleRepositoryInterface
├── ModuleService.php    # Business logic
└── ModuleView.php       # HTML rendering
```

### 3. Database Implementation Flexibility

**IBL5 supports TWO different database implementations. Always detect and support both:**

```php
if (method_exists($this->db, 'sql_escape_string')) {
    // LEGACY: Use sql_* methods with DatabaseService::escapeString()
    $escaped = \Services\DatabaseService::escapeString($this->db, $input);
    $result = $this->db->sql_query("SELECT * FROM table WHERE name = '$escaped'");
} else {
    // MODERN: Use prepared statements (preferred)
    $stmt = $this->db->prepare("SELECT * FROM table WHERE name = ?");
    $stmt->bind_param('s', $input);
    $stmt->execute();
    $result = $stmt->get_result();
}
```

### 4. Testing Requirements

- **Framework**: PHPUnit 12.4+ (see [TESTING_STANDARDS.md](../ibl5/docs/TESTING_STANDARDS.md))
- **Location**: All tests in `ibl5/tests/`
- **PR Criteria**: No warnings or failures allowed
- **Never use `markTestSkipped()`** - delete tests instead
- **Register all tests** in `ibl5/phpunit.xml`

### 5. Type Hints Required

**ALL functions and methods MUST include complete type hints:**

```php
public function getPlayer(int $playerId): ?Player
public function getTeamRoster(int $teamId): array
public function isPlayerActive(int $playerId): bool
public function logEvent(string $message, ?string $userId = null): void
```

---

## Code Quality Checklist

Before completing any PR:

- [ ] No `require()` or `require_once()` for classes - use autoloader
- [ ] All classes in `ibl5/classes/` directory
- [ ] All tests pass without warnings or failures
- [ ] Complete type hints on all functions/methods
- [ ] Database methods support both legacy and modern implementations
- [ ] All linter warnings and errors fixed
- [ ] Strict types enabled (`declare(strict_types=1);`)
- [ ] No deprecated functions - update all call sites
- [ ] Domain constants are class constants, not function arguments

---

## Best Practices (Condensed)

### View Rendering - Use Output Buffering
```php
public function renderExample(string $title): string
{
    ob_start();
    ?>
<div class="container">
    <h1><?= htmlspecialchars($title) ?></h1>
</div>
    <?php
    return ob_get_clean();
}
```

### Constants - Use Class Constants
```php
// ✅ CORRECT
class PlayerValidator {
    private const MIN_EXPERIENCE = 2;
    private function check(Player $p): bool {
        return $p->experience >= self::MIN_EXPERIENCE;
    }
}

// ❌ WRONG - Don't pass as arguments
private function check(Player $p, int $minExp): bool { ... }
$this->check($player, 2);
```

### Constructor Argument Validation

**Before any class instantiation, verify:**
1. Find the `__construct()` method signature
2. Count required vs optional parameters
3. Ensure every instantiation passes correct arguments
4. Search for all usages: `grep_search "new ClassName"`

### Deprecated Function Handling

1. Search all call sites: `grep_search "oldFunction"`
2. Update every call site to new implementation
3. Delete the deprecated function entirely
4. Run tests to verify

### Refactoring Cleanup

After refactoring:
- Remove unused method parameters (update all call sites)
- Delete dead code and commented-out blocks
- Update PHPDoc to match actual signatures
- Run full test suite

---

## Environment Setup

**See [DEVELOPMENT_ENVIRONMENT.md](../ibl5/docs/DEVELOPMENT_ENVIRONMENT.md) for complete setup.**

### Quick Reference - Cached Dependencies

```bash
# Check if dependencies exist
ls -la ibl5/vendor/bin/phpunit 2>/dev/null && echo "✅ Ready"

# Run tests (if vendor exists)
cd ibl5 && vendor/bin/phpunit

# If vendor doesn't exist
bash bootstrap-phpunit.sh
```

### PHPUnit 12.4.3 Syntax

```bash
# ✅ CORRECT
vendor/bin/phpunit tests/Player/
vendor/bin/phpunit --filter testMethodName

# ❌ WRONG - These don't exist in 12.4.3
vendor/bin/phpunit -v
vendor/bin/phpunit --verbose
```

---

## Database & Statistics

### Schema Reference
- **Location**: `ibl5/schema.sql`
- **Guide**: See [DATABASE_GUIDE.md](../DATABASE_GUIDE.md)

### Key Relationships
- **Player-Team**: `ibl_plr.tid` → `ibl_team_info.teamid`
- **Player ID**: `ibl_plr.pid` (internal), `ibl_plr.uuid` (public API)
- **History**: `ibl_hist` tracks year-by-year player statistics

### Statistics Formatting

Use `Statistics\StatsFormatter` for consistent formatting:
```php
StatsFormatter::formatPercentage($made, $attempted);     // "0.500"
StatsFormatter::formatPerGameAverage($total, $games);    // "12.5"
StatsFormatter::formatPer36Stat($total, $minutes);       // "18.0"
StatsFormatter::calculatePoints($fgm, $ftm, $tgm);       // 25
```

**Reference**: See [ibl5/docs/STATISTICS_FORMATTING_GUIDE.md](../ibl5/docs/STATISTICS_FORMATTING_GUIDE.md)

---

## Documentation Updates

**See [DOCUMENTATION_STANDARDS.md](../ibl5/docs/DOCUMENTATION_STANDARDS.md) for complete standards.**

### During Refactoring PRs

Update documentation incrementally:
1. Update `STRATEGIC_PRIORITIES.md` with completion summary
2. Update `REFACTORING_HISTORY.md` with details
3. Create `ibl5/classes/ModuleName/README.md`
4. Update `DEVELOPMENT_GUIDE.md` status counts
5. Verify all links work

---

## Copilot Agent Behavior

The agent will:
- Open PRs for code changes with detailed descriptions
- Run tests and ensure they pass before completing PRs
- Use the class autoloader (no manual `require()` statements)
- Add complete type hints to all functions/methods
- Fix all type mismatches and linter warnings
- **Zero tolerance for warnings or errors**

The agent will **not** merge PRs automatically; human review is required.

---

## Additional Resources

- [DEVELOPMENT_GUIDE.md](../DEVELOPMENT_GUIDE.md) - Refactoring priorities, module status
- [DATABASE_GUIDE.md](../DATABASE_GUIDE.md) - Schema reference, migrations
- [API_GUIDE.md](../API_GUIDE.md) - API development with UUIDs, views, caching
- [ibl5/docs/STRATEGIC_PRIORITIES.md](../ibl5/docs/STRATEGIC_PRIORITIES.md) - Strategic analysis
- [ibl5/docs/REFACTORING_HISTORY.md](../ibl5/docs/REFACTORING_HISTORY.md) - Complete timeline
- [Copilot Coding Agent Best Practices](https://gh.io/copilot-coding-agent-tips)
- [Conventional Commits](https://www.conventionalcommits.org/)
