---
description: A ship-pipeline gate name alone no longer routes /plan to plan-architect-xhigh; it needs a weakening signal. Adding a hold is not a trigger, and bin/plan-now warns instead of exiting 4 for a non-security xhigh hint.
last_verified: 2026-10-28
owner: ajaynicolas
---

# ADR-0220: Fable Trigger Needs a Weakening Signal

**Status:** Accepted
**Date:** 2026-10-28
**Deciders:** ajaynicolas
**Related:** ADR-0122 (`ibl5/docs/decisions/0122-plan-tier-hint-advisory-tool.md`), [backlog#1667](https://github.com/a-jay85/IBL5-backlog/issues/1667)

## Context

18 of the last 60 plan prompts declared `plan-architect-xhigh` (Fable). About 8 of them were real weakenings of an executable gate. The rest were routed by two leaks. Leak (a): `bin/plan-tier-hint` check 1b matched the bare words `auto-merge`, `human signoff`, `Phase 6.5`, and `arming condition`, so a task that only named a ship-pipeline gate went to Fable. Leak (b): the check-1 prose carried a rationale about changes to "what can clear condition N" that no one had defined, so adding a hold read as a trigger. A Fable planning run costs about $5.70 against about $2 for Opus.

## Decision

Check 1b keeps only the own-merge tokens (`bootstrap hazard`, `governs its own`, `its own merge`). The four ship-pipeline words move to a new `PIPELINE_RE` that feeds check 1c, so they hint xhigh only next to a weakening signal and only when the task is not additive. The weakening signal is `WEAKEN_RE` plus a new `PIPELINE_WEAKEN_RE` (`stops holding`, `no longer holds`, `lets X pass`, `narrow` directly before an arming-condition noun, `so ... auto-merge`). Bare `narrow` is never a token. Adding a hold, narrowing what passes, and a clearing-path change that lets no held input pass are not triggers. `ADDITIVE_RE` accepts `adds a hold` and `tighten`, still vetoed by `HARD_WEAKEN_RE` and `PIPELINE_WEAKEN_RE`.

Four security and destructive tokens are qualified. `escape hatch` and `pre-authoriz*` no longer match `SECURITY_RE`, and an escape hatch on a gate reaches check 1c through `WEAKEN_RE`. `delete from` and `truncate` in `DESTRUCTIVE_RE` now need a table identifier.

`bin/plan-now` warns and proceeds when a `plan-architect` declaration meets an xhigh hint whose reason starts `check 1b` or `check 1c`. Every other xhigh mismatch still exits 4, including any check-1a hint. The cross-check is the headless overrule path, so no separate overrule marker exists. This resolves [backlog#1667](https://github.com/a-jay85/IBL5-backlog/issues/1667).

Enforcement: `bin/test-plan-tier-hint` (cases 30a to 38d plus drift assertions across the trigger copies) and `bin/test-plan-now` (T-10 to T-14).

## Alternatives Considered

- **Gate words stay in check 1b.** Add `additive_only` to that check. Rejected because a task such as "make every Phase 6.5 arming condition branch-name-agnostic" has no weakening verb and no `additive` marker, so it would still reach Fable. A word that only names a gate is the leak itself.
- **Denylist in the warn branch.** Block only `check 1a` and warn on the rest. Rejected because an unrecognized reason shape would then pass silently, and the existing T-9 stub reason would flip from exit 4 to a warning. The allowlist fails closed.
- **A separate overrule marker.** Put a marker in the prompt. Rejected because the `--tier-ok` flag and the new warn branch already cover the headless path.

## Backtest

The corpus is the plan-now prompts under `/tmp`, frozen once at implementation. At plan time that was the newest 60 by name. A reboot had cleared `/tmp` by implementation, so the frozen corpus holds 43 files. The prompts for ids 115439, 134111, and 144516 were gone, so those three first lines are pinned as cases 30a, 30b, and 30d in `bin/test-plan-tier-hint` instead. The "before" ran on master at commit `0b2dbad419`.

Before:

```
total=43 declared: xhigh=10 default=18 sonnet=0 none=15
hinted: xhigh=8 default=35 sonnet=0
```

After:

```
total=43 declared: xhigh=10 default=18 sonnet=0 none=15
hinted: xhigh=4 default=39 sonnet=0
XHIGH	plan-now-20261024-202536-15844	check 1c — ship-pipeline gate 'auto_merge' plus weakening signal ' so more maintenance prs auto-me
XHIGH	plan-now-20261025-160530-32598	check 1c — ship-pipeline gate 'phase 6.5' plus weakening signal 'weaken'
XHIGH	plan-now-20261026-191125-62239	check 1c — ship-pipeline gate 'arming condition' plus weakening signal 'relax'
XHIGH	plan-now-20261026-191442-99190	check 1c — ship-pipeline gate 'arming condition' plus weakening signal 'relax'
```

Declared counts are identical in both runs, because only the hint changed. The two real weakenings present in the corpus (191125, 191442) stay xhigh. Prompts 113618, 113903, 153924, and 185917 dropped to default. Prompts 202536 and 160530 stay xhigh under check 1c, with the reasons printed above.

## Consequences

- Positive: a task that only names a ship-pipeline gate, or only adds a hold, goes to Opus instead of Fable.
- Positive: `bin/plan-now` no longer declines a run on a check-1b or check-1c hint, so unattended runs stop stalling on a keyword.
- Negative: a real weakening phrased without a listed verb or phrase now reaches Opus. Backstops: the pinned cases 30a to 30d and 35b to 35c in `bin/test-plan-tier-hint`, and check 1 itself, which the orchestrator still applies by judgment.

## References

- `bin/plan-tier-hint`: the advisory tool, with `PIPELINE_RE`, `PIPELINE_WEAKEN_RE`, and `SEC_NOISE_RE`
- `bin/test-plan-tier-hint`: fixture cases and drift assertions
- `bin/plan-now`: the tier warn branch
- `bin/test-plan-now`: T-10 to T-14
- `.claude/skills/plan/SKILL.md`: canonical Step 3 check 1
- `.claude/skills/plan-prompt/SKILL.md`: Step 4 copy
- `.claude/rules/agent-tiering-fable.md`: the overrule paragraph
