---
description: A project-level PostToolUse hook appends an elapsed-clock line after every tool call in automouse impl runs, driven by two budget env vars that bin/automouse/run sets, and bin/test-headless-elapsed-hook guards the hook and its wiring.
last_verified: 2026-09-28
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0141: Automouse Impl Runs See an Elapsed Clock After Every Tool Call

**Status:** Accepted
**Date:** 2026-09-28
**Deciders:** post-plan harness (auto-draft)

## Context

`bin/adr-check` flagged one decision-trigger surface on this branch: the new developer tool `bin/test-headless-elapsed-hook`, a regression script of more than 50 lines. It exists to guard the mechanism this record describes. `bin/automouse/run` launches the impl phase as `claude -p` under `timeout "$REMAINING_SECS"`. The prompt in `bin/automouse/prompt-impl` tells the agent to treat 90% of that budget as its deadline and to write a handoff file before it runs out. The agent had no way to know the time. It could only guess from how much work it had done, and a run that hit the `timeout` wall was killed before it wrote the handoff. `bin/lib/automouse-stream-filter.sh` already tracks the run's progress, but it writes to the operator's log and never reaches the agent's context.

## Decision

A PostToolUse hook, `bin/lib/headless-elapsed-hook.sh`, is registered for every tool in the checked-in `.claude/settings.json`. It reads two env vars that `bin/automouse/run` sets on the impl invocation only: `IBL5_BUDGET_START_EPOCH` (the epoch when the impl started) and `IBL5_BUDGET_SECS` (the `REMAINING_SECS` value also passed to `timeout`). When both are present and numeric with a positive budget, the hook emits `additionalContext` of the form `elapsed <E>s / <B>s`. Once elapsed time reaches 90% of the budget, the line also tells the agent to stop implementing and write the handoff. The 90% test uses integer math. In every other session either var is missing, and the hook drains stdin and exits 0 with no output. It also exits 0 silently when `jq` is absent. `bin/automouse/prompt-impl` gains one paragraph telling the agent that this line is its clock. Enforcement is `bin/test-headless-elapsed-hook`, which runs as a step in `.github/workflows/tests.yml`. Its six cases cover the missing-var and non-numeric no-op paths, the JSON shape and elapsed value under budget, and the past-90% warning. The last two cases assert that `.claude/settings.json` wires the hook exactly once and that `bin/automouse/run` still sets `IBL5_BUDGET_SECS` from `REMAINING_SECS`.

## Alternatives Considered

- **Prompt the agent to run `date` itself.** Keep the clock out of the harness and tell the agent to check the time between phases. Rejected because: it costs a tool call each time and depends on the agent remembering to do it, which is the same discipline that was already failing.
- **Register the hook in `~/.claude/settings.json`.** Keep the wiring machine-local next to the other personal hooks. Rejected because: the automouse runner and its budget vars live in the repo, and a machine-local registration cannot be asserted by a CI test, so a missing hook would fail silently.
- **Gate the hook on `CLAUDE_HEADLESS`.** Fire in every headless run and read the budget from elsewhere. Rejected because: other headless runs such as `/post-plan` have no budget, and the hook needs the start time and budget numbers in any case. The two budget vars carry both the opt-in signal and the data.
- **Surface the clock from `bin/lib/automouse-stream-filter.sh`.** Reuse the filter that already sees every tool event. Rejected because: the filter consumes the agent's output stream and has no channel back into the agent's context.

## Consequences

- Positive: the impl agent sees how much budget it has used after every tool call, and gets an explicit instruction to write the handoff once it passes 90%.
- Positive: interactive sessions and other headless runs pay only a process spawn per tool call. The hook exits at once with no output when the budget vars are absent.
- Positive: the wiring is covered by CI. Removing the settings entry or the env var from `bin/automouse/run` turns `bin/test-headless-elapsed-hook` red.
- Negative: every session in this repo now spawns a shell after each tool call, and the entry carries a 5-second timeout.
- Negative: the clock appears only after a tool call. An agent blocked on one long call, such as a slow `Agent` delegate, sees nothing until that call returns.
- Negative: the start epoch is taken when `bin/automouse/run` builds the command, so the reported elapsed time includes Claude startup. The skew is a few seconds and errs toward an earlier handoff.
- Neutral: this branch also edits one line in `.claude/skills/plan/SKILL.md` naming the `plan-architect-xhigh` model and effort. That edit is unrelated to the clock.

## References

- `bin/lib/headless-elapsed-hook.sh`: the hook, its env-var validation, and the 90% threshold.
- `bin/test-headless-elapsed-hook`: the six regression cases flagged by `bin/adr-check`.
- `.claude/settings.json`: the project-level PostToolUse registration.
- `bin/automouse/run`: sets `IBL5_BUDGET_START_EPOCH` and `IBL5_BUDGET_SECS` on the impl invocation.
- `bin/automouse/prompt-impl`: the paragraph that tells the agent to read the clock line.
- `bin/lib/README.md`: the library index entry for the hook.
- `.github/workflows/tests.yml`: the CI step that runs the regression test.
- `ibl5/docs/decisions/README.md`: the decision-record policy `bin/adr-check` enforces.
