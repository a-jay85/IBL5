---
description: Post-plan engine internals — compiled harness vs. Sonnet skill fallback, what `--auto`'s skip gate does, and where the auto-merge arming decision is made. Lazy companion to workflow-continuity.md; loads only when a post-plan surface is in play.
last_verified: 2026-09-06
paths:
  - ".claude/skills/post-plan/SKILL.md"
  - ".claude/skills/ship/SKILL.md"
  - "tools/postplan-harness/**"
no-adr: lazy-load companion relocating existing guidance out of workflow-continuity.md behind a glob; introduces no new decision surface, mechanism, or default — exactly mirrors agent-tiering-detail.md and work-triage-detail.md
---

# Workflow Continuity — Post-Plan Detail

Companion to `.claude/rules/workflow-continuity.md` § Post-Plan. That rule carries the
triggers (never inline; auto-fire when verified clean; don't commit first). This file
carries the internals — read it before debugging or changing a post-plan run.

## Engine

`bin/post-plan-now --auto` spawns a detached, launchd-supervised post-plan run on the current
branch; it survives you closing Claude Code. Engine selection:

- **Compiled post-plan harness** (`tools/postplan-harness`) when present — a
  deterministic sequencer with bounded LLM calls. `bin/post-plan-now` pins the MAIN-CHECKOUT
  copy, never the worktree's (ADR-0092).
- **Fallback:** a fresh **Sonnet 4.6** `/post-plan` skill session, used if the harness
  fails or is absent. `POST_PLAN_SKILL=1` forces the skill path.
- **Fidelity handoff (exit 4):** the harness cannot run Phase 5.5's plan-intent review — its
  LLM adapter is a `claude -p --max-turns 1 --tools ""` call in a neutral temp cwd, so an agent
  there cannot read the repo. When every other arm condition clears but no fidelity verdict
  exists, the harness holds condition (12) and exits **4**. That is a handoff, not a failure:
  `bin/post-plan-now` re-enters the skill **at Phase 5.5** — the fidelity review, the sticky
  verdict + merge-digest comment, and a fresh twelve-condition arming pass, per
  `.claude/skills/post-plan/_phase-5.5-fidelity.md` — instead of replaying the whole pipeline.
  Exit **3** (rebase conflict) is unchanged and still suppresses the skill fallback entirely.

## What `--auto` adds

`--auto` adds exactly one safety gate; a bare `bin/post-plan-now` skips it, because running it by
hand IS the decision to ship. The gate: **skip** when already inside a headless/automouse
run — the automouse runner fires post-plan itself.

It does **not** hold post-plan for a "risky" plan. Post-plan **always** runs and opens
the PR. Whether the PR then **auto-merges** is decided at `/post-plan` Phase 6.5, which
honors a plan's `auto_merge: false` (see `/plan` Step 4) and otherwise leaves the PR open
for a human to merge.

## Ad-hoc branches

An ad-hoc branch with no plan file makes post-plan run **plan-blind**. That is expected,
not an error. The merge-arming decision still happens at `/post-plan` Phase 6.5, so
auto-opening the PR never auto-merges a `feat:` / `auto_merge: false` / visual PR without
human signoff.

## No deadlock

`/post-plan` resolves the **highest-numbered** plan variant for the branch slug (`<slug>-3.md`
beats `<slug>-2.md` beats `<slug>.md`; a non-numeric suffix like `-shared-context.md` is never a
variant), so no handoff file is needed, and the detached child reparents under launchd and clears
its own plan-gate independently. Override the selection with `bin/post-plan-now --plan <abs-path>`.
