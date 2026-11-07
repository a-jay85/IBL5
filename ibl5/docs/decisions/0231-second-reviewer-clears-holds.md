---
description: ADR-0231. A blind Opus seat may clear arming conditions 7, 10, 12 and 17 when every blocked condition is one of them. The verdict binds to the tree it reviewed, and the owner keeps one-way and merge-rule changes. UI paths are guarded only on condition 10 and by the `ui` plan token.
last_verified: 2026-11-06
---

# ADR-0231: A second reviewer clears the non-UI owner holds

**Status:** Accepted
**Date:** 2026-11-06

## Context

The owner decided on 2026-11-06 to review UI/UX and risky PRs only. Several arming holds still stopped PRs that needed a second opinion and no owner taste. Three recent shapes show it: [#3733](https://github.com/a-jay85/IBL5/pull/3733) held on a plan's `auto_merge: false`, [#3673](https://github.com/a-jay85/IBL5/pull/3673) held on a gate backtest, and [#3730](https://github.com/a-jay85/IBL5/pull/3730) held on a fidelity finding about a gate edit.

A plan's `auto_merge: false` also recorded no reason, so arming could not tell a UI hold from a gate-weakening hold.

## Decision

Add a blind second reviewer to Phase 6.5. It lives in `tools/postplan-harness/harness/second_review.py` and `.claude/agents/pr-second-reviewer.md`.

**Clearable set.** The seat may clear conditions 7 (plan hold), 10 (pipeline-authored), 12 (fidelity) and 17 (gate backtest). It runs only when every blocked condition is in that set. Any other blocked condition means no spawn and no clear.

**Never-clear floor.**

- Condition 7 clears only when the plan's `hold_trigger` tokens are all reviewer-clearable. A missing key holds, and so does an unknown token.
- Condition 10 holds when a changed file matches the UI path floor, the `UI_PATH_PREFIXES` and `UI_PATH_SUFFIXES` constants in `armable.py`. They cover the design, theme, template, JS library, module and image folders, plus any `.css`, `.js`, `.html`, `.twig` or `View.php` file. The floor is broad on purpose.
- Condition 12 clears only on a `NOT READY` verdict whose blocking findings all carry `[GATE-EDIT]`. A `[MERGE-RULE]`, `[ONE-WAY]` or untagged finding holds.
- Condition 17 clears only on status `HELD`. `UNKNOWN` means the replay failed, and it holds.
- Conditions 15 (red CI), 9 (safety), 18 (merge rule) and 19 (planted bug) are outside the set, so their presence stops the seat outright.

**The `hold_trigger` vocabulary.** A plan with `auto_merge: false` records one or more tokens in frontmatter. The owner-held tokens are `ui`, `truly-manual`, `schema`, `merge-rule`, `gate-removal`, `one-way`, `unresolved-fork` and `other`. The reviewer-clearable tokens are `security-surface`, `gate-weakening` and `golden-snapshot`. An older plan with no key stays with the owner.

**Seat isolation.** Each seat is a fresh Opus session with no resume, continue or session argument. Its prompt holds the stated reason, the hold list and the diff. Lines that frame earlier review rounds are dropped from the reason and from hold text. The seat has Read, Grep and Glob only. Its own two files sit in `_ARMING_PATHS`, so a change to the seat is a one-way merge-rule change and can never pass through the seat.

**Run order and tree binding.** The seat runs inside the re-callable Phase 6.5 unit, before the preview evaluation, and again after the Phase 7 refresh. The verdict binds to the `HEAD^{tree}` captured before the spawn. Arming accepts it only when that tree equals `current_tree`, so a later push makes the verdict stale. A second pass on the same tree reuses the file and spends no tokens.

The seat runs before the preview so that a cleared PR still reaches the add-only safety verdict. That verdict runs only when the preview has no holds.

**Shell twin.** `pr_pipeline_authored_hold` in `bin/lib/pr-armable.sh` takes three optional arguments and applies the same clear to condition 10. Its two-argument form behaves as before.

**Outcome text.** Each clear prints in the merge digest with the condition and the reviewer's reason. A hold's reason gains `second reviewer: REJECT <reason>`, `absent`, `stale tree` or `no CLEARS line`.

**Out of reach.** Hand-opened PRs such as [#3585](https://github.com/a-jay85/IBL5/pull/3585) and [#3565](https://github.com/a-jay85/IBL5/pull/3565) never meet this seat, since they skip the post-plan run. That route is filed as [IBL5-backlog#1792](https://github.com/a-jay85/IBL5-backlog/issues/1792).

## Alternatives Considered

- **Let the seat clear any hold.** Rejected because: red CI, a planted-bug failure and a merge-rule edit are facts or one-way doors. A reviewer's opinion cannot settle them.
- **Reuse the fidelity reviewer's session.** Rejected because: a reviewer that read the earlier rounds would judge them as well as the diff, and its answer would no longer be independent.
- **Run the seat after the preview.** Rejected because: any clearable hold would skip the safety verdict, and the seat would then arm a PR whose safety check never ran.
- **Infer the plan's hold reason from its prose.** Rejected because: a parser that guesses can guess wrong in the unsafe direction. An explicit token with a fail-closed default cannot.

## Consequences

- Positive: tooling PRs held only on a second-opinion gate can merge without the owner, with the reason on record in the digest.
- Positive: a change to the seat itself always waits for the owner.
- Negative: one Opus spawn per eligible tree. It runs only when every hold is clearable, and only for diffs inside the 40 KB inline budget. A larger diff holds.
- Negative: plans written before this change carry no `hold_trigger`, so they keep waiting on the owner.
- Negative: the UI path floor guards condition 10 only. A UI file on a PR blocked only on condition 7, 12 or 17 can still clear. Extending `touches_ui` to those conditions is an owner decision.
- Negative: the UI path floor also holds some non-visual PHP under `ibl5/modules/` that a reviewer could have cleared.

## References

- `tools/postplan-harness/harness/armable.py`
- `tools/postplan-harness/harness/second_review.py`
- `tools/postplan-harness/harness/gate_backtest.py`
- `tools/postplan-harness/runner.py`
- `tools/postplan-harness/tests/test_second_review.py`
- `tools/postplan-harness/tests/test_armable_second_review.py`
- `tools/postplan-harness/tests/test_second_review_shell_parity.py`
- `tools/postplan-harness/tests/test_second_review_backtest.py`
- `.claude/agents/pr-second-reviewer.md`
- `.claude/review-shared/_plan-fidelity-review.md`
- `.claude/skills/plan/_validate-and-write.md`
- `bin/lib/pr-armable.sh`
