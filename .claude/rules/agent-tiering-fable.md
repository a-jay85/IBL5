---
description: Read-on-demand only (no auto-attach trigger) — when Fable is justified over Opus, and how to use it. No user approval needed; state the justification in one line and spawn. Parent agent-tiering.md carries the resident one-line summary.
last_verified: 2026-10-08
paths: ".claude/rules/agent-tiering-fable.md"
---

Read-on-demand companion to `agent-tiering.md` § Tiers (Fable row).

## When Fable is justified

Default to Opus. Fable costs 2.5× Opus 5.5 per token ($10/$50 vs $4/$20 per MTok), so it must buy something Opus likely can't. Use Fable when **both** hold:

- **Opus is likely to get it wrong.** The task needs novel reasoning, an exhaustive negative proof ("X never happens anywhere"), or a verdict where Opus has a track record of confident wrong answers.
- **A wrong answer is expensive.** It would ship, land in an ADR or backlog, or need a redo loop (revert, regen goldens, rebaseline tests) that costs more than the 2.5×.

If Opus stalled at medium effort, rerun it at high first. High adds about 20K thinking tokens (~$0.40 a task), far less than a Fable rerun.

When the test passes, use Fable. Do not ask the user. Say one line before the spawn: what the task is, and which trait makes Opus risky. Spawn with `model: "fable"`. Claude cannot switch its own session model, so Fable work is always a sub-agent.

When the test fails, stay on Opus (or a lower tier). Most tasks fail it.

## Pinned agent defs

A def whose frontmatter pins `model: fable` always runs on Fable. Today that is `plan-architect-xhigh` at high effort, selected by `/plan` Step 3 check 1.

## Asm-level static RE (JSB engine): Fable is the default

Asm-level static reverse-engineering always passes the test. This covers argument-binding derivations, NaN/FPU-flag paths, and encoded operands (pinning a `FUN_*`/`+0xNNN` operand, or a faithful-vs-divergent port verdict from `objdump` or decompile). Opus has shipped provably false conclusions here. On 2026-07-23 it misread J24 putback-3pt because Ghidra mis-numbered params (`param_6` was a `double` using two stack slots). Undoing that took an ADR, a golden regen, and test rebaselines. On 2026-07-07 a Fable session overturned an Opus-era "requires live debugging" premise on the foul divisor.

- **Use Fable from the start** when the load-bearing step is the asm derivation.
- **If Opus drafted the RE, have a Fable sub-agent verify** the faithful-vs-divergent or NOT-A-LEVER verdict before it lands in the backlog, an ADR, or a port. `advisor()` cannot be pointed at Fable, so this must be a spawned sub-agent.
- **Measured work stays on Opus.** A/B sweeps, corpus statistics, and CI-floor construction are arithmetic. Fable's edge is reading asm. The exception covers only conclusions whose proof is a disassembly.
