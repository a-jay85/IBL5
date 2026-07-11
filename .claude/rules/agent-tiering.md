---
description: Which tier to pick for each sub-agent, plus how to tier Explore agents. Skip-vs-spawn heuristic and deeper rationale live in agent-tiering-detail.md.
last_verified: 2026-07-11
---

# Agent Tiering

Tier every sub-agent (and every agent a plan spawns) by the reasoning the task actually needs — never default to Opus.

## Tiers

| Tier | Model param | Use for |
|------|-------------|---------|
| **Haiku** | `model: "haiku"` | Command output, pattern-matching named checklists, grep-and-format, mechanical lookups — answerable by running commands and reporting, without judging relevance. |
| **Sonnet** | `model: "sonnet"` | Synthesis: "is this finding relevant?", cross-file traces, semantic compliance checks, rename sweeps needing call-site judgment. |
| **Opus** | self (no delegation) | Novel reasoning, FK ordering, rule authoring, ADR writing, ambiguous test failures, final code review, diff-triage. Never delegate understanding. |
| **Opus (delegated)** | `subagent_type: "plan-architect"` | Implementation **planning** only, via `/plan` Step 3 — three defs selected by ONE ordered precedence (mirrors Step 3): **`plan-architect-xhigh`** (`effort: xhigh`) FIRST for security surfaces, trust boundaries, destructive migrations, or ship-pipeline invariant changes; else **`plan-architect-sonnet`** (`model: sonnet`) when the task is recipe-backed — an explicit recipe plus a named existing pattern to copy — since composing a plan from a pre-resolved recipe is mechanical composition, not novel design (no understanding is delegated, only recipe composition, so it does not breach the Opus (self) row's "never delegate understanding"); else the default **`plan-architect`** (`model: opus` + `effort: high`). Do **not** pass an inline `model` override — each def owns it. |
| **Fable** | `model: "fable"` — **prompt first, last resort** | Rung above Opus (~2× cost). Use **only** when a task is absolutely critical **and** Fable is 100% necessary to solve it — and **never without prompting the user first** for explicit approval. Default to Opus. Full gate: `.claude/rules/agent-tiering-detail.md`. |

> **The boundary keys on task *type* (judgment vs. mechanical), not raw model capability** — a stronger Sonnet moves nothing across the line. Re-validated 2026-06-30 vs Sonnet 5 (then the `sonnet` alias, native 1M context): unchanged. Why: `agent-tiering-detail.md`.

## Explore Agents

Tier per prompt — don't default all Explore agents to one tier.

**Explore is pinned to Sonnet 4.6, not Sonnet 5.** The built-in agent is shadowed by a user def (`~/.claude/agents/Explore.md`, frontmatter `model: claude-sonnet-4-6`) to dodge Sonnet 5's ~30% token tax — its tokenizer inflates every token the run charges against a subscription's budget. The pin wins **only when the `model` param is omitted**; passing `model: "sonnet"` is **blocked** by `~/.claude/hooks/explore-model-gate.sh` (it resolves to Sonnet 5 and would override the pin).

| Tier | Model param | Use for Explore | Examples |
|------|-------------|-----------------|---------|
| **Haiku** | `model: "haiku"` | Enumeration, single-file lookups, grep-and-list | "find all callers of getTeamByName", "does column X exist in migration Y" |
| **Sonnet 4.6** | *omit `model`* | Multi-hop traces, cross-module synthesis, open-ended investigation | "trace the encoding pipeline from .plr read to Team page" |

**Heuristic:** notice connections / judge relevance / trace data flow → omit `model` (Sonnet 4.6). Answerable by grep + format → `model: "haiku"`.

Plan-authoring tiering (labeling each phase, mechanical-recipe agents, bulk-sweep patterns) lives in `.claude/skills/plan/_architect-contract.md`, the plan-architect's output contract that `/plan` Step 3 directs the architect to Read.
