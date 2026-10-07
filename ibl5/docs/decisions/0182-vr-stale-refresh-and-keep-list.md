---
description: A daily and on-demand workflow republishes stale visual-review galleries through a workflow_call entry into e2e-tests.yml, and gh-pages retention keeps every dir an open PR links to.
last_verified: 2026-10-07
---

# ADR-0182: VR stale-gallery refresh and open-PR keep-list

**Status:** Accepted
**Date:** 2026-10-07

## Context

The Visual Regression job publishes each PR's gallery to `gh-pages` under `<sha>/visual-review/` and links it from the `visual-review` and `manual-row-screenshots` sticky comments and the PR-body new-screens block (ADR-0068, ADR-0069, ADR-0076, ADR-0126). On a pull_request event the key is `github.sha`, the test-merge commit. The `vr-pages-cleanup` job deletes dirs older than 7 days and caps the tree at 300 dirs. A PR that sits for a week loses its images, and its screenshots stop matching current master. Refreshing must stay a review aid. It may not add or alter a required check, it may not cancel the master push run, and it must not fork a second copy of the capture and publish steps.

## Decision

1. `.github/workflows/vr-refresh.yml` runs daily and on dispatch. It selects open visual PRs and calls `e2e-tests.yml` through a new `workflow_call` entry, once per PR. In that mode only the `e2e` job runs. One job-level context block (`VRCTX_*`) feeds the existing steps their PR number, base SHA, Pages key, and fork guard.
2. The Pages key for a refresh is the PR head SHA, a 40-hex name that retention still manages. The age signal is the `gh-pages` commit time of the dir the PR's `visual-review` comment links to. Every publish writes `refreshed-at.txt`, so the age always advances.
3. A refresh skips forks, `update-baselines` PRs, drafts in a sweep, non-master bases, and PRs behind master. The refresh runs master's workflow steps against the PR head's scripts. A behind head can lack a flag those steps pass, and its tree may not contain the master tip used as the baseline.
4. Retention keeps every SHA found in any comment or the body of any open PR, plus each head SHA. The exemption covers both prune passes. A failed keep-list computation skips pruning for that run.
5. A sweep refreshes at most 10 PRs, 2 at a time, and dispatches `pages-deploy.yml` once.
6. Every decision lives in `ibl5/tests/e2e/vr-refresh.ts`, which vitest covers. `bin/vr-refresh-targets` only runs `gh` and `git`, and it fails closed: any API error or a truncated PR list exits 1 with empty stdout. `bin/prune-vr-galleries` stays an offline tool that reads a keep file.

## Alternatives Considered

- Dispatch `e2e-tests.yml` on the PR ref. Rejected because it posts an `E2E Tests` check on the PR head.
- Dispatch `e2e-tests.yml` on master. Rejected because it joins the master push run's concurrency group and cancels it.
- Rerun with `gh run rerun`. Rejected because it reruns the gate, freezes the base SHA, and no-ops on a memo hit.
- Move the VR steps into a reusable workflow or composite action. Rejected because it relocates about 400 lines that other open work edits.

## Consequences

- Refresh-leg check runs attach to the master tip SHA as `VR refresh (PR <N>) / …`.
- A push landing mid-refresh can be overwritten by the older render until the PR's next push.
- The tree can exceed 300 dirs by the number of linked open PRs.
- The first live refresh is the first scheduled run after merge.

## References

- `.github/workflows/vr-refresh.yml`: select, capped matrix, single Pages dispatch, and the pull_request dry-run.
- `.github/workflows/e2e-tests.yml`: the `workflow_call` refresh entry and the `vr-pages-cleanup` keep-list wiring.
- `bin/vr-refresh-targets`: PR listing, gallery ages, and fail-closed exits.
- `bin/prune-vr-galleries`: the `--keep-file` exemption.
- `ibl5/tests/e2e/vr-refresh.ts`: selection, keep-list, and argument parsing.
