---
description: ADR recording deletion of Services/ and Shared/ catch-all namespaces
last_verified: 2026-07-22
---

# ADR-0028: Delete Services/ and Shared/ Namespaces

## Status

Accepted

## Context

`classes/Services/` and `classes/Shared/` were catch-all namespaces that accumulated unrelated domain classes alongside cross-cutting utilities. This made dependency direction unclear and hindered module cohesion. Maintenance backlog items 2.22, 2.23, 4.3, and 4.25 all identified this as technical debt.

After Plan B (PR #777) split `CommonMysqliRepository` into three narrow repositories, both directories contained only relocatable classes with clear domain homes.

## Decision

Delete both `Services/` and `Shared/` entirely. Relocate every class to its natural domain module or to a new purpose-named cross-cutting namespace:

| Class | From | To | Rationale |
|-------|------|----|-----------|
| `NewsService` | `Services\` | `Topics\News\` | Writes to `nuke_stories`; News topic owns this |
| `PlayerDataConverter` | `Services\` | `Player\` | Converts arrays to `Player\PlayerData` |
| `CommonContractValidator` | `Services\` | `FreeAgency\` | Largest consumer; other modules inject via interface |
| `SalaryConverter` | `Shared\` | `BasketballStats\` | Single static method for salary display conversion |
| `CommonValidator` | `Services\` | `Validation\` | Cross-cutting validation primitive |
| `ValidationResult` | `Services\` | `Validation\` | Cross-cutting validation primitive |
| `QueryConditions` | `Services\` | `Validation\` | WHERE-clause builder using validation accumulator pattern |
| `TeamIdentityRepository` | `Services\` | `Repositories\` | Cross-cutting lookup repository |
| `PlayerLookupRepository` | `Services\` | `Repositories\` | Cross-cutting lookup repository |
| `SalaryCapRepository` | `Services\` | `Repositories\` | Cross-cutting lookup repository |

All interfaces moved alongside their implementations. A new `NewsServiceInterface` was authored for `Topics\News\NewsService`.

## Consequences

- No class lives in a catch-all namespace; every class has a domain-motivated home.
- Future code review can reject domain classes placed in `Repositories/` or `Validation/` — these are explicitly cross-cutting-only.
- The PHPStan rule `BanDirectCommonMysqliInstantiationRule` was updated to reference `Repositories\` instead of `Services\`.
- 177 files touched for namespace updates across classes, modules, tests, and scripts.

## Supersedes

None.
