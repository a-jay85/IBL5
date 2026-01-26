# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

IBL5 is an Internet Basketball League fantasy basketball site powered by Jump Shot Basketball simulation engine. PHP 8+ with MariaDB 10.6, using interface-driven architecture.

## Commands

```bash
# Run all tests
cd ibl5 && vendor/bin/phpunit

# Run single test file
cd ibl5 && vendor/bin/phpunit tests/Player/PlayerRepositoryTest.php

# Run single test method
cd ibl5 && vendor/bin/phpunit --filter testMethodName

# Run specific test suite
cd ibl5 && vendor/bin/phpunit --testsuite "Player Module Tests"

# Show ALL issues (deprecations, warnings, notices, risky tests, etc.)
cd ibl5 && vendor/bin/phpunit --display-all-issues

# Use specific config (e.g., CI config without local-only tests)
cd ibl5 && vendor/bin/phpunit -c phpunit.ci.xml
```

### PHPUnit 12.x Display Options
By default, PHPUnit 12.x only shows summary counts for non-failures. Use these flags to see details:
- `--display-all-issues` - Show ALL issue details (recommended)
- `--display-deprecations` - Show deprecation details
- `--display-warnings` - Show warning details
- `--display-notices` - Show notice details
- `--display-phpunit-deprecations` - Show PHPUnit deprecation details

**Note:** `-v`/`--verbose` do NOT exist in PHPUnit 12.x. Use `--display-all-issues` instead.

### Test Completion Requirements
**IMPORTANT:** Before considering any PHPUnit-related task complete:
1. Run the full test suite: `vendor/bin/phpunit`
2. Verify zero warnings, zero failures, zero errors
3. If warnings exist, resolve them (don't just silence them unless truly necessary)
4. The final output should show `OK (X tests, Y assertions)` with no issues

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

## Git Commit Conventions

When committing changes, use this format:

1. **Short message (first line):** Concise summary using conventional commit style (e.g., `feat:`, `fix:`, `test:`, `docs:`, `refactor:`)
2. **Long message (body):** Detailed summary with sections, bullet points, and context

**Format:**
```
<type>: <short summary>

## Section Header

<detailed description with bullet points>

- Item 1
- Item 2

## Another Section

<more details>

Co-Authored-By: Claude Opus 4.5 <noreply@anthropic.com>
```

**Important:** When asked to commit, only stage files that were modified as part of the current task. Check `git status` first and be selective about what to stage.

## Mandatory Rules

### XSS Protection
Use `Utilities\HtmlSanitizer::safeHtmlOutput()` on ALL output (database results, user input, error messages).

### Type Hints
`declare(strict_types=1);` in every file. Full type hints on all methods.

### Statistics Formatting
Use `BasketballStats\StatsFormatter` for all stats - never `number_format()` directly.

### HTML Modernization
Replace deprecated tags: `<b>` → `<strong>`, `<font>` → `<span>`, `<center>` → `<div style="text-align: center;">`.

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
