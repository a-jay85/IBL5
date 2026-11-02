---
description: The update-baselines label starts its own caller workflow, so e2e-tests.yml never triggers on label events and cannot re-post the required E2E Tests check.
last_verified: 2026-11-02
owner: ajaynicolas
---

# ADR-0225: Update-baselines regen runs from a caller workflow

**Status:** Accepted
**Date:** 2026-11-02
**Deciders:** a-jay85

## Context

`E2E Tests` is a required check posted by the `gate` job of `.github/workflows/e2e-tests.yml`. The gate fails only on a `failure` or `cancelled` need. `e2e-tests.yml` listed `labeled` in `pull_request.types`, so every label event (`human-approved`, `post-plan-running`) started a run that skipped every work job and re-posted `E2E Tests` as success on the same head SHA. That turned a red required gate green. [Run 41094364054](https://github.com/a-jay85/IBL5/actions/runs/41094364054) was a `human-approved` label event: it finished `success`, its `E2E Tests` job passed, and `Visual Regression` was skipped. The report is [backlog#1707](https://github.com/a-jay85/IBL5-backlog/issues/1707). The `update-baselines` label had the same hole, because its `continue-on-error` flips let `Visual Regression` go green on a failed VR step.

## Decision

`e2e-tests.yml` does not trigger on `labeled` or `unlabeled`. The regen runs from `.github/workflows/update-baselines.yml` (workflow name `VR Baseline Regen`). It triggers on `labeled`, runs only when the label name is `update-baselines`, and calls `e2e-tests.yml` through `workflow_call` with no inputs. The three `workflow_call` inputs are optional so that call starts. Inside the callee, `github.event.action == 'labeled'` marks regen mode, and the `gate` job skips in that mode. Called-workflow checks report as `Update baselines / <job>`, so they cannot overwrite `E2E Tests` or `Visual Regression`. The per-label `run_id` concurrency suffix moves to the caller, so `post-plan-running` cannot cancel a regen.

`bin/check-composite-contracts` pins this. L1 rejects a `labeled` or `unlabeled` type in the `e2e-tests.yml` `on:` block. L2 rejects a job or workflow name in the caller that equals a required check name. A6 requires `VR Baseline Regen` in `all-checks-settled.yml` `workflow_run.workflows`. `ibl5/tests/ts-unit/vr-workflow-guard.test.ts` tests 14a to 14f pin the caller wiring.

## Alternatives Considered

- **Gate looks up the prior result** (the issue's option 1). Rejected because: every label event still churns the required check.
- **Skip the `gate` job on label runs.** Rejected because: a skipped check counts as success, so the hole stays.
- **Standalone copy of the VR steps.** A second workflow with its own steps. Rejected because: about 700 duplicated lines, and the publish-step guards in `vr-workflow-guard.test.ts` would watch only one of two copies.
- **Shared reusable VR workflow.** Extract the VR steps into a new reusable file. Rejected because: it is the largest diff and re-points `vr-refresh.yml`, the guard test, and `bin/test-vr-apply-baselines` for no gain.

## Consequences

- Positive: a label event can no longer re-post `E2E Tests`, whatever the label.
- Positive: no regen or publish step moved, so the existing guard tests keep watching the code that runs.
- Negative: applying `update-baselines` no longer cancels an in-flight `E2E Tests` run, because the regen's concurrency group carries the caller's workflow name. The bot push still starts a fresh `E2E Tests` run through `CI_PAT`.
- Negative: every label event starts a short `VR Baseline Regen` run whose only job is skipped.
- Neutral: the regen's checks count in `All checks settled` (not ignored), as they did while they lived in `e2e-tests.yml`.

## References

- ADR-0074, ADR-0181 and ADR-0214 describe the label as an `e2e-tests.yml` behavior. They stay unedited, and this ADR supplies the move.
- `.github/workflows/update-baselines.yml`
- `.github/workflows/e2e-tests.yml`
- `.github/workflows/vr-refresh.yml`
- `bin/check-composite-contracts`
- `ibl5/tests/ts-unit/vr-workflow-guard.test.ts`
