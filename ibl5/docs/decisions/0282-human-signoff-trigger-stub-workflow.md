---
description: The human-signoff gate splits into a PR-side trigger stub and a master-run workflow_run gate that posts a commit status.
last_verified: 2026-11-24
---

# ADR-0282: Human-signoff trigger stub plus workflow_run gate

**Status:** Accepted
**Date:** 2026-11-24

## Context

`human-signoff.yml` ran on `pull_request`. GitHub runs the PR's own copy of a `pull_request` workflow, so a PR could edit the gate file and turn its own `human-signoff` check green ([backlog#1798](https://github.com/a-jay85/IBL5-backlog/issues/1798)). Loading the classifier lib from the base ref left that hole open, because the workflow file still came from the head. The repo cannot use `pull_request_target`: GitHub blocks it by default in public repos, and `bin/check-workflow-run-interpolation` denies it.

## Decision

We split the gate into two workflows. `.github/workflows/human-signoff-trigger.yml` runs on the PR's `pull_request` events with `permissions: {}` and one job that runs `true`. It decides nothing. Its run title `hs:<action>:<label>` carries the event to the gate. `.github/workflows/human-signoff.yml` runs on `workflow_run` of that stub, so GitHub always runs master's copy. It judges the PR from API data and master's `bin/lib/human-signoff-classifier.sh`, then posts a `human-signoff` commit status on the PR head. The gate trusts a stub run only when the head's blob of the stub equals master's (`hs_trigger_trusted`). A PR that edits the stub holds for the owner, who merges it with `gh pr merge --admin <N>` after reading the diff. `bin/test-human-signoff-classifier` pins the trust boundary in `hs_trust_boundary_ok`.

## Alternatives Considered

- **Base-ref lib.** Keep `pull_request` and load the lib from the base ref. Rejected because the PR still controls the workflow file itself.
- **`pull_request_target`.** Rejected because GitHub blocks it by default in public repos and `bin/check-workflow-run-interpolation` denies it.
- **Check run.** Post a check run from the `workflow_run` job. Rejected because that check run lands on master's SHA, so the PR never sees it.

## Consequences

- Positive: a PR can no longer edit the gate and pass its own sign-off.
- Positive: the new workflow carries no permissions and runs no PR code.
- Negative: `All checks settled` never hears the gate finish, because the gate's run belongs to master's tip. The gate's `post-status` job re-runs master's `bin/settle-pr-status` on the PR head after it posts, with the same flags as `all-checks-settled.yml`.
- Negative: a PR that edits the stub always holds and needs an admin merge.
- Negative: a Dependabot-triggered `workflow_run` gets a read-only token, so the owner re-runs the gate with `gh workflow run human-signoff.yml -f pr_number=<N>`.

## References

- `.github/workflows/human-signoff-trigger.yml`
- `.github/workflows/human-signoff.yml`
- `bin/lib/human-signoff-classifier.sh`
- `bin/test-human-signoff-classifier`
- `ibl5/docs/decisions/0062-human-signoff-gate-for-feature-prs.md` (Update 2026-11-21)
