---
description: Commit/PR work from the worktree; the Stop hook warns if the main checkout is dirty (work landed in the wrong place); amend when fixing unpushed work
last_verified: 2026-06-13
---

# Auto-Commit

All work happens in a worktree, never the main checkout (ADR-0062, `workflow-continuity.md`). Worktree work is committed by `/post-plan` (auto-fired) or `/commit-commands:commit-push-pr` — not by this hook.

The `$HOME/.claude/hooks/auto-commit-reminder.sh` Stop hook is now a **misplaced-work warning**, not a commit reminder: it fires at turn-end when the **main checkout** has uncommitted changes (and no plan workflow is active), telling you the work landed in the wrong place and to move it into a worktree (`bin/wt-new`). It stays silent in worktrees and during `/post-plan`. It is a warning, not a hard block — heed it unless you have a deliberate reason to be touching the main checkout.

When you finish a unit of work in a worktree (impl, fix, refactor, config, docs) and are not using the `/post-plan` auto-fire handoff, invoke `/commit-commands:commit` (or `commit-push-pr`). Skip it when mid-task or when the user is only exploring.

## Amend vs new commit

The hook does not decide this — you do.

**Amend** (`--amend`) when ALL hold: the immediately preceding commit in this conversation is what you're fixing; the user asked for a correction/tweak; it's NOT pushed yet.

**New commit** when ANY hold: distinct logical change; previous commit already pushed; you're unsure (default to new — safer).

Heuristic: same task + unpushed + correcting yourself = amend.
