---
description: Sub-agent decision rules — when to spawn, when to skip, which model to pick, and how sub-agent delegation keeps orchestrator context low
last_verified: 2026-07-09
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
| **Opus (delegated)** | `subagent_type: "plan-architect"` | Implementation **planning** only, via `/plan` Step 3. The def pins `model: opus` + `effort: high`; escalate to `plan-architect-xhigh` (`effort: xhigh`) for security surfaces, trust boundaries, destructive migrations, or ship-pipeline invariant changes. Do **not** pass an inline `model` override — the def owns it. |
| **Fable** | `model: "fable"` — **prompt first, last resort** | Rung above Opus (~2× cost). Use **only** when a task is absolutely critical **and** Fable is 100% necessary to solve it — and **never without prompting the user first** for explicit approval. Default to Opus. Full gate: `.claude/rules/agent-tiering-detail.md`. |

> **The boundary keys on task *type* (judgment vs. mechanical), not raw model capability** — a stronger Sonnet moves nothing across the line. Re-validated 2026-06-30 vs Sonnet 5 (then the `sonnet` alias, native 1M context): unchanged. Why: `agent-tiering-detail.md`.

## Flat fan-out, context economics, and prompt style

Keep **flat fan-out** — the Opus session owns every fan-out and absorbs each agent's output; never nest sub-agents in `/plan`, `/pr-review`, `/security-audit`, `/post-plan`, or automouse. The context win is **delegation, not dismissal** (a sub-agent's intermediate work never enters the orchestrator, so dismissing it reclaims nothing); keep returns **thin** (`path:line`, not file bodies), and since your own context can't self-clear mid-run, **split a too-big run into multiple plans/sessions** rather than nesting orchestrators. Prompt **Haiku** with a concrete grep/find command, "list EVERY match", pre-resolved absolute paths, and structured output — never relevance judgments or multi-hop traces; **Sonnet**'s current style is fine. Full rationale (nested-agent tripwire, the fresh-`Agent()`-vs-`SendMessage` cache tradeoff, per-tier prompt detail): `.claude/rules/agent-tiering-detail.md`.

## Explore Agents

Tier per prompt — don't default all Explore agents to one tier.

**Explore is pinned to Sonnet 4.6, not Sonnet 5.** The built-in agent is shadowed by a user def (`~/.claude/agents/Explore.md`, frontmatter `model: claude-sonnet-4-6`) to dodge Sonnet 5's ~30% token tax — its tokenizer inflates every token the run charges against a subscription's budget. The pin wins **only when the `model` param is omitted**; passing `model: "sonnet"` is **blocked** by `~/.claude/hooks/explore-model-gate.sh` (it resolves to Sonnet 5 and would override the pin).

| Tier | Model param | Use for Explore | Examples |
|------|-------------|-----------------|---------|
| **Haiku** | `model: "haiku"` | Enumeration, single-file lookups, grep-and-list | "find all callers of getTeamByName", "does column X exist in migration Y" |
| **Sonnet 4.6** | *omit `model`* | Multi-hop traces, cross-module synthesis, open-ended investigation | "trace the encoding pipeline from .plr read to Team page" |

**Heuristic:** notice connections / judge relevance / trace data flow → omit `model` (Sonnet 4.6). Answerable by grep + format → `model: "haiku"`.

Plan-authoring tiering (labeling each phase, mechanical-recipe agents, bulk-sweep patterns) lives in `.claude/skills/plan/_architect-contract.md`, the plan-architect's output contract that `/plan` Step 3 directs the architect to Read.
