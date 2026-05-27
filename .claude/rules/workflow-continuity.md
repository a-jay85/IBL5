---
description: Worktree setup and /post-plan workflow rules for multi-step implementation work.
last_verified: 2026-05-27
---

# Workflow Continuity Rule

## Planning

Use `/plan <task description>` for implementation planning.

## Worktree Setup

Before implementation, create a worktree unless one already exists for this task:

```bash
bin/wt-new <slug>   # slug = kebab-case branch name derived from the plan
```

Use `--base <branch>` for stacked PRs. Work in `worktrees/<slug>/ibl5/`. Skip if already inside a worktree or the plan specifies an existing one.

## Post-Plan

After implementation, invoke `/post-plan` and let it run to completion.
