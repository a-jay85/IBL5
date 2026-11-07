---
description: ADR-0234. The Merge danger Door means one-way only for effects a revert cannot undo; arming-path and gate-removed changes carry a separate Self-governing segment, and condition (18) holds both
last_verified: 2026-11-07
---

# ADR-0234: Narrow the Merge danger one-way Door and add a Self-governing segment

**Status:** Accepted
**Date:** 2026-11-07

## Context

The `**Merge danger:**` line (backlog#1438) used one Door label for two meanings. A destructive migration, a prod sync, or a sent message is one-way because `git revert` cannot undo it. An edit to the arming machinery or a removed gate was also one-way, because the PR would judge its own merge. A revert undoes the second kind cleanly. The shared label told readers and the merge digest DM that such PRs were irreversible, and the revert guidance followed the label.

## Decision

`Door: one-way` now fires only on the destructive-migration, external-path, and notify-workflow triggers. The arming-path and gate-removed triggers instead add an optional ` · Self-governing: yes` segment between Blast radius and Why, and leave the Door two-way. `SELF_GOVERNING_KINDS` in `tools/postplan-harness/harness/merge_danger.py` names the two kinds. Phase 6.5 condition (18) holds on any state other than `TWO-WAY`, and the new `SELF-GOVERNING` state joins `ONE-WAY`. One-way wins when both signals are present. The arm step computes the state with `classify_with_floor`, which re-checks a two-way body against the diff floor. A stripped segment, a hand-lowered Door, or a derive error still holds. In Phase 2 the segment comes only from the mechanical floor, so an LLM cannot add it. A later phase keeps a human-added segment. Review depth treats any line carrying the `Self-governing:` substring as `deep`, so a misplaced or mangled segment over-reviews. The shell twins (`pr_merge_danger_hold` in `bin/lib/pr-armable.sh`, `bin/lib/review-depth.sh`, `bin/digest-dm-build`) parse the same segment.

## Alternatives Considered

- **Keep one Door label.** Rejected because the merge DM and revert guidance keep calling reversible PRs irreversible.
- **A third Door value.** Rejected because Door answers one question (can a revert undo it), and a third value would mix in the second question again.
- **Drop the hold for arming-path PRs.** Rejected because the bootstrap hazard is real: a PR that edits the arming gate must not arm itself.

## Consequences

- Positive: the held set is unchanged. `tools/postplan-harness/tests/test_merge_danger_parity.py` proves every legacy body held before is held after, in both twins.
- Positive: the merge digest DM opens a Self-governing merge with `!! SELF-GOVERNING: owner sign-off required` and keeps the full body.
- Positive: review depth for arming-path PRs stays `deep` (addendum to ADR-0202).
- Negative: revert guidance on these PRs now reads as two-way. That is accurate, but a reader used to the old label may expect the one-way banner.
- Negative: every parser of the line must accept the new segment. A copy that does not reads the line as malformed, which holds.

## References

- `tools/postplan-harness/harness/merge_danger.py`: `derive_merge_danger`, `merge_verdicts`, `classify_with_floor`.
- `tools/postplan-harness/runner.py`: `_arm_merge_danger_state`.
- `bin/lib/pr-armable.sh`: `pr_merge_danger_hold`.
- `.claude/skills/post-plan/_pr-body-claims.md`: Merge danger rule.
- `ibl5/docs/decisions/0202-review-depth-by-merge-danger.md`: review depth by Merge danger.
