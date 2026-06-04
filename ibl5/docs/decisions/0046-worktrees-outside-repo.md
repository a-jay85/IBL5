---
description: Git worktrees are created OUTSIDE the repo tree (a canonical-case sibling, IBL5-worktrees/<slug>) instead of nested at $REPO_ROOT/worktrees/<slug>. Nesting made the repo-root .claude/rules a filesystem ancestor of every worktree file, so Claude Code's path-conditional rule loader injected each matching rule twice. Records the layout decision, the git-based worktree detection it requires, and the safe migration path.
last_verified: 2026-06-04
---

# ADR-0046: Worktrees live outside the repo tree

**Status:** Accepted
**Date:** 2026-06-04

## Context

Worktrees were created at `$REPO_ROOT/worktrees/<slug>/` — **nested inside the repo**.
Claude Code injects path-conditional rules (a `.claude/rules/*.md` file with a
`paths:` frontmatter, e.g. `view-rendering.md` `paths:"**/*View.php"`) by walking the
**touched file's ancestor directories** for `.claude/rules/`. A nested worktree file
therefore had **two** matching `.claude/rules` ancestors — the worktree's own copy
**and** the repo root's — so every matching rule was injected **twice** (~15-18K tokens,
re-sent every turn).

This was verified empirically: an external worktree (sibling of the repo, so the repo
root is *not* an ancestor) loads each rule exactly once; rules never previously loaded
from the main-repo path appeared only from the worktree path. The mechanism is pure
ancestor-walk, **not** the registered-working-dir set — so `cd`, a fresh session, and
`EnterWorktree` cannot fix it; only removing the repo-root ancestor does.

A secondary defect rode along: because `bin/wt-new` derived the worktree path from the
launch-time `$REPO_ROOT`, launching from `~/github/IBL5` vs `~/GitHub/IBL5` (the same
inode on case-insensitive APFS) registered worktrees under inconsistent case, splitting
both `git worktree list` and Claude's path-keyed state.

## Decision

Create worktrees at `<parent-of-main-checkout>/IBL5-worktrees/<slug>` — a **canonical-case
sibling** of the repo, **outside** its tree. The path is derived (not hardcoded) by
`worktrees_parent_dir()` in `bin/lib/git-helpers.sh`, which resolves the canonical main
checkout via `resolve_canonical_root()` and normalizes the on-disk case via
`canonicalize_case()`, so the result is the same regardless of launch cwd.

"Am I in a worktree?" guards switch from the brittle `*/worktrees/*` path substring to a
layout-independent git check (`is_in_worktree`: a linked worktree's `--git-dir` differs
from its `--git-common-dir`). `cleanup`'s orphan-directory **sweep** stays directory-based
(it needs the root path), repointed at the external root.

Existing in-repo worktrees are relocated by `bin/wt-migrate-layout`, which gates each
worktree on the `bin/lib/wt-guards.sh` safety predicates (`is_worktree_in_use`,
`has_uncommitted_changes`, `has_untracked_files`) and **skips** any that are busy or dirty
— never a big-bang move. All worktree tooling continues to accept the legacy in-repo
location during the transition.

## Consequences

- Each worktree's `.claude/rules` is the only one on its ancestor chain → a single rule
  copy, while rules stay per-branch editable (the checkout still carries its own copy).
- New worktrees register under canonical case, ending the `github`/`GitHub` split.
- Worktrees are no longer visible inside the repo. The `.gitignore worktrees/` entry is
  retained as a guard against accidental re-nesting and to cover un-migrated worktrees.
- Docker is unaffected: `docker/worktree-compose.yml` mounts `${WORKTREE_PATH}` (exported
  by the caller), so only the value changes.
- Blast radius: `bin/wt-new`, `wt-up`, `wt-remove`, `wt-down`, `wt-list`, `wt-rebase`,
  `wt-db-test`, `db-test-up`, `e2e-wt.sh`, `cleanup`, `ibl5/bin/e2e-local.sh`, and
  `bin/lib/git-helpers.sh`. Local, non-repo follow-ups (vendor-repair hook in
  `.claude/settings.local.json`, permission globs in `~/.claude/settings.json`, stale
  `~/.claude.json` project keys) are documented in the PR, not in this diff.
