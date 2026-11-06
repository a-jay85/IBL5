---
description: ADR-0230. The post-plan sticky gains a display-only "Conflict resolved by Claude" section built from a record beside the condition-(14) marker, so a model-resolved conflict and its lost lines are visible without touching any arming condition
last_verified: 2026-11-05
---

# ADR-0230: Show model-resolved conflicts in the post-plan sticky

**Status:** Accepted
**Date:** 2026-11-05

## Context

When the harness lets a model resolve a rebase conflict, the owner saw only an arming hold. The sticky said `verdict=ABSENT` with no reason. The cause is that `purge_verdict_artifacts` deletes the review's sidecar and verdict file at every rebase, while the condition-(14) marker stays. A later clean re-run writes no new review, so `conflict_verdict_for` returns nothing. `tests/test_conflict_absent_trace.py` pins that behavior. Nothing in the sticky named the resolved files, the review's reason, or the code the resolution dropped. PR [#3705](https://github.com/a-jay85/IBL5/pull/3705) replaced nine master lines in `bin/lib/wt-guards.sh` that way.

## Decision

Add `tools/postplan-harness/harness/conflict_report.py`. `gitad.py` writes one record per resolution beside the marker (`<marker>.report.json`, atomic replace). `compose_sticky` takes an optional `conflict_section` and prints it above `### Merge digest`. The section is built from the record, git objects (a per-side multiset diff against the merge base, whitespace normalized, conflict-marker and trivial lines ignored, lines moved to another file reported as moved) and the live verdict state. It is carried forward from the prior sticky when the record is gone but the marker is still present. A full `/tmp` wipe removes both, and the section then reads as dropped ([backlog#1779](https://github.com/a-jay85/IBL5-backlog/issues/1779)). Lost lines are computed against HEAD, so a line a later commit restored leaves the list. The skill fallback runs the same code through `python3 -m harness.conflict_report`. The report is display-only: `armable.py` and `bin/lib/pr-armable.sh` are untouched, the marker and the purge are untouched, and a report failure prints one failure line and changes no verdict or exit code. Untrusted text is escaped so it cannot form the sticky marker, the merge-digest heading or a verdict line.

## Alternatives Considered

- **Stop purging the verdict on a clean re-run.** It would remove the ABSENT reading at the source. Rejected because: it changes condition (14), and the hold is fail-closed on purpose.
- **Have the model write the summary.** Rejected because: an LLM summary can invent a loss or hide one. The line check is mechanical.
- **Put the record in the marker file.** Rejected because: the marker's format is parsed by the arming code, and any new line there risks reading as malformed.

## Consequences

- Positive: an owner sees which files a model resolved, why the check reads the review as missing, and which unique lines were dropped.
- Positive: the arming hold itself is unchanged, so no safety property moves.
- Negative: the sticky grows by one section on branches with a model-resolved conflict. Long loss lists are truncated with a count.
- Negative: the `verdict=ABSENT` hold after a clean re-run still needs a human or a fresh review. That gap is filed as [backlog#1778](https://github.com/a-jay85/IBL5-backlog/issues/1778).

## References

- `tools/postplan-harness/harness/conflict_report.py`
- `tools/postplan-harness/tests/test_conflict_report.py`
- `tools/postplan-harness/tests/test_conflict_report_sticky.py`
- `tools/postplan-harness/tests/test_conflict_absent_trace.py`
- `tools/postplan-harness/README.md` § Safety model
- `ibl5/docs/decisions/0228-postplan-sync-rebases-onto-target.md`
