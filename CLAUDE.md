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
# IMPORTANT: CWD is usually ibl5/ (from "cd ibl5 && ..." commands), so use ./bin/db-query
./bin/db-query "SELECT * FROM ibl_plr LIMIT 5"
./bin/db-query "SELECT COUNT(*) FROM ibl_team_info"
./bin/db-query "DESCRIBE ibl_plr"
```

**db-query pitfalls:**
- **Never prefix with `cd ibl5 &&`.** CWD persists between Bash calls, so after any `cd ibl5 && ...` command (e.g., phpunit), you're already in `ibl5/`. A second `cd ibl5` fails because `ibl5/ibl5/` doesn't exist. Just use `./bin/db-query` directly.
- **Never use `!=` in SQL queries passed via double quotes.** Bash interprets `!` as history expansion inside double quotes, mangling the query (`sh: : command not found`). Use SQL's `<>` operator instead: `./bin/db-query "SELECT * FROM t WHERE col <> ''"`.

**When to use `db-query`:** Use this script to explore the database schema, verify data after making changes, check record counts, and validate your work. This is the preferred method for Claude to query the local database since it's configured for auto-approval in the user's Claude Code settings.

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

### Multiple Claude Instances Protocol

Other Claude instances may be working in this directory simultaneously.

1. **Before editing a file:** Run `git status`. If the file has unstaged changes you didn't make, alert the user before proceeding.
2. **Scope discipline:** Only modify files directly related to your task. If you need to change a shared file, confirm with the user first.
3. **Before staging:** Run `git diff --name-only` and only stage files you personally modified. Never use `git add .` or `git add -A`.
4. **Testing:** Always run the full test suite, even if other instances may have partial work in progress. If another instance's in-progress changes cause failures in files you did not touch, note them but do not suppress them.

## PHP / Database Gotchas

- PHP class constants cannot be interpolated in double-quoted strings; use concatenation instead.
- The `active` field on players means "on a depth chart", NOT "active/retired status".
- `MYSQLI_OPT_INT_AND_FLOAT_NATIVE` affects `COALESCE` — nullable LEFT JOIN columns may still produce `null` despite COALESCE.
- Database views may filter results unexpectedly; check view definitions before assuming query bugs.

## Frontend / CSS

When debugging CSS layout issues, immediately check for inherited properties like `white-space: nowrap` that may override your fixes. Use browser DevTools-style reasoning: inspect computed styles, not just the element's own rules.

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

### PR Documentation Checklist
After completing a module refactoring or significant feature, update these files:
- `STRATEGIC_PRIORITIES.md` — mark module complete
- `REFACTORING_HISTORY.md` — add entry for the work done
- `ibl5/classes/ModuleName/README.md` — create module README
- `ibl5/docs/DEVELOPMENT_GUIDE.md` — update module counts and status

### Production Validation
After refactoring, compare output against iblhoops.net. Results must match exactly.

### Post-Plan-Approval Workflow (Mandatory)

**This workflow is MANDATORY.** After any plan is finalized and the user approves it, Claude MUST execute this entire workflow autonomously without waiting for user prompts between steps. Do not ask "should I proceed?" — just execute each step in order.

#### Phase 1: Branch & Worktree Setup
1. Create a new branch from `master` with a descriptive name (e.g., `feat/feature-name`, `fix/bug-name`, `refactor/module-name`)
2. Create a new git worktree in the `worktrees/` directory: `git worktree add worktrees/<branch-name> <branch-name>`
3. Change working directory to the new worktree

#### Phase 2: Implementation
4. Implement the approved plan in the worktree
5. Run the full PHPUnit test suite — fix any failures, notices, deprecations, or warnings until clean
6. Run PHPStan (`composer run analyse`) — fix any errors above baseline until clean

#### Phase 3: Commit, Push & PR
7. Run `/commit-commands:commit-push-pr` to commit all changes, push the branch, and open a PR

#### Phase 4: Code Review
8. Run `/code-review:code-review` on the PR
9. If code review finds issues at or above the configured threshold: fix all issues immediately, then commit the fixes (use `/commit-commands:commit` and push)
10. Re-run `/code-review:code-review` to verify — repeat until clean

#### Phase 5: Security Review
11. Run `/security-audit` on the changed files
12. If security review finds any issues: fix all issues immediately, then commit the fixes (use `/commit-commands:commit` and push)
13. Re-run `/security-audit` to verify — repeat until clean

#### Phase 6: Final Verification
14. Run the full PHPUnit test suite one final time — confirm zero failures, errors, notices, deprecations, or risky tests
15. Run PHPStan one final time — confirm zero errors above baseline
16. Report completion to the user with a summary of what was done

**Important notes:**
- If any step fails repeatedly (3+ attempts), stop and ask the user for guidance
- The worktree keeps `master` clean while working on the feature branch
- After the PR is merged, clean up the worktree with `git worktree remove worktrees/<branch-name>`

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
