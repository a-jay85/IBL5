---
description: ADR for completing Bootstrap\Application as the single composition root for web, api, and test entry points
last_verified: 2026-07-22
---

# 0030 — Bootstrap unified composition (web/api/test)

## Status

Accepted (2026-05-17)

## Context

Three bootstrap paths (mainfile.php / api.php / tests/bootstrap.php) each contained inline procedural setup code. ADR-0029 wired `Bootstrap\Application` into mainfile.php (web) and api.php was wired separately, but mainfile.php still carried ~340 lines of legacy function definitions duplicated in `classes/Bootstrap/LegacyFunctions.php`. The duplicates diverged silently — `blocks()` in mainfile.php had a raw SQL injection that `LegacyFunctions.php` had already fixed with `real_escape_string()`.

`tests/bootstrap.php` had 96 lines of inline setup (memory limits, autoloader registration, class aliases, config loading) that paralleled the web/api bootstrap but shared no code with it.

## Decision

`Bootstrap\Application` is the single composition orchestrator. Three factories build mode-specific step lists:

- `WebApplicationFactory` — web entry point (mainfile.php)
- `ApiApplicationFactory` — API entry point (api.php)
- `TestApplicationFactory` — test entry point (tests/bootstrap.php)

`LegacyFunctions.php` is the single source for PHP-Nuke legacy global functions. mainfile.php delegates to it via `require_once` after bootstrap. The `function_exists` guard in `LegacyFunctions.php` is retained as cheap safety.

## Consequences

- New bootstrap concerns are added as a Step class registered in one or more factories. No duplicate code paths.
- `LegacyFunctions.php` is the authoritative source for legacy globals (`include_secure`, `filter`, `blocks`, etc.).
- mainfile.php reduced from 410 to 65 lines; tests/bootstrap.php from 96 to 7 lines (+ worktree autoloader). (Update 2026-07-22: both files subsequently grew as worktree autoloader blocks were added. Current counts: mainfile.php 72 lines, tests/bootstrap.php 40 lines.)
- Test path benefits from the same container pipeline (future test parity wins).
- The `blocks()` function now uses `real_escape_string()` in production (previously only the unused LegacyFunctions copy did).

## Supersedes

[ADR-0029](0029-bootstrap-wiring-web.md) (web-only wiring) — this ADR completes the work ADR-0029 started by extending the pattern to tests and consolidating the function duplicates.
