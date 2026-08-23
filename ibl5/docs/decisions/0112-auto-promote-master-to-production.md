---
description: master fast-forwards to production automatically on all-green CI, gated by the AUTO_PROMOTE_PAUSED repo variable; the push is FF-only and uses CI_PAT.
last_verified: 2026-08-23
---

# ADR-0112: Auto-promote master to production on green CI

**Status:** Accepted
**Date:** 2026-08-23
**Deciders:** ajaynicolas

## Context

Promotion to `production` has been a manual act: the maintainer runs `bin/merge-master-to-prod`, which unshallows the clone if needed, verifies `bin/check-master-ci-green` on master HEAD, fast-forwards `production`, pushes, and prunes merged worktrees. The push updates `production`, which is the trigger for `.github/workflows/main.yml` (`Build and Deploy`) — so *that push is the deploy*. The gate is therefore already fully mechanical; the only human contribution is remembering to run it. The observed cost is latency: green code sits on `master` until someone is at a terminal. A fix that is verified, reviewed, and merged is not a fix until it is deployed.

## Decision

A `workflow_run`-triggered workflow, `.github/workflows/promote-to-production.yml`, fast-forwards `master` to `production` when master HEAD is all-green. Each load-bearing property is explained below, because an undocumented constraint is the first thing a future editor removes:

- **Kill switch:** repo variable `AUTO_PROMOTE_PAUSED`. `1` pauses; unset or anything else runs. Evaluated as `vars.AUTO_PROMOTE_PAUSED != '1'` in the job `if:`, so a paused repo never checks out, never authenticates, and never reaches a push. A *repo variable* rather than a file in the tree, precisely because pausing must not require a PR — the emergency case is "stop deploying, now".

- **Credential:** `secrets.CI_PAT`, never `GITHUB_TOKEN`. The reason is a bug, not a preference: a `GITHUB_TOKEN` push does not start downstream workflows, so it would move the `production` ref and start **no** `Build and Deploy` — the ref says shipped, the site says otherwise, and nothing is red. `permissions: contents: read` is declared deliberately so that a future regression to `GITHUB_TOKEN` fails loudly instead of reproducing that silent state.

- **Push shape:** `git push origin "$SHA:refs/heads/production"` — the *verified SHA*, not the branch name, closing the window between "verified master HEAD" and "pushed master"; plain refspec with no `+` and no force flag of any kind, so a diverged `production` is rejected by the server and the run fails loudly.

- **Green definition:** `bin/check-master-ci-green` with `MIN_REQUIRED_CHECKS=3` and `MAX_WALKBACK=0` — the SHA's own checks or nothing.

- **Self-exclusion:** the promoter's own jobs post check-runs onto the SHA they are evaluating, so they are excluded by exact name via `SKIP_CHECKS`. Without this, the workflow deadlocks on itself and never promotes, ever.

- **Trigger breadth:** all thirteen master-push workflows are listed, because eleven are path-filtered and the two unfiltered ones are fast. Fewer names is not a smaller surface — it is a promoter that only ever looks too early.

- **Provenance guards:** `head_branch == 'master' && head_repository.full_name == github.repository && workflow_run.event == 'push'`, plus a runtime `git merge-base --is-ancestor` re-check in `bin/promote-master-to-production`.

- **Notification:** Discord DM via `.github/actions/notify-discord` on promoted, on paused, and on hard failure — never on the ordinary "not green yet" no-op.

- **`bin/merge-master-to-prod` is retained unchanged as the manual/emergency path**, which is what makes pausing cheap and reversible.

## Alternatives Considered

- **A scheduled `cron` promoter** (e.g. hourly, promote whatever is green). Rejected: it decouples the deploy from the merge that caused it, so a bad merge's blast radius is bounded by a clock instead of by CI, and the DM arrives with no causal commit attached. `workflow_run` promotes *because* this SHA went green.

- **Filtering the trigger on `workflow_run.conclusion == 'success'`.** Rejected: the trigger decides *when to look*, the green check decides *what to trust*. Conclusion-filtering only removes chances to look, and if the last arriving workflow is the one that is skipped or cancelled, the promoter is never invited back for that SHA — a merge that is genuinely green silently never ships.

- **`push` on `master` as the trigger instead of `workflow_run`.** Rejected: it fires before any check exists, so every run sees "checks still running" and exits; there is no second event.

- **Adding `workflow_dispatch` to the new workflow for a manual button.** Rejected: `bin/merge-master-to-prod` already *is* the manual path, and it runs under the maintainer's own credentials. A dispatch trigger would widen the automated-push surface for zero new capability.

- **Granting `permissions: contents: write` "so the push works".** Rejected as actively harmful — see the Decision section; withholding it is the mechanism that converts a credential regression from silent into loud.

- **Automatic/time-based pausing** (e.g. pause overnight, or around backups). Rejected as out of scope and as a false safety: it would add a second, invisible reason for promotion not to happen, competing with the one explicit control the maintainer is meant to trust.

## Consequences

- Positive: merge-to-live latency collapses to CI duration; the deploy is attributable to a specific SHA and announced by DM.
- Positive: the security properties are pinned by a gate, not by review — `bin/check-composite-contracts` fails the PR on a force flag, a `contents: write`, a `GITHUB_TOKEN` push, a missing kill-switch clause, a missing fork guard, a non-zero walkback, or `SKIP_CHECKS`-vs-job-name drift (Phase 5).
- Negative: **a docs-only merge does not self-promote.** Only two check-runs exist on such a SHA at decision time (`Meta checks`, `Scan for committed secrets`), which is below `MIN_REQUIRED_CHECKS=3`, so the promoter fails closed and the change rides the next code merge. This is the intended direction of failure; a maintainer who wants it live now runs `bin/merge-master-to-prod`.
- Negative: any red check on master blocks promotion, including a red `Canary PR (NNNN)` check-run that is unrelated to master's own health. Escape hatch: set `AUTO_PROMOTE_PAUSED=1` and promote manually.
- Negative: one more unattended path holding a PAT that bypasses branch protection. Mitigated by the eight pinned invariants in `bin/check-composite-contracts`, by FF-only pushes (nothing is ever overwritten), and by the fact that the workflow runs no repo-supplied code beyond two `bin/` scripts already in the diff.

## References

- `.github/workflows/promote-to-production.yml`
- `bin/promote-master-to-production`
- `bin/test-promote-master-to-production`
- `bin/check-master-ci-green`
- `bin/merge-master-to-prod`
- `.github/actions/notify-discord`
- `.github/workflows/main.yml`
- `bin/check-composite-contracts`
- `.claude/rules/meta-tooling-bar.md`
