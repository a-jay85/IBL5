---
description: Linear history — squash/rebase-merge only — path-scoped, loads only for post-plan/rebase surfaces. Read before diagnosing a "SHA not in master" result or rebasing a stacked branch after its parent merged.
last_verified: 2026-10-22
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

If you keep a backup branch before a rebase, a squash, or a reset, mint its name.
Never type one by hand:

```bash
source bin/lib/git-helpers.sh
name=$(wt_backup_branch_name <your-branch> pre-rebase) || exit 1
git branch "$name" <your-branch>
```

The name is `backup/<slug>/<YYYYMMDD-HHMMSS>-<reason>`, with reason `pre-rebase`, `presquash`, or `local`. If the helper fails because an older flat `backup/<slug>` branch exists, stop and report the name it printed. `refs/wt-sync-backup/*` is a separate ref namespace written by `bin/wt-sync-tick`; leave it alone.

Both `/post-plan` engines now merge `origin/master` into the branch (`git merge --no-edit`).
A merge sees the squash-merged parent's content on both sides and does not replay it, so the
trap above does not fire in Phase 2. It still fires for a hand-run `git rebase origin/master`.
When a Phase 2 conflict does occur, it is a real overlap with master, and what happens next
**differs by engine**. Establish which engine ran before you act.

**Harness** (the default, `tools/postplan-harness/`). `gitad.py` `rebase_onto` aborts the
merge when neither `harness/conflict.py` nor its escalation ladder can resolve it, raises `rebase-conflict`, and `runner.py` `exit_code_for` returns **3**.
`should_fallback` in `bin/post-plan-now` treats 3 as fail-closed and **does NOT escalate to a
`/post-plan` skill session**. The run stops there and a **human** resolves the branch by hand.
If that's you: run `git merge origin/master`, resolve three-way, commit, then re-run
`bin/post-plan-now --auto`.
When the stacked `--onto` fallback (`gitad.py` `_autoresolve_stacked_rebase`) finds a merge commit in the `iblBase..HEAD` range, it first collapses the range into one commit with the identical tree, parented on `merge-base(iblBase, HEAD)`, and it moves the branch back to the original tip on every decline.

**Skill** (`POST_PLAN_SKILL=1`): the shrunk skill has no Phase 2 rebase; conflict resolution is harness-only (`tools/postplan-harness/harness/conflict.py`, `conflict_ladder.py`).

No path spawns a skill session *because of* a conflict. Engine split and exit codes:
`.claude/rules/workflow-continuity-detail.md`, ADR-0092.
