---
description: This repo merges PRs by squash (or rebase) — never a merge commit. master is linear and a feature branch's commit SHAs never appear verbatim in it. Read before diagnosing a "SHA not in master" result or rebasing a stacked branch after its parent merged.
last_verified: 2026-07-23
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

The post-plan harness rebases with a plain `git rebase origin/master` (`gitad.py`
`rebase_onto`), which on such a branch replays the parent's now-duplicated commits →
conflict → it **fails closed** to a fresh `/post-plan` skill session. If you're that
fallback: this is the squash trap, not a broken branch — use the `--onto` form above.
