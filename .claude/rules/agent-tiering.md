---
description: Which tier to pick for each sub-agent, plus the Sonnet 4.6 def-pins.
last_verified: 2026-08-26
---

# Agent Tiering

Tier every sub-agent (and every agent a plan spawns) by the reasoning the task actually needs — never default to Opus.

## Tiers

| Tier | Model param | Use for |
|------|-------------|---------|
| **Haiku** | `model: "haiku"` | Command output, grep-and-format, mechanical lookups — answerable by running commands and reporting, without judging relevance. |
| **Sonnet** | `subagent_type: "sonnet-4-6"`, omit `model` — see § Sonnet 4.6 pins | Synthesis: "is this finding relevant?", cross-file traces, semantic compliance checks, rename sweeps needing call-site judgment, review agents, backlog housekeeping, manual-test classification. Never pass `model: "sonnet"` — the alias now resolves to Sonnet 5. |
| **Opus** | self (no delegation) | Novel reasoning, FK ordering, rule authoring, ADR writing, ambiguous test failures, final code review, open-ended diff-triage (Phase 6.5 bounded checklist: `agent-tiering-bounded-checklist.md`). Never delegate understanding. |
| **Opus (delegated)** | `subagent_type: "plan-architect"` | Implementation **planning** only, via `/plan` Step 3 — three defs by ONE ordered precedence: **`plan-architect-xhigh`** (gate-removal, security, destructive — full trigger: `/plan` Step 3 check 1); **`plan-architect-sonnet`** (recipe-backed); **`plan-architect`** (Opus, default). Do **not** pass inline `model`. |
| **Fable** | `model: "fable"` | Rung above Opus (~2× cost). Default to Opus; **never spawn Fable without prompting the user first**. Full gate: `agent-tiering-fable-gate.md`. |

> **The boundary keys on task *type* (judgment vs. mechanical), not raw model capability** — a stronger Sonnet moves nothing across the line. Why: `agent-tiering-detail.md`.

## Fat-tail delegation

**Only the fat tail of tool results is worth a spawn.** A call is **fat** when it is a `Read` ≥ 8 KB, or a Bash command in: bare `cat`, `git log` with no bound, `find` with no limit, a full Playwright run. **Two fat calls per turn pass; the 3rd is denied** — batch it and the rest into ONE `Agent(subagent_type: "sonnet-4-6")` (omit `model`). Enforced by **Check F** in `~/.claude/hooks/output-guard.sh`; fails open; touch the override path from the deny message for a one-off. Evidence and reconciliation: `agent-tiering-detail.md` § Skip the Agent.

## `/plan` orchestrator model

Single backlog item → **Sonnet** orchestrator; multiple items → **Opus**. Default: offload via **`/plan-prompt`** → `bin/plan-now` (detached Sonnet run). Stay inline only when the user must weigh in mid-run. Mechanics and evidence: `agent-tiering-detail.md` § `/plan` orchestrator model.

## Sonnet 4.6 pins

Sonnet surfaces are pinned to 4.6 via an agent def or skill frontmatter — **the def-based pin wins only when `model` is omitted**.

**Never pass `model: "sonnet"` to Agent()** — that alias now resolves to Sonnet 5.

| Surface | Def / File | Spawn / invoke with |
|---------|-----------|---------------------|
| **Explore** | `~/.claude/agents/Explore.md` (machine-local) | `subagent_type: "Explore"`, **omit `model`**. Blocked by `~/.claude/hooks/explore-model-gate.sh` if you pass `model: "sonnet"`. |
| **Automouse impl delegates** | `.claude/agents/automouse-delegate.md` (in-repo) | `subagent_type: "automouse-delegate"`, **omit `model`**. Fired by `bin/automouse/prompt-impl` for each `### Delegate` packet. |
| **General Sonnet tasks** (review agents, backlog housekeeping, any Sonnet-tier spawn) | `.claude/agents/sonnet-4-6.md` (in-repo) | `subagent_type: "sonnet-4-6"`, **omit `model`**. No `Agent` — it cannot spawn. |
| **Plan architect (Sonnet tier)** | `.claude/agents/plan-architect-sonnet.md` (in-repo) | `subagent_type: "plan-architect-sonnet"`, **omit `model`**. Selected by `/plan` Step 3 precedence. |
| **`/pr-review` & `/security-audit` runners** | Their `SKILL.md` frontmatter | Pinned via `model: claude-sonnet-4-6` in skill frontmatter. No spawn change needed. |

## Explore Agents

Tier Explore per prompt — Haiku for enumeration / single-file lookups / grep-and-list; omit `model` (Sonnet 4.6) for multi-hop traces, cross-module synthesis, open-ended investigation. Table + examples: `agent-tiering-detail.md` § Explore Agent Tiering.

Plan-authoring tiering: `.claude/skills/plan/_architect-contract.md` — Read by the **`plan-architect`** at `/plan` Step 3, not by you. Never Read it on the main thread; that bulk must never enter the orchestrator's context (`.claude/skills/plan/SKILL.md` Step 3).
