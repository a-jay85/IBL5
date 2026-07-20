---
description: Read-on-demand detail for agent-tiering — the skip-vs-spawn heuristic, Fable approval-gate procedure, flat-fan-out (nested sub-agent) rationale, orchestrator context economics (delegate-don't-dismiss, split-don't-self-clear), and per-tier prompt style. Loads only when editing workflow orchestration defs.
last_verified: 2026-07-20
paths:
  - ".claude/skills/**/*.md"
---

# Agent Tiering — Detail

Read-on-demand companion to `agent-tiering.md` (always-loaded). The parent holds the
operative Tier table and Explore tiering. This file holds the longer rationale — the
skip-vs-spawn heuristic, the Fable gate, flat-fan-out and orchestrator context economics,
and prompt style — pulled out of the always-loaded budget.

## Skip the Agent — Direct Tool Calls

Each sub-agent costs ~3–5K tokens (system prompt + rules + memory, loaded before its prompt), and its output re-loads in Opus's context every later turn.

**Run directly (no agent) when ALL hold:** single command/tool call · output under ~50 lines · nothing else to run in parallel · output won't persist across turns.

**Spawn an agent when ANY hold:** output unpredictably verbose (large grep, failing suites with stack traces) and the agent can return a summary · multiple independent verbose tasks run concurrently · the task is multiple sequential tool calls.

**When you spawn, minimize invocation count** — the question is *how many agents are needed*, not *parallel vs. sequential*; token spend outranks wall-clock time. Batch N related tasks into one agent (or do them yourself) — each spawn re-pays the ~3–5K overhead. Separate agents only when each genuinely needs its own context (independent worktrees, isolating verbose output), not because tasks are logically distinct.

**PHPUnit and PHPStan are always direct Bash calls** — passing output is ~5 lines, failures usually under 50; agent overhead dwarfs it. Use `run_in_background` for parallelism without an agent — **but only in the interactive harness**, where a finished background task re-invokes you. In a **headless** run (`claude -p`, e.g. `/post-plan` under automouse) there is no re-invocation: a live background task at turn-end stall-kills the run — run blocking, or poll `BashOutput` to completion in-turn (post-plan `SKILL.md` Phase 5).

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

Re-validated 2026-06-30 against Sonnet 5 (then the `sonnet` alias, native 1M context). The
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

## Orchestrator context economics — delegate to never-hold, split don't self-clear

The context saving from a sub-agent comes from **delegation, not dismissal**. A sub-agent runs in its own window; when it finishes, only its final message returns — every intermediate tool call and result stays isolated and evaporates. So "spin up → dismiss → spin up fresh" beats inlining the same work because the bulk **never entered the orchestrator**, not because dismissal evicts it (dismissal reclaims nothing — the internals were never in the parent). Corollary: keep returns **thin** — pointers (`path:line`), not file bodies (`feedback_orchestrator_pass_pointers_not_contents`).

**The orchestrator cannot clear itself.** Its context grows monotonically by the sum of return summaries across a run. The `/clear`-equivalent lives one layer down, in sub-agent lifecycle: a fresh `Agent()` spawn = clean context + cold cache + the ~3–5K spawn overhead; continuing an agent via `SendMessage` = warm cache but carries the prior task's context forward. **Fresh spawn = clear; `SendMessage` = keep talking** — pick by whether the next task actually needs the prior one's context.

**The only true reset is the session boundary.** That is exactly why `/post-plan` runs in a **fresh** session (`workflow-continuity.md`: inline re-reads full implementation context every phase, costing several times a fresh run). For a run too large to fit one orchestrator context, the fix is **split into multiple plans/sessions**, not orchestrator-level sub-agent juggling — and nesting orchestrators is closed by design (see Nested Sub-Agents above).

**Automouse:** same rules, headless. Lean-orchestrator + thin returns apply as-is, but it cannot self-clear between phases — a very long plan pays for its accumulating orchestrator context until the session ends. If that measurably hurts, split the plan into stacked pieces; don't reach for nested orchestrators. **Tripwire to revisit:** a *measured* automouse orchestrator-context problem — then split the plan first, before reconsidering nesting.

## Prompt Style by Tier

**Haiku** (compensate for its tendency to stop at "enough"): lead with a concrete grep/find command · say "list EVERY match" / "do NOT skip files" when exhaustiveness matters · pre-resolve absolute paths · request structured output (table/list) · for checklists, "check EACH pattern, cite file:line or state not found" · never ask it to judge relevance, trace multi-hop flows, or relate a past event to the current context.

**Sonnet**: open-ended exploration, multi-file synthesis, ambiguous queries where the first grep might miss — current style is fine.

## `/plan` orchestrator model

The rows in agent-tiering.md tier sub-agents; the `/plan` session model is a separate call. The `plan-architect` is tiered by Step-3 precedence (xhigh → sonnet → opus) regardless of the orchestrator — a Sonnet `/plan` spawning `plan-architect` still gets an Opus-authored plan.

Tier the orchestrator by the judgment **it** retains:

- **Single backlog item** → **Sonnet** (Steps 2.5/3/4 orchestrator calls are light; same recipe-backed class the "Opus (delegated)" row routes to `plan-architect-sonnet`).
- **Multiple items in one pass** → **Opus** (cross-item PR decomposition, **dependency ordering**, tier-boundary splits). Cheaper: run each as its own **Sonnet** `/plan` and make only the ordering call yourself.

## Explore Agent Tiering

Tier per prompt — don't default all Explore agents to one tier. Explore itself is pinned to Sonnet 4.6 (see agent-tiering.md § Sonnet 4.6 pins); the choice below is Haiku-vs-Sonnet-4.6 for the Explore *task*.

| Tier | Model param | Use for Explore | Examples |
|------|-------------|-----------------|---------|
| **Haiku** | `model: "haiku"` | Enumeration, single-file lookups, grep-and-list | "find all callers of getTeamByName", "does column X exist in migration Y" |
| **Sonnet 4.6** | *omit `model`* | Multi-hop traces, cross-module synthesis, open-ended investigation | "trace the encoding pipeline from .plr read to Team page" |

**Heuristic:** notice connections / judge relevance / trace data flow → omit `model` (Sonnet 4.6). Answerable by grep + format → `model: "haiku"`.
