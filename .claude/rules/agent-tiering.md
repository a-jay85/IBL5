---
description: Which tier to pick for each sub-agent, plus the Sonnet 5.5 def-pins.
last_verified: 2026-09-28
---

# Agent Tiering

Tier every sub-agent (and every agent a plan spawns) by the reasoning the task actually needs — never default to Opus.

## Tiers

| Tier | Model param | Use for |
|------|-------------|---------|
| **Haiku** | `model: "haiku"` | Command output, grep-and-format, mechanical lookups — answerable by running commands and reporting, without judging relevance. |
| **Sonnet** | `subagent_type: "sonnet-5-5"`, omit `model`. See § Sonnet 5.5 pins. | Synthesis: "is this finding relevant?", cross-file traces, semantic compliance checks, rename sweeps needing call-site judgment, review agents, backlog housekeeping, manual-test classification. |
| **Opus** | self (no delegation) | Novel reasoning, FK ordering, rule authoring, ADR writing, ambiguous test failures, final code review, open-ended diff-triage (Phase 6.5 bounded checklist: `agent-tiering-bounded-checklist.md`). Never delegate understanding. |
| **Opus (delegated)** | `subagent_type: "plan-architect"` | Implementation **planning** only, via `/plan` Step 3. Three defs by ONE ordered precedence: **`plan-architect-xhigh`** (Fable; security, destructive, executable-gate removal; full trigger: `/plan` Step 3 check 1); **`plan-architect-sonnet`** (recipe-backed); **`plan-architect`** (Opus, default). Do **not** pass inline `model`. |
| **Fable** | `model: "fable"` | Rung above Opus (2.5× Opus 5.5 per token). Default to Opus; raise Opus effort before moving up; **never spawn Fable without prompting the user first**; a def pinned to Fable is a standing yes. Full gate: `agent-tiering-fable-gate.md`. |

> **The boundary keys on task *type* (judgment vs. mechanical), not raw model capability** — a stronger Sonnet moves nothing across the line. Why: `agent-tiering-detail.md`.

**Plan `impl_model:` is a different namespace** — these values do not carry over; six literals only: `.claude/rules/automouse-workflow.md`.

## Fat-tail delegation

Only the fat tail of tool results is worth a spawn. A call is **fat** when it is a `Read` ≥ 8 KB, or a Bash command in: bare `cat`, `git log` with no bound, `find` with no limit, a full Playwright run. **Two fat calls per turn pass.** The 3rd is denied. Batch it and the rest into ONE `Agent(subagent_type: "sonnet-5-5")` (omit `model`). Enforced by **Check F** in `~/.claude/hooks/output-guard.sh`; fails open; touch the override path from the deny message for a one-off. Independent slow items fan out concurrently instead. Detail: `agent-tiering-detail.md` §§ Skip the Agent, Fan out by independence.

## `/plan` orchestrator model

Single backlog item → **Sonnet** orchestrator; multiple items → **Opus**. The `plan-architect` is Step-3-tiered (xhigh → sonnet → opus) **regardless of orchestrator**. Default: offload via **`/plan-prompt`** → `bin/plan-now` (detached Sonnet run). Stay inline only when the user must weigh in mid-run. Mechanics and evidence: `agent-tiering-detail.md` § `/plan` orchestrator model.

## Sonnet 5.5 pins

Sonnet surfaces are pinned to 5.5 via an agent def or skill frontmatter. The def-based pin wins only when `model` is omitted.

| Surface | Def / File | Spawn / invoke with |
|---------|-----------|---------------------|
| **Explore** | `~/.claude/agents/Explore.md` (machine-local) | `subagent_type: "Explore"`, **omit `model`**. |
| **Automouse impl delegates** | `.claude/agents/automouse-delegate.md` (in-repo) | `subagent_type: "automouse-delegate"`, **omit `model`**. Fired by `bin/automouse/prompt-impl` for each `### Delegate` packet. |
| **General Sonnet tasks** (any Sonnet-tier spawn) | `.claude/agents/sonnet-5-5.md` (in-repo) | `subagent_type: "sonnet-5-5"`, **omit `model`**. The `Agent` tool is absent; it cannot spawn. |
| **Plan architect (Sonnet tier)** | `.claude/agents/plan-architect-sonnet.md` (in-repo) | `subagent_type: "plan-architect-sonnet"`, **omit `model`**. Selected by `/plan` Step 3 precedence. |
| **`/pr-review` & `/security-audit` runners** | Their `SKILL.md` frontmatter | Pinned via `model: claude-sonnet-5-5` in skill frontmatter. No spawn change needed. |

## Explore Agents

Tier Explore per prompt. Use Haiku for enumeration / single-file lookups / grep-and-list; omit `model` (Sonnet 5.5) for multi-hop traces, cross-module synthesis, open-ended investigation. Table + examples: `agent-tiering-detail.md` § Explore Agent Tiering.

Plan-authoring tiering: `.claude/skills/plan/_architect-contract.md` — Read by the **`plan-architect`** at `/plan` Step 3, not by you. Never Read it on the main thread; that bulk must never enter the orchestrator's context (`.claude/skills/plan/SKILL.md` Step 3).
