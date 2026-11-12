---
description: A pull_request edited job dispatches a master-only workflow_dispatch run that re-arms auto-merge after a PR body edit when the last post-plan run held the PR only on body-text conditions and those now pass.
last_verified: 2026-11-12
---

# ADR-0245: CI re-arms auto-merge after a body edit clears a text-only hold

**Status:** Accepted
**Date:** 2026-11-12
**Deciders:** ajaynicolas

## Context

Post-plan holds auto-merge when the PR body misses a removed-guards reason (condition 20) or carries a missing or malformed merge-danger line (condition 18). The owner fixes those holds by editing the PR body. Until now nothing re-checked the body after that edit, so the PR stayed held until someone ran post-plan again by hand. PR #3898 sat held on condition 20 for that reason. The local hold state under `tools/postplan-harness/out/state/` is invisible to GitHub Actions, and the sticky's plain-words bullets do not say whether (18) held as one-way.

## Decision

Post-plan writes one machine-read line into the audit trail of a held sticky: `<!-- pr-ready-holds: v1 diff=<patch-id> held=<n>[:<STATE>],... -->`. Two workflows carry the re-check. `.github/workflows/text-hold-recheck.yml` runs on `pull_request` `edited` only. It holds no secret, checks out nothing, and only dispatches `.github/workflows/text-hold-recheck-run.yml` on master. That `workflow_dispatch` job refuses any ref but master and runs master's `bin/text-hold-recheck` with `CI_PAT`, so a PR cannot change the code that decides whether it arms. The driver reads the record from the newest owner-authored sticky whose last line is the verdict marker. It arms with `CI_PAT` only when all of these hold: every recorded hold is (20) or (18) in state `MISSING` or `MALFORMED`; the current patch-id equals the recorded one; the live UI floor (condition 8) passes; and both body checks in `bin/lib/pr-armable.sh` now pass. Any other input yields `NOARM` and exit 0. A push never re-arms here. A push that changes the diff changes its patch-id, and `bin/pr-cycle` then re-runs full post-plan, which arms through its own Phase 6.5. The decision logic lives in `bin/lib/text-hold-recheck.sh`, and `bin/test-text-hold-recheck` covers it on every PR.

## Alternatives Considered

- **A `bin/pr-cycle` stage.** It has the full local `result.json`. Rejected because it runs only on the owner's machine on its own cadence, so a body edit could wait hours.
- **Extend `.github/workflows/auto-mergeable-label.yml`.** It already fires on `edited`. Rejected because it runs under `pull_request` and maintains a label, and adding a privileged arming step there would mix a `CI_PAT` trigger into a label job.
- **One `pull_request` job that arms in place.** Rejected because under `pull_request` the workflow file and `bin/` come from the PR head, so the PR could rewrite the driver and arm itself with `CI_PAT`.
- **`pull_request_target`.** Rejected because GitHub blocks it by default for public repos and `bin/check-workflow-run-interpolation` denies it (ADR-0223 addendum of 2026-11-09).
- **Parse the `- (N)` bullets with `hp_sticky_reasons`.** Rejected because the bullets are plain words and do not carry the (18) state. A versioned line with an exact regex fails closed on any drift.
- **Key the record on the head SHA.** Rejected because the sticky already stores the patch-id, and `bin/pr-cycle` treats it as the identity of a reviewed diff. A head SHA would treat a rebase onto an unchanged diff as a new diff.
- **Pass the plan to `bin/lib/deleted-guards` in CI.** Rejected because shipping plan text to Actions needs a new channel. Without `--plan`, CI flags a superset of the guards the harness flagged, which can only add holds.

## Consequences

- Positive: a body edit that fixes a text hold re-arms within a minute, with no command to remember.
- Positive: CI and the harness agree on the body grammar, because the driver calls the shell twins that share `bin/fixtures/removed-guards/cases.json` with the Python classifier.
- Negative: a sticky written before this change carries no record and never re-arms. Each such PR needs one post-plan run to gain a record.
- Negative: the dispatched run cannot run before merge, because GitHub dispatches only a workflow master already carries. Until then the edited job takes its 404 no-op path. The first real body edit after merge exercises the full path.
- Negative: `fidelity.machine_hold_record` and `THR_RECORD_RE` in `bin/lib/text-hold-recheck.sh` must change together.

## References

- `tools/postplan-harness/harness/fidelity.py` (`machine_hold_record`, `compose_sticky`)
- `bin/lib/text-hold-recheck.sh`
- `bin/text-hold-recheck`
- `bin/test-text-hold-recheck`
- `.github/workflows/text-hold-recheck.yml`
- `.github/workflows/text-hold-recheck-run.yml`
- `bin/lib/pr-armable.sh` (`pr_removed_guards_hold`, `pr_merge_danger_hold`, `pr_ui_hold`)
- `bin/lib/pr-cycle-watch.sh` (`pcw_patch_id`)
- `.github/workflows/dependabot-auto-merge.yml` (the `CI_PAT` arming pattern)
