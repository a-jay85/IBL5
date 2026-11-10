---
description: bin/post-plan-now runs bin/lib/prefire-verify (full PHPUnit, the plan's matrix rows, changed-file Infection at MSI 100) and exits 9 until all three pass on the current tree; the automouse impl prompt runs it stage by stage first. The Infection stage was removed by ADR-0237.
last_verified: 2026-11-09
---

# ADR-0227: Pre-fire verification gate in post-plan-now

**Status:** Accepted; the Infection stage is superseded by ADR-0237
**Date:** 2026-11-03
**Deciders:** ajaynicolas

## Context

PR #3672 shipped with 491 of 2295 mutants escaped and CI's per-PR Infection job red. Its implementation ran no Infection before firing. Two paths fire a ship: a human or interactive session calling `bin/post-plan-now`, and the automouse runner calling `post-plan-now --foreground` from the handoff file. Neither checked that verification had run.

## Decision

- One gate script, `bin/lib/prefire-verify`, is called from `bin/post-plan-now` before any launch. It exits 9 on refusal.
- Automouse's `bin/automouse/prompt-impl` Step 6.9 runs the same script stage by stage, so the impl agent can fix failures while it still has the tree.
- The gate re-runs every check and trusts no implementer-written ledger. Pass records are keyed by tree hash and written only by the gate.
- Infection runs through `bin/mutation-diff`, so CI's flags and MSI 100 live in one place (the per-PR check of ADR-0145).
- The gate fails closed: a missing prerequisite, a timed-out stage, or a missing record all mean no fire.
- The bypass, `--skip-verify=<reason>`, is human-only, needs a reason of at least 15 characters, is refused under `CLAUDE_HEADLESS`, and is logged to `prefire-verify-bypass.log` in the main checkout's `.git`.

## Extend-before-add

No existing host owns this. `bin/lib/plan-matrix-assertions` checks that a diff footprint exists and never executes a row. `bin/post-plan-now` is the host, but the same logic must also be callable stage by stage from the impl prompt, so it lives in `bin/lib/` and is called from both places. The matrix parser moved from `bin/check-plan` into the shared `bin/lib/plan-matrix-rows.sh`, so there is one parser.

## Alternatives Considered

- **Trust a ledger the implementer writes.** Rejected because: the check would then rest on the claim it exists to verify.
- **Gate only in CI.** Rejected because: CI already has the Infection job and `#3672` shipped red anyway; the failure must reach the implementer before the fire.

## Consequences

- Negative: the first fire on a new tree costs a full PHPUnit run plus a changed-file mutation run.
- Positive: a stage the impl session could not finish within its 600 s cap reruns at the fire point, inside the runner's remaining budget.
- Neutral: ad-hoc runs with no plan keep PHPUnit and Infection and skip the matrix stage.
- Neutral: fleet rescues (`--pr`) are gated too, and `--rebase-only` is exempt because it pushes no new content.

## References

- `bin/lib/prefire-verify`: the gate and its pass records.
- `bin/test-prefire-verify`: the harness.
- `bin/post-plan-now`: the fire point and exit 9.
- `bin/automouse/prompt-impl`: Step 6.9.
- `bin/mutation-diff`: the shared Infection runner.
- `bin/lib/plan-matrix-rows.sh`: the shared matrix parser.
- `.github/workflows/mutation-pr.yml`: the CI side of the same check.
- `ibl5/docs/decisions/0145-mutation-pr-required-own-workflow.md`: the per-PR mutation check.

## Addendum 2026-11-03: fourth stage, exec-mutations

The gate gained a fourth stage, `exec-mutations`. It runs the plan's Executable Mutations rows through `bin/lib/plan-exec-mutations run`, which stays the only parser and runner (ADR-0199). Its pass record is keyed on tree hash and plan blob hash, so editing a row forces a rerun. Any verdict other than TRIPPED fails the stage. Each failing row prints a `NEXT-STEP` line from the lib, so this gate and post-plan gate 19 print the same guidance. Every gate call first restores a mutation left by a killed run, before it hashes the tree. Gate `[XM]` in `bin/check-plan` now also rejects a mutation row whose check references a `/tmp/` path, because that file is gone by the time either gate runs.

## Addendum 2026-11-04: exec-mutations limits differ at the fire point

The plan chose one fixed budget at both call sites so they would agree. PR review found that leaves any plan with slow checks permanently unshippable: the stage stays UNDECIDED, and automated runs refuse the human bypass. The owner approved the reversal in a [PR comment](https://github.com/a-jay85/IBL5/pull/3712#issuecomment-6640681504). Implementation keeps the short limits (120 s per check run, 300 s total), which fit one 600 s foreground call. `bin/post-plan-now` passes `--fire-point`, which uses 300 s and 1800 s, matching the post-plan gate in `tools/postplan-harness/harness/mutations.py`. A pass recorded under the short limits stays valid at the fire point, because the record is keyed on the tree and plan hashes alone. A timeout is still never a pass at either limit.

A clean-code run that times out (exit 124) now reports UNDECIDED instead of the BASELINE-FAILED that ADR-0199 describes. The same owner comment approves this change. Otherwise a check slower than the implementer limit could never be handed to the fire point. Both verdicts still hold at the post-plan gate, because `tools/postplan-harness/harness/mutations.py` accepts only TRIPPED. Any other non-zero clean-code exit stays BASELINE-FAILED, including a check that cannot start (exit 125 to 127), because more time cannot fix it.

## Addendum — Infection stage removed (2026-11-09) <!-- slop-ok -->

The gate ran four stages, including changed-file Infection through `bin/mutation-diff`. Since ADR-0237 it runs three: matrix, phpunit and exec-mutations. CI's required check `Infection PHP (per-PR diff)` is the mutation gate. The Context, Decision and Alternatives above are unchanged and record what was decided on 2026-11-03.
