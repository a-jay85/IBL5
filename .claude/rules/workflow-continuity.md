# Workflow Continuity Rule

## Phase 1: Worktree Setup

Before implementation, create a worktree unless one already exists for this task:

```bash
bin/wt-new <slug>   # slug = kebab-case branch name derived from the plan
```

Use `--base <branch>` for stacked PRs. Work in `worktrees/<slug>/ibl5/`. Skip if already inside a worktree or the plan specifies an existing one.

## Phases 3-9: Post-Plan

Phases 3-9 of the post-plan workflow are consolidated into a single `/post-plan` skill. This eliminates inter-skill stopping points — all phases execute within one skill invocation. Phase 9 ensures the worktree's Docker environment is running for browser-based visual verification.

- After Phase 2 (Implementation), invoke `/post-plan` and let it run to completion.
- Do NOT invoke `/simplify`, `/commit-commands:commit-push-pr`, `/code-review:code-review`, or `/security-audit` separately during the post-plan workflow. `/post-plan` handles all of them internally using direct tool calls (Bash, Agent, Read, Edit).
- Those individual skills remain available for standalone use outside the workflow.
