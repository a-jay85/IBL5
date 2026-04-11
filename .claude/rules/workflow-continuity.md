---
description: Worktree setup and /post-plan workflow rules for multi-step implementation work.
last_verified: 2026-04-11
---

# Workflow Continuity Rule

## Phase 1: Worktree Setup

Before implementation, create a worktree unless one already exists for this task:

```bash
bin/wt-new <slug>   # slug = kebab-case branch name derived from the plan
```

Use `--base <branch>` for stacked PRs. Work in `worktrees/<slug>/ibl5/`. Skip if already inside a worktree or the plan specifies an existing one.

## Post-Plan

The post-plan workflow is consolidated into a single `/post-plan` skill. This eliminates inter-skill stopping points — all phases execute within one skill invocation. The final phase ensures the worktree's Docker environment is running for browser-based visual verification. (The skill maintains its own internal Phase 1-11 numbering — see `.claude/skills/post-plan/SKILL.md`.)

- After the implementation work is done (post–worktree-setup coding), invoke `/post-plan` and let it run to completion.
- Do NOT invoke `/simplify`, `/commit`, `/code-review:code-review`, or `/security-audit` separately during the post-plan workflow. `/post-plan` handles all of them internally using direct tool calls (Bash, Agent, Read, Edit).
- Those individual skills remain available for standalone use outside the workflow.
