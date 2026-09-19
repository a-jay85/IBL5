---
description: Heavy jobs in the two required CI contexts skip when the HEAD tree over a declared input path set already passed, keyed by `bin/ci-memo` and stored in `actions/cache`.
last_verified: 2026-09-18
---

# ADR-0131: CI tree-hash memoization of heavy jobs

**Status:** Accepted
**Date:** 2026-09-18

## Context

The two required contexts `Tests and Analysis` and `E2E Tests` re-run their full job sets on every push and every rebase. Many rebases move master only in paths no memoized job reads. `dorny/paths-filter` narrows by the diff against base, and a diff cannot express "this exact content already passed". Branch protection is `strict: true`, so a PR that falls behind must rebase and re-run before it can merge. Most of the repeated cost lands there.

`bin/website-affecting` was considered as a host. It answers "does this diff affect rendering?" from a changed-file list on stdin. The memo needs the digest of a tree over a declared path set, which is a different input and a different question, so it gets its own script.

## Decision

Memoize by content. The key is `(scope, digest of the HEAD tree over a declared input path set)`. `bin/ci-memo key <scope>` reads HEAD into a throwaway index, lists every tracked file the manifest matches with `git ls-files -s` (mode, blob SHA, path), sorts it under `LC_ALL=C`, prefixes a `ci-memo/v1` format line and the scope, and pipes the result through `git hash-object --stdin`. The cache key is `ci-memo-<scope>-v1-<digest>`. `git ls-tree` was the first choice, but it matches pathspecs by literal prefix only and rejects `:(glob)` magic, so the throwaway index is what makes glob entries work while still reading only HEAD.

On a `pull_request` run HEAD is the PR-into-base merge commit, so master's side of the tree is inside the digest. A master move inside the input set changes the key. A master move outside it does not.

Input sets are static manifests at `.github/ci-memo/tests.paths` and `.github/ci-memo/e2e.paths`, one git pathspec per line. Each is a superset of the change detection for the jobs it covers. The tests manifest widens the dorny globs to whole directories and adds all of `.github` and `ibl5/tests`. The e2e manifest takes all of `ibl5` and `.github` minus docs and markdown, the Docker runtime, and every `bin/` script the e2e jobs call, because `bin/website-affecting` treats almost everything outside its deny set as website-side. Every non-exclude pathspec must match at least one file at HEAD or the script exits 3. That guard stops a renamed input from silently dropping out of the key.

The e2e key also folds the registry manifest of `ghcr.io/a-jay85/ibl5/php-apache:latest`, read with `docker manifest inspect` (metadata only, no pull). That tag is rebuilt by master merges the PR never touched. When the manifest cannot be read the script prints empty `hash=` and `key=`, so neither lookup nor save fires.

A `ci-memo-check` job in each workflow computes the key, restores the sentinel with `actions/cache/restore`, and publishes `hit`. Every heavy job carries `ci-memo-check` in `needs:` and `needs.ci-memo-check.outputs.hit != 'true'` in `if:`. The aggregator `gate` job saves the sentinel after its existing `exit 1` step, only when no need failed or was cancelled, the key is non-empty, and the run was a miss. The save step is `continue-on-error: true`.

Memoization is PR-only. Push runs, re-runs (`run_attempt > 1`), and dispatches set `CI_MEMO_BYPASS`, which empties the key so neither lookup nor save fires. For e2e, a `labeled` event bypasses too, so the `update-baselines` label always forces a real run. The Visual Regression job (`e2e`) is never memo-gated because it publishes the per-SHA gallery and regenerates baselines.

## Alternatives Considered

- **Key on the PR diff or `git patch-id`.** Rejected because: two runs with the same patch can sit on different masters, so a hit would skip tests whose environment changed underneath them.
- **Hash the whole repo tree.** Rejected because: any master move would evict every memo, so it would almost never hit.
- **A GitHub merge queue.** Rejected because: it is unavailable for this account.
- **Memoize master pushes too.** Rejected because: a squash merge lands the tree the PR already passed, so every master push would hit and skip `update-baselines`.

## Consequences

- Positive: `Tests and Analysis` finishes in the time of the memo check plus the small unmemoized jobs when a rebase moves master only outside the tests input set. `E2E Tests` saves the shard, mutator, and API-E2E runner time on a hit while Visual Regression still runs.
- Positive: every failure direction falls toward a real run. A broken key step fails `ci-memo-check`, which sits in both gates' `needs:`, so it reds the required context. An unreadable image manifest or a cache-service error only costs a real run.
- Positive: the downstream-consumer invariant holds. A job that needs a memo-gated job carries the same memo clause, so nothing runs against a missing artifact. `bin/test-ci-memo --case gate-topology` asserts this over both workflow files.
- Negative: the manifests are a second list to keep in step with the dorny filters. `bin/test-ci-memo --case manifest-coverage` fails when a memoized dorny glob matches a file the manifest does not.
- Negative: a job whose outcome depends on a file outside its manifest can be skipped wrongly. Over-inclusion is the safe direction, so widen the manifest when in doubt.

## Revisiting

The key carries a `-v1-` segment and the digest stream starts with `ci-memo/v1`. Any change to what the hash covers bumps both to `v2`, which invalidates every stored memo in one edit. That is also the emergency lever if a manifest turns out to be incomplete.

## References

- `bin/ci-memo` for the key, record, and report logic.
- `bin/test-ci-memo` for the harness, including the topology and coverage cases.
- `.github/ci-memo/tests.paths` and `.github/ci-memo/e2e.paths` for the input sets.
- `.github/workflows/tests.yml` and `.github/workflows/e2e-tests.yml`, the `ci-memo-check` and `gate` jobs.
