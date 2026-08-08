---
description: Read-on-demand only (no auto-attach trigger) — Fable approval-gate procedure: surface a suggestion, AskUserQuestion gate before any Fable spawn, and the asm-level static-RE exception where Fable is the recommended tier. Parent agent-tiering.md:18 carries the resident stop-text; read this for the full procedure.
last_verified: 2026-08-08
paths: ".claude/rules/agent-tiering-fable-gate.md"
---

Read-on-demand companion to `agent-tiering.md` § Tiers (Fable row). Nothing here auto-attaches — `agent-tiering.md` line 18 carries the resident stop-text (**never without prompting the user first** / Default to Opus) that halts an unprompted Fable spawn on its own; this file carries the full procedure.

## Fable Approval Gate

> **Status: Fable is available again (2026-07-01) but tightly gated.** Never select it on
> your own; use it *only* when a task is absolutely critical **and** Fable is 100% necessary
> to solve it, and *only* after an explicit user yes. Default to Opus — treat Fable as a last
> resort, not a routine capability upgrade.

**Claude must never select Fable on its own** — neither the session model nor a `model: "fable"` sub-agent. When a task matches the Fable row, do not silently run on Opus *and* do not switch; **surface a suggestion** and wait for an explicit yes. The suggestion states:

- **What** the task is and which Opus-row trait it exceeds (novel reasoning / exhaustive negative proof / high-blast-radius triage).
- **Pros**: the specific failure mode Opus risks (missed aliased ref, wrong FK order, an edge case reaching prod) and what one-shot correctness is worth.
- **Cons**: ~2× cost ($10/$50 vs $5/$25 per MTok); Opus is *likely sufficient* (most tasks are); the gain is a ceiling-raise, not a guarantee.
- **Recommendation**: a clear "I'd use Fable here" / "Opus is probably fine, flagging it" — not a neutral survey.

Absent approval, proceed on Opus (or the correct lower tier) — flag and continue, don't block. Approval covers that one task; a new task re-triggers the gate. Because Fable is a last resort, any actual intent to run on Fable is itself a genuine fork — **always** use `AskUserQuestion` to get the explicit yes *before* selecting it; never proceed on Fable from an inline suggestion alone.

### Exception — asm-level static RE (JSB engine): Fable is the *recommended* tier, not merely last-resort

For **asm-level static reverse-engineering** — the class the Fable row names (argument-binding derivations, NaN/FPU-flag paths, encoded operands; e.g. pinning a `FUN_*`/`+0xNNN` operand or a faithful-vs-divergent port verdict from `objdump`/decompile) — Fable is not a ceiling-raise, it is the **empirically-warranted** tier, because Opus has a **track record of provably-false conclusions here**. Precedents: the 2026-07-23 J24 putback-3pt misread (Ghidra mis-numbered params because `param_6` was a `double` consuming two stack slots → an Opus session recorded, shipped, and then had to *reverse* a remove-the-gate change across an ADR, a golden regen, and test rebaselines) and the 2026-07-07 foul-divisor pin (a Fable session overturned an Opus-era "requires live debugging" premise). One wrong asm verdict that ships costs far more than Fable's ~2× — the redo loop dwarfs the per-call delta.

**How to apply** (the gate still holds — surface the suggestion, get the explicit `AskUserQuestion` yes; never self-select):

- **Prefer Fable from the start** when the RE's load-bearing step *is* the asm derivation (the token-efficient path — it avoids the redo loop rather than paying to unwind it).
- **Otherwise, Fable-verify before recording** — if Opus drafts the RE, a `model: "fable"` sub-agent should check the faithful-vs-divergent / NOT-A-LEVER verdict *before* it lands in the backlog, an ADR, or a port. The `advisor()` tool cannot be repointed to Fable, so this verification is a spawned Fable sub-agent, not an advisor call.
- **Not for the empirical layer** — measured A/B sweeps, corpus statistics, and CI-floor construction are **not** this class (Opus/measurement is correct there; Fable's edge is asm reads, not arithmetic). Scope this exception to conclusions whose proof is a disassembly, not a measurement.

