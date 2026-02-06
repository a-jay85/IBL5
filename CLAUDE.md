# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

IBL5 is an Internet Basketball League fantasy basketball site powered by Jump Shot Basketball simulation engine. PHP 8+ with MariaDB 10.6, using interface-driven architecture.

## Commands

```bash
# Run all tests
cd ibl5 && vendor/bin/phpunit --no-progress --no-output --testdox-summary

# Run single test file
cd ibl5 && vendor/bin/phpunit --no-progress --no-output --testdox-summary tests/Player/PlayerRepositoryTest.php

# Run single test method
cd ibl5 && vendor/bin/phpunit --no-progress --no-output --testdox-summary --filter testMethodName

# Run specific test suite
cd ibl5 && vendor/bin/phpunit --no-progress --no-output --testdox-summary --testsuite "Player Module Tests"

# Show ALL issues (deprecations, warnings, notices, risky tests, etc.)
cd ibl5 && vendor/bin/phpunit --no-progress --no-output --testdox-summary --display-all-issues

# Use specific config (e.g., CI config without local-only tests)
cd ibl5 && vendor/bin/phpunit --no-progress --no-output --testdox-summary -c phpunit.ci.xml
```

**PHPUnit output rule:** Always use `--no-progress --no-output --testdox-summary`. Only read output below `Summary of tests with errors, failures, or issues:` — this shows `OK (X tests, X assertions)` when passing, or only the failures/errors. Ignore everything above that line to save tokens.

**Note:** PHPUnit 12.x has no `-v`/`--verbose`. Use `--display-all-issues` instead. See `phpunit-tests.md` for full testing rules and completion criteria.

**Post-change test rule:** After making ANY code changes (source or test files), always run the **full** test suite (`vendor/bin/phpunit --no-progress --no-output --testdox-summary`), not just the tests for the module you changed. Changes in one module can break tests in other modules that depend on the same code.

### Static Analysis (PHPStan)

```bash
# Run PHPStan (level max + strict-rules + bleedingEdge)
cd ibl5 && composer run analyse
```

**PHPStan gate rule:** Run `composer run analyse` **before** creating or running unit tests. If your changes introduce new errors above the baseline, fix them before proceeding. The only exception: errors clearly caused by another Claude instance's simultaneous changes to files you did not touch — those may be ignored.

**Write PHPStan-clean code proactively.** Don't rely on the analyser to catch mistakes. The project runs level `max` with `phpstan-strict-rules` and `bleedingEdge`, which means:

- **No `mixed` leakage:** Never pass, return, or operate on `mixed`. Narrow types from database results, arrays, and function returns before use — via type checks, assertions, or PHPDoc `@var` annotations on fetched rows.
- **Explicit return types & parameter types:** Every method/function must have complete native type declarations. Use union types (`int|string`) or generics (`array<int, Player>`) where needed.
- **No loose comparisons:** `===`/`!==` only. `in_array()` must pass `true` as the third argument. Never use `empty()` — check the specific condition instead (`=== ''`, `=== []`, `=== null`).
- **Null safety:** Never call methods or access properties on possibly-null values without a null check. Use `?->`, `??`, or explicit guards.
- **No dead code:** Don't write always-true/false conditions, unreachable branches, or unused variables/parameters.
- **Strict boolean context:** Never use non-boolean expressions (int, string, array) as bare if-conditions. Write explicit comparisons (`$count > 0`, `$name !== ''`, `$items !== []`).
- **Array shapes:** Use PHPDoc `array{key: type, ...}` shapes for structured arrays (especially database rows) so PHPStan can verify field access.
- **No deprecated APIs:** Don't use deprecated PHP functions, class methods, or constants.
- **PHPUnit awareness:** The `phpstan-phpunit` extension understands `assertSame`, `expectException`, etc. — write assertions that align with PHPStan's type narrowing (e.g., `assertInstanceOf` narrows the type in subsequent code).

## Architecture

### Interface-Driven Modules
All 30 modules follow Repository/Service/View pattern with interfaces in `Contracts/` subdirectories:
```
ibl5/classes/
├── Player/
│   ├── Contracts/           # Interfaces (PlayerRepositoryInterface, etc.)
│   ├── PlayerRepository.php # Database operations
│   ├── PlayerService.php    # Business logic
│   └── PlayerView.php       # HTML rendering
├── FreeAgency/
├── Trading/
└── ... (30 modules total)
```

### Legacy (Non-IBL) Modules
- **SiteStatistics:** A legacy PHP-Nuke module for tracking site visitor/page-view statistics. It is **not** basketball- or IBL-related and should be deprioritized against core IBL modules during refactoring or feature work.

### Key Patterns
- **Repository:** Database queries via prepared statements
- **Service:** Business logic, validation, calculations
- **View:** HTML generation with XSS protection
- **Controller:** Request handling (where applicable)

### Class Autoloading
Classes autoload from `ibl5/classes/`. Never use `require_once`.

### Database
- Schema: `ibl5/schema.sql` - **always verify table/column names here**
- Use `$mysqli_db` (modern MySQLi) over legacy `$db`
- 52 InnoDB tables with foreign keys, 84 legacy MyISAM tables
- **Native types enabled:** `MYSQLI_OPT_INT_AND_FLOAT_NATIVE` is set on both `$mysqli_db` (in `db/db.php`) and `DatabaseConnection` (in `classes/DatabaseConnection.php`). INT columns return PHP `int`, FLOAT columns return PHP `float`, VARCHAR/TEXT columns return PHP `string`. Compare with native types accordingly (e.g., `=== 0` for INT columns, `=== '0'` for VARCHAR columns). The legacy `$db` connection does NOT have native types.

### Local MAMP Database Connection

**Connection Details:**
- Host: `localhost` or `127.0.0.1`
- Port: `3306`
- Database: `iblhoops_ibl5`
- Socket: `/Applications/MAMP/tmp/mysql/mysql.sock`
- Credentials: See `ibl5/config.php` (`$dbuname`, `$dbpass`)

**PHP Connection (app standard):**
```php
// Via app bootstrap (standard way)
require_once 'autoloader.php';
include 'config.php';
include 'db/db.php';
// $mysqli_db and $db are now available globally
```

**PHP Connection (for tests/standalone):**
```php
// Use DatabaseConnection helper class
require_once 'classes/DatabaseConnection.php';
$player = DatabaseConnection::fetchRow("SELECT * FROM ibl_plr WHERE pid = ?", [123]);
$players = DatabaseConnection::fetchAll("SELECT * FROM ibl_plr LIMIT 10");
$count = DatabaseConnection::fetchValue("SELECT COUNT(*) FROM ibl_plr");
```

**Command Line Access:**
```bash
# IMPORTANT: Use MAMP's mysql client, NOT Homebrew mysql
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  --socket=/Applications/MAMP/tmp/mysql/mysql.sock \
  -u root -p'root' \
  iblhoops_ibl5
```

**Why MAMP's client?** Homebrew's mysql client has authentication plugin incompatibility with MAMP's MySQL 8.0 server. Always use `/Applications/MAMP/Library/bin/mysql80/bin/mysql`.

**Claude Code Database Queries (Auto-Approved):**
```bash
# Use this wrapper script for database queries - it auto-approves without user confirmation
ibl5/bin/db-query "SELECT * FROM ibl_plr LIMIT 5"
ibl5/bin/db-query "SELECT COUNT(*) FROM ibl_team_info"
ibl5/bin/db-query "DESCRIBE ibl_plr"
```

**When to use `db-query`:** Use this script to explore the database schema, verify data after making changes, check record counts, and validate your work. This is the preferred method for Claude to query the local database since it's configured for auto-approval in the user's Claude Code settings.

## Git Workflow

**After committing:** Always suggest `/mergeAndPush` (not a bare `git push`). Only suggest it as a clickable inline prompt — do NOT mention it in your text messages.

## Git Commit Conventions

Commit body format — use `## Section` headers with bullet points:
```
<type>: <short summary>

## Section Header

- Detail 1
- Detail 2
```

### Multiple Claude Instances Protocol

Other Claude instances may be working in this directory simultaneously.

1. **Before editing a file:** Run `git status`. If the file has unstaged changes you didn't make, alert the user before proceeding.
2. **Scope discipline:** Only modify files directly related to your task. If you need to change a shared file, confirm with the user first.
3. **Before staging:** Run `git diff --name-only` and only stage files you personally modified. Never use `git add .` or `git add -A`.
4. **Testing:** If other instances may have partial work in progress, prefer running your module's test suite over the full suite.

## Mandatory Rules

### XSS Protection
Use `Utilities\HtmlSanitizer::safeHtmlOutput()` on ALL output (database results, user input, error messages).

**`htmlspecialchars()` type rule:** PHP 8.1+ requires the first argument to be a string. Never pass integers, floats, or null — this causes a `TypeError` at runtime. For integer values (player IDs, salaries, ages, team IDs, ratings), use `(int)` casting instead — integers cannot contain HTML special characters and do not need escaping. Only use `htmlspecialchars()` on actual string data (names, cities, user input). Remove any existing `htmlspecialchars()` calls wrapping values that are already known to be integers.

### Type Safety (Strict Types)
Every PHP file must have `declare(strict_types=1);` at the top. Additional requirements:

- **Typed properties:** All class properties must have type declarations
- **Typed methods:** All parameters and return types must be declared
- **Strict equality:** Always use `===` and `!==`, never `==` or `!=`
- **Null handling:** Use nullable types (`?string`) and null coalescing (`??`) appropriately

### Statistics Formatting
Use `BasketballStats\StatsFormatter` for all stats - never `number_format()` directly.

### CSS Centralization
All CSS styles for modules and pages must be placed in `ibl5/design/components/`. Never write `<style>` blocks or CSS-generating methods in PHP class files. For dynamic team colors, use CSS custom properties set via inline `style` attributes on container elements, with the corresponding rules in centralized CSS files.

### HTML Modernization
See `view-rendering.md` for the full deprecated-tag replacement table.

### Production Validation
After refactoring, compare output against iblhoops.net. Results must match exactly.

## Progressive Loading

Context-aware rules auto-load when relevant:

**Path-Conditional** (`.claude/rules/`):
- `php-classes.md` → editing `ibl5/classes/**/*.php`
- `phpunit-tests.md` → editing `ibl5/tests/**/*.php`
- `view-rendering.md` → editing `**/*View.php`

**Task-Discovery** (`.claude/skills/`):
- `refactoring-workflow/` - Module refactoring with templates
- `security-audit/` - XSS/SQL injection patterns
- `phpunit-testing/` - Test patterns and mocking
- `basketball-stats/` - StatsFormatter usage
- `contract-rules/` - CBA salary cap rules
- `database-repository/` - BaseMysqliRepository patterns
- `code-review/` - PR validation checklist
- `documentation-updates/` - Doc update workflow

## Key References

| Resource | Location |
|----------|----------|
| Schema | `ibl5/schema.sql` |
| Development status | `DEVELOPMENT_GUIDE.md` |
| Database guide | `DATABASE_GUIDE.md` |
| MAMP connection | `ibl5/MAMP_DATABASE_CONNECTION.md` |
| API patterns | `API_GUIDE.md` |
| Interface examples | `classes/Player/Contracts/`, `classes/FreeAgency/Contracts/` |
