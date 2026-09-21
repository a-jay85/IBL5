---
description: The post-plan harness drafts a missing ADR with Opus 5 when bin/pre-push-adr-hook denies a push for a decision-trigger surface, gated by the same local checks a human runs, one attempt per run.
last_verified: 2026-09-20
---

# ADR-0135: Post-Plan Harness Drafts Missing ADRs on Pre-Push Denial

**Status:** Accepted
**Date:** 2026-09-20
**Deciders:** A-Jay

## Context

`bin/pre-push-adr-hook` refuses a push when the branch adds a decision-trigger surface (a new `bin/` script, a `.claude/rules/` file, a workflow, a PHPStan rule, a destructive migration, a composer dependency) without an ADR. In the compiled post-plan harness that refusal is a `local-gate` denial, and `runner.py` maps it to exit 3: the run stops, DMs the human, and the branch waits until someone writes the ADR by hand and re-runs `bin/post-plan-now`. Every other mechanical gate on the push path already has a bounded auto-remediation (doc dates, stale lease, stale base). The missing ADR was the last wall on an otherwise unattended ship, and the document it asks for is drafted from the same diff the harness already holds.

## Decision

The post-plan harness auto-drafts missing ADRs using Opus 5 when the pre-push-adr-hook denies a push for a decision-trigger surface. `tools/postplan-harness/harness/adr_draft.py` runs one bounded `claude-opus-5` tooled call per run with Read, Grep, Glob and Write only, into a file allocated by `bin/next-adr`. The draft must open with a harness attribution blockquote, pass a structural validator, touch no other file, and clear `bin/check-numbering`, `bin/check-docs`, `bin/check-prose` and `bin/adr-check` locally before the harness commits it and re-pushes once. A numbering collision is fixed by renumbering the still-empty template before the model writes. Any failure re-raises the original hook denial, so the run still exits 3 and the DM names the drafted file. The wrapper `_push_with_adr_draft` sits outside `_push_with_lease_retry`, runs at the Phase 2 and Phase 5.5 push sites, and never fires on a stale-base denial. `bin/adr-check` and `bin/pre-push-adr-hook` are unchanged; the hook decides whether the draft is enough.

## Alternatives Considered

- **Keep the human wall.** Rejected because: it was the only remaining deterministic stop on an unattended run, and the ADR the human writes comes from the same diff the harness already holds.
- **Write a `no-adr:` bypass marker automatically.** Rejected because: the marker exists for diffs that carry no decision; writing it from a script certifies a decision nobody recorded.
- **Draft inside `_push_with_lease_retry`.** Rejected because: the retry loop would let a draft and a stale-base refetch interleave; a one-shot wrapper outside the loop cannot re-enter it.
- **Let the drafter run Bash and commit itself.** Rejected because: the harness owns the commit, the gate order and the revert; a model with Bash could satisfy the hook by editing the hook.

## Consequences

- Positive: a run that adds a `bin/` script or a rule ships without a human writing the ADR first; the human reviews the draft on the PR under the `human-signoff` check instead.
- Positive: the drafted file carries the attribution blockquote as its first body line, so a reader can tell a harness draft from a human record.
- Negative: one more Opus call on the push path when the hook denies, bounded to one attempt and 20 turns.
- Negative: a draft can be plausible and wrong. The PR reviewer owns that judgment. Merge arming is unaffected: the draft changes nothing about conditions (7), (8) or the signoff check.
- `bin/post-plan-now` pins the main-checkout harness copy (ADR-0092), so this change takes effect only after merge.

## References

- `tools/postplan-harness/harness/adr_draft.py`
- `tools/postplan-harness/runner.py` (`_push_with_adr_draft`, `_GATE_REMEDY`)
- `tools/postplan-harness/tests/test_adr_draft_on_denial.py`
- `bin/pre-push-adr-hook`, `bin/adr-check`, `bin/next-adr`, `bin/check-numbering`
- ADR-0092 (harness pinned to the main checkout), ADR-0132 (pre-push local meta-checks)
