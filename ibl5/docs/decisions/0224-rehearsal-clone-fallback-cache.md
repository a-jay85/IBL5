---
description: Deploy Rehearsal falls back to an encrypted cached prod dump, up to 7 days old, when the live clone fails with a connection-class MySQL error or an ssh connect failure.
last_verified: 2026-11-18
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

## Addendum 2026-11-18: ssh connect failures

Run 44411988785 (commit cf38d9d) failed twice before a third attempt passed. On attempt 1 the setup-ssh keyscan came back empty after six tries, and the dump's ssh call then printed `ssh: connect to host *** port ***: Connection timed out` and exited 255. On attempt 2 the keyscan was empty again. Ssh reached the host about seven minutes later and stopped with `Host key verification failed`. Other runners reached prod in the same windows, so the prod firewall was blocking single runner IPs. The classifier knew only MySQL error codes, so it refused the fallback both times.

`bin/rehearsal-clone-fallback classify` now also returns `fallback: ssh-connect` for the ssh client's own network-level lines:

- `ssh: connect to host <h> port <p>:` followed by `Connection timed out`, `Connection refused`, `No route to host`, `Network is unreachable`, or `Connection reset by peer`.
- `kex_exchange_identification:` or `ssh_exchange_identification:` with a closed, reset, or timed-out connection.
- `banner exchange: Connection to <h> port <p>:` with a timed-out, refused, or reset connection.
- `Connection closed by <h> port <p>` and `Connection reset by <h> port <p>`.
- `Host key verification failed.`, only when the caller passes `--keyscan=empty`.

Each pattern must match a whole stderr line from start to end. The stderr file also holds the remote script's output, and a remote line that quotes one of these phrases inside other text does not match. The MySQL codes are still checked first, unchanged. After them, any line with `Permission denied` or `Could not resolve hostname` returns `no-fallback`, even when an eligible line is also present. A failed login or a bad host name is a config fault, and a cached dump would hide it.

The dump step learns the keyscan fact itself. Just before the ssh call it runs `ssh-keygen -F` against `~/.ssh/known_hosts` for the bare host and for the `[host]:port` form, which is the question ssh asks when it verifies a host key. A hit, or any probe exit code other than 1 (no entry), reads `present`. Only a missing file or a clean miss on both forms reads `empty`. A stale or pre-existing known_hosts can only push the answer to `present`, which keeps a host key mismatch a hard failure. `classify` defaults to `--keyscan=present` when the argument is absent, and an unknown value exits 2.

The dump step still makes the ssh call when the keyscan is empty. The real stderr is the evidence the classifier reads, and the existing ConnectTimeout and ConnectionAttempts settings bound the cost.

The rejected alternative "Classify by ssh exit status" stands. Exit 255 also covers auth failures and host key mismatches, so the classifier still reads stderr text.

When the fallback runs for `ssh-connect`, the warning, the job summary, and both failure errors in the `fallback` step name "SSH connect failure" in the place where the MySQL path names its code. The summary states the age of the cached dump in hours and the URL of the run that cloned it. The 7-day age cap and the fail-when-no-cache rule are unchanged.

A second dispatch-only input, `simulate_ssh_connect_failure`, writes a verbatim ssh timeout line into the dump stderr so the real classifier routes to the fallback. A runner IP block cannot be reproduced on demand, so this input is the one way to drive the path on a real runner. Like `simulate_clone_connection_failure`, it is read only through a `workflow_dispatch` guard, and `workflow_call` declares no inputs.

The rehearsal's `Setup SSH` step must never set `fail-on-unreachable: 'true'` (the input from [#4053](https://github.com/a-jay85/IBL5/pull/4053)). An empty keyscan has to reach the dump step so this fallback can run. `bin/test-rehearsal-clone-fallback` fails if that line appears.
