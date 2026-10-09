---
description: Drops the user-approval step before a Fable sub-agent spawn. Claude now uses Fable when Opus is likely wrong and a wrong answer is expensive, states the reason in one line, and asm-level static RE defaults to Fable. The rule moves to agent-tiering-fable.md.
last_verified: 2026-10-08
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0187: Fable tier needs a stated justification instead of user approval

**Status:** Accepted
**Date:** 2026-10-08
**Deciders:** post-plan harness (auto-draft)

## Context

`bin/adr-check` flagged one surface: the new `.claude/rules/agent-tiering-fable.md`, an operational convention for agents. It replaces the old agent-tiering-fable-gate.md, which this PR deletes.

The old file said Claude must never pick Fable on its own. Before any Fable spawn it had to surface a pros-and-cons suggestion and get an explicit yes through `AskUserQuestion`. A def pinned to `model: fable` counted as a standing yes. The same file named asm-level static reverse-engineering of the JSB engine as the class where Fable is the recommended tier, because Opus had shipped provably false verdicts there (the 2026-07-23 J24 putback-3pt misread and the 2026-07-07 foul-divisor premise). Even in that class, the approval prompt still applied.

The prompt stalled headless and long-running work on a question the rule itself already answered. It also pushed sessions to proceed on Opus in exactly the asm cases where Opus had a record of wrong answers.

## Decision

Claude uses Fable without asking when both conditions hold: Opus is likely to get the task wrong, and a wrong answer is expensive (it would ship, land in an ADR or backlog, or force a redo loop). Before the spawn, Claude states in one line what the task is and which trait makes Opus risky. Fable work always runs as a `model: "fable"` sub-agent, since a session cannot switch its own model. Opus stays the default, and a stalled Opus run is retried at high effort before Fable is considered. Asm-level static RE always passes the test, so Fable is its default tier. An Opus-drafted asm verdict gets a Fable sub-agent check before it lands in the backlog, an ADR, or a port. Measured work (A/B sweeps, corpus statistics, CI floors) stays on Opus.

The Fable row in `.claude/rules/agent-tiering.md` carries the one-line summary and points at `.claude/rules/agent-tiering-fable.md`. `bin/check-rules-byte-budget` maps the new companion to its parent so the companion is counted against the same budget the old file used. `.claude/rules/agent-tiering-detail.md` updates its two pointers to the new file name.

The same PR carries an unrelated factual fix to `.claude/rules/ci-node-toolchain-pin.md`. That rule now records that `.github/workflows/npm-audit-fix.yml` pins Node `'24'` on purpose ([#2870](https://github.com/a-jay85/IBL5/pull/2870)), and that the canonical `'22'` pin lives in `.github/workflows/tests.yml` and `.github/workflows/main.yml`.

## Alternatives Considered

- **Keep the approval prompt.** Leave agent-tiering-fable-gate.md as written. Rejected because the prompt blocks headless runs and repeats a judgment the written test already makes.
- **Drop the prompt only for asm-level RE.** Keep `AskUserQuestion` for every other Fable case. Rejected because it keeps two procedures for one tier, and the two-condition test already limits Fable to rare, high-cost cases.
- **Remove the Fable tier and stay on Opus.** Rejected because the asm precedents show Opus shipping false verdicts whose redo loop cost far more than the 2.5x token price.

## Consequences

- Positive: Fable work in headless and automouse runs proceeds without waiting on a human answer.
- Positive: asm-level RE verdicts get Fable by default, which targets the class with a documented Opus failure record.
- Positive: the one-line justification leaves a visible record of why each Fable spawn happened.
- Negative: Fable spend can rise without a per-task human check. The only brake is Claude applying the two-condition test honestly.
- Negative: a pinned `model: fable` def no longer needs a separate approval story, so adding a new pin is reviewed only through its PR.

## References

- `.claude/rules/agent-tiering-fable.md`
- `.claude/rules/agent-tiering.md`
- `.claude/rules/agent-tiering-detail.md`
- `.claude/agents/plan-architect-xhigh.md`
- `bin/check-rules-byte-budget`
- `.claude/rules/ci-node-toolchain-pin.md`
- `.github/workflows/npm-audit-fix.yml`
