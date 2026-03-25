# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

**Context budget rule:** This file and all always-loaded rules/memory files consume ~5K tokens every conversation. When adding content, prefer pointers to conditional files over inline detail. Move reference material (command variants, setup scripts, lookup tables) to path-conditional rules or `memory/` topic files, keeping only error-prevention rules and frequently-needed patterns here.

## Project Overview

IBL5 is an Internet Basketball League fantasy basketball site powered by Jump Shot Basketball simulation engine. PHP 8+ with MariaDB 10.6, using interface-driven architecture.

## Commands

```bash
# Run all tests
bin/test

# Quick pass/fail check
bin/test | tail -3
```

`bin/test` wraps PHPUnit with standard flags (`--no-progress --no-output --testdox-summary`). Pass additional flags directly: `bin/test --filter testMethodName`, `bin/test --testsuite "Module"`. Only read output below `Summary of tests with errors, failures, or issues:`. See `phpunit-tests.md` for full rules.

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

E2E tests do NOT auto-run via hooks — run manually. Requires Docker + `.env.test`. See `playwright-tests.md` for full rules and command variants.

## Architecture

New features should follow the Repository-Service-View pattern. See `ibl5/scripts/scoParser.php` as the canonical refactored example.

### Interface-Driven Modules
All 30 modules in `ibl5/classes/` follow Repository/Service/View pattern with interfaces in `Contracts/` subdirectories. See `php-classes.md` for structure details.

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
- Schema: `ibl5/migrations/000_baseline_schema.sql` - **always verify table/column names here** (check subsequent migrations for alterations)
- **Migrations are the single source of truth.** `000_baseline_schema.sql` is the production snapshot; all subsequent migrations alter it. There is no separate `schema.sql`.
- **Native types enabled:** `MYSQLI_OPT_INT_AND_FLOAT_NATIVE` is set on `$mysqli_db`. See `core-coding.md` for type comparison rules.
- **Docker:** `docker compose up -d` starts MariaDB + PHP-Apache (`http://main.localhost/ibl5/`). See `database-access.md` for connection details and `ibl5/docs/DOCKER_SETUP.md` for full setup.
- **CLI MariaDB access:** `mariadb -h 127.0.0.1 --skip-ssl -u root -proot iblhoops_ibl5`. For quick queries, prefer the `./bin/db-query "SQL"` wrapper.

## Git & Commits

When committing, only include files relevant to the current task. Always review `git diff --staged` before committing. Never commit unrelated files.

### Commit Conventions

Commit body: `<type>: <short summary>` then `## Section` headers with bullet points.

## Frontend / CSS

When debugging CSS layout issues, immediately check for inherited properties like `white-space: nowrap` that may override your fixes. Use browser DevTools-style reasoning: inspect computed styles, not just the element's own rules.

When modifying HTML output, selectors, or user-facing text in View classes, run `bun run test:e2e` before considering the task complete.

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

### Production Validation
After refactoring, compare output against iblhoops.net. Results must match exactly.

