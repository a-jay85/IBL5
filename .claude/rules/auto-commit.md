---
description: Auto-commit after completing changes; amend when fixing unpushed work
last_verified: 2026-06-04
---

# Auto-Commit

## After completing changes

When you finish making changes in response to a user prompt, invoke `/commit-commands:commit` without waiting to be asked. This applies to implementation work, bug fixes, refactors, config changes — any prompt that results in file edits.

Do NOT auto-commit when:
- You're mid-task with more phases to complete (commit at logical checkpoints instead)
- The user is asking a question or exploring options (no files changed)
- You're inside `/post-plan` (it handles commits internally)
- You just finished implementing a `/plan` (the plan workflow is active, or you're in a plan worktree) — do NOT commit. STOP and hand off; the separate `/post-plan` session commits the working tree (its Phase 2) and ships.

## Amend vs new commit

**Amend** (`--amend` flag or interactive amend) when ALL of these are true:
- Your immediately preceding commit in this conversation is what you're fixing
- The user asked for a correction, tweak, or "actually do X instead"
- The commit has NOT been pushed yet

**New commit** when ANY of these are true:
- The work is a distinct logical change from the previous commit
- The previous commit has already been pushed
- You're unsure — default to new commit (safer)

The heuristic is simple: same task + unpushed + correcting yourself = amend.
