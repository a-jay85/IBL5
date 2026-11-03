---
description: A plan whose only Opus reason is Truly-manual rows runs its implementation on Sonnet when it declares manual_judge opus; the automouse runner then spawns a read-only Opus judge that rules on opus-judge rows, reviews the diff, and holds the plan to skipped on any non-pass outcome.
last_verified: 2026-10-28
owner: ajaynicolas
---

# ADR-0221: A runner-side Opus judge for Truly-manual rows

**Status:** Accepted
**Date:** 2026-10-28
**Deciders:** a-jay85

## Context

Gate `[13]` in `bin/check-plan` tied any Truly-manual Verification Matrix row to an Opus implementation run. The impl agent never reads a manual row: `bin/automouse/prompt-impl` Steps 6.6 to 7 skip them, and the only code that ticks a manual box is post-plan's allowlisted ticker. So the Opus impl run bought judgment that was never used on those rows. Baseline for 2026-10-13 to 2026-10-27: 71 `impl_model: opus` plans against 218 `sonnet` plans, out of 289, with about 31 of the 71 citing a manual row. The 14-day impl cost was 51 Opus runs at about $228 and 224 Sonnet runs at about $450.

## Decision

A plan may declare the line-1 key `manual_judge: opus` (closed value set). Gate `[13]` then lets `impl_model: sonnet` sit beside a Truly-manual row. The key with zero manual rows is a violation, and `haiku` beside a manual row stays one. A manual row is judgeable only when its location cell opens with `opus-judge: <criterion>`, or the token follows a `;` in that cell, and the criterion runs at least 15 characters. Every other manual row is human-only. The token beside a `vr:` or `no-vr:` cell, on a non-manual row, or with a short criterion is a gate `[13]` violation. `bin/lib/plan-model-consistency` owns these checks, and its `--judge-rows` mode lists the judgeable rows.

After the impl run writes its handoff, `bin/automouse/run` spawns `claude -p --model claude-opus-5-5` in the worktree with `bin/automouse/prompt-judge`. The model is hardcoded and never derived from the plan. The spawn runs with `Edit`, `Write`, `NotebookEdit` and `Agent` disallowed. It runs for a plan declaring `manual_judge: opus`, and for a non-Opus plan carrying a Truly-manual row. The runner reads the verdict only from its own capture of that spawn's stream-json result, and the last line must be exactly `JUDGE_VERDICT: pass`. A pass lets post-plan fire. A `fail`, a missing or malformed marker, a crash, or a script error in the consistency helper moves the plan to `skipped/` with a `skipped-<slug>` report. The judge never ticks a row. Gate 14(a) keeps `auto_merge: false` for every manual-row plan, and a judge pass never releases that hold.

An explicit `impl_model: opus` now needs one reason from a closed list: a phase's design is unresolved; the diff edits executable ship-pipeline gate code of the gate-14(e) class; or the plan carries Truly-manual rows without `manual_judge: opus`. This list is prose in `.claude/skills/plan/_validate-and-write.md`. No `bin/check-plan` code reads it.

## Alternatives Considered

- **In-impl judge.** The impl run spawns the judge and writes a verdict file. Rejected because the Sonnet impl agent runs with no tool limits and could skip the judge or forge the verdict, and the resume path skips the impl run when a handoff already exists.
- **Post-plan fidelity review.** Leave the rows to post-plan's Opus reviewer. Rejected because that reviewer is out of scope here, and nothing there holds a PR on an unjudged row.
- **Persisted verdict.** Write a verdict file and reuse it across runs. Rejected because a file on disk is forgeable and goes stale when the branch moves.

## Consequences

- Positive: an Opus judge run costs less than an Opus impl run, so a manual-row plan moved to Sonnet saves money while the judge's median cost stays under about $2.46.
- Positive: every arming condition can only hold more often, because the judge decides only whether post-plan fires.
- Negative: a judge `fail` costs a human requeue, with no automatic retry.
- Negative: a row tagged `opus-judge:` that Opus cannot rule on from the diff produces a hold. More than 30% of judge runs ending in a hold means the token rules need a second look.

Run both commands on or after 2026-11-24, four weeks after merge. `MERGE_DATE` is this PR's merge date (`gh pr view <n> --json mergedAt -q .mergedAt | cut -c1-10`).

Cost by phase and model, from the sidecar ledger:

```bash
cat ~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse/logs/*.costs.jsonl |
  jq -s --arg since MERGE_DATE '
    map(select(.date >= $since))
    | group_by([.phase, .model])
    | map({phase: .[0].phase, model: .[0].model, runs: length,
           total: (map(.cost) | add), median: (map(.cost) | sort | .[length/2|floor])})'
```

Declared impl tier share, from the plans themselves:

```bash
find ~/claude-plans -maxdepth 1 -name '*.md' -newermt MERGE_DATE -print0 |
  xargs -0 head -n1 | grep -oE 'impl_model: *[a-z0-9-]+' | sort | uniq -c
```

Keep the design when the Opus impl share is at or below 15% and the judge's median cost is under $2.46. Revert to Opus impl for manual-row plans when the judge median exceeds $2.46 for two straight weeks.

## References

- `bin/lib/plan-model-consistency`: gate `[13]` checks and the `--judge-rows` mode.
- `bin/automouse/run`: the judge gate, `plan_requires_judge`, `parse_judge_verdict` and `hold_plan_judge`.
- `bin/automouse/prompt-judge`: the judge prompt.
- `bin/test-check-plan`, `bin/test-automouse-postplan-disposition`, `bin/test-automouse-env-breaker`: the cases that lock the behavior.
- `.claude/rules/automouse-workflow.md`: the runner loop description.
