---
description: Markdown docs whose only conflict is the frontmatter last_verified date merge through bin/lib/merge-last-verified, which keeps the newer date. Registration is per clone and its absence degrades to git's plain conflict.
last_verified: 2026-10-21
---

# ADR-0203: Merge driver for last_verified-only conflicts

**Status:** Accepted
**Date:** 2026-10-19
**Deciders:** a-jay85

## Context

The on-touch rule (`.claude/rules/doc-freshness.md`) makes every doc edit bump `last_verified`. Two branches that touch the same doc therefore always conflict on that frontmatter line, even when their body edits are disjoint. PR [#3262](https://github.com/a-jay85/IBL5/pull/3262) failed post-plan with `rebase-conflict: resolver declined` on `.claude/skills/plan/_architect-contract-detail.md`, and the only conflicted hunk in it and in `_architect-contract.md` was that date. PR [#3267](https://github.com/a-jay85/IBL5/pull/3267) hit the same shape in `.claude/skills/post-plan/SKILL.md`.

## Decision

`.gitattributes` assigns `merge=lastverified` to the markdown trees that carry the field. The ADR index line `ibl5/docs/decisions/README.md merge=union` stays last, so it keeps winning for that file.

`bin/lib/merge-last-verified` runs the whole 3-way merge. It rewrites the first-frontmatter `last_verified` date of all three versions to one target (the changed side when only one side changed it, else the newer date), runs `git merge-file` on those copies, and accepts the result only when it is clean. Otherwise it reruns `git merge-file` on the originals and exits 1, leaving standard markers.

`register_last_verified_merge_driver` in `bin/lib/git-helpers.sh` writes `merge.lastverified.driver` into the common `.git/config` with the main checkout's absolute driver path. `bin/install-git-hooks` calls it once per clone and `bin/wt-new` calls it on every worktree creation. Worktrees share that config, so the post-plan harness merge inside a PR worktree picks it up.

A clone without the registration, or with a stale or missing driver path, falls back to git's plain text merge and gets an ordinary conflict. Git 2.55 behaves this way for `merge`, `rebase`, `cherry-pick` and `merge-tree --write-tree`, and `bin/test-merge-last-verified` pins it.

## Alternatives Considered

- `merge=union` on the docs. Union keeps both date lines and duplicates any line both sides changed. Rejected because it corrupts the frontmatter.
- A date-only rule in the post-plan conflict resolver. It covers only harness runs. Rejected because manual rebases, `bin/wt-rebase` and `git merge-tree` predictions would still conflict.
- Registration from `bin/pre-commit-hook`. A config write at commit time runs on the wrong trigger and fires on every commit. Rejected for that reason.
- Extending an existing tool (meta-tooling bar). No existing script owns git content merges, the trigger (git's per-file merge) is distinct from every hook and gate, and two PRs in one week failed on it.

## Consequences

- Positive: date-only conflicts resolve without a human or a resolver rung. Mixed conflicts keep standard markers, labelled `ours:<path>` / `base:<path>` / `theirs:<path>`.
- Negative: git does not pass `-X ours`, `-X theirs` or `-X ignore-space-change` to a custom merge driver. A favor merge or rebase that conflicts on a `merge=lastverified` path stops with markers, where plain git would resolve it. No script in this repo passes `-X` today. For one command, swap in a favoring driver (`--ours` for `-X ours`):

  ```bash
  drv='git merge-file --theirs --marker-size=%L %A %O %B'
  git -c merge.lastverified.driver="$drv" rebase -X theirs <base>
  ```

  An empty driver value is unsafe, because git then reports the conflict but leaves our version with no markers. For `-X ignore-space-change`, add `*.md merge=text` to `$(git rev-parse --git-common-dir)/info/attributes` for the command and remove it afterwards. Every worktree sees that file while it exists.
- Negative: the driver runs from the main checkout's working tree, so a stale main checkout runs an older driver version. The contract is small and the fallback is a plain conflict, so this is acceptable.
- Negative: a new clone has no driver until `bin/install-git-hooks` or `bin/wt-new` runs. Until then it behaves like plain git.
- Out of scope: duplicate `last_verified` lines that `merge=union` can produce in the ADR index.

## References

- `bin/lib/merge-last-verified` is the driver.
- `bin/lib/git-helpers.sh` holds `register_last_verified_merge_driver`.
- `bin/test-merge-last-verified` is the harness that pins the contract.
- `.gitattributes` carries the scoped `merge=lastverified` block.
- `.claude/rules/doc-freshness.md` is the on-touch rule that creates the conflicts.
