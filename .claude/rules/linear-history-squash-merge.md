---
description: Linear history — squash/rebase-merge only — path-scoped, loads only for post-plan/rebase surfaces. Read before diagnosing a "SHA not in master" result or rebasing a stacked branch after its parent merged.
last_verified: 2026-09-17
paths:
  - ".claude/skills/post-plan/SKILL.md"
  - "tools/postplan-harness/**"
---

# Linear History — Squash/Rebase Merge Only

## The practice (ground truth)

This repo squash/rebase-merges and **disallows merge commits**, so `master` is linear
(`git log origin/master --first-parent`, no merge commits). When a PR merges, its
branch's commits are **replaced by one new squash commit with a different SHA** — the
original SHAs never land in `master`.

## The diagnostic trap this prevents

`git branch --contains <sha>` / `git merge-base` showing a merged branch's SHA
**absent from master is the NORMAL squash artifact** — not a stale fetch, not an
unmerged parent, not a lost commit. Don't re-`fetch` or assume the parent didn't
merge: confirm the change is in `master` **by content** (files/diff present), then
treat the parent as merged.

## Rebasing a stacked branch after its parent merged

Replay **only your own commits** onto the new master tip — not the parent's
already-squashed work:

```bash
git rebase --onto origin/master <parent-tip-before-merge> <your-branch>
```

`<parent-tip-before-merge>` is the last commit that belonged to the parent;
everything after it is yours. Expect a clean replay; conflicts here usually mean the
range is wrong.

Both `/post-plan` engines rebase with a plain `git rebase origin/master`, which on such a
branch replays the parent's now-duplicated commits → conflict. What happens next **differs
by engine**. Establish which engine ran before you act.

**Harness** (the default, `tools/postplan-harness/`). `gitad.py` `rebase_onto` aborts the
rebase, raises `rebase-conflict`, and `runner.py` `exit_code_for` returns **3**.
`should_fallback` in `bin/post-plan-now` treats 3 as fail-closed and **does NOT escalate to a
`/post-plan` skill session**. The run stops there and a **human** resolves the branch by hand.
If that's you: this is the squash trap. Use the `--onto` form above, then re-run
`bin/post-plan-now --auto`.

**Skill** (`POST_PLAN_SKILL=1`, or the harness is absent). Phase 2 prints `STOP-AND-RESOLVE:`
and the run **continues** into `.claude/skills/post-plan/_phase-2-conflict-resolution.md`,
which re-runs the rebase in the `--onto` form, resolves three-way, and proves
`TREE-EQUIVALENT` before any push. A resolved conflict holds auto-merge at Phase 6.5 condition (14) until step 7.5
of `_phase-2-conflict-resolution.md` clears the resolution for the current `HEAD`.

No path spawns a skill session *because of* a conflict. Engine split and exit codes:
`.claude/rules/workflow-continuity-detail.md`, ADR-0092.
