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

## Git Commit Conventions

Commit body format — use `## Section` headers with bullet points:
```
<type>: <short summary>

## Section Header

- Detail 1
- Detail 2
```

**Multiple Claude Instances Warning:** Other Claude instances may have unstaged changes in the working tree. Only stage files YOU modified in this session; leave other unstaged files alone. If unsure, ask the user.

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

**Task-Discovery** (`.github/skills/`):
- `refactoring-workflow/` - Module refactoring with templates
- `security-audit/` - XSS/SQL injection patterns
- `phpunit-testing/` - Test patterns and mocking
- `basketball-stats/` - StatsFormatter usage

## Key References

| Resource | Location |
|----------|----------|
| Schema | `ibl5/schema.sql` |
| Development status | `DEVELOPMENT_GUIDE.md` |
| Database guide | `DATABASE_GUIDE.md` |
| MAMP connection | `ibl5/MAMP_DATABASE_CONNECTION.md` |
| API patterns | `API_GUIDE.md` |
| Interface examples | `classes/Player/Contracts/`, `classes/FreeAgency/Contracts/` |
