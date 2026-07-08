---
description: Rationale for removing the broad *Repository* mutation testing exclusion and adding MariaDB to the mutation CI workflow.
last_verified: 2026-07-07
---

# ADR-0019: Mutation Testing Unlock — Repositories

**Status:** Accepted
**Date:** 2026-05-05

## Context

`infection.json5` excluded `*Repository*` from mutation testing, silently exempting 82 Repository implementations from the 100% MSI gate. Repositories are SQL-heavy and contain conditional result mapping — prime sources of mutation-sensitive bugs (flipped WHERE clauses, dropped casts, corrupted null-coalesce defaults).

Additionally, the mutation CI workflow (`mutation.yml`) ran PHPUnit without a MariaDB service and used `phpunit.xml` for coverage generation. That config excludes the `database` group and excludes Repository/View/Controller files from `<source>`, meaning:

1. DB integration tests never ran during mutation coverage generation
2. Repository files had zero coverage data, so Infection couldn't detect covering tests

Plan 15 (Views unlock) removed `*View*` from `infection.json5` but didn't address the `phpunit.xml` source exclude, meaning the scheduled Monday mutation job would fail once it first ran post-merge.

## Decision

1. **Remove the broad `*Repository*` exclude** from `infection.json5` and add specific per-file excludes for 4 repositories lacking direct test coverage (Pass 2 deferred): `ApiKeysRepository`, `BaseMysqliRepository`, `PlrBoxScoreRepository`, `VotingRepository`.

2. **Create `phpunit-mutation.xml`** — a PHPUnit configuration variant that includes the `database` group and has a broader `<source>` covering Repositories, Views, Controllers, and Handlers. This config is used exclusively by the mutation CI workflow's coverage-generation step.

3. **Add MariaDB service** to both the `mutation` and `mutation-pr` CI jobs, with migration application and seed data import steps, so DB integration tests provide coverage for Repository mutations.

4. **Set `testFrameworkOptions`** in `infection.json5` to `--configuration=phpunit-mutation.xml` so local `composer mutation` runs also use the correct coverage scope.

## Consequences

- Pass 1 brought all but the four deferred repositories under the 100% MSI mutation gate. Pass 2 has since completed (PR #872), writing direct DB integration tests for the four deferred repositories and removing their per-file excludes. `infection.json5` no longer excludes any Repository — every Repository implementation is now subject to the mutation gate.
- The mutation CI workflow takes longer (~30s for MariaDB startup + migrations + seed), but this is the same overhead already paid by the `db-integration` job in `tests.yml`.
- The `phpunit-mutation.xml` config must be kept in sync with `phpunit.xml` when new test suites are added. The testsuites section is identical; only the `<groups>` and `<source>` blocks differ.
- Running `composer mutation` locally now requires a running MariaDB instance (matching the CI service config) because `testFrameworkOptions` points at `phpunit-mutation.xml`, which includes DB integration tests. Developers without a local MariaDB will see connection failures during coverage generation.
