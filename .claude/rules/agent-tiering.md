---
description: Sub-agent decision rules — when to spawn, when to skip, and which model to pick
last_verified: 2026-06-27
---

# Agent Tiering

Tier every sub-agent (and every agent a plan spawns) by the reasoning the task actually needs — never default to Opus.

## Skip the Agent — Direct Tool Calls

Each sub-agent pays fixed overhead (~3–5K tokens: system prompt + rules + memory, loaded before it reads its prompt), and every token of its output is re-sent in Opus's context each subsequent turn. Justified only when the agent absorbs verbose output, runs in parallel, or needs multi-step reasoning.

**Run directly (no agent) when ALL hold:** single command/tool call · output under ~50 lines · nothing else needs to run in parallel · output won't persist across many turns.

**Spawn an agent when ANY hold:** output unpredictably verbose (large grep, failing suites with stack traces) and the agent can return a summary · multiple independent verbose tasks can run concurrently · the task is multiple sequential tool calls.

**When you do spawn, minimize the invocation count — the question is "how many agents are actually needed," not "parallel vs sequential."** Each spawn re-pays the ~3–5K overhead, so batch N related tasks into one agent (or do them yourself) rather than one agent per task. Separate agents are justified only when each genuinely needs its own context (independent worktrees, isolating verbose output) — not merely because the tasks are logically distinct.

**PHPUnit and PHPStan are always direct Bash calls** — passing output is ~5 lines, failures usually under 50; agent overhead dwarfs it. Use `run_in_background` for parallelism without an agent — **but only in the interactive harness, where a finished background task re-invokes you.** In a **headless** run (`claude -p`, e.g. `/post-plan` under automouse) there is no re-invocation: ending the turn with a background task alive stall-kills the run. There, either run blocking, or poll `BashOutput` to completion in-turn before ending the turn (see post-plan `SKILL.md` Phase 5).

## Tiers

| Tier | Model param | Use for |
|------|-------------|---------|
| **Haiku** | `model: "haiku"` | Command output, pattern-matching named checklists, grep-and-format, mechanical lookups — answerable by running commands and reporting, without judging relevance. |
| **Sonnet** | `model: "sonnet"` | Synthesis: "is this finding relevant?", cross-file traces, semantic compliance checks, rename sweeps needing call-site judgment. |
| **Opus** | self (no delegation) | Novel reasoning, FK ordering, rule authoring, ADR writing, ambiguous test failures, final code review, diff-triage. Never delegate understanding. |
| **Opus (delegated)** | `subagent_type: "plan-architect"` | Implementation **planning** only, via `/plan` Step 3. The def pins `model: opus` + `effort: xhigh` (the built-in Plan agent has no per-call effort override; A/B proved them equivalent), so planning runs at Opus depth in a clean sub-context. Do **not** pass an inline `model` override — the def owns it. |
| **Fable** | **unavailable — do not select** | Currently inaccessible; never pick `model: "fable"`. When access returns it is the opt-in rung above Opus (ceiling-binding tasks), gated behind explicit approval — full procedure in `.claude/rules/agent-tiering-detail.md`. |

## Flat fan-out (no nested sub-agents)

Sub-agents *can* spawn sub-agents (5 deep), but we keep **flat fan-out**: the Opus session owns every fan-out and absorbs every agent's output. Do **not** nest in the recurring workflows (`/plan`, `/pr-review`, `/security-audit`, `/post-plan`, automouse). Rationale + the tripwire to revisit: `.claude/rules/agent-tiering-detail.md`.

## Prompt style

When prompting **Haiku**, compensate for its tendency to stop at "enough": concrete grep/find command, "list EVERY match", pre-resolved absolute paths, structured output, and never ask it to judge relevance or trace multi-hop flows. **Sonnet**'s current style is fine. Full guidance: `.claude/rules/agent-tiering-detail.md`.

## Explore Agents

Tier per prompt — don't default all Explore agents to one tier.

| Tier | Use for Explore | Examples |
|------|-----------------|---------|
| **Haiku** | Enumeration, single-file lookups, grep-and-list | "find all callers of getTeamByName", "which files import WaiverService", "does column X exist in migration Y" |
| **Sonnet** | Multi-hop traces, cross-module synthesis, open-ended investigation | "trace the encoding pipeline from .plr read to Team page", "how does module A interact with B" |

**Heuristic:** notice connections / judge relevance / trace data flow → Sonnet. Answerable by grep + format → Haiku.

Plan-authoring tiering (labeling each phase Sonnet/Haiku/self, mechanical-recipe agents, bulk-sweep patterns) lives in `.claude/commands/plan.md` Step 3.
