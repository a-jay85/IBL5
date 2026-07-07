---
description: Sub-agent decision rules — when to spawn, when to skip, which model to pick, and how sub-agent delegation keeps orchestrator context low
last_verified: 2026-07-07
---

# Agent Tiering

Tier every sub-agent (and every agent a plan spawns) by the reasoning the task actually needs — never default to Opus.

## Skip the Agent — Direct Tool Calls

Each sub-agent costs ~3–5K tokens (system prompt + rules + memory, loaded before its prompt), and its output re-loads in Opus's context every later turn.

**Run directly (no agent) when ALL hold:** single command/tool call · output under ~50 lines · nothing else to run in parallel · output won't persist across turns.

**Spawn an agent when ANY hold:** output unpredictably verbose (large grep, failing suites with stack traces) and the agent can return a summary · multiple independent verbose tasks run concurrently · the task is multiple sequential tool calls.

**When you spawn, minimize invocation count** — the question is *how many agents are needed*, not *parallel vs. sequential*; token spend outranks wall-clock time. Batch N related tasks into one agent (or do them yourself) — each spawn re-pays the ~3–5K overhead. Separate agents only when each genuinely needs its own context (independent worktrees, isolating verbose output), not because tasks are logically distinct.

**PHPUnit and PHPStan are always direct Bash calls** — passing output is ~5 lines, failures usually under 50; agent overhead dwarfs it. Use `run_in_background` for parallelism without an agent — **but only in the interactive harness**, where a finished background task re-invokes you. In a **headless** run (`claude -p`, e.g. `/post-plan` under automouse) there is no re-invocation: a live background task at turn-end stall-kills the run — run blocking, or poll `BashOutput` to completion in-turn (post-plan `SKILL.md` Phase 5).

## Tiers

| Tier | Model param | Use for |
|------|-------------|---------|
| **Haiku** | `model: "haiku"` | Command output, pattern-matching named checklists, grep-and-format, mechanical lookups — answerable by running commands and reporting, without judging relevance. |
| **Sonnet** | `model: "sonnet"` | Synthesis: "is this finding relevant?", cross-file traces, semantic compliance checks, rename sweeps needing call-site judgment. |
| **Opus** | self (no delegation) | Novel reasoning, FK ordering, rule authoring, ADR writing, ambiguous test failures, final code review, diff-triage. Never delegate understanding. |
| **Opus (delegated)** | `subagent_type: "plan-architect"` | Implementation **planning** only, via `/plan` Step 3. The def pins `model: opus` + `effort: xhigh`, so planning runs at Opus depth in a clean sub-context. Do **not** pass an inline `model` override — the def owns it. |
| **Fable** | `model: "fable"` — **prompt first, last resort** | Rung above Opus (~2× cost). Use **only** when a task is absolutely critical **and** Fable is 100% necessary to solve it — and **never without prompting the user first** for explicit approval. Default to Opus. Full gate: `.claude/rules/agent-tiering-detail.md`. |

> **The boundary keys on task *type* (judgment vs. mechanical), not raw model capability** — a stronger Sonnet moves nothing across the line. Re-validated 2026-06-30 vs Sonnet 5 (then the `sonnet` alias, native 1M context): unchanged. Why: `agent-tiering-detail.md`.

## Flat fan-out (no nested sub-agents)

Sub-agents *can* nest (5 deep), but we keep **flat fan-out**: the Opus session owns every fan-out and absorbs every agent's output. Do **not** nest in `/plan`, `/pr-review`, `/security-audit`, `/post-plan`, or automouse. Rationale + tripwire: `.claude/rules/agent-tiering-detail.md`.

## Context economics: delegate to never-hold, split don't self-clear

A sub-agent's *intermediate* work never enters the orchestrator — only its final message returns. Delegating heavy work keeps it out of your context; **dismissing the agent reclaims nothing** (the internals were never yours), so the win is delegation, not eviction. Keep returns **thin** — `path:line` pointers, not file bodies. Your own context only grows and **can't self-clear mid-run**: the real reset is the **session boundary** (why `/post-plan` runs fresh). For a run too big to fit, **split into multiple plans/sessions** — never nest orchestrators. The fresh-`Agent()`-vs-`SendMessage` cache tradeoff and full rationale: `.claude/rules/agent-tiering-detail.md`.

## Prompt style

Prompting **Haiku**: compensate for its tendency to stop at "enough" — concrete grep/find command, "list EVERY match", pre-resolved absolute paths, structured output; never ask it to judge relevance or trace multi-hop flows. **Sonnet**'s current style is fine. Full guidance: `.claude/rules/agent-tiering-detail.md`.

## Explore Agents

Tier per prompt — don't default all Explore agents to one tier.

**Explore is pinned to Sonnet 4.6, not Sonnet 5.** The built-in agent is shadowed by a user def (`~/.claude/agents/Explore.md`, frontmatter `model: claude-sonnet-4-6`) to dodge Sonnet 5's ~30% token tax — its tokenizer inflates every token the run charges against a subscription's budget. The pin wins **only when the `model` param is omitted**; passing `model: "sonnet"` is **blocked** by `~/.claude/hooks/explore-model-gate.sh` (it resolves to Sonnet 5 and would override the pin).

| Tier | Model param | Use for Explore | Examples |
|------|-------------|-----------------|---------|
| **Haiku** | `model: "haiku"` | Enumeration, single-file lookups, grep-and-list | "find all callers of getTeamByName", "does column X exist in migration Y" |
| **Sonnet 4.6** | *omit `model`* | Multi-hop traces, cross-module synthesis, open-ended investigation | "trace the encoding pipeline from .plr read to Team page" |

**Heuristic:** notice connections / judge relevance / trace data flow → omit `model` (Sonnet 4.6). Answerable by grep + format → `model: "haiku"`.

Plan-authoring tiering (labeling each phase, mechanical-recipe agents, bulk-sweep patterns) lives in `.claude/skills/plan/SKILL.md` Step 3.
