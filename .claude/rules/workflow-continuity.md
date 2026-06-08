---
description: Worktree setup and the implementation→/post-plan handoff (run separately) for multi-step work.
last_verified: 2026-06-08
---

# Workflow Continuity Rule

## Planning

Use `/plan <task description>` for implementation planning.

## Worktree Setup

Before implementation, create a worktree unless one already exists for this task:

```bash
bin/wt-new <slug>   # slug = kebab-case branch name derived from the plan
```

Use `--base <branch>` for stacked PRs. Work in `IBL5-worktrees/<slug>/ibl5/` (worktrees live outside the repo — ADR-0046). Skip if already inside a worktree or the plan specifies an existing one.

## Post-Plan

After implementation, **STOP** — do **NOT** invoke `/post-plan` in this session. Leave the worktree with **uncommitted** changes and hand off; do not commit.

`/post-plan` is run **separately**, in a **fresh** session (typically Sonnet), with **cwd = this worktree** so it sees the uncommitted working tree. Run it from a fresh session in the worktree as the model handing off, or hand the branch to a teammate.

Why separate: `/post-plan` re-reads the full implementation context on every phase, so running it inline after a long implementation session — especially on Opus — costs several times a fresh run.

`/post-plan` auto-resolves the plan from the branch slug, fetches the diff itself, and commits the uncommitted working tree in its Phase 2.
