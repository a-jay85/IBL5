# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

**Context budget rule:** This file and all always-loaded rules/memory files consume ~5K tokens every conversation. When adding content, prefer pointers to conditional files over inline detail. Move reference material (command variants, setup scripts, lookup tables) to path-conditional rules or `memory/` topic files, keeping only error-prevention rules and frequently-needed patterns here.

## Project Overview

IBL5 is an Internet Basketball League fantasy basketball site powered by Jump Shot Basketball simulation engine. PHP 8+ with MariaDB 10.6, using interface-driven architecture.

## Commands

```bash
# Run all tests (always use these flags)
cd ibl5 && vendor/bin/phpunit --no-progress --no-output --testdox-summary

# Quick pass/fail check (append | tail -n 3)
cd ibl5 && vendor/bin/phpunit --no-progress --no-output --testdox-summary | tail -n 3
```

**PHPUnit output rule:** Always use `--no-progress --no-output --testdox-summary`. Only read output below `Summary of tests with errors, failures, or issues:`. Add `--filter`, `--testsuite`, `--display-all-issues`, or `-c phpunit.ci.xml` as needed. See `phpunit-tests.md` for full rules.

### Static Analysis (PHPStan)

```bash
# Run PHPStan (level max + strict-rules + bleedingEdge)
cd ibl5 && composer run analyse
```

**Note:** PHPStan and the **full** PHPUnit test suite run automatically via PostToolUse hooks after every Edit/Write — no need to run them manually between edits. If your changes introduce new errors above the baseline, fix them before proceeding. The only exception: errors clearly caused by another Claude instance's simultaneous changes to files you did not touch — those may be ignored.

**Full test suite rule:** Always run the **full** PHPUnit test suite (no `--testsuite` or `--filter` flags) after making PHP changes and before considering any task complete. Changes in one module frequently break tests in other modules (e.g., updating a shared mock, interface, or base class). Only use `--testsuite` or `--filter` when actively debugging a specific failing test — then re-run the full suite once it passes.

**Write PHPStan-clean code proactively.** The project runs level `max` with `phpstan-strict-rules` and `bleedingEdge`. See `php-classes.md` for the full list of PHPStan rules.

### E2E Tests (Playwright)

```bash
cd ibl5 && bun run test:e2e
```

E2E tests do NOT auto-run via hooks — run manually. Requires MAMP + `.env.test`. See `playwright-tests.md` for full rules and command variants.

## Architecture

New features should follow the Repository-Service-View pattern. See `ibl5/scripts/scoParser.php` as the canonical refactored example.

### Interface-Driven Modules
All 30 modules in `ibl5/classes/` follow Repository/Service/View pattern with interfaces in `Contracts/` subdirectories. See `php-classes.md` for structure details.

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
- **Native types enabled:** `MYSQLI_OPT_INT_AND_FLOAT_NATIVE` is set on `$mysqli_db`. See `core-coding.md` for type comparison rules. The legacy `$db` connection does NOT have native types.
- **MAMP connection & db-query script:** See `database-access.md` for local connection details and the auto-approved `./bin/db-query` wrapper.
- **CLI MySQL access:** Always use MAMP's mysql client (`/Applications/MAMP/Library/bin/mysql80/bin/mysql --socket=/Applications/MAMP/tmp/mysql/mysql.sock -u root -p'root'`), NOT Homebrew's `mysql`. Homebrew MySQL has authentication plugin incompatibility with MAMP's server. For quick queries, prefer the `./bin/db-query "SQL"` wrapper.

## Git & Commits

When committing, only include files relevant to the current task. Always review `git diff --staged` before committing. Never commit unrelated files.

### Commit Conventions

Commit body: `<type>: <short summary>` then `## Section` headers with bullet points.

## PHP / Database Gotchas

- PHP class constants cannot be interpolated in double-quoted strings; use concatenation instead.
- The `active` field on players means "on a depth chart", NOT "active/retired status".
- `MYSQLI_OPT_INT_AND_FLOAT_NATIVE` affects `COALESCE` — nullable LEFT JOIN columns may still produce `null` despite COALESCE.
- Database views may filter results unexpectedly; check view definitions before assuming query bugs.

## Frontend / CSS

When debugging CSS layout issues, immediately check for inherited properties like `white-space: nowrap` that may override your fixes. Use browser DevTools-style reasoning: inspect computed styles, not just the element's own rules.

When modifying HTML output, selectors, or user-facing text in View classes, run `bun run test:e2e` before considering the task complete.

## Workflow Continuity

Post-plan Phases 3-8 are consolidated into a single `/post-plan` skill invocation. After Phase 2 (Implementation), invoke `/post-plan` which handles simplify, commit/push/PR, code review, security audit, verification, CI monitoring, and retrospective internally.

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

**Always Loaded** (`.claude/rules/`):
- `workflow-continuity.md` → `/post-plan` orchestrator rules for post-plan workflow
- `core-coding.md` → key constants, common repository helpers, gotchas
- `environment.md` → bun, CSS, IBLbot commands

**Path-Conditional** (`.claude/rules/`):
- `php-classes.md` → editing `ibl5/classes/**/*.php`
- `phpunit-tests.md` → editing `ibl5/tests/**/*.php`
- `playwright-tests.md` → editing `ibl5/tests/e2e/**/*.ts`
- `view-rendering.md` → editing `**/*View.php`
- `database-access.md` → editing `**/*Repository.php`

**Task-Discovery** (`.claude/skills/`): Discovered automatically when relevant skills are invoked. Includes refactoring-workflow, security-audit, phpunit-testing, basketball-stats, contract-rules, database-repository, documentation-updates.
