---
description: Which tier to pick for each sub-agent, plus the Sonnet 4.6 def-pins.
last_verified: 2026-08-10
---

# Agent Tiering

Tier every sub-agent (and every agent a plan spawns) by the reasoning the task actually needs — never default to Opus.

## Tiers

| Tier | Model param | Use for |
|------|-------------|---------|
| **Haiku** | `model: "haiku"` | Command output, grep-and-format, mechanical lookups — answerable by running commands and reporting, without judging relevance. |
| **Sonnet** | `subagent_type: "sonnet-4-6"`, omit `model` — see § Sonnet 4.6 pins | Synthesis: "is this finding relevant?", cross-file traces, semantic compliance checks, rename sweeps needing call-site judgment, review agents, backlog housekeeping, manual-test classification. Never pass `model: "sonnet"` — the alias now resolves to Sonnet 5. |
| **Opus** | self (no delegation) | Novel reasoning, FK ordering, rule authoring, ADR writing, ambiguous test failures, final code review, open-ended diff-triage (Phase 6.5 bounded checklist: `agent-tiering-bounded-checklist.md`). Never delegate understanding. |
| **Opus (delegated)** | `subagent_type: "plan-architect"` | Implementation **planning** only, via `/plan` Step 3 — three defs by ONE ordered precedence (mirrors Step 3): **`plan-architect-xhigh`** (`effort: xhigh`) FIRST for security surfaces, trust boundaries, destructive migrations, or a ship-pipeline **gate removal/weakening or bootstrap hazard** (`.claude/skills`, `.claude/rules`, `~/.claude/hooks`) — deleting, relaxing, or disabling an enforcement mechanism; *not* additive gates, decision-procedure-preserving prose, or mechanism/plumbing (full clause: `/plan` Step 3 check 1); else **`plan-architect-sonnet`** (`model: claude-sonnet-4-6`) for recipe-backed tasks; else the default **`plan-architect`** (`model: opus` + `effort: high`). Do **not** pass an inline `model` override — each def owns it. |
| **Fable** | `model: "fable"` | Rung above Opus (~2× cost). Default to Opus; **never spawn Fable without prompting the user first**. Full gate: `agent-tiering-fable-gate.md`. |

> **The boundary keys on task *type* (judgment vs. mechanical), not raw model capability** — a stronger Sonnet moves nothing across the line. Why: `agent-tiering-detail.md`.

## `/plan` orchestrator model

The `/plan` session model is a separate call from the rows above. Tier the orchestrator by the judgment it retains — single backlog item → **Sonnet**, multiple items in one pass → **Opus** (cross-item decomposition + dependency ordering). The `plan-architect` is Step-3-tiered (xhigh → sonnet → opus) regardless of orchestrator.

**Default: don't run `/plan` inline from an Opus session — offload it.** Once the design thinking is done, run **`/plan-prompt`** (`.claude/skills/plan-prompt/SKILL.md`), which fires it via `bin/plan-now` as a **detached headless Sonnet 4.6 `/plan` session**. Sonnet orchestrates; the tier directive keeps the *design* on Opus. Stay inline only when the fork genuinely needs the human in the loop mid-run (`/plan` Step 3.5) and you can't pre-resolve it. Mechanics and evidence: `agent-tiering-detail.md` § `/plan` orchestrator model.

## Sonnet 4.6 pins

Sonnet surfaces are pinned to 4.6 via an agent def or skill frontmatter — **the def-based pin wins only when `model` is omitted**.

**Never pass `model: "sonnet"` to Agent()** — that alias now resolves to Sonnet 5.

| Surface | Def / File | Spawn / invoke with |
|---------|-----------|---------------------|
| **Explore** | `~/.claude/agents/Explore.md` (machine-local) | `subagent_type: "Explore"`, **omit `model`**. Blocked by `~/.claude/hooks/explore-model-gate.sh` if you pass `model: "sonnet"`. |
| **Automouse impl delegates** | `.claude/agents/automouse-delegate.md` (in-repo) | `subagent_type: "automouse-delegate"`, **omit `model`**. Fired by `bin/automouse/prompt-impl` for each `### Delegate` packet. |
| **General Sonnet tasks** (review agents, backlog housekeeping, any Sonnet-tier spawn) | `.claude/agents/sonnet-4-6.md` (in-repo) | `subagent_type: "sonnet-4-6"`, **omit `model`**. Full tool access. |
| **Plan architect (Sonnet tier)** | `.claude/agents/plan-architect-sonnet.md` (in-repo) | `subagent_type: "plan-architect-sonnet"`, **omit `model`**. Selected by `/plan` Step 3 precedence. |
| **`/pr-review` & `/security-audit` runners** | Their `SKILL.md` frontmatter | Pinned via `model: claude-sonnet-4-6` in skill frontmatter. No spawn change needed. |

## Explore Agents

Tier Explore per prompt — Haiku for enumeration / single-file lookups / grep-and-list; omit `model` (Sonnet 4.6) for multi-hop traces, cross-module synthesis, open-ended investigation. Table + examples: `agent-tiering-detail.md` § Explore Agent Tiering.

Plan-authoring tiering: `.claude/skills/plan/_architect-contract.md`, Read at `/plan` Step 3.
