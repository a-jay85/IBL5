---
description: ADR for BanDirectCommonMysqliInstantiationRule enforcing constructor injection of the split CommonMysqli repository interfaces
last_verified: 2026-07-22
---

# ADR-0027: Ban Direct CommonMysqliRepository Instantiation

**Status:** Accepted
**Date:** 2026-05-16

## Context

`Services\CommonMysqliRepository` is a 16-method shared lookup utility used across 34 call sites. Prior to this change, every caller instantiated it inline (`new CommonMysqliRepository($db)`) rather than receiving it via constructor injection. This prevented:

- Mocking in unit tests (callers coupled to concrete class)
- Adding caching decorators (no interface seam)
- Reducing per-request duplicate queries (each call site creates its own instance)

The interface (`CommonMysqliRepositoryInterface`) was extracted and all 34 sites were converted to constructor injection. A PHPStan rule prevents regression.

## Decision

Add `BanDirectCommonMysqliInstantiationRule` (`ibl5/phpstan-rules/BanDirectCommonMysqliInstantiationRule.php`, rule ID `ibl.directCommonMysqliInstantiation`) that flags `new \Services\CommonMysqliRepository(...)` in any file except:

- `modules/**` (composition roots)
- `tests/**` (test setup)
- `scripts/**` (CLI entry points)
- `mainfile.php` and `api.php` (application entry points)

## Alternatives Considered

- **No rule — rely on code review** — rejected; the codebase has 34 sites that accumulated the anti-pattern over time. Without mechanical enforcement, new code will regress.
- **PHP-DI container** — would eliminate manual wiring but adds framework dependency disproportionate to the immediate DI goal.

## Consequences

- Positive: New class code cannot instantiate `CommonMysqliRepository` directly — must inject the interface.
- Positive: Enables future caching decorator (Plan B) and class splitting without touching call sites.
- Negative: Module `index.php` files must create the instance and wire it down manually.

**Update 2026-07-22:** `CommonMysqliRepository` and `CommonMysqliRepositoryInterface` were deleted when `Services/` was removed (ADR-0028, PR #777). The class was split into three narrow repositories: `Repositories\TeamIdentityRepository`, `Repositories\PlayerLookupRepository`, and `Repositories\SalaryCapRepository` (see `.claude/rules/core-coding.md` for their method signatures). The rule class `BanDirectCommonMysqliInstantiationRule` still exists and is still registered in `ibl5/phpstan.neon`, but it was updated to ban direct instantiation of those three split classes. The rule identifier changed from `ibl.directCommonMysqliInstantiation` to `ibl.directRepoInstantiation`. The allowed-patterns list gained `/Bootstrap/` in addition to the original four patterns (`modules/`, `tests/`, `scripts/`, `mainfile.php`, `api.php`). The anti-pattern-prevention goal is unchanged.
