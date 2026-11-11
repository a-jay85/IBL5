---
description: The Phase 5.5 reviewer's SHADOW-ARMING block may add an arming hold (condition 21) and never clears one; Python stays the only clearer of conditions 3 and 18.
last_verified: 2026-11-10
---

# ADR-0239: Reviewer shadow verdicts add holds and never clear

**Status:** Accepted
**Date:** 2026-11-10
**Deciders:** ajaynicolas

## Context

The shadow trial of PR #3559 ran the Phase 5.5 Opus reviewer beside the Python checks for condition 3 (planned files and tests landed) and condition 18 (merge-danger door). Condition 3 agreed on 319 of 351 runs, with 26 Python-only holds and 6 reviewer-only holds. Condition 18 agreed on 455 of 511 runs, with 54 Python-only holds and 2 reviewer-only holds. The Python-only holds include real catches, so letting the reviewer clear them would ship unreviewed code. The reviewer-only holds are runs where the reviewer saw a problem the Python checks missed, and arming ignored them.

## Decision

Arming condition 21, `reviewer-arming-hold`, holds when the reviewer's typed block says `hold: true` for `planned_landed` (on a run with a plan) or for `merge_danger`. A `hold: false` reaches no code that can release a hold, so `conformance.py` and `merge_danger.py` stay the only clearers of conditions 3 and 18. A missing, invalid or malformed block is neutral. An item the final diff realises is dropped from the planned-landed part. Condition 21 stays out of the in-run fix round, the hold-repeat key and the held-unfixable marker, and the ADR-0231 second reviewer cannot clear it. The check lives in `fidelity.reviewer_arming_holds`, and `tests/test_fidelity_shadow.py` pins the add-only property.

## Alternatives Considered

- Finish the swap and let the reviewer clear 3 and 18. Rejected because it would drop the 26 and 54 Python-only holds, which include real catches.
- Reuse conditions 3 and 18 for the reviewer text. Rejected because four consumers key on condition 3 or on the condition 18 `state=` reason, and free reviewer text would change each of them.
- Gate the hold on `reviewed_tree == current_tree`. Rejected because Phase 6 commits after the review on the ordinary path, so the hold would drop on almost every PR.

## Consequences

- Positive: reviewer-only holds now stop an arm, and every input that held before still holds.
- Positive: an unchanged diff and plan carry the same verdict and shadow forward, so a re-run returns the same hold at no model cost.
- Negative: a reviewer false positive holds a PR for a human, and nothing in the in-run fix round can clear it.
- Replay over the stored corpus: `bench/compare_fidelity_shadow.py --add-only` reports 3 of 1813 PRs newly held (PRs 3495, 3702 and 3861; planned_landed=2, merge_danger=1). Each PR counts by its latest run. PRs 3438 and 3712 had reviewer holds on earlier runs, and their latest runs scored clear, so the rule does not hold them. The count is an upper bound, because the replay has no diff to drop items that a later commit landed.

## References

- `tools/postplan-harness/harness/armable.py`
- `tools/postplan-harness/harness/fidelity.py`
- `tools/postplan-harness/bench/compare_fidelity_shadow.py`
- `tools/postplan-harness/tests/test_armable_reviewer_holds.py`
- `tools/postplan-harness/README.md`
