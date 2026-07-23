---
description: Deploy rehearsal workflow gates production deploys by dry-running composer --no-dev, migrations, and schema validation against a prod-cloned disposable MariaDB before SSH steps touch prod.
last_verified: 2026-07-22
---

# ADR-0023: Deploy Rehearsal Pre-Flight Gate

**Status:** Accepted
**Date:** 2026-05-13

## Context

`main.yml` SSHes into the production server and runs `composer install --no-dev`, `php bin/migrate`, and `php bin/validate-schema` directly against the production database. If any of these steps fail, production is left in a partially-deployed state. There was no CI gate that exercised the exact production install sequence (no-dev deps, migration application, schema validation) against a disposable database before the deploy touched prod. The existing `tests.yml` uses `composer install` with dev dependencies and `migration-safety.yml` checks migration idempotency but not the validate-schema assertion set.

## Decision

A reusable workflow `.github/workflows/deploy-rehearsal.yml` mirrors the exact `main.yml` production sequence (`composer install --no-dev`, `php bin/migrate`, `php bin/validate-schema`) against a fresh MariaDB 10.11 service container. It runs on `pull_request` and `push` to `master` for early signal, and is called from `main.yml` as a `pre-flight` job that gates `build-and-deploy` via `needs:`. The on-box `Validate database schema` step in `build-and-deploy` is kept as defense in depth (catches prod-DB mutations outside the migration path).

## Alternatives Considered

- **Run validate-schema only on the prod box (status quo)** — no CI signal; failures discovered after SSH has already mutated prod. Rejected because the whole point is pre-flight gating.
- **Add migration + validate-schema steps inline to `main.yml`** — duplicates the MariaDB service setup and makes `main.yml` longer. Rejected because a reusable workflow is callable from both `main.yml` and independently for PR/push signal.
- **Remove the on-box validate-schema after adding the pre-flight** — removes defense in depth against out-of-band prod-DB changes. Rejected because the two steps catch different failure modes.

## Consequences

- Positive: production deploys are blocked before SSH if migrations or schema assertions are inconsistent.
- Positive: PR authors get early signal on migration/schema issues without waiting for a production push.
- Negative: adds ~2-3 minutes to the production deploy pipeline (MariaDB boot + migration apply + validation).

## References

- `.github/workflows/deploy-rehearsal.yml` — the reusable workflow
- `.github/workflows/main.yml` — `pre-flight` job + `notify-deploy-failure` adjustment
- `.github/workflows/migration-safety.yml` — template for MariaDB service + tracker-seed pattern
- `ibl5/bin/validate-schema` — schema assertion runner
- `ibl5/config/schema-assertions.php` — assertion definitions

**Update 2026-07-22:** The deploy rehearsal no longer runs migrations against a fresh/empty MariaDB schema. Following a live production failure (migration 144, FK on real orphan rows — see ADR-0059), the rehearsal was changed to clone production data into the CI MariaDB via `bin/rehearsal-prod-dump` over SSH. The `composer install --no-dev` + `php bin/migrate` + `php bin/validate-schema` sequence and the `needs: pre-flight` gate in `main.yml` are unchanged; only the database seed changed from a snapshot to a prod clone. The MariaDB image remains 10.11.
