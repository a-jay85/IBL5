---
description: A PR-only CI job runs production's database PHPUnit suite against the PR-head schema, so a migration that breaks the code auto-rollback restores fails before merge.
last_verified: 2026-10-01
---

# ADR-0155: CI Gate for Production PHP on the PR-Head Schema

**Status:** Accepted
**Date:** 2026-10-01
**Deciders:** automouse implementation run

## Context

Production deploys run `git reset --hard origin/production`, then `php ibl5/bin/migrate`. Migrations are forward-only. When post-deploy smoke fails, `smoke-prod.yml` reverts the code and leaves the schema migrated, so the previous production PHP then runs on the new schema. `ibl5/migrations/README.md` required new migrations to stay compatible with that previous PHP, but nothing enforced it. `deploy-rehearsal.yml` (ADR-0059) proves migrations apply to a prod clone. It runs no old code against the result.

## Decision

Add two PR-only jobs to `.github/workflows/migration-safety.yml`, both hung under the existing `Migration Safety` gate. `old-code-compat` checks out `origin/production` as the old tree. It builds a scratch MariaDB at the PR-head schema with `ibl5/bin/run-migrations-ci`, imports the old tree's `db-seed.sql`, and runs the old tree's full `--group database` suite. `bin/check-old-code-compat` renders the verdict. `old-code-compat-selftest` runs the script's end-to-end scenarios against real MariaDB.

- **Old tree = `origin/production`.** Auto-promotion fast-forwards production to green master, so production at PR time is the code a rollback restores.
- **Subset = the full database suite.** Repository SQL against a real schema is what a drop or rename breaks. The build mirrors the `db-integration` job, so the suite is green on master and a failure points at the schema delta. We rejected an on-box smoke run (it needs a web server in CI) and a tagged subset (tags drift).
- **Distinct marker `rollback-incompatible:`,** per file or per PR, with a reason of at least 20 characters. It is separate from `destructive-migration:`, which accepts data loss and must not silently waive this check.
- **PR-only.** A push has no PR body, so a PR-body bypass would pass on the PR and then fail on master, blocking auto-promotion.
- **Advisory at the required-context level.** `Migration Safety` stays out of branch protection and is enforced through auto-promotion and the merge line, as before.
- **False-green guards exit 3.** No marker waives them. The DB must be at the PR-head schema (runner count matches the numbered files, and every changed migration was applied), and the old tree must execute at least 100 tests.

## Consequences

A contract-phase drop of something production still reads needs an explicit marker. `.php` data migrations are not exercised, because the CI runner skips them. Code paths outside the database suite remain unchecked.
