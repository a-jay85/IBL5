---
description: A scheduled (every-15-min) GitHub Actions workflow that finds open auto-merge-armed PRs stuck BEHIND master and refreshes them via the update-branch API using CI_PAT, with concurrency-cancel and a per-PR check-run gate for loop safety, so armed PRs auto-unstick without manual intervention.
last_verified: 2026-07-07
---

# ADR-0081: Scheduled branch-update for armed PRs stuck BEHIND master

**Status:** Accepted
**Date:** 2026-07-07

## Context

An armed auto-merge PR merges only when its branch is up to date with master. When a PR falls BEHIND (master advanced after the PR was armed), auto-merge stalls until something refreshes the branch. `rebase-prs.yml` handles this on push to master, but its `paths-ignore` deliberately skips markdown/docs and `.claude/**` pushes — so a doc-only PR merging to master never triggers a rebase, leaving any armed PR it put BEHIND stuck indefinitely. Until now the only fix was a human manually calling the update-branch API on each stuck PR.

## Decision

Add a new scheduled workflow, `.github/workflows/update-behind-prs.yml`, running every 15 minutes (`*/15 * * * *`, plus `workflow_dispatch`). It enumerates open, auto-merge-armed PRs targeting master via GraphQL, and for each PR whose `mergeStateStatus` is `BEHIND` (and not conflicting) calls the `update-branch` REST API (`gh api -X PUT repos/{owner}/{repo}/pulls/{number}/update-branch`) — a **merge** commit from master, not a rebase. The `gh` CLI authenticates as **`CI_PAT`** (not the built-in `GITHUB_TOKEN`) so the resulting push triggers CI on the refreshed branch. Loop safety is two-layered: a `concurrency` group with `cancel-in-progress: true` prevents overlapping ticks, and a per-PR gate skips any PR with a `queued`/`in_progress` check run on its head commit, so a branch is never re-updated while its prior CI is still running. Conflicting PRs (`DIRTY`/`CONFLICTING`) are skipped and logged for manual resolution.

## Alternatives Considered

- **Extend `rebase-prs.yml` with a `schedule`/`workflow_dispatch` trigger instead of a new file** — rejected: it uses a different update strategy (rebase + force-push, which rewrites commits) and a different scope (all open PRs, not just armed ones). For armed auto-merge PRs, `update-branch`'s merge commit is safer — it preserves the original commits and the auto-merge arming survives the update. Keeping the two workflows separate keeps each strategy focused and its concurrency group independent.
- **GitHub merge queue** — rejected: unavailable on personal accounts.
- **Use the default `GITHUB_TOKEN` for the update** — rejected: branch mutations made with `GITHUB_TOKEN` are silently ignored by GitHub's anti-recursion guard and do NOT trigger downstream CI, so the refreshed PR would sit with no CI and never merge. `CI_PAT` (already used by `rebase-prs.yml` for this exact reason) avoids this.

## Consequences

- Positive: Armed PRs left BEHIND — including by doc-only master merges that `rebase-prs.yml` skips — now auto-unstick within at most 15 minutes, with no human intervention.
- Positive: The merge-commit update strategy preserves original commits and keeps auto-merge armed across the refresh.
- Positive: Loop safety guarantees the same branch is never re-updated while its CI is live, so the job cannot thrash a PR.
- Neutral: The human-signoff hold is unaffected — refreshing a `feat:` PR's branch does not merge it while the `human-approved` label is absent; the required human-signoff check still gates the merge.
- Negative: A small recurring CI cost (one short job every 15 min); bounded and cheap, as most ticks find nothing to do and exit quickly.

## References

- `.github/workflows/update-behind-prs.yml` — the scheduled workflow this ADR introduces.
- `.github/workflows/rebase-prs.yml` — the on-push rebase workflow whose `paths-ignore` gap this fills; source of the `CI_PAT` token pattern.
