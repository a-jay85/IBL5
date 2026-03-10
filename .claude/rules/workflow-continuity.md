# Workflow Continuity Rule

Phases 3-9 of the post-plan workflow are consolidated into a single `/post-plan` skill. This eliminates inter-skill stopping points — all phases execute within one skill invocation. Phase 9 tears down the worktree and its Docker environment after a successful run.

- After Phase 2 (Implementation), invoke `/post-plan` and let it run to completion.
- Do NOT invoke `/simplify`, `/commit-commands:commit-push-pr`, `/code-review:code-review`, or `/security-audit` separately during the post-plan workflow. `/post-plan` handles all of them internally using direct tool calls (Bash, Agent, Read, Edit).
- Those individual skills remain available for standalone use outside the workflow.
