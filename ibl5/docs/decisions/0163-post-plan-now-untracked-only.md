---
description: bin/post-plan-now counts untracked, non-ignored files as work to ship, so a worktree whose only change is a new file passes the nothing-to-ship guard.
last_verified: 2026-10-03
---
> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0163: Count untracked files in the post-plan-now nothing-to-ship guard

**Status:** Accepted
**Date:** 2026-10-03
**Deciders:** post-plan harness (auto-draft)

## Context

`bin/post-plan-now` exits 1 with `nothing to do` when the tree looks clean and the branch has no commits ahead of `origin/master`. The guard checked `git diff --quiet` and `git diff --cached --quiet`. Both ignore untracked files. A worktree whose only change was a new file, such as a new test, was refused as empty. The workflow rule tells sessions to leave the tree dirty and uncommitted before firing post-plan, so a new-file-only change had no way through. `bin/adr-check` flagged this PR because it adds `bin/test-post-plan-now-untracked-only`, a new `bin/` test script of more than 50 lines.

## Decision

The guard also requires `git ls-files --others --exclude-standard` to print nothing before it refuses. An untracked file that `.gitignore` does not match now counts as work, and the run proceeds. Ignored files still do not count. `bin/test-post-plan-now-untracked-only` builds a throwaway origin, main checkout, and worktree, then asserts three cases: a clean tree is refused, a tree holding only an ignored file is refused, and a tree holding only an untracked new file passes the guard. The test runs in CI from the meta-tests list in `.github/workflows/tests.yml`.

## Alternatives Considered

- **One porcelain check.** Replace the three checks with `git status --porcelain`. Rejected because: its untracked output obeys the `status.showUntrackedFiles` setting, so a user config could hide the same case again.
- **Stage first.** Run `git add -A` before the guard. Rejected because: it changes the index on a run that may still refuse, and a refused run should leave the tree as it found it.
- **Manual step.** Ask sessions to commit or `git add -N` new files first. Rejected because: the workflow rule requires a dirty, uncommitted tree for shipping, and a manual step is easy to forget.
- **Reuse the existing test.** Add the cases to `bin/test-post-plan-now-pr-creates-worktree`. Rejected because: that test covers PR creation from a worktree. The guard cases need their own fixture with a `.gitignore`.

## Consequences

- Positive: a change that only adds files ships through `bin/post-plan-now` with no extra step.
- Positive: build output and other ignored files still leave the guard closed.
- Negative: a stray untracked scratch file that `.gitignore` misses now lets the run proceed, and post-plan would commit it.
- Negative: one more test script in the CI meta-tests list.

## References

- `bin/post-plan-now`
- `bin/test-post-plan-now-untracked-only`
- `bin/test-post-plan-now-pr-creates-worktree`
- `.github/workflows/tests.yml`
- `.claude/rules/workflow-continuity.md`
