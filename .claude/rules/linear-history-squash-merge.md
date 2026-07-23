---
description: This repo merges PRs by squash (or rebase) — never a merge commit. master is linear and a feature branch's commit SHAs never appear verbatim in it. Read before diagnosing a "SHA not in master" result or rebasing a stacked branch after its parent merged.
last_verified: 2026-07-23
---

# Linear History — Squash/Rebase Merge Only

## The practice (ground truth)

GitHub merge settings: `mergeCommitAllowed: false`, `squashMergeAllowed: true`,
`rebaseMergeAllowed: true`. Every commit on `master` is a single squashed commit
titled `<type>(scope): summary (#NNNN)`; `git log origin/master --first-parent` is linear
with **no** merge commits.

**Consequence:** when a PR merges, its branch's individual commit SHAs are
**discarded** and replaced by one new squash commit with a **different** SHA. The
original SHAs never land in `master`.

## The diagnostic trap this prevents

`git branch --contains <sha>` / `git merge-base` showing a merged feature branch's
SHA **absent from master is the NORMAL squash artifact — not** a stale fetch, not
an unmerged parent, not a lost commit.

- ❌ Wrong reaction: `git fetch` again, assume the parent didn't merge, or replay
  the whole stack.
- ✅ Right reaction: confirm the change is in `master` **by content** (the files /
  diff are present), then treat the parent as merged.

## Rebasing a stacked branch after its parent merged

When a stacked PR's parent squash-merges, rebase **only your own commits** onto
the new master tip — do not replay the parent's already-merged work:

```bash
git fetch origin
git rebase --onto origin/master <parent-branch-tip-before-merge> <your-branch>
```

`<parent-branch-tip-before-merge>` is the last commit that belonged to the parent;
everything after it is yours. This drops the parent's commits (now squashed into
master) and replays only your delta. Expect a clean replay; conflicts here usually
mean the range is wrong.

## When you're the post-plan skill fallback

The compiled post-plan harness rebases with a plain `git rebase origin/master`
(`gitad.py` `rebase_onto`). For a stacked branch whose parent already squash-merged,
that replays the parent's now-duplicated commits → conflict → the harness **aborts
and fails closed**, handing conflict judgment to a fresh `/post-plan` skill session.
If you are that fallback: this is the squash trap, not a broken branch. Confirm the
parent's change is in `master` by content, then use the `--onto` rebase above.
