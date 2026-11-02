---
description: Deploy Rehearsal falls back to an encrypted cached prod dump, up to 7 days old, when the live clone fails with a connection-class MySQL error.
last_verified: 2026-11-02
---

# ADR-0224: Rehearsal Clone Fallback to an Encrypted Cached Dump

**Status:** Accepted
**Date:** 2026-11-02

## Context

On 2026-10-27 production MySQL ran out of connections (ERROR 1203, `max_user_connections` 30). The revert deploy that fixed the outage then failed at the Deploy Rehearsal clone step (run 40439627733), because the rehearsal could not open a connection to dump prod either. The pre-flight gate from ADR-0023, rehearsing against the prod clone from ADR-0059, blocked the deploy that would have ended the outage.

## Decision

When the remote dump fails with MySQL 1203, 1040, 2002, 2003, or 2013, the rehearsal loads the most recent cached dump, if that dump is at most 7 days old. Every other failure fails the job as before. Only the remote dump call's captured stderr is classified, and `bin/rehearsal-clone-fallback` makes that call. A local load error in the CI MariaDB is never classified.

This is the first time any copy of the filtered prod dump outlives its runner. The repo is public, and fork PRs can restore default-branch caches. So the cached copy is gzip-compressed and then encrypted with AES-256-CBC (PBKDF2, 100000 iterations). The key is derived with HMAC-SHA256 from the existing `PRIVATE_KEY` secret under a fixed public label. Fork PRs receive no secrets and cannot decrypt the cache. Workflow artifacts are never used, because any signed-in GitHub user can download one. A dump is saved only on non-`pull_request` events, only from a fresh clone, and only after the bound check passes. The PII filtering in `bin/rehearsal-prod-dump` is unchanged.

The bound check, `ibl5/bin/migrate`, the pending-set assertion, `ibl5/bin/validate-schema`, the duplicate-boxscore check, and the smoke probe all run against the fallback data. The fallback relaxes only the clone-availability condition of the ADR-0023 gate. Its verdict on migrations is unchanged.

A fallback run is flagged by a `::warning::` annotation and a `$GITHUB_STEP_SUMMARY` table that name the failure code, dump age, source run, and source SHA. No Discord message is added. The only pre-flight Discord job in `.github/workflows/main.yml` fires on failure, and a fallback run succeeds.

The `workflow_dispatch`-only input `simulate_clone_connection_failure` writes a verbatim 1203 line into the stderr capture. The real classifier, restore, age check, decrypt, and every downstream check then run. `workflow_call` from `.github/workflows/main.yml` cannot set the input.

## Alternatives Considered

- **Workflow artifact** holding the last good dump. Rejected because the repo is public and any signed-in GitHub user can download an artifact.
- **A new dedicated encryption secret.** Rejected because it needs a manual setup step before the fallback works at all. Reusing `PRIVATE_KEY` couples the two: rotating the deploy key makes cached dumps undecryptable until the next fresh clone re-saves one.
- **One cache entry per run.** Rejected because production deploys land several times a day, and dump-sized entries per deploy would evict other workflows' caches from the shared 10 GB budget. Keys are bucketed by UTC day instead.
- **Classify by ssh exit status.** Rejected because ssh passes the remote exit code through, so a 1203 and a SQL syntax error both surface as exit 1. Only the error-code text separates them.

## Consequences

- Positive: a prod connection outage no longer blocks the deploy that fixes it, as long as a dump from the last 7 days is cached.
- Negative: on the fallback path, migrations are validated against prod data up to 7 days old. A prod row written after the cached clone that would break a pending migration stays unseen until the next fresh rehearsal or the real deploy.
- Negative: cache keys are bucketed by UTC day, and GitHub evicts entries not accessed for 7 days. A deploy gap longer than 7 days leaves no usable fallback, and the job then fails as it did before this change.

## References

- `bin/rehearsal-clone-fallback` holds the classifier, metadata age check, and encrypt and decrypt wrappers.
- `bin/test-rehearsal-clone-fallback` covers the helper and the workflow wiring on every PR.
- `.github/workflows/deploy-rehearsal.yml` is the rehearsal job with the dump, restore, decrypt, load, and save steps.
- `bin/rehearsal-prod-dump` is the read-only server-side dump script, unchanged.
- `ibl5/docs/decisions/0023-deploy-rehearsal-pre-flight-gate.md` defines the pre-flight gate this relaxes.
- `ibl5/docs/decisions/0059-rehearse-migrations-against-prod-clone.md` defines the prod clone.
