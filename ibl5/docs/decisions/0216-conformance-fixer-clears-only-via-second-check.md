---
description: The post-plan Phase 5.0-pre fixer may write plan-promised tests and files, and only the final mechanical conformance pass after Phase 5 verify can clear arming condition (3).
last_verified: 2026-10-25
owner: ajaynicolas
---

# ADR-0216: Conformance fixer clears condition (3) only through the second check

**Status:** Accepted
**Date:** 2026-10-25
**Deciders:** a-jay85

## Context

Phase 5.0 (`harness/conformance.py`) reports plan items the diff never realised: `MISSING:` matrix test paths, `MISSING-FILE:` Critical Files, `MISSING-METHOD:` Required Test Methods, `UNREALISED-ASSERTION:` rows, `MISSING-PHASE:` phases. Any unresolved item holds arming condition (3). On 2026-10-24 `bin/pr-triage --held` listed eight PRs held on condition (3), several of them with every production edit present and only a promised test file or method absent. Phase 5.5 remediation runs too late to help Phase 5 verify, and a "drop with reason" plan marker was rejected because it weakens condition (3).

## Decision

A bounded fixer (`harness/conformance_fix.py`) runs once per post-plan run, after Phase 4.5 and before the review worker, Phase 5 verify and Phase 5.0. It receives only items in the fixable classes (`MISSING-METHOD`, test-path `MISSING:`, non-denied `MISSING-FILE`, `UNREALISED-ASSERTION`); `MISSING-PHASE` and `UNMET-CONTRACT` never reach it. It may write only inside plan-cited paths, may only add lines to a pre-existing test file, has no Bash, and is subject to line caps and the gate-owning deny list. Its edits are reverted unless mechanical post-checks pass; the harness commits and pushes what survives. Nothing the fixer writes or reports clears anything: condition (3) is decided by the existing final `conformance.check` after Phase 5 verify, and the done-marker is written only by that pass. `POSTPLAN_CONFORMANCE_FIX=0` disables the fixer, which restores the prior behavior exactly. Like every harness change, this is inert until merged to the main-checkout `master` (ADR-0092).

## Alternatives Considered

- **Drop marker.** A plan marker that drops an item with a reason. Rejected because it lets a plan author clear condition (3) by assertion, which weakens the gate.
- **Phase 5.5 writes the tests.** Rejected because Phase 5.5 runs after Phase 5 verify, so the new tests would never run before arming.
- **Fixer report clears the item.** Rejected because a model's claim would then enter the arming decision with no mechanical check behind it.

## Consequences

- Positive: a PR held only because a promised test file or method is absent can arm in the same run, once the fixer writes it and Phase 5 verify runs it.
- Positive: the hold line on condition (3) names the fixer outcome as ` [conformance-fix: <reason>]`, so a human sees why a promised item was not written.
- Negative: each run whose pre-verify pass finds fixable items pays for one more Sonnet round, even when the post-checks then reject its edits.
- Negative: a fixer commit moves the branch head before review, so the reviewed diff includes model-written tests that a human did not write.

## References

- `tools/postplan-harness/harness/conformance_fix.py`
- `tools/postplan-harness/runner.py`
- `tools/postplan-harness/harness/armable.py`
- `tools/postplan-harness/harness/conformance.py`
- ADR-0092 (main-checkout harness pin)
