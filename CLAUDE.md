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

**Token-saving tip:** When merely checking if tests pass (not debugging failures), append `| tail -n 3` to commands to output only the final summary. Example: `cd ibl5 && vendor/bin/phpunit --no-progress --no-output --testdox-summary | tail -n 3`

**Note:** PHPUnit 13.x has no `-v`/`--verbose`. Use `--display-all-issues` instead. See `phpunit-tests.md` for full testing rules and completion criteria.

### Static Analysis (PHPStan)

```bash
# Run PHPStan (level max + strict-rules + bleedingEdge)
cd ibl5 && composer run analyse
```

**Note:** PHPStan and the **full** PHPUnit test suite run automatically via PostToolUse hooks after every Edit/Write — no need to run them manually between edits. If your changes introduce new errors above the baseline, fix them before proceeding. The only exception: errors clearly caused by another Claude instance's simultaneous changes to files you did not touch — those may be ignored.

**Full test suite rule:** Always run the **full** PHPUnit test suite (no `--testsuite` or `--filter` flags) after making PHP changes and before considering any task complete. Changes in one module frequently break tests in other modules (e.g., updating a shared mock, interface, or base class). Only use `--testsuite` or `--filter` when actively debugging a specific failing test — then re-run the full suite once it passes.

**Write PHPStan-clean code proactively.** The project runs level `max` with `phpstan-strict-rules` and `bleedingEdge`. See `php-classes.md` for the full list of PHPStan rules.

## Architecture

New features should follow the Repository-Service-View pattern. See `ibl5/scripts/scoParser.php` as the canonical refactored example.

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

### OneOnOneGame Warning
`classes/OneOnOneGame/` is NOT a representation of how the Jump Shot Basketball (JSB) simulation engine works. It was created as a mini-game by fans of JSB, and may have similarities in logic, but it should not be interpreted as a faithful representation of how the JSB engine works. In terms of using it to understand JSB, pretend it does not exist.

### Key Patterns
- **Repository:** Database queries via prepared statements
- **Service:** Business logic, validation, calculations
- **View:** HTML generation with XSS protection
- **Controller:** Request handling (where applicable)

### Class Autoloading
Classes autoload from `ibl5/classes/`. Never use `require_once`.

### Database
- Schema: `ibl5/schema.sql` - **always verify table/column names here**
- **Schema is reference-only:** `schema.sql` is a snapshot of production's database schema. Never edit it directly to make schema changes. Instead, create migration files. `schema.sql` will not be reimported into production.
- Use `$mysqli_db` (modern MySQLi) over legacy `$db`
- 51 InnoDB tables with foreign keys, 84 legacy MyISAM tables, 23 database views
- **Native types enabled:** `MYSQLI_OPT_INT_AND_FLOAT_NATIVE` is set on `$mysqli_db` (in `db/db.php`). INT columns return PHP `int`, FLOAT columns return PHP `float`, VARCHAR/TEXT columns return PHP `string`. Compare with native types accordingly (e.g., `=== 0` for INT columns, `=== '0'` for VARCHAR columns). The legacy `$db` connection does NOT have native types.
- **MAMP connection & db-query script:** See `database-access.md` for local connection details and the auto-approved `./bin/db-query` wrapper.

## Git & Commits

**After committing:** Always suggest `/mergeAndPush` (not a bare `git push`). Only suggest it as a clickable inline prompt — do NOT mention it in your text messages.

When committing, only include files relevant to the current task. Always review `git diff --staged` before committing. Never commit unrelated files.

### Commit Conventions

Commit body format — use `## Section` headers with bullet points:
```
<type>: <short summary>

## Section Header

- Detail 1
- Detail 2
```

## PHP / Database Gotchas

- PHP class constants cannot be interpolated in double-quoted strings; use concatenation instead.
- The `active` field on players means "on a depth chart", NOT "active/retired status".
- `MYSQLI_OPT_INT_AND_FLOAT_NATIVE` affects `COALESCE` — nullable LEFT JOIN columns may still produce `null` despite COALESCE.
- Database views may filter results unexpectedly; check view definitions before assuming query bugs.

## Frontend / CSS

When debugging CSS layout issues, immediately check for inherited properties like `white-space: nowrap` that may override your fixes. Use browser DevTools-style reasoning: inspect computed styles, not just the element's own rules.

## Mandatory Rules

### XSS Protection
Use `Utilities\HtmlSanitizer::safeHtmlOutput()` (or its short alias `HtmlSanitizer::e()`) on ALL output (database results, user input, error messages). Both methods accept `mixed` and return `string` — no type annotations needed at call sites. Prefer `e()` in View templates for brevity.

### Type Safety (Strict Types)
Every PHP file must have `declare(strict_types=1);` at the top (enforced by PHPStan custom rule `RequireStrictTypesRule`). Additional requirements:

- **Typed properties:** All class properties must have type declarations
- **Typed methods:** All parameters and return types must be declared
- **Strict equality:** Always use `===` and `!==`, never `==` or `!=`
- **Null handling:** Use nullable types (`?string`) and null coalescing (`??`) appropriately

### CSS & HTML Rules
- All CSS must go in `ibl5/design/components/`. Never write `<style>` blocks or CSS-generating methods in PHP class files.
- Use `BasketballStats\StatsFormatter` for all stats — `number_format()` is banned by PHPStan custom rule `BanNumberFormatRule` (except inside StatsFormatter itself).
- See `view-rendering.md` for HTML modernization and deprecated-tag replacement table.

### PR Documentation Checklist
After completing a module refactoring or significant feature, update these files:
- `STRATEGIC_PRIORITIES.md` — mark module complete
- `REFACTORING_HISTORY.md` — add entry for the work done
- `ibl5/classes/ModuleName/README.md` — create module README
- `ibl5/docs/DEVELOPMENT_GUIDE.md` — update module counts and status

### Production Validation
After refactoring, compare output against iblhoops.net. Results must match exactly.

## Progressive Loading

Context-aware rules auto-load when relevant:

**Path-Conditional** (`.claude/rules/`):
- `php-classes.md` → editing `ibl5/classes/**/*.php`
- `phpunit-tests.md` → editing `ibl5/tests/**/*.php`
- `view-rendering.md` → editing `**/*View.php`
- `database-access.md` → editing `**/*Repository.php`

**Task-Discovery** (`.claude/skills/`):
- `refactoring-workflow/` - Module refactoring with templates
- `security-audit/` - XSS/SQL injection patterns
- `phpunit-testing/` - Test patterns and mocking
- `basketball-stats/` - StatsFormatter usage
- `contract-rules/` - CBA salary cap rules
- `database-repository/` - BaseMysqliRepository patterns
- `documentation-updates/` - Doc update workflow

## Key References

| Resource | Location |
|----------|----------|
| Schema | `ibl5/schema.sql` |
| Development status | `ibl5/docs/DEVELOPMENT_GUIDE.md` |
| Database guide | `ibl5/docs/DATABASE_GUIDE.md` |
| MAMP connection | `ibl5/docs/DEVELOPMENT_ENVIRONMENT.md` |
| API patterns | `ibl5/docs/API_GUIDE.md` |
| Interface examples | `classes/Player/Contracts/`, `classes/FreeAgency/Contracts/` |
