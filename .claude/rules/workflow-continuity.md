---
description: All work happens in a worktree (never the main checkout); worktree setup and the implementation→/post-plan handoff (auto-fired in a detached fresh session) for any verified-complete worktree work, plan-driven or ad-hoc.
last_verified: 2026-07-04
---

# Workflow Continuity Rule

## All work happens in a worktree

**Never edit the main checkout (`/Users/ajaynicolas/GitHub/IBL5`, branch `master`) directly** — not code, migrations, docs, `.claude/rules`, config, or ADRs. No size or category exception (ADR-0062). The main checkout is reference/read-only: it holds canonical `master`, is the base for `bin/wt-new`, and runs main-stack Docker/DB tooling.

Exempt — files that physically live **outside** the repo tree: Claude hooks (`~/.claude/hooks/`), per-project memory (`~/.claude/projects/.../memory/`), and `~/.claude/settings*.json`. Edit those in place.

## Planning

Use `/plan <task description>` for implementation planning.

## Worktree Setup

Before touching any repo file, be in a worktree. Create one unless it already exists for this task:

```bash
bin/wt-new <slug>   # slug = kebab-case branch name derived from the plan
```

Use `--base <branch>` for stacked PRs. Work in `IBL5-worktrees/<slug>/ibl5/` (worktrees live outside the repo — ADR-0046). Skip creation only when this task's worktree already exists (or the plan names one) — never because "this edit is small enough for master."

## Post-Plan

Never run `/post-plan` **inline** — it re-reads full implementation context every phase, so an inline run after a long session (especially Opus) costs several times a fresh run. Run it in a **fresh** session, cwd = this worktree.

### Interactive sessions — auto-fire the handoff

For **any** verified-complete unit of work in a worktree — plan-driven **or ad-hoc**. When it has verified clean and only the mechanical push + open-PR remains, that ship step needs **no** confirmation prompt — do **not** ask "want me to push and open the PR?". The global "confirm before outward-facing actions" default is **durably overridden here**: shipping verified-complete worktree work is pre-authorized. Final action:

```bash
bin/post-plan-now --auto
```

(Ad-hoc branch with no plan file → post-plan runs plan-blind; expected, not an error. The merge-arming decision still happens at `/post-plan` Phase 6.5, so auto-opening the PR never auto-merges a `feat:`/`auto_merge: false`/visual PR without human signoff.)

This spawns a detached, fresh **Sonnet 4.6** `/post-plan` on this branch (launchd-supervised, so it survives you closing Claude Code). Notes:

- **Do NOT commit first.** Leave the worktree **dirty** — `/post-plan` commits the uncommitted tree in Phase 2 and opens the PR. Committing here changes what it ships.
- **Only fire when verification passed.** If implementation did **not** verify clean (failing tests, unresolved blocker, you stopped to ask the user something), do **not** fire — leave the worktree dirty and hand off in prose. Turn-end ≠ done; that judgment is yours.
- **No deadlock.** `/post-plan` resolves the plan from the branch slug (no handoff file needed); the detached child reparents under launchd and clears its own plan-gate independently.

`--auto` adds one safety gate (a bare `bin/post-plan-now` skips it — running it by hand IS the decision to ship): it **skips** when already inside a headless/automouse run (the automouse runner fires post-plan itself). It no longer holds post-plan for a "risky" plan — post-plan **always** runs and opens the PR. Whether the PR then **auto-merges** is decided at `/post-plan` Phase 6.5, which honors a plan's `auto_merge: false` (see `/plan` Step 4) and otherwise leaves the PR open for a human to merge.
