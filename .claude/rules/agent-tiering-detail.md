---
description: Read-on-demand detail for agent-tiering — full Fable approval-gate procedure, flat-fan-out (nested sub-agent) rationale, and per-tier prompt style. Loads only when editing workflow orchestration defs, where this rationale applies.
last_verified: 2026-07-01
paths:
  - ".claude/commands/**/*.md"
  - ".claude/skills/**/SKILL.md"
---

# Agent Tiering — Detail

Read-on-demand companion to `agent-tiering.md` (always-loaded). The parent holds the
operative rules — the Tier table, the Skip-the-Agent heuristic, and Explore tiering.
This file holds the longer rationale, pulled out of the always-loaded budget.

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

## Boundary keys on task type, not model capability

Re-validated 2026-06-30 against Sonnet 5 (now the `sonnet` alias, native 1M context). The
Opus-only column (final code review, diff-triage, rule/ADR authoring, novel reasoning,
ambiguous failures) stays Opus because **"never delegate understanding" is a *delegation*
rule, not a "wait for a smarter model" rule** — a more capable Sonnet does not make
delegating the judgment safe, because the cost was never Sonnet's raw ability, it was that
the Opus session loses the findings it would otherwise filter (see the flat-fan-out
rationale below, and `feedback_sonnet_proving_negatives` / `feedback_review_agent_full_diff`).
Sonnet 5's larger context window only **strengthens** the existing "spawn Sonnet to absorb
verbose output" rationale; it is not a reason to push understanding-class work down a tier.
**Tripwire to revisit:** a model generation where the *delegation* failure mode itself
changes (e.g. a coordinator that can surface its own filtered-out findings), not merely a
higher per-task capability score.

## Nested Sub-Agents — Available, Deliberately Unused

Sub-agents can spawn sub-agents (5 deep), but we keep **flat fan-out**: the Opus session owns every fan-out and absorbs every agent's output. Do not nest in the recurring workflows (`/plan`, `/pr-review`, `/security-audit`, `/post-plan`, automouse). Why: our fan-out is narrow (1–4 agents/phase, not the wide verbose fan-out where nesting pays); the pipelines keep review/triage in Opus by design (the review→score→filter step *is* triage — a coordinator would blind Opus to the findings it filtered, and delegated judgment degrades — see `feedback_sonnet_proving_negatives`, `feedback_review_agent_full_diff`); and `/post-plan` is a single-context state machine whose Phase 3/5/6.5 gates read from main-session context, where nesting could only hide the filtered-out findings, not the survivor list Opus still needs.

**Tripwire to revisit:** a *measured* post-plan context-window problem, or a new workflow with genuinely wide fan-out and verbose per-agent intermediates.

## Prompt Style by Tier

**Haiku** (compensate for its tendency to stop at "enough"): lead with a concrete grep/find command · say "list EVERY match" / "do NOT skip files" when exhaustiveness matters · pre-resolve absolute paths · request structured output (table/list) · for checklists, "check EACH pattern, cite file:line or state not found" · never ask it to judge relevance, trace multi-hop flows, or relate a past event to the current context.

**Sonnet**: open-ended exploration, multi-file synthesis, ambiguous queries where the first grep might miss — current style is fine.
