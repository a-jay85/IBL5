---
description: A GitHub Actions workflow that finds open PRs stuck BEHIND master and refreshes them via the update-branch API using CI_PAT. Triggered on push to master (auto-merge-armed PRs only, coalesced) and on a best-effort schedule (all open non-draft PRs, debounced on an hour of master quiet), guarded by concurrency-cancel plus a per-PR check-run gate, so PRs stay current without manual intervention and without a CI storm per merge.
last_verified: 2026-08-28
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

**Amended 2026-08-28 (push trigger + event-dependent scope).** The `schedule` trigger turned out to be unreliable enough that it cannot carry a latency-sensitive job. On 2026-08-27/28 GitHub delivered ~3 of ~96 expected ticks, leaving three green armed dependabot PRs (#2008, #2010, #2011) stuck BEHIND with nothing to unstick them; PR #2017 moved the cron off the quarter-hour on the theory that this repo's own Actions volume was triggering the documented high-load drop, and that did not help. Measurement ruled the load theory out: the delay hit **every** scheduled workflow in the repo simultaneously (`pr-collisions-cron` ~11-12h late, `doc-freshness-audit` ~9.5h, `cache-dependencies` ~5.5-9.7h) on the two days with the *lowest* run volume of the week (950 and 1054 runs, versus 2907 on 08-24 when crons fired on time). The delay is GitHub-side and not reachable by any change to this repo.

The workflow therefore stops depending on `schedule` for the part that matters. It now also triggers on **`push` to master** — the exact moment every open PR goes BEHIND — reusing the retired eager-rebase workflow's `paths-ignore` list so doc-only merges do not fire a pass. `push` and `workflow_dispatch` are not subject to the schedule-event delay.

That re-opens the storm this ADR's 2026-08-24 amendment closed, since a push cannot be debounced on master quiet time (a push means master moved ~0s ago, so the quiet check would skip every push run and make the trigger a silent no-op). Two mechanisms replace it, and this is exactly the "narrow the scope back toward armed PRs" lever the Consequences section named:

- **Scope is now event-dependent.** A `push` pass covers **auto-merge-armed PRs only** — the PRs where BEHIND is actually blocking a merge. Measured against the live PR set on 2026-08-28 that is 3 PRs (≈33 CI runs per merge) versus 33 (≈363). `schedule` and `workflow_dispatch` keep the full #1936 all-open-PRs scope, so long-lived branches still get their freshness sweep; that expensive pass simply stays on the infrequent triggers, where its cost is bounded by the existing hour-quiet debounce.
- **Push passes coalesce on a `PUSH_COALESCE_SECONDS` (300) wait.** A second merge during the wait starts a new run, and `cancel-in-progress` kills the first, so a burst of back-to-back merges still produces one update pass.
- **Retry budgets are now event-dependent too, because every timing constant here was calibrated for a cold scheduled tick.** A scheduled tick runs after master has been quiet for an hour: GitHub has long since settled `mergeStateStatus`, the runner pool is idle, and — decisively — a PR skipped on this tick is retried by the next tick 15 minutes later, so a skip is a *deferral*. A push pass has none of those properties. It fires while GitHub is still recomputing mergeability across the whole open-PR set, while the runner pool is saturated by the merge that just landed, and with **no next tick** — the run after a push is the next master merge. There a skip is not a deferral but a silent permanent miss, on exactly the PRs the trigger exists to unstick, inside a run that still reports success. Worse, the armed PRs' head commits are the ones *most* likely to have CI in flight, so the pre-existing "skip if any check run is queued/in_progress" guard would have fired on essentially every push. On `push` therefore: `UNKNOWN_ATTEMPTS=20` (x3s) gives GitHub up to a minute to settle merge state, and `CI_WAIT_ATTEMPTS=10` (x60s) makes the CI-in-flight guard **poll** rather than skip once — deliberately the same shape as the `UNKNOWN` retry loop directly above it in the file. On `schedule` they stay at 5 and 0, since waiting there buys nothing the next tick would not. A push PR still not clear after the full wait emits a `::warning::` instead of a quiet summary line, because that case is a genuine miss a human may need to see, and the job carries `timeout-minutes: 60` so a patient pass can never hang a runner indefinitely.

A second daily cron (`30 11 * * *`) was added as an overnight full-sweep backstop: 11:30 UTC is 04:30 PDT and 03:30 PST — the same instant in both, because GitHub cron is UTC-only and never observes DST, so one entry covers the year. It is an additional draw on the same unreliable schedule lottery, not a guarantee, and is documented in the workflow as a backstop rather than a fix.

## Alternatives Considered

- **Extend the eager-rebase workflow with a `schedule`/`workflow_dispatch` trigger instead of a new file** — rejected: it uses a different update strategy (rebase + force-push, which rewrites commits) and a different scope (all open PRs, not just armed ones). For armed auto-merge PRs, `update-branch`'s merge commit is safer — it preserves the original commits and the auto-merge arming survives the update. Keeping the two workflows separate keeps each strategy focused and its concurrency group independent.
- **GitHub merge queue** — rejected: unavailable on personal accounts.
- **Use the default `GITHUB_TOKEN` for the update** — rejected: branch mutations made with `GITHUB_TOKEN` are silently ignored by GitHub's anti-recursion guard and do NOT trigger downstream CI, so the refreshed PR would sit with no CI and never merge. `CI_PAT` (already used by the eager-rebase workflow for this exact reason) avoids this.

## Consequences

- Positive: PRs left BEHIND auto-unstick with no human intervention — armed PRs on the master merge that put them BEHIND (push trigger), and the rest within an hour of master going quiet (scheduled sweep). No end-to-end latency figure is claimed for the push path yet: a push run coalesces for 300s and then waits on any in-flight CI for up to 10 minutes per PR, so the real number depends on runner-pool contention and has to be measured from actual runs rather than inferred from the constants. Doc-only master merges are excluded from the push trigger by `paths-ignore`, but are still covered by the scheduled sweep, which was this ADR's original motivation.
- Positive: The merge-commit update strategy preserves original commits and keeps auto-merge armed across the refresh.
- Positive: Loop safety guarantees the same branch is never re-updated while its CI is live, so the job cannot thrash a PR.
- Neutral: The human-signoff hold is unaffected — refreshing a `feat:` PR's branch does not merge it while the `human-approved` label is absent; the required human-signoff check still gates the merge.
- Negative: A small recurring CI cost (one short job every 15 min); bounded and cheap, as most ticks find nothing to do and exit quickly.
- Negative (bounded by the debounce and, since 2026-08-28, by the event-dependent scope): an update pass still costs one full CI matrix per PR in scope. The debounce caps the scheduled all-open pass at roughly one per quiet period instead of one per merge; the armed-only push scope caps the per-merge pass at the PRs that BEHIND actually blocks. Neither makes an individual pass cheaper.
- Negative: while master moves more often than once an hour, PRs go un-updated. Acceptable: this workflow never merges and never arms auto-merge, so a stale-but-clean PR blocks nothing — and `workflow_dispatch` forces a pass when one is actually needed.

## References

- `.github/workflows/update-behind-prs.yml` — the workflow this ADR introduces.
- the eager-rebase workflow (retired; its `paths-ignore` gap was the motivation for this ADR; its `CI_PAT` pattern was adopted here).
