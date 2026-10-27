---
description: A 10-case corpus of past /plan prompts, replayed in history-truncated clones with today's plan surface overlaid, scores /plan changes on mechanical pass, file recall and architect tier via bin/plan-eval.
last_verified: 2026-10-26
---

# ADR-0217: Plan eval corpus and replay harness

**Status:** Accepted
**Date:** 2026-10-26
**Deciders:** a-jay85

## Context

Changes to the `/plan` skill, its architect contract and `bin/check-plan` had no before/after signal. A skill edit shipped on review alone, and nobody could say whether plans got better or worse. ADR-0201 set the pattern for the post-plan reviewer: a maintainer-owned golden-case corpus, a surface hash, a corpus digest and one comparable score line. `/plan` needs the same thing, with two extra problems. A plan run takes about 20 minutes, longer than a 10-minute headless Bash call. A run at today's HEAD sees the merged work and halts at Step 2.1.

## Decision

`bin/plan-eval` (module `tools/postplan-harness/harness/plan_eval.py`) scores `/plan` on a committed 10-case corpus in `tools/postplan-harness/fixtures/plan-eval/`. `mine` selects the cases from the frozen prompt set by a fixed rule: 5 default, 2 Sonnet and 3 xhigh architect cases, each with 2 to 12 expected files and no follow-up fix within the window. Four cases are `core`, the default run set. Each run replays the case prompt in a `git clone --local` at the PR's base SHA with refs, remote and reflog removed, and with today's plan surface (`PLAN_SURFACE_ENTRIES`) copied over it. A prompt coda sends the plan to a scratch plans dir, and a sweep deletes any `eval-<token>-<caseid>-r<k>` file that leaks into `~/claude-plans`. The stream-json transcript gives the architect tier, token counts and cost. Runs execute as detached workers, so `run` exits 4 while any are pending and the caller repeats it. The score line reports mechanical pass, file recall against the realized PR and tier match.

Two selection rules differ from the plan as first written, because the strict rule left too few eligible cases:

- A follow-up only disqualifies a case when its removed lines in an expected file blame to the case's merge commit. Unrelated `fix:` commits on busy shared files no longer count. This copies `blamed_by_fix_within` from the reviewer eval.
- Files the prompt names are dropped from `expected_files` before the 2-to-12 bound applies. Nearly every xhigh prompt names a file it touches, so without this step no xhigh case qualified. Recall then measures the files the planner had to find for itself.

## Alternatives Considered

- **A scratch `HOME` for each run.** It isolates `~/claude-plans` for free. Rejected because a probe on 2026-10-24 showed `claude` reporting "Not logged in" and "OAuth token revoked" under a fresh `HOME`.
- **`PLAN_NOW_PLANS_DIR`.** Rejected because it moves only `bin/plan-now`'s own bookkeeping. The skill still writes to `~/claude-plans`.
- **Driving runs through `bin/plan-now`.** Rejected because it queues the plan to automouse, which would implement eval plans for real.
- **Running at HEAD without history truncation.** Rejected because `/plan` Step 2.1 finds the merged work and halts with no plan.

## Consequences

- Positive: a `/plan` change can show a before/after score line, using `--base origin/master` for the before side.
- Positive: the cache is keyed by surface hash and corpus digest, so a repeat `run` costs nothing.
- Negative: one run costs about 20 minutes and 74K output tokens. The default 4-case set costs about 294K output tokens.
- Negative: it is a manual tool with no CI gate until a baseline exists.
- Negative: the plans-dir override relies on the model following the coda. The leak sweep catches a miss and marks the run `leaked`.

## References

- `bin/plan-eval`
- `tools/postplan-harness/harness/plan_eval.py`
- `tools/postplan-harness/fixtures/plan-eval/corpus.json`
- `ibl5/docs/plan-eval.md`
- `ibl5/docs/decisions/0201-reviewer-eval-corpus.md`
