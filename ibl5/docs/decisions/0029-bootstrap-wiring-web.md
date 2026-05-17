---
description: ADR for wiring Bootstrap\Application as mainfile.php composition root (web mode)
last_verified: 2026-05-17
---

# 0029 — Bootstrap\Application wired as mainfile.php composition root (web)

## Status

**Status:** Superseded by ADR-0030 (2026-05-17)

## Context

`Bootstrap\Application` is a DI container + step pipeline with 8 step classes, but it was used by zero production files. `mainfile.php` (the production web entry point, loaded by every page) still ran ~160 lines of procedural bootstrap code. The step classes and mainfile duplicated identical logic — bugs in one didn't reach the other.

This is the riskiest change in the bootstrap cluster because mainfile.php is loaded by every page on the production site.

## Decision

Replace procedural bootstrap blocks in mainfile.php with `WebApplicationFactory::build(__DIR__)->boot()`. The migration is staged in three plans:

- **Plan A (this PR):** Wire `Application` into mainfile.php for web mode. Procedural blocks become step-class calls. Function definitions remain in mainfile.php for backward compat.
- **Plan B:** Wire `Application` into api.php.
- **Plan C:** Reconcile LegacyFunctions.php — move function definitions out of mainfile.php, delete duplication.

### Design choices

1. **AutoloaderBootstrap excluded from factory** — the Composer autoloader must be loaded before the factory class can be resolved. mainfile.php handles autoloader setup inline.
2. **One factory boot call, not per-block replacement** — the plan originally proposed one commit per block, but since all steps were already tested and the factory orchestrates them correctly, a single swap is safer (atomic revert).
3. **Function definitions stay** — PHP hoists function definitions, so `filter()` is available when `ConfigBootstrap::loadNukeConfig()` calls it during boot. Plan C moves them to LegacyFunctions.php.
4. **SecurityBootstrap order change** — FB bot early-exit and gzip now run after autoloader (was before). Functionally equivalent; bots pay a trivial autoloader cost before their 403.

## Consequences

- Single point of truth for bootstrap logic (no more drift between mainfile.php and step classes)
- Each step is independently testable via PHPUnit
- Rollback is a single `git revert` of the mainfile.php commit
- `mainfile.php` dropped from 667 lines to ~320 lines (function defs + post-boot runtime)
- Plans B and C depend on this landing first

## Superseded by

[ADR-0030](0030-bootstrap-unified-composition.md) — completes the work by extending the pattern to tests, consolidating LegacyFunctions, and deleting mainfile.php function duplicates.
