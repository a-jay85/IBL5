---
description: Use a sibling ibl5_test database for local PHPUnit --group database runs instead of destroying iblhoops_ibl5.
last_verified: 2026-05-25
---

# ADR-0033: Non-Destructive Sibling Test Database

**Status:** Accepted
**Date:** 2026-05-25

## Context

`bin/wt-db-test` destroyed `iblhoops_ibl5` on every run (DROP DATABASE + recreate), wiping developer data and requiring a full resync afterward. The main checkout had no way to run `phpunit --group database` locally at all — only worktrees with `bin/wt-up` could run integration tests, and each run was destructive.

## Decision

Use a sibling `ibl5_test` database via `bin/db-test-up`. The script drops and recreates `ibl5_test` (never `iblhoops_ibl5`), runs migrations, applies the idempotent seed, and optionally executes `phpunit --group database` against it. `bin/wt-db-test` is refactored to a thin wrapper that delegates to `bin/db-test-up`.

## Alternatives Considered

- **Destructive reset of iblhoops_ibl5** — the prior approach. Rejected because: destroys developer data and requires time-consuming resync.
- **Environment-file switching** — toggle `DB_NAME` via `.env` files. Rejected because: error-prone, risk of accidentally running tests against dev data.
- **Separate Docker container for tests** — spin up a dedicated MariaDB instance. Rejected because: resource-heavy for a 365-line seed file; sibling database in the same container is simpler.

## Consequences

- Positive: Developer data in `iblhoops_ibl5` is never touched.
- Positive: Main checkout can now run database integration tests locally.
- Negative: `bin/wt-db-test` no longer resets `iblhoops_ibl5` — any workflow depending on that side effect breaks (no known dependents).

## References

- `bin/db-test-up`
- `bin/db-migrate`
- `bin/wt-db-test`
- `ibl5/docs/decisions/0004-docker-only-dev-environment.md`
