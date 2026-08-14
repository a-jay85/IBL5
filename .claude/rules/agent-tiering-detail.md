---
description: Read-on-demand detail for agent-tiering — the skip-vs-spawn heuristic, flat-fan-out (nested sub-agent) rationale, boundary keys on task type, orchestrator context economics (delegate-don't-dismiss, split-don't-self-clear), the measured evidence behind the offload-`/plan`-by-default rule, and per-tier prompt style. Fable approval gate moved to `agent-tiering-fable-gate.md`; bounded-checklist diff-triage rationale moved to `agent-tiering-bounded-checklist.md`. Loads only when editing workflow orchestration defs.
last_verified: 2026-08-14
paths:
  - ".claude/skills/**/*.md"
---

# Agent Tiering — Detail

Read-on-demand companion to `agent-tiering.md` (always-loaded). The parent holds the
operative Tier table and Explore tiering. This file holds the longer rationale — the
skip-vs-spawn heuristic, the Fable gate, flat-fan-out and orchestrator context economics,
and prompt style — pulled out of the always-loaded budget.

## Skip the Agent — Direct Tool Calls

Each sub-agent costs ~17–23K tokens (system prompt + rules + memory, loaded before its prompt) [CORRECTED 2026-08-14: was "~3–5K"; measured p50 spawn context is 17–23K], and its output re-loads in Opus's context every later turn.

**Run directly (no agent) when ALL hold:** single command/tool call · output under ~50 lines · nothing else to run in parallel · output won't persist across turns.

**Spawn an agent when ANY hold:** output unpredictably verbose (large grep, failing suites with stack traces) and the agent can return a summary · multiple independent verbose tasks run concurrently · the task is multiple sequential tool calls.

**When you spawn, minimize invocation count** — the question is *how many agents are needed*, not *parallel vs. sequential*; token spend outranks wall-clock time. Batch N related tasks into one agent (or do them yourself) — each spawn re-pays the ~17–23K overhead. Separate agents only when each genuinely needs its own context (independent worktrees, isolating verbose output), not because tasks are logically distinct.

**PHPUnit and PHPStan are always direct Bash calls** — passing output is ~5 lines, failures usually under 50; agent overhead dwarfs it. Use `run_in_background` for parallelism without an agent — **but only in the interactive harness**, where a finished background task re-invokes you. In a **headless** run (`claude -p`, e.g. `/post-plan` under automouse) there is no re-invocation: a live background task at turn-end stall-kills the run — run blocking, or poll `BashOutput` to completion in-turn (post-plan `SKILL.md` Phase 5).

> The Fable tier approval procedure (incl. the asm-level static-RE exception) has moved to `agent-tiering-fable-gate.md`.

## Boundary keys on task type, not model capability

Re-validated 2026-06-30 against Sonnet 5 (then the `sonnet` alias, native 1M context). The
Opus-only column (final code review, diff-triage, rule/ADR authoring, novel reasoning,
ambiguous failures) stays Opus because **"never delegate understanding" is a *delegation*
rule, not a "wait for a smarter model" rule** — a more capable Sonnet does not make
delegating the judgment safe, because the cost was never Sonnet's raw ability, it was that
the orchestrator session loses the findings it would otherwise filter (see the flat-fan-out
rationale below, and `feedback_sonnet_proving_negatives` / `feedback_review_agent_full_diff`).
Sonnet 5's larger context window only **strengthens** the existing "spawn Sonnet to absorb
verbose output" rationale; it is not a reason to push understanding-class work down a tier.
**Tripwire to revisit:** a model generation where the *delegation* failure mode itself
changes (e.g. a coordinator that can surface its own filtered-out findings), not merely a
higher per-task capability score.

## Nested Sub-Agents — Available, Deliberately Unused

Sub-agents can spawn sub-agents (5 deep), but we keep **flat fan-out**: the orchestrator session owns every fan-out and absorbs every agent's output. Do not nest in the recurring workflows (`/plan`, `/pr-review`, `/security-audit`, `/post-plan`, automouse). Why: our fan-out is narrow (1–4 agents/phase, not the wide verbose fan-out where nesting pays); the pipelines keep review/triage **in the orchestrator session** by design, whatever tier that session runs at (the review→score→filter step *is* triage — a coordinator would blind the orchestrator to the findings it filtered, and delegated judgment degrades — see `feedback_sonnet_proving_negatives`, `feedback_review_agent_full_diff`); and `/post-plan` is a single-context state machine whose Phase 3/5/6.5 gates read from main-session context, where nesting could only hide the filtered-out findings, not the survivor list the orchestrator still needs.

**Tripwire to revisit:** a *measured* post-plan context-window problem, or a new workflow with genuinely wide fan-out and verbose per-agent intermediates.

> The bounded-checklist rationale for `/post-plan` Phase 6.5 condition (9) has moved to `agent-tiering-bounded-checklist.md`.

## Orchestrator context economics — delegate to never-hold, split don't self-clear

The context saving from a sub-agent comes from **delegation, not dismissal**. A sub-agent runs in its own window; when it finishes, only its final message returns — every intermediate tool call and result stays isolated and evaporates. So "spin up → dismiss → spin up fresh" beats inlining the same work because the bulk **never entered the orchestrator**, not because dismissal evicts it (dismissal reclaims nothing — the internals were never in the parent). Corollary: keep returns **thin** — pointers (`path:line`), not file bodies (`feedback_orchestrator_pass_pointers_not_contents`).

**The orchestrator cannot clear itself.** Its context grows monotonically by the sum of return summaries across a run. The `/clear`-equivalent lives one layer down, in sub-agent lifecycle: a fresh `Agent()` spawn = clean context + cold cache + the ~17–23K spawn overhead; continuing an agent via `SendMessage` = warm cache but carries the prior task's context forward. **Fresh spawn = clear; `SendMessage` = keep talking** — pick by whether the next task actually needs the prior one's context.

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

**Getting to a Sonnet orchestrator from an Opus session** — the operative rule now lives in `agent-tiering.md` § `/plan` orchestrator model (promoted there 2026-08-02, because a session that never Read this file was the one making the inline call). This file keeps the mechanics and the evidence:

- `/plan-prompt` (`.claude/skills/plan-prompt/SKILL.md`) drafts the handoff and fires it via `bin/plan-now`; the clipboard copy remains, for running it by hand.
- Because the fired run has no human in it, `/plan-prompt` Step 4.5 makes the *drafting* session resolve the user-facing forks that `/plan` Step 3.5 would otherwise put to a human, and `bin/plan-now` holds auto-merge on any fork that survives.
- **Measured 2026-08-02** over ~30 days of this project's transcripts (capacity-proxy $ as a throughput measure, not billing): 195 architect spawns, of which **114 (58%) ran inline inside interactive sessions** rather than in a dedicated `/plan` run. Orchestrator cost per spawn: **Opus $7.36** (53 spawns, $390) vs **Sonnet $2.66** (142 spawns, $377). The architect tier is unchanged either way, so the delta is pure orchestrator overhead.
- Sonnet-orchestrating is only cheaper if the context actually crosses the session boundary — the handoff prompt is the thing that carries it.

## Explore Agent Tiering

Tier per prompt — don't default all Explore agents to one tier. Explore itself is pinned to Sonnet 4.6 (see agent-tiering.md § Sonnet 4.6 pins); the choice below is Haiku-vs-Sonnet-4.6 for the Explore *task*.

| Tier | Model param | Use for Explore | Examples |
|------|-------------|-----------------|---------|
| **Haiku** | `model: "haiku"` | Enumeration, single-file lookups, grep-and-list | "find all callers of getTeamByName", "does column X exist in migration Y" |
| **Sonnet 4.6** | *omit `model`* | Multi-hop traces, cross-module synthesis, open-ended investigation | "trace the encoding pipeline from .plr read to Team page" |

**Heuristic:** notice connections / judge relevance / trace data flow → omit `model` (Sonnet 4.6). Answerable by grep + format → `model: "haiku"`.
