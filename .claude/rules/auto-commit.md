---
description: Commit/PR work from the worktree autonomously when finished; never ask the user whether to commit. Amend only unpushed fixes. Carries the one-line PR-title decision test (full rubric in commit-conventions.md).
last_verified: 2026-07-19
---

# Auto-Commit

All work happens in a worktree, never the main checkout (ADR-0062, `workflow-continuity.md`). Worktree work is committed by `/post-plan` (auto-fired) or `/commit-commands:commit-push-pr`.

**PR/commit title type** (when titling via `/commit-commands:*`): decision test — *"Would a league GM notice a new ability they didn't have before?"* Yes → `feat:` (trips the human-signoff hold — that's the gate working, not a cost to route around); invisible to a GM (dev tooling, a new slash command, an internal refactor, a doc, a dep bump) → `chore:`/`fix:`/`refactor:`/`docs:`. Classify by what the diff **is**, never by the desired merge outcome. Full rubric incl. edge cases: `.claude/rules/commit-conventions.md`.

When you finish a unit of work in a worktree and are not using the `/post-plan` auto-fire handoff, invoke `/commit-commands:commit` (or `commit-push-pr`). Skip when mid-task or only exploring.

## Amend vs new commit

You decide.

**Amend** (`--amend`) when ALL hold: the immediately preceding commit in this conversation is what you're fixing; the user asked for a correction/tweak; it's NOT pushed yet.

**New commit** when ANY hold: distinct logical change; previous commit already pushed; you're unsure (default to new — safer).

Heuristic: same task + unpushed + correcting yourself = amend.
