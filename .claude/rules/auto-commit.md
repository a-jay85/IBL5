---
description: Commit after a unit of work (Stop hook reminds you); amend when fixing unpushed work
last_verified: 2026-06-04
---

# Auto-Commit

The `$HOME/.claude/hooks/auto-commit-reminder.sh` Stop hook delivers the "commit when done" reminder at runtime: it fires at turn-end when the main checkout has uncommitted changes and no plan workflow is active, and stays silent in worktrees and during `/post-plan` (those hand off / commit internally). When reminded — or whenever a prompt finishes a unit of work (impl, fix, refactor, config, docs) — invoke `/commit-commands:commit`. Skip it when mid-task or when the user is only exploring.

## Amend vs new commit

The hook does not decide this — you do.

**Amend** (`--amend`) when ALL hold: the immediately preceding commit in this conversation is what you're fixing; the user asked for a correction/tweak; it's NOT pushed yet.

**New commit** when ANY hold: distinct logical change; previous commit already pushed; you're unsure (default to new — safer).

Heuristic: same task + unpushed + correcting yourself = amend.
