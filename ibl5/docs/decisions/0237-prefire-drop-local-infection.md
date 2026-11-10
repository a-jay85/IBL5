---
description: bin/lib/prefire-verify drops its local Infection stage; CI's required per-PR Infection check is the mutation gate. Partially supersedes ADR-0227.
last_verified: 2026-11-09
---

# ADR-0237: Drop the local Infection stage from prefire-verify

**Status:** Accepted
**Date:** 2026-11-09
**Deciders:** ajaynicolas
**Supersedes:** ADR-0227, the Infection stage only

## Context

ADR-0227 added a local Infection stage to the pre-fire gate after [#3672](https://github.com/a-jay85/IBL5/pull/3672). The stage ran `ibl5/bin/check-infection-excludes` and `bin/mutation-diff` on the dev machine before every fire. That repeats work CI's required per-PR check already does, and it ties up the dev machine for the length of a coverage run.

## Decision

`bin/lib/prefire-verify` runs three stages: matrix, phpunit and exec-mutations. `--stage infection` exits 2 with a message that names the CI check and says to skip the call. `bin/mutation-diff`, its lock and `bin/test-mutation-diff` stay as an opt-in local tool. The `PREFIRE_MUTATION_CMD` and `PREFIRE_EXCLUDES_CMD` test seams are gone. Stale `infection` pass records stay in place, and nothing reads them. `bin/test-prefire-verify` pins the removal: `test_stage_infection_removed_exits_2` checks the exit and message, and `test_all_never_invokes_infection` plants failing stubs at the old default paths and checks that `--stage all` never calls them.

## Why #3672 is still caught

The check `Infection PHP (per-PR diff)` became required on 2026-09-29 in [#2534](https://github.com/a-jay85/IBL5/pull/2534), before #3672 existed. #3672 merged on 2026-11-05, and its final `Infection PHP (per-PR diff)` result was SUCCESS. The run with 491 escaped mutants was a red CI result. The required check held the PR until a later run went green, so those mutants never reached master.

One caveat applies. The status rollup keeps only the latest run, so the earlier red state rests on ADR-0227's Context.

CI runs Infection diff-scoped at `--min-msi=100` in `.github/workflows/mutation-pr.yml`. The `bin/test-prefire-verify` case `test_mutation_diff_flags_match_ci` pins that the local runner's flags match CI's. CI also runs `ibl5/bin/check-infection-excludes` in `.github/workflows/tests.yml`, `.github/workflows/mutation-pr.yml` and `.github/workflows/mutation.yml`. To confirm the check is required, run `gh api repos/a-jay85/IBL5/branches/master/protection --jq '.required_status_checks.contexts'`.

## Alternatives Considered

- **Keep the stage and lower its cost** by cutting Infection threads or running it under `nice`. Rejected because the owner chose removal.
- **Keep `ibl5/bin/check-infection-excludes` as a local pre-step.** Rejected because three CI workflows already run it, so a local copy adds upkeep and no coverage.
- **Have `--stage infection` exit 0 as a silent skip.** Rejected because a pass for a stage that ran nothing reads as verification.
- **Treat `infection` as a generic unknown stage.** Rejected because the generic message does not tell a mid-flight automouse run what to do.

## Consequences

- Positive: a local fire no longer needs pcov, Docker for Infection, or the `bin/mutation-diff` lock.
- Positive: the gate does less duplicate work before each ship.
- Negative: escaped mutants now surface only after the PR opens, in CI.
- Negative: an automouse run whose prompt was rendered before this merged calls `--stage infection` once and gets exit 2. The message tells it to skip the call.
