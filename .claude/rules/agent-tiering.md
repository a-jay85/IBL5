---
description: Which tier to pick for each sub-agent, plus the Sonnet 5.5 def-pins.
last_verified: 2026-10-08
---

# Agent Tiering

Tier every sub-agent (and every agent a plan spawns) by the reasoning the task actually needs — never default to Opus.

## Tiers

| Tier | Model param | Use for |
|------|-------------|---------|
| **Haiku** | `model: "haiku"` | Command output, grep-and-format, mechanical lookups — answerable by running commands and reporting, without judging relevance. |
| **Sonnet** | `subagent_type: "sonnet-5-5"`, omit `model`. See § Sonnet 5.5 pins. | Synthesis: "is this finding relevant?", cross-file traces, semantic compliance checks, rename sweeps needing call-site judgment, review agents, manual-test classification. |
| **Opus** | self (no delegation) | Novel reasoning, FK ordering, rule authoring, ADR writing, ambiguous test failures, final code review, open-ended diff-triage (Phase 6.5 bounded checklist: `agent-tiering-bounded-checklist.md`). Never delegate understanding. |
| **Opus (delegated)** | `subagent_type: "plan-architect"` | Implementation **planning** only, via `/plan` Step 3. Three defs by ONE ordered precedence: **`plan-architect-xhigh`** (Fable; security, destructive, executable-gate removal; full trigger: `/plan` Step 3 check 1); **`plan-architect-sonnet`** (recipe-backed); **`plan-architect`** (Opus, default). Do **not** pass inline `model`. |
| **Fable** | `model: "fable"` | Rung above Opus (2.5× Opus 5.5 per token). Default to Opus; raise Opus effort before moving up; **never spawn Fable without prompting the user first**; a def pinned to Fable is a standing yes. Full gate: `agent-tiering-fable-gate.md`. |

> **The boundary keys on task *type* (judgment vs. mechanical), not raw model capability** — a stronger Sonnet moves nothing across the line. Why: `agent-tiering-detail.md`.

**Haiku price cliff.** Above ~100K prompt tokens per request Haiku's rates rise 5x; send large-context jobs to Sonnet. Detail: `agent-tiering-detail.md` § Haiku 5.5 measurement.

**Plan `impl_model:` is a different namespace** — these values do not carry over; six literals only: `.claude/rules/automouse-workflow.md`.

## Fat-tail delegation

Only the fat tail of tool results is worth a spawn. **Two fat calls per turn pass** (a `Read` ≥ 8 KB, unbounded `cat`/`git log`/`find`, a full Playwright run). The 3rd is denied. Batch it and the rest into ONE `Agent(model: "haiku")` digest spawn; use `subagent_type: "sonnet-5-5"` when the batch may pass ~100K tokens. Enforced by Check F in `~/.claude/hooks/output-guard.sh`. Independent slow items fan out concurrently instead. Detail: `agent-tiering-detail.md` §§ Skip the Agent, Fan out by independence.

## `/plan` orchestrator model

Single backlog item → **Sonnet** orchestrator; multiple items → **Opus**. Default: offload via **`/plan-prompt`** → `bin/plan-now`. Stay inline only when the user must weigh in mid-run. Detail: `agent-tiering-detail.md` § `/plan` orchestrator model.

## Sonnet 5.5 pins

Sonnet surfaces are pinned to 5.5 via an agent def or skill frontmatter. The def-based pin wins only when `model` is omitted.

| Surface | Def / File | Spawn / invoke with |
|---------|-----------|---------------------|
| **Explore** | `~/.claude/agents/Explore.md` (machine-local) | `subagent_type: "Explore"`, **omit `model`**. |
| **General Sonnet tasks** (any Sonnet-tier spawn) | `.claude/agents/sonnet-5-5.md` (in-repo) | `subagent_type: "sonnet-5-5"`, **omit `model`**. The `Agent` tool is absent; it cannot spawn. |

Also pinned (automouse delegates, `plan-architect-sonnet`, `/pr-review` and `/security-audit` runners): `agent-tiering-detail.md` § Sonnet 5.5 pins, other surfaces.

## Explore Agents

Tier Explore per prompt: Haiku for grep-and-list, omit `model` for multi-hop traces. Table: `agent-tiering-detail.md` § Explore Agent Tiering.

Plan-authoring tiering: `.claude/skills/plan/_architect-contract.md` — Read by the **`plan-architect`** at `/plan` Step 3, not by you. Never Read it on the main thread; that bulk must never enter the orchestrator's context (`.claude/skills/plan/SKILL.md` Step 3).
