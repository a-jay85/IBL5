---
description: Until 2026-11-23, Opus edits a design-resolved, machine-verifiable chunk of under 5 files inline instead of delegating it to Sonnet, and `bin/lib/opus-trial-cost.jq` measures the per-task cost.
last_verified: 2026-10-26
---

# ADR-0218: Opus inline-edit trial for chunks under 5 files

**Status:** Accepted
**Date:** 2026-10-26

## Context

`work-triage.md` § Execution routing told Opus to hand every design-resolved, machine-verifiable chunk to one Sonnet sub-agent. A spawn costs 17-23K tokens of fixed context, and the handoff summary loses detail the recipe writer already holds. For small chunks that overhead can exceed the edits being moved. Nobody had measured whether delegation saves money on chunks of a few files.

## Decision

Until 2026-11-23, a chunk that meets the Sonnet-executable criteria and touches fewer than 5 distinct files is edited inline by Opus. A chunk of 5 or more files still hands off to one Sonnet sub-agent by default, and the ≥5-file hook in `~/.claude/hooks/plan-gate-edit.sh` is unchanged. The review compares per-task cost before and after the split with `bin/lib/opus-trial-cost.jq` against the baseline saved at `~/claude-plans/_reports/2026-10-26-opus-inline-trial-baseline.json`. Inline stays if the after median is at or below baseline with `n` ≥ 20 in both windows. Otherwise delegation returns. Either way the trial label is dropped.

## Alternatives Considered

- **Keep delegation for every chunk.** Rejected because the spawn cost on small chunks was never measured against inline editing.
- **Make inline the permanent default now.** Rejected because there is no cost data yet, and a dated trial forces a review.
- **Measure with `logged_cost` from the sidecar ledgers.** Rejected because those ledgers price headless sessions only, and the rule is a no-op under headless runs.

## Consequences

- Positive: small chunks skip the spawn and the lossy handoff, and the editor can revise mid-turn.
- Positive: the review uses a saved baseline, so the 30-day transcript expiry does not erase the before window.
- Negative: the editor and the recipe writer are the same model, so review independence drops. The Sonnet review agents partly restore it.
- Negative: headless sessions share the worktree dirs and dilute both windows. The result is observational evidence.

## References

- `.claude/rules/work-triage.md`
- `.claude/rules/work-triage-detail.md`
- `.claude/rules/work-triage-inline-trial.md`
- `bin/lib/opus-trial-cost.jq`
- `bin/lib/automouse-pricer`
- `bin/measure-delegate-cost`
