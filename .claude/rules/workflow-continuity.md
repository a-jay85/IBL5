---
description: All work happens in a worktree (never the main checkout); where plans live (~/claude-plans/<branch-slug>.md, outside the repo); worktree setup (hostname stub → worktree-hostname.md, squash-merge stub → linear-history-squash-merge.md); and post-plan handoff triggers. Engine internals: workflow-continuity-detail.md.
last_verified: 2026-07-25
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

For **any** verified-complete unit of work in a worktree — plan-driven **or ad-hoc** — when it has verified clean and only the mechanical push + open-PR remains, that ship step needs **no** confirmation prompt. Do **not** ask "want me to push and open the PR?". The global "confirm before outward-facing actions" default is **durably overridden here**: shipping verified-complete worktree work is pre-authorized. Final action:

```bash
bin/post-plan-now --auto
```

- **Do NOT commit first.** Leave the worktree **dirty** — `/post-plan` commits the uncommitted tree in Phase 2 and opens the PR. Committing here changes what it ships.
- **Only fire when verification passed.** If implementation did **not** verify clean (failing tests, unresolved blocker, you stopped to ask the user something), do **not** fire — leave the worktree dirty and hand off in prose. Turn-end ≠ done; that judgment is yours.

Engine (harness vs. Sonnet skill fallback), what `--auto`'s skip gate does, plan-blind ad-hoc runs, and where auto-merge is armed: `.claude/rules/workflow-continuity-detail.md`.
