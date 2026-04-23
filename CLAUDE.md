---
description: Root Claude Code instructions for IBL5: commands, mandatory rules, and architecture pointers.
last_verified: 2026-04-23
---

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

**Context budget rule:** This file and all always-loaded rules/memory files consume ~5K tokens every conversation. When adding content, prefer pointers to conditional files over inline detail. Move reference material (command variants, setup scripts, lookup tables) to path-conditional rules or `memory/` topic files, keeping only error-prevention rules and frequently-needed patterns here.

## Project Overview

IBL5 is an Internet Basketball League fantasy basketball site powered by Jump Shot Basketball simulation engine. PHP 8+ with MariaDB 10.11, using interface-driven architecture.

## Commands

```bash
bin/test                    # Run all PHPUnit tests
bin/test | tail -3          # Quick pass/fail check
cd ibl5 && composer run analyse  # PHPStan (level max + strict-rules + bleedingEdge)
cd ibl5 && bun run test:e2e      # Playwright E2E (requires Docker + .env.test)
```

**Full test suite rule:** Always run `bin/test` (no `--filter`/`--testsuite`) after PHP changes and before considering any task complete. Only use targeted flags when actively debugging — then re-run the full suite.

PHPStan and PHPUnit auto-run via PostToolUse hooks on task completion when PHP files changed. E2E tests do NOT auto-run. See `phpunit-tests.md` and `playwright-tests.md` for full rules.

## Architecture

New features should follow the Repository-Service-View pattern. See `Waivers/` (Repository, Service, Processor, Validator, View, Controller) as the canonical refactored example.

### Interface-Driven Modules
The modules in `ibl5/classes/` follow Repository/Service/View pattern with interfaces in `Contracts/` subdirectories. See `php-classes.md` for structure details. A machine-generated module map (file counts, roles, cross-module dependencies) is in `codebase-map.md` — consult it before broad codebase searches.

### Legacy (Non-IBL) Modules
- **SiteStatistics:** A legacy PHP-Nuke module for tracking site visitor/page-view statistics. It is **not** basketball- or IBL-related and should be deprioritized against core IBL modules during refactoring or feature work.

### Key Patterns
- **Repository:** Database queries via prepared statements
- **Service:** Business logic, validation, calculations
- **View:** HTML generation with XSS protection
- **Controller:** Request handling (where applicable)

### Class Autoloading
Classes autoload from `ibl5/classes/`. `require_once`/`require`/`include` in `classes/**` is enforced-banned by PHPStan rule `BanRequireOnceRule` (`ibl.requireOnce`).

### Database
- Schema: `ibl5/migrations/000_baseline_schema.sql` — **always verify table/column names here** (check subsequent migrations for alterations). Migrations are the single source of truth.
- **Native types enabled:** `MYSQLI_OPT_INT_AND_FLOAT_NATIVE` is set on `$mysqli_db`. See `core-coding.md` for type comparison rules.
- **Docker:** `docker compose up -d` → `http://main.localhost/ibl5/`. See `database-access.md` for connection details.
- **CLI queries:** `./bin/db-query "SQL"`. See `duckdb-analytics.md` for cross-season OLAP analysis.

## Nightly Queue

Queue a plan for overnight autonomous execution: `bin/nightly-queue <plan-slug>`. See `.claude/rules/nightly-workflow.md`.

## Git & Commits

When committing, only include files relevant to the current task. Always review `git diff --staged` before committing. Never commit unrelated files.

### Commit Conventions

Commit body: `<type>: <short summary>` then `## Section` headers with bullet points.

## Frontend / CSS

When debugging CSS layout issues, immediately check for inherited properties like `white-space: nowrap` that may override your fixes. Use browser DevTools-style reasoning: inspect computed styles, not just the element's own rules.

When modifying HTML output, selectors, or user-facing text in View classes, run `bun run test:e2e` before considering the task complete.

## Mandatory Rules

### XSS Protection
Views must wrap dynamic output in `HtmlSanitizer::e()` (short alias) or `HtmlSanitizer::safeHtmlOutput()`. Both accept `mixed` and return `string`. Enforced by PHPStan rule `RequireEscapedOutputRule` (`ibl.unescapedOutput`) — see `_review-rubric.md` for the full list of safe expression patterns (casts, literals, whitelisted helpers).

### Type Safety (Strict Types)
Every PHP file must have `declare(strict_types=1);` at the top (enforced by PHPStan custom rule `RequireStrictTypesRule`). Additional requirements:

- **Typed properties:** All class properties must have type declarations
- **Typed methods:** All parameters and return types must be declared
- **Strict equality:** Always use `===` and `!==`, never `==` or `!=`
- **Null handling:** Use nullable types (`?string`) and null coalescing (`??`) appropriately

### CSS & HTML Rules
- All CSS must go in `ibl5/design/components/`. Inline `<style>` blocks and `style="..."` attributes in PHP string literals are enforced-banned by `BanInlineCssRule` (`ibl.inlineCss`); exception: `style="--..."` CSS custom properties are allowed.
- Deprecated HTML tags (`<b>`, `<i>`, `<center>`, `<font>`, `<u>`) are enforced-banned by `BanDeprecatedHtmlTagsRule` (`ibl.deprecatedHtmlTag`).
- Use `BasketballStats\StatsFormatter` for all stats — `number_format()` is banned by `BanNumberFormatRule` (`ibl.bannedNumberFormat`) except inside StatsFormatter itself.

### Production Validation
After refactoring, compare output against iblhoops.net. Results must match exactly.

### Doc Freshness
Every repo-tracked `.md` that an agent may read is validated by `bin/check-docs` (CI workflow `doc-freshness.yml`). Frontmatter must carry a `description` and a `last_verified` date no older than 60 days. See `.claude/rules/doc-freshness.md` for the schema and the dead-reference rules.

### Architecture Decisions
Load-bearing decisions are captured as ADRs in `ibl5/docs/decisions/`. PRs that touch mechanical-enforcement surfaces (new PHPStan rule, new `.claude/rules/*.md`, new CI workflow, destructive migration, new `bin/` script ≥ 50 lines, new composer dependency) must add an ADR or a bypass marker — enforced by `bin/adr-check` (CI workflow `adr-required.yml`). See `ibl5/docs/decisions/README.md` for the policy.

