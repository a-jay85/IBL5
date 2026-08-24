---
description: A scheduled (every-15-min) GitHub Actions workflow that finds open non-draft PRs stuck BEHIND master and refreshes them via the update-branch API using CI_PAT, debounced on an hour of master quiet and guarded by concurrency-cancel plus a per-PR check-run gate, so PRs stay current without manual intervention and without a CI storm per merge.
last_verified: 2026-08-24
---

# ADR-0081: Scheduled branch-update for armed PRs stuck BEHIND master

**Status:** Accepted
**Date:** 2026-07-07

## Context

An armed auto-merge PR merges only when its branch is up to date with master. When a PR falls BEHIND (master advanced after the PR was armed), auto-merge stalls until something refreshes the branch. The eager-rebase workflow handled this on push to master, but its `paths-ignore` deliberately skips markdown/docs and `.claude/**` pushes — so a doc-only PR merging to master never triggers a rebase, leaving any armed PR it put BEHIND stuck indefinitely. Until now the only fix was a human manually calling the update-branch API on each stuck PR.

## Decision

Add a new scheduled workflow, `.github/workflows/update-behind-prs.yml`, running every 15 minutes (`*/15 * * * *`, plus `workflow_dispatch`). It enumerates open, auto-merge-armed PRs targeting master via GraphQL, and for each PR whose `mergeStateStatus` is `BEHIND` (and not conflicting) calls the `update-branch` REST API (`gh api -X PUT repos/{owner}/{repo}/pulls/{number}/update-branch`) — a **merge** commit from master, not a rebase. The `gh` CLI authenticates as **`CI_PAT`** (not the built-in `GITHUB_TOKEN`) so the resulting push triggers CI on the refreshed branch. Loop safety is two-layered: a `concurrency` group with `cancel-in-progress: true` prevents overlapping ticks, and a per-PR gate skips any PR with a `queued`/`in_progress` check run on its head commit, so a branch is never re-updated while its prior CI is still running. Conflicting PRs (`DIRTY`/`CONFLICTING`) are skipped and logged for manual resolution.

**Amended 2026-08-24 (scope + debounce).** PR #1936 widened the selection from auto-merge-armed PRs to *every* open non-draft PR targeting master, so long-lived branches stay fresh continuously. That made the per-tick cost scale with the whole PR set: because a master merge is exactly what puts every open PR BEHIND, the first tick after any merge updated all of them at once — measured on 2026-08-24 at 30 PRs × ~11 workflows ≈ 330 CI runs, twice in one afternoon (16:16 and 17:45 UTC ticks, following the #1971 and #1947/#1975 merges).

The workflow is therefore **debounced on master quiet time**: a scheduled tick exits early unless master's last commit is at least `DEBOUNCE_SECONDS` (3600) old. A burst of merges now coalesces into one update pass after the burst ends, rather than one storm per merge. `workflow_dispatch` bypasses the debounce so on-demand unsticking and the dispatch smoke test are unaffected. If master's commit date cannot be read, the debounce **fails open** (proceeds) — a transient API error must not silently park PR updates indefinitely.

## Alternatives Considered

- **Extend the eager-rebase workflow with a `schedule`/`workflow_dispatch` trigger instead of a new file** — rejected: it uses a different update strategy (rebase + force-push, which rewrites commits) and a different scope (all open PRs, not just armed ones). For armed auto-merge PRs, `update-branch`'s merge commit is safer — it preserves the original commits and the auto-merge arming survives the update. Keeping the two workflows separate keeps each strategy focused and its concurrency group independent.
- **GitHub merge queue** — rejected: unavailable on personal accounts.
- **Use the default `GITHUB_TOKEN` for the update** — rejected: branch mutations made with `GITHUB_TOKEN` are silently ignored by GitHub's anti-recursion guard and do NOT trigger downstream CI, so the refreshed PR would sit with no CI and never merge. `CI_PAT` (already used by the eager-rebase workflow for this exact reason) avoids this.

## Consequences

- Positive: PRs left BEHIND — including by doc-only master merges that the eager-rebase workflow skipped — auto-unstick with no human intervention, within an hour of master going quiet.
- Positive: The merge-commit update strategy preserves original commits and keeps auto-merge armed across the refresh.
- Positive: Loop safety guarantees the same branch is never re-updated while its CI is live, so the job cannot thrash a PR.
- Neutral: The human-signoff hold is unaffected — refreshing a `feat:` PR's branch does not merge it while the `human-approved` label is absent; the required human-signoff check still gates the merge.
- Negative: A small recurring CI cost (one short job every 15 min); bounded and cheap, as most ticks find nothing to do and exit quickly.
- Negative (bounded by the debounce): an update pass still costs one full CI matrix per open PR. The debounce caps that at roughly one pass per quiet period instead of one per merge; it does not make an individual pass cheaper. Narrowing the scope back toward armed/labelled PRs remains the lever if the cost returns.
- Negative: while master moves more often than once an hour, PRs go un-updated. Acceptable: this workflow never merges and never arms auto-merge, so a stale-but-clean PR blocks nothing — and `workflow_dispatch` forces a pass when one is actually needed.

## References

- `.github/workflows/update-behind-prs.yml` — the scheduled workflow this ADR introduces.
- the eager-rebase workflow (retired; its `paths-ignore` gap was the motivation for this ADR; its `CI_PAT` pattern was adopted here).
