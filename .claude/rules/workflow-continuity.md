---
description: All work happens in a worktree (never the main checkout); worktree setup and the implementation→/post-plan handoff (auto-fired in a detached fresh session) for multi-step work.
last_verified: 2026-06-13
---

# Workflow Continuity Rule

## All work happens in a worktree

**Never edit the main checkout (`/Users/ajaynicolas/GitHub/IBL5`, branch `master`) directly** — not code, not migrations, not docs, not `.claude/rules`, not config, not ADRs. No size or category exception (ADR-0062). The main checkout is reference/read-only: it holds canonical `master`, is the base for `bin/wt-new`, and runs main-stack Docker/DB tooling.

The only files exempt are those that physically live **outside** the repo tree and cannot go in a worktree: the Claude hooks (`~/.claude/hooks/`), per-project memory (`~/.claude/projects/.../memory/`), and `~/.claude/settings*.json`. Edit those in place as usual.

## Planning

Use `/plan <task description>` for implementation planning.

## Worktree Setup

Before touching any repo file, make sure you are in a worktree. Create one unless it already exists for this task:

```bash
bin/wt-new <slug>   # slug = kebab-case branch name derived from the plan
```

Use `--base <branch>` for stacked PRs. Work in `IBL5-worktrees/<slug>/ibl5/` (worktrees live outside the repo — ADR-0046). The only reason to skip creation is that the worktree for this task already exists (or the plan names an existing one) — never because "this edit is small enough to do on master."

## Post-Plan

Never run `/post-plan` **inline** in this session — it re-reads the full implementation context on every phase, so an inline run after a long implementation session (especially on Opus) costs several times a fresh run. It must run in a **fresh** session, with **cwd = this worktree**.

### Interactive sessions — auto-fire the handoff

When implementation is **verified complete**, your **final action** is:

```bash
bin/post-plan-now --auto
```

This spawns a detached, fresh **Sonnet 4.6** `/post-plan` on this branch (supervised by launchd, so it survives you closing Claude Code). It removes the manual "open a new session and hand off" step. Notes:

- **Do NOT commit first.** Leave the worktree **dirty** — `/post-plan` commits the uncommitted tree in its Phase 2 and opens the PR. Committing here would change what it ships.
- **Only fire when verification passed.** If implementation did **not** verify clean (failing tests, unresolved blocker, you stopped to ask the user something), do **not** run it — leave the worktree dirty and hand off in prose instead. Turn-end ≠ done; that judgment is yours.
- **No deadlock.** `/post-plan` resolves the plan from the branch slug (no handoff file needed); the detached child is reparented under launchd, so it clears its own plan-gate independently of this session.

`--auto` adds two safety gates (a bare `bin/post-plan-now` skips them — running it by hand IS the decision to ship): it **skips** when already inside a headless/nightly run (the nightly runner fires post-plan itself), and it **honors** a plan that declares `auto_postplan: false` — a plan judged risky enough (see `/plan` Step 4) to want a human eyeball before the PR opens and auto-merge arms. A skipped risky plan is shipped later with a reviewed bare `bin/post-plan-now`.
