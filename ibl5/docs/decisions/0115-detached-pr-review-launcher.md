---
description: Why /pr-ready fires bin/pr-review-now as a detached launcher with its own slot namespace instead of running the review inline.
last_verified: 2026-09-04
---

# ADR-0115: Detached `/pr-review` launcher with its own slot namespace

**Status:** Accepted
**Date:** 2026-09-04

## Context

`/pr-ready` Phase 6 can determine that a PR needs a structured code review — either none ever ran (`PHASE_4B_RAN=false`) or one ran against a head the branch has since moved past. Until now the run could only *recommend* `/pr-review <N>` in the verdict comment, which depends on the user reading the comment and typing the command later. Running the review inline is not available either: `/pr-ready` declares `disallowed-tools: [EnterPlanMode, ExitPlanMode, Skill]`, so it cannot call another skill, and its Phase 7 hard terminator ends the run at the posted comment. A review is also minutes of work that must not extend or block the `/pr-ready` run it was triggered from.

## Decision

Ship `bin/pr-review-now <PR>` as a standalone detached launcher mirroring `bin/pr-ready-now`'s launchd slot-token architecture, and have `/pr-ready` Phase 7 fire it through `scripts/review-owed.sh` as the single permitted post-verdict action. The launcher owns its own label namespace (`com.ibl5.pr-review-now-<PR>`) and its own test seams (`PR_REVIEW_NOW_*`); the plist file is the slot token, so an already-live slot makes `review-owed.sh` skip rather than double-fire. Enforced by `bin/test-pr-review-now` (9 cases) and by the terminator/auto-fire pins in `bin/test-pr-ready-now`, both wired into `.github/workflows/tests.yml`.

## Alternatives Considered

- **Run the review inline inside `/pr-ready`** — one skill does both. Rejected because: `/pr-ready` cannot call `Skill` at all, and an inline review would extend a run whose whole contract is to stop at the verdict comment.
- **Share `bin/pr-ready-now`'s slot pool** — reuse one accounting surface for both launchers. Rejected because: a `/pr-ready` run holding its own slot would be counted against the review it just triggered, and the two workloads have different lifetimes.
- **Keep recommending `/pr-review <N>` in the comment only** — no new tooling. Rejected because: the recommendation is silently dropped whenever the user merges without re-reading the comment, which is the exact failure this closes.

## Consequences

- Positive: a review that is owed actually runs, without the user having to notice a line in a comment.
- Positive: separate label namespace and seams mean `bin/pr-review-now` can be tested, stopped, and reasoned about independently of `bin/pr-ready-now`.
- Negative: a second launcher duplicates the slot/stop/teardown logic rather than sharing a library, so a fix to the fail-closed reap semantics must be applied in two files.

## References

- `bin/pr-review-now` — the launcher.
- `bin/test-pr-review-now` — its harness.
- `bin/pr-ready-now` — the mirrored architecture.
- `.claude/skills/pr-ready/scripts/review-owed.sh` — the fire path, including the live-slot skip.
- `.claude/skills/pr-ready/_phase7-verdict.md` — the terminator amendment naming this as the one permitted action.
