---
description: All work happens in a worktree (never the main checkout); where plans live (~/claude-plans/<branch-slug>.md, outside the repo); worktree setup (hostname stub → worktree-hostname.md, squash-merge stub → linear-history-squash-merge.md); and post-plan handoff triggers. Engine internals: workflow-continuity-detail.md.
last_verified: 2026-09-25
---

# Workflow Continuity Rule

## All work happens in a worktree

**Never edit the main checkout (`/Users/ajaynicolas/GitHub/IBL5`, branch `master`) directly** — not code, migrations, docs, `.claude/rules`, config, or ADRs. No size or category exception (ADR-0062). The main checkout is reference/read-only: it holds canonical `master`, is the base for `bin/wt-new`, and runs main-stack Docker/DB tooling.

Exempt — files that physically live **outside** the repo tree: Claude hooks (`~/.claude/hooks/`), per-project memory (`~/.claude/projects/.../memory/`), and `~/.claude/settings*.json`. Edit those in place.

## Planning

Use `/plan <task description>` for implementation planning.

**Plans live OUTSIDE the repo at `~/claude-plans/<branch-slug>.md`.** There is no `plans/` directory in the repo, so a repo-relative search finds nothing. The path is deterministic from the branch name — resolve it, don't search:

```bash
ls ~/claude-plans/"$(git rev-parse --abbrev-ref HEAD)".md
```

This is exactly how `bin/post-plan-now` resolves the plan for a branch. `~/claude-plans/` is the single source of truth — no other directory holds plan files.

**Never `Read` a plan whole** (58-320 KB = 15-80K tokens). Index it, then read by range: `bin/plan-index <path>` prints each `## ` section's start/end line; `sed -n 'START,ENDp'` the ones you need.

## Worktree Setup

Before touching any repo file, be in a worktree. Create one unless it already exists for this task:

```bash
bin/wt-new <slug>   # slug = kebab-case branch name derived from the plan
```

Use `--base <branch>` for stacked PRs. Work in `IBL5-worktrees/<slug>/ibl5/` (worktrees live outside the repo — ADR-0046). Skip creation only when this task's worktree already exists (or the plan names one) — never because "this edit is small enough for master."

That worktree's Docker hostname is `<slug>.localhost`, where slug = `basename "$(git rev-parse --show-toplevel)"` — derive it, never hardcode one from a previous worktree, never use `main.localhost` from a worktree, and always navigate `/ibl5/` paths, never bare `/`. Detail: `.claude/rules/worktree-hostname.md`.

`master` is linear (squash/rebase-merge only), so a merged branch's SHAs never land in it — `git branch --contains` showing a merged SHA absent from `master` is the **normal squash artifact**, not a stale fetch or a lost commit; confirm by content instead. Before rebasing a stacked branch whose parent merged: `.claude/rules/linear-history-squash-merge.md`.

## Post-Plan

Never run `/post-plan` **inline** — it re-reads full implementation context every phase, so an inline run after a long session (especially Opus) costs several times a fresh run. Run it in a **fresh** session, cwd = this worktree.

**Plan-driven work** (session has a `/plan`): when verified clean, fire `bin/post-plan-now --auto` with no confirmation prompt. The "confirm before outward-facing actions" default is **durably overridden** for plan-driven work. Shipping is pre-authorized.

**Ad-hoc work** (no plan): if this session created the worktree, shipping is pre-authorized. If it already existed, hold: when verified clean, commit with `/commit-commands:commit`, don't fire post-plan, and end with `cd <abs worktree path> && bin/post-plan-now` to paste. It ships only when the user arms the branch (never arm it or suggest arming) or says ship. A skill ending in shipping is the instruction. To ship, fire `bin/post-plan-now --auto` on the dirty tree:

```bash
bin/post-plan-now --auto
```

- **Do NOT commit first.** Leave the worktree **dirty**. `/post-plan` commits the uncommitted tree in Phase 2 and opens the PR. Committing here changes what it ships.
- **Only fire when verification passed.** If implementation did **not** verify clean (failing tests, unresolved blocker, you stopped to ask the user something), do **not** fire. Leave the worktree dirty and hand off in prose. Turn-end is not done; that judgment is yours.

Engine (harness vs. Sonnet skill fallback), what `--auto`'s skip gate does, plan-blind ad-hoc runs, and where auto-merge is armed: `.claude/rules/workflow-continuity-detail.md`.
