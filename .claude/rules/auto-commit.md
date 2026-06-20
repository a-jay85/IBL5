---
description: Commit/PR work from the worktree; the Stop hook nudges to commit in a worktree and warns if the main checkout is dirty (work landed in the wrong place); amend when fixing unpushed work
last_verified: 2026-06-19
---

# Auto-Commit

All work happens in a worktree, never the main checkout (ADR-0062, `workflow-continuity.md`). Worktree work is committed by `/post-plan` (auto-fired) or `/commit-commands:commit-push-pr` — not by this hook.

The `$HOME/.claude/hooks/auto-commit-reminder.sh` Stop hook fires at turn-end when there are uncommitted changes, and nudges the right action depending on **where** the work is:

- **Main checkout** (master) dirty → **misplaced-work warning**: the work landed in the wrong place; move it into a worktree (`bin/wt-new`) — the main checkout is reference/read-only.
- **Worktree** dirty → **commit nudge**: this is the correct place to work; if you finished a unit of work, commit it via `/commit-commands:commit` (or `commit-push-pr`).

It stays silent when the tree is clean, on loop re-fire, and while a plan workflow is active (which covers `/post-plan` auto-fire committing in a worktree). It nudges on **uncommitted** changes only — push is owned by `commit-push-pr`/`/post-plan`. It is a nudge, not a hard block — ignore it if you are mid-task, just exploring, or have a deliberate reason to be touching the main checkout.

When you finish a unit of work in a worktree (impl, fix, refactor, config, docs) and are not using the `/post-plan` auto-fire handoff, invoke `/commit-commands:commit` (or `commit-push-pr`). Skip it when mid-task or when the user is only exploring.

## Amend vs new commit

The hook does not decide this — you do.

**Amend** (`--amend`) when ALL hold: the immediately preceding commit in this conversation is what you're fixing; the user asked for a correction/tweak; it's NOT pushed yet.

**New commit** when ANY hold: distinct logical change; previous commit already pushed; you're unsure (default to new — safer).

Heuristic: same task + unpushed + correcting yourself = amend.
