---
description: Gate judgment on CI-modifying PRs is an input to the Kahn merge-order sort, not a post-hoc annotation — enforced by the two-invocation design of bin/pr-attack.
last_verified: 2026-09-01
---

# ADR-0113: Gate judgment is a sort input, not a post-hoc annotation

**Status:** Accepted
**Date:** 2026-09-01

## Context

`.claude/skills/pr-attack/SKILL.md` Step 3b nominates "gate-sensitive" PRs — those touching `.github/workflows/`, `bin/check-*`, `bin/lib/pr-armable.sh`, or related ship-pipeline files. For each nominee, the question is: does this PR add or tighten a CI check that another orderable PR would violate, such that ordering them wrong either red-lights a PR that is actually fine or grants false confidence? That question requires reading the nominee's diff against the context of every other open PR — it cannot be answered mechanically. When `bin/pr-attack` mechanizes the deterministic steps of the skill, a design choice arises: do the gate judgments feed *into* Kahn's topological sort as edges (input), or do they annotate the output order after the sort runs (post-hoc)?

## Decision

Gate judgment is a **sort input**, not a post-hoc annotation. `bin/pr-attack` uses a two-invocation design: invocation 1 (`--gate-candidates`) identifies nominees and halts; invocation 2 (`--work … --gate-edge '#A>#B' …`) ingests the human-supplied judgments as `gate`-type edges and then runs Kahn's algorithm. If gate candidates are present and invocation 2 is called without any `--gate-edge` or `--gate-edges` argument, the script exits 2 and writes nothing — there is no `--no-gate` bypass flag. `--gate-edges /dev/null` is the judged-empty case (the human reviewed all nominees and concluded no edge exists); it succeeds and proceeds. The constraint is enforced mechanically: the exit-2 branch in `bin/pr-attack`'s `emit_order()` function.

## Alternatives Considered

- **One-pass design** — run gate judgment inline with the sort. Rejected because: the question "does this gate affect that PR?" requires reading the nominee's diff against every other PR, which means the human must be in the loop *before* an order can be produced; a one-pass shape would either skip the judgment or block mid-run waiting for it.
- **Post-hoc annotation** — produce the order first, then let the human annotate it with gate edges. Rejected because: a gate edge can change the order entirely (gate-first means affected PRs must land first; gate-last means they must land after). Annotating an already-committed order makes it meaningless — the annotation would overrule the row numbers.
- **`--no-gate` flag** — let the caller skip judgment when confident no edge exists. Rejected because: the judged-empty case (`--gate-edges /dev/null`) already handles this with an explicit human signal; a `--no-gate` flag provides no audit trail and makes silent omissions indistinguishable from deliberate ones.

## Consequences

- Positive: gate edges participate in cycle detection and hub-degree tie-breaking, producing a total order that reflects real constraints.
- Positive: the exit-2 path makes unjudged candidates impossible to miss; there is no way to produce a document while leaving a gate question unanswered.
- Negative: two shell invocations instead of one; the caller must thread the `$WORK` directory between them and supply judgment before the second runs.

## References

- `bin/pr-attack` — enforces the two-invocation contract and the exit-2 guard.
- `.claude/skills/pr-attack/SKILL.md` Step 3b — the gate-sensitivity heuristic and the judgment question.
- `.claude/rules/work-triage.md` § Safety mirror — why ship-pipeline-surface changes warrant a plan and human sign-off.
