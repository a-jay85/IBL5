---
description: Post-plan writes the plan's CI-runnable matrix rows and mutation rows into the PR body, and the required Plan checks job runs them.
last_verified: 2026-11-12
---

# ADR-0244: Plan Checks Run in a Required CI Job, Read from the PR Body

**Status:** Accepted
**Date:** 2026-11-12

## Context

Post-plan's local mutation run took about 8 hours of Mac time from 2026-11-05 to 2026-11-12. Each run took 8 to 12 minutes, and the longest took 33. Pre-fire's matrix stage was its slowest stage at about 5 minutes. ADR-0227 kept the gate on the Mac after a CI attempt shipped red. ADR-0199 designed the executable mutations. A CI job cannot read the plan, because plans live outside the repo and the owner ruled out a token or a private repo for them.

## Decision

Post-plan writes a base64 plan-checks block in a collapsed `<details>` section of the PR body and adds the `plan-checks` label. The block holds the matrix rows CI can run and the parsed mutation records, compressed, with a SHA-256 header. The required `Plan checks` job (`.github/workflows/plan-checks.yml`) decodes the block with `bin/lib/plan-checks-block ci` and runs the CI rows and then the mutation rows through `bin/lib/plan-exec-mutations run --records`. Pre-fire runs only the rows that `row_runs_in_ci` in `bin/lib/plan-matrix-rows.sh` keeps local, such as DB rows and rows that need the plan file. The fire point no longer runs exec-mutations. Arming condition 19 holds on a failed CI result, a missing label, or a stale block.

## Alternatives Considered

- **CI reads the plan itself.** Rejected because: it needs a private plans repo or a token, which the owner ruled out.
- **A commit trailer as the plan-driven signal.** Rejected because: fix rounds add commits on top, so CI would have to walk history.
- **Plain JSON in a fence.** Rejected because: a markdown edit, a backtick, or `-->` inside a field corrupts it silently. The SHA turns any damage into a hard fail.

## Why dropping the local gate is safe

The `Plan checks` context is required, and `All checks settled` lists it, so no PR merges while a row fails. The label makes a lost block fail closed: a labelled PR whose body has no block fails the job.

## Consequences

- Positive: the Mac no longer spends 8 to 33 minutes per plan on mutation runs.
- Negative: CI minutes rise, since every body edit re-runs the job.
- Negative: DB rows still need the local worktree stack.
- Negative: a plan-driven PR whose harness crashed before Phase 6.5 carries neither label nor block. Condition 19 never armed it, and an owner who merges it by hand skips the CI rows.

## Rollback

Revert this PR, then remove the context with `gh api -X DELETE repos/a-jay85/IBL5/branches/master/protection/required_status_checks/contexts -f 'contexts[]=Plan checks'`.

## Lineage

Supersedes in part ADR-0227, since the local gate keeps only the rows that need the Mac. Supersedes in part ADR-0199, since the mutation rows now run in the CI job.

## References

- `bin/lib/plan-checks-block`
- `bin/lib/plan-matrix-rows.sh`
- `bin/lib/prefire-verify`
- `bin/lib/plan-exec-mutations`
- `.github/workflows/plan-checks.yml`
- `tools/postplan-harness/runner.py`
- `tools/postplan-harness/harness/armable.py`
