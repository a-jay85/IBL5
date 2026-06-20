---
description: All work happens in a git worktree; the main checkout (master) is reference/read-only and is never edited directly. Generalizes the automouse agent's "never modify files on master" rule to every session — interactive and headless, code and repo-meta (rules, docs, config, ADRs). Records why and the enforcement surfaces (auto-commit Stop hook flips to a warning in the main checkout).
last_verified: 2026-06-20
---

# ADR-0062: All work happens in a worktree; the main checkout is reference-only

**Status:** Accepted
**Date:** 2026-06-13

## Context

Worktrees were already the norm for plan-driven implementation
(`workflow-continuity.md` told you to `bin/wt-new` *before* implementation), but the
prohibition on editing the main checkout directly only existed in one place: the
automouse agent's prompt (`bin/automouse-prompt-impl`: "Work only in worktrees, never
modify files on the master branch directly"). Interactive sessions had no such rule.

In practice this left the main checkout (`/Users/ajaynicolas/GitHub/IBL5`, branch
`master`) treated as a valid place for "small" work — config tweaks, doc edits,
`.claude/rules` changes. The `auto-commit-reminder.sh` Stop hook reinforced this: it
*reminded you to commit* uncommitted changes in the main checkout. That is exactly
the behavior we no longer want.

Working on `master` directly is a footgun: commits land on the integration branch with
no PR, no CI gate, no review, and no isolation from a concurrent Claude instance (see
the `feedback_concurrent_instance_use_worktree` memory — main-checkout HEAD churn is a
known failure mode). The "it's just a one-line config change" exception is where the
discipline erodes.

## Decision

**All work happens in a worktree. The main checkout is reference/read-only and is never
edited directly — not code, not migrations, not docs, not `.claude/rules`, not config,
not ADRs.** No size or category exception.

- Every session that will edit a repo file first ensures it is in a worktree
  (`bin/wt-new <slug>`); the only reason to skip creation is that the worktree for this
  task already exists.
- The main checkout exists to: hold the canonical `master`, serve as the base for
  `bin/wt-new`, run main-stack Docker/DB tooling, and be read for reference. It is not
  an editing surface.
- The `auto-commit-reminder.sh` Stop hook **flips**: instead of reminding you to commit
  uncommitted changes in the main checkout, it now **warns** that work is happening in
  the wrong place and tells you to move it to a worktree. It is a warning, not a hard
  block (a human may have legitimate reasons to touch the main checkout briefly), per
  the user's explicit choice.

Files that physically live outside the repo tree — the Stop hook itself
(`~/.claude/hooks/`), Claude's per-project memory (`~/.claude/projects/.../memory/`),
and `~/.claude/settings*.json` — cannot be placed in a worktree and are edited directly
as before. This ADR governs **repo files only**.

## Consequences

- Every repo change flows through a worktree → branch → PR → CI, with no master-direct
  escape hatch. Review and CI gates apply uniformly.
- A trivial one-line repo-meta edit now carries worktree ceremony. Accepted: the cost is
  small (`bin/wt-new` needs no Docker for a markdown/config edit) and the eroded-discipline
  failure mode it prevents is worse.
- The auto-commit Stop hook no longer nudges-to-commit in the main checkout; an
  uncommitted main checkout is now a signal that work landed in the wrong place.
- Lineage: generalizes the automouse-only rule in `bin/automouse-prompt-impl` to all sessions;
  builds on ADR-0046 (worktree layout) and the `workflow-continuity.md` rule.
