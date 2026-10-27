---
description: Post-plan's Phase 4.5 fixer also dispositions the review threads the same run posted, one round, then a delta re-review.
last_verified: 2026-10-23
---

# ADR-0207: Same-run review thread remediation

**Status:** Accepted
**Date:** 2026-10-21

## Context

Phase 4.5 acts only on threads in the pre-posting snapshot, so findings the same run's Phase 4D posts are never remediated. On 2026-10-20, 11 of 39 open PRs were held on `unresolved-finding`, and pr-cycle skips a held PR whose checks are green, so no later run comes along. Sampled findings were mechanical bugs with local fixes. Arming condition (11) reads open threads from GitHub, while condition (2) reads the run's own scored findings from memory. Both must release for a fixed finding, and neither predicate may be edited because their shell and Python twins are parity-tested.

## Decision

Widen the Phase 4.5 candidate set to this run's own threads. They are identified by a post-join thread-id snapshot minus the pre-posting snapshot, then restricted to threads whose `(path, body prefix)` the process itself posted. Reuse the existing fixer, packet, trust contract and commit/push/resolve path. One round only: after a fix commit, the code review runs once over the fix delta, and new findings are posted and left open. Fixed or declined findings are pruned from the in-memory list condition (2) reads, and the predicates themselves are unchanged. A fix commit re-keys CI to the new head, re-runs conformance, and re-pins fidelity verdict 2 once when one exists. Phase 5 verify is not re-run locally. CI verifies the new head, as it does for a fidelity remediation commit. Outcome-log events carry `origin` so the two sources can be reported apart. The skill fallback keeps its pre-existing-only candidate set, because it has no record of what its own Phase 4D posted. This decision covers the compiled harness only. It supersedes the old rule in `.claude/review-shared/_posting-procedure.md` that a run never dispositions its own fresh findings.

## Alternatives Considered

- **Inside the fidelity loop.** Run the pass inside the fidelity remediation loop, which reuses an existing fixer loop. Rejected because it couples two different gates and lets fidelity rounds consume the same-run budget.
- **Move Phase 4.5 after the review join.** One pass for both sets. Rejected because it changes the pre-existing path's semantics and every existing wiring test.
- **Predicate edit.** Edit conditions (11) and (2) to ignore same-run threads, which frees the hold without a fixer. Rejected because it weakens the gate and the shell and Python twins would need matching edits.

## Consequences

- Positive: a PR previously held only on its own review threads can now arm after the fixer resolves them.
- Positive: a DECLINE with a stated reason and category is enough to release, as it already is for pre-existing threads.
- Positive: threads opened by other actors during the review window are untouched. A failed snapshot or thread fetch acts on nothing and keeps the hold.
- Negative: one extra bounded review pass and, when a verdict 2 exists, one extra fidelity re-review per run that fixes something.

## References

- `tools/postplan-harness/README.md`, Safety model: Phase 4.5b same-run thread remediation.
- `.claude/review-shared/_posting-procedure.md`, Remediating trusted threads.
- The skill's Phase 4 file (retired 2026-10-21 by PR #3367), section 4.5. `tools/postplan-harness/harness/thread_ingestion.py` now holds the only copy.
