---
description: ADR-0229. The post-plan Opus closer A/B trial is over; the closer, the arm assignment and the readout are deleted and every run uses the narrow fixers.
last_verified: 2026-11-04
---

# ADR-0229: End the post-plan closer A/B trial

**Status:** Accepted
**Date:** 2026-11-04

## Context

The trial ran an Opus closer session on half the enrolled post-plan runs. On those runs the narrow fixers stayed off. Trial PRs: [#3669](https://github.com/a-jay85/IBL5/pull/3669), [#3692](https://github.com/a-jay85/IBL5/pull/3692), [#3717](https://github.com/a-jay85/IBL5/pull/3717). Only the harness README described it, so no ADR recorded the start.

The 2026-11-04 readout compared the two arms:

| Arm | n | Merged | Median $/PR | Total $ | Median hours to merge | Median human touches | Armed with no human |
|-----|---|--------|-------------|---------|-----------------------|----------------------|---------------------|
| Old fixers | 10 | 10 | 2.53 | 81.60 | 8.4 | 0.5 | 3/10 |
| Closer | 9 | 8 | 7.30 | 95.68 | 11.8 | 6 | 0/9 |

Reverts were 0 on both arms, and every merged PR was still inside the revert window. The full rows are in `~/claude-plans/_reports/2026-11-04-postplan-ab-readout-final.json` (example).

The closer never re-reviews in the same run. A body or tree edit logs a deferred arming re-check, nothing starts that run, and the closer stops after 3 sessions per PR.

## Decision

Delete the closer session, the arm assignment and enrollment, the outcome log, the readout, and `bin/postplan-ab-readout` (example). Every run uses the narrow fixers. The owner chose deletion over a disable switch on 2026-11-04, before the revert window closed.

`POSTPLAN_FIRED_BY` and the `fired-by=` log line stay, because automouse, the fleet and burndown attribution use them. `POSTPLAN_AB_ARM` is no longer read. Old files under `tools/postplan-harness/out/state/` stay on disk and are ignored. No revert check runs on the merged closer PRs. The saved report is the record.

The harness tests assert that `harness.closer`, `harness.ab_arm` and `harness.ab_readout` stay unimportable and that `runner._maybe_run_closer` stays absent.

## Alternatives Considered

- Keep the closer behind a disabled switch. It keeps the closer code and its eight branch sites alive for a trial with a settled result. Rejected because the result is in the saved report.
- Repair the closer so it re-reviews in the same run. Rejected because it cost about three times as much per PR and needed six times the human touches.
- Build a new PR-text fixing agent. Rejected because the fidelity fixer already edits PR bodies.

## Consequences

- The narrow fixers are the only path, so a reader no longer tracks the `_narrow_fixers_off` branches.
- `result.json` is unchanged for runs that never enrolled, because the removed fields were omitted when unset.
- Human-touch counts in the readout ran high because burndown jobs act under the owner's login. That measure goes away with the readout.

## References

- `tools/postplan-harness/harness/closer.py` (example)
- `tools/postplan-harness/harness/ab_arm.py` (example)
- `tools/postplan-harness/harness/ab_readout.py` (example)
- `ibl5/docs/decisions/0092-postplan-harness-in-repo-with-python-ci.md`
- `ibl5/docs/decisions/0192-postplan-fix-entry-point.md`
