---
description: Which tier to pick for each sub-agent, including the two Sonnet 4.6 def-pins (Explore + automouse delegates). Skip-vs-spawn heuristic and deeper rationale live in agent-tiering-detail.md.
last_verified: 2026-07-15
---

# Agent Tiering

Tier every sub-agent (and every agent a plan spawns) by the reasoning the task actually needs — never default to Opus.

## Tiers

| Tier | Model param | Use for |
|------|-------------|---------|
| **Haiku** | `model: "haiku"` | Command output, pattern-matching named checklists, grep-and-format, mechanical lookups — answerable by running commands and reporting, without judging relevance. |
| **Sonnet** | `model: "sonnet"` | Synthesis: "is this finding relevant?", cross-file traces, semantic compliance checks, rename sweeps needing call-site judgment. Two surfaces are **pinned to 4.6** — see § Sonnet 4.6 pins. |
| **Opus** | self (no delegation) | Novel reasoning, FK ordering, rule authoring, ADR writing, ambiguous test failures, final code review, diff-triage. Never delegate understanding. |
| **Opus (delegated)** | `subagent_type: "plan-architect"` | Implementation **planning** only, via `/plan` Step 3 — three defs selected by ONE ordered precedence (mirrors Step 3): **`plan-architect-xhigh`** (`effort: xhigh`) FIRST for security surfaces, trust boundaries, destructive migrations, or ship-pipeline invariant changes; else **`plan-architect-sonnet`** (`model: sonnet`) when the task is recipe-backed — an explicit recipe plus a named existing pattern to copy — since composing a plan from a pre-resolved recipe is mechanical composition, not novel design (no understanding is delegated, only recipe composition, so it does not breach the Opus (self) row's "never delegate understanding"); else the default **`plan-architect`** (`model: opus` + `effort: high`). Do **not** pass an inline `model` override — each def owns it. |
| **Fable** | `model: "fable"` — **prompt first, last resort** | Rung above Opus (~2× cost). Use **only** when a task is absolutely critical **and** Fable is 100% necessary to solve it — and **never without prompting the user first** for explicit approval. Default to Opus. Full gate: `.claude/rules/agent-tiering-detail.md`. |

> **The boundary keys on task *type* (judgment vs. mechanical), not raw model capability** — a stronger Sonnet moves nothing across the line. Re-validated 2026-06-30 vs Sonnet 5 (then the `sonnet` alias, native 1M context): unchanged. Why: `agent-tiering-detail.md`.

## `/plan` orchestrator model

The rows above tier sub-agents; the `/plan` session model is a separate call. The `plan-architect` is tiered by Step-3 precedence (xhigh → sonnet → opus) regardless of the orchestrator — a Sonnet `/plan` spawning `plan-architect` still gets an Opus-authored plan.

Tier the orchestrator by the judgment **it** retains:

- **Single backlog item** → **Sonnet** (Steps 2.5/3/4 orchestrator calls are light; same recipe-backed class the "Opus (delegated)" row routes to `plan-architect-sonnet`).
- **Multiple items in one pass** → **Opus** (cross-item PR decomposition, **dependency ordering**, tier-boundary splits). Cheaper: run each as its own **Sonnet** `/plan` and make only the ordering call yourself.

## Sonnet 4.6 pins

Two high-volume Sonnet surfaces are pinned to 4.6 via an agent def — the `model` enum can't express a specific version; the def's frontmatter is the only way, and **the pin wins only when `model` is omitted**.

| Surface | Def | Spawn with |
|---------|-----|-----------|
| **Explore** | `~/.claude/agents/Explore.md` (machine-local) | `subagent_type: "Explore"`, **omit `model`**. Blocked by `~/.claude/hooks/explore-model-gate.sh` if you pass `model: "sonnet"`. |
| **Automouse impl delegates** | `.claude/agents/automouse-delegate.md` (in-repo) | `subagent_type: "automouse-delegate"`, **omit `model`**. Fired by `bin/automouse-prompt-impl` for each `### Delegate` packet. |

## Explore Agents

Tier per prompt — don't default all Explore agents to one tier. Explore is pinned to Sonnet 4.6 — see § Sonnet 4.6 pins.

| Tier | Model param | Use for Explore | Examples |
|------|-------------|-----------------|---------|
| **Haiku** | `model: "haiku"` | Enumeration, single-file lookups, grep-and-list | "find all callers of getTeamByName", "does column X exist in migration Y" |
| **Sonnet 4.6** | *omit `model`* | Multi-hop traces, cross-module synthesis, open-ended investigation | "trace the encoding pipeline from .plr read to Team page" |

**Heuristic:** notice connections / judge relevance / trace data flow → omit `model` (Sonnet 4.6). Answerable by grep + format → `model: "haiku"`.

Plan-authoring tiering (labeling each phase, mechanical-recipe agents, bulk-sweep patterns) lives in `.claude/skills/plan/_architect-contract.md`, the plan-architect's output contract that `/plan` Step 3 directs the architect to Read.
