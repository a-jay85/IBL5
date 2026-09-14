---
description: The eager-rebase workflow returns as manual workflow_dispatch only; the push-to-master auto-trigger that caused rebase storms is never restored.
last_verified: 2026-09-10
---

# ADR-0125: Manual-Dispatch Rebase for Open PRs

**Status:** Accepted
**Date:** 2026-09-10

## Context

`.github/workflows/rebase-prs.yml` used to rebase every open PR on each push to
`master`. Because the trigger was `push: branches: [master]`, a single merge set off a
rebase storm across the whole open-PR set — every rebased branch was force-pushed, and
every force-push re-ran that PR's full CI matrix. It was retired for exactly that reason
in PR #1949 (commit `514516fa3`), which also swept its references out of the docs. No
replacement was possible: this repo is User-owned on the free tier, so a GitHub merge
queue is permanently unavailable (`mergeQueue: null`; ADR-0081:22). Since then PRs have
been rebased by hand, one at a time.

The storm was never the objectionable part on its own. What made it unacceptable was that
it fired **automatically**, as an unchosen consequence of merging, at a moment nobody had
budgeted CI for. A maintainer who deliberately starts a storm, knows its size in advance,
and watches it settle is in a different situation entirely.

## Decision

Restore the eager-rebase workflow as `workflow_dispatch`-only, and never restore its
automatic trigger. The workflow has no `push`, `schedule`, or `pull_request` trigger and
must not acquire one; a human presses Run workflow or it does not run. To make the cost
knowable *before* it is incurred, the workflow carries a `dry_run` boolean input that
performs every local rebase — proving each branch applies cleanly and reporting the exact
orphaned commits it would drop — while pushing nothing, and the step summary reports the
PR counts either way. The workflow keeps its own `concurrency` group (`rebase-prs`) with
`cancel-in-progress: false`, distinct from `update-behind-prs`.

## Alternatives Considered

- **Leave it retired; keep rebasing by hand** — the status quo since PR #1949. Rejected because: the objection in #1949 was to the automatic trigger, not to the batch operation, and hand-rebasing N branches costs the same CI as the button while costing a maintainer's attention too.
- **Restore the `push: branches: [master]` trigger with a debounce or a PR-count cap** — keep it automatic but bound the blast radius. Rejected because: a bounded storm is still an unchosen one, and the cap becomes a second thing to tune; the value of manual dispatch is that a human has judged *this* moment, which no cap can encode.
- **Fold rebase into `.github/workflows/update-behind-prs.yml` as a strategy flag** — one workflow, two modes. Rejected because: ADR-0081 § Alternatives Considered already decided these stay separate. They use different strategies (that one adds a MERGE commit via the update-branch API and preserves commits and armed auto-merge; this one REBASES and force-pushes). More decisively, `concurrency` groups are repo-scoped and the *canceling* run's setting wins, so a shared group would let a scheduled `update-behind-prs` tick kill a manual rebase mid-flight.
- **Bypass the ADR gate with a commit marker** — treat this as a mechanical restore of a prior file. Rejected because: a trigger-less workflow is exactly the shape a future maintainer would "fix" by adding the trigger back, and only a recorded decision prevents that.

## Consequences

- Positive: the batch rebase is available again without reintroducing the failure that retired it; the trigger model, not the operation, was the defect.
- Positive: `dry_run` makes the cost legible before it is spent — the operator sees the PR count and the exact orphan drops before anything is pushed.
- Positive: the restored script fixes two latent defects in the retired version. It now checks the force-push exit status rather than assuming success (in the retired version the `git push` was a bare command inside a `then` block; under `bash -e`, a rejected `--force-with-lease` aborted the entire step — the batch stopped at the first rejection, remaining PRs went unrebased, and no step summary was written; the restored script puts the push in a condition so a rejection is reported per-PR and the batch continues), and it normalizes the `dry_run` input so an unexpected rendering fails *safe* (skip the push) rather than open (push anyway).
- Negative: the storm still exists — pressing the button still re-runs CI for every rebased PR. This ADR relocates the decision to a human; it does not reduce the cost.
- Negative: two overlapping branch-freshening workflows remain, and a maintainer must know which one to reach for. The race between them is handled rather than prevented: `--force-with-lease` fails safe on a stale lease, and the run reports the rejection instead of clobbering.

## References

- `.github/workflows/rebase-prs.yml` — the restored workflow; its header comment records the cost model and the no-auto-trigger constraint.
- `.github/workflows/update-behind-prs.yml` — the merge-commit sibling this deliberately does not merge into.
- `ibl5/docs/decisions/0081-scheduled-branch-update-for-armed-behind-prs.md` — § Alternatives Considered (separate files, independent concurrency groups) and the 2026-09-10 addendum recording this restoration.
- PR #1949 / commit `514516fa3` — the retirement this ADR partially reverses, and whose reasoning about the auto-trigger it preserves in full.
