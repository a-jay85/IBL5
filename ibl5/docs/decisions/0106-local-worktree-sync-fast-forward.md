---
description: Local worktree sync driver — fast-forward-only, HID-idle-gated, straggler-logging.
last_verified: 2026-08-20
---

# ADR-0106: Local Worktree Sync via Fast-Forward Only

**Status:** Accepted
**Date:** 2026-08-20
**Deciders:** A-Jay

## Context

The cloud `update-behind-prs.yml` workflow (PR #1924) keeps open PRs current with `master` by pushing merge commits to each branch via the GitHub API. Local worktrees therefore fall behind their `origin/<branch>` counterparts on every workflow run. Developers coming back to a worktree after time away must manually `git pull` each one, or they work on stale code. A local background poller that fast-forwards idle worktrees would eliminate this friction without touching anything the operator is actively working on.

## Decision

`bin/wt-sync-tick` is a poll-only bash driver, fired every 900 seconds by a launchd LaunchAgent installed via `bin/wt-sync-cron-setup`. It may only fast-forward (`git merge --ff-only`). It never pushes, never rebases, never resets, never calls `gh`, and never creates or deletes worktrees. Three non-negotiable gates guard every worktree before it is touched:

1. **HID-idle presence gate.** `ioreg -c IOHIDSystem` is read to obtain the system HID idle time. If the value is missing, non-numeric, or below `WT_SYNC_IDLE_SECS` (default 600 s), the tick exits 0 without touching anything. This is fail-closed: an unreadable idle value is treated as operator present.
2. **Ahead-of-origin skip.** If `git rev-list --left-right --count "origin/<branch>...HEAD"` shows any commits in HEAD not in `origin/<branch>` (i.e., ahead > 0), the worktree is skipped and logged as a STRAGGLER with reason `ahead`. This gate is load-bearing: the cloud workflow owns `origin/<branch>` via merge commits, and a local rebase or reset on a branch with unpushed commits would diverge from the cloud's canonical history, creating conflicts.
3. **Dirty/in-use skips.** Uncommitted or staged changes (`git diff`/`git diff --cached`) and active lsof-detected CWD processes skip the worktree without touching it.

Failed fast-forward attempts (e.g., an untracked working-tree file that the incoming commit would overwrite) are counted as `cannot-ff` and logged as STRAGGLER with reason `cannot-ff`. The straggler log (appended to `~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/wt-sync/wt-sync.log`) is the evidence base for deciding later whether a smarter conflict-resolution tool is worth building.

Lock/log namespacing is `wt-sync/` under `~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/`, entirely separate from bug-pipeline's namespace, so the two launchd agents cannot deadlock.

## Alternatives Considered

- **Rebase instead of ff-only** — rejected because the cloud workflow pushes merge commits to `origin/<branch>`, so a local rebase rewrites history that is already referenced on the remote, causing divergence on the next pull. Fast-forward-only is the only operation that preserves identity with origin.
- **Push local branches to keep them current** — rejected because this driver is a local quality-of-life tool with no credentials, no network writes, and strictly read-then-fast-forward semantics. Pushing is entirely out of scope.
- **Run under a timer trigger (calendar interval) instead of StartInterval** — rejected because calendar intervals fire at a wall-clock time and miss intervals when the machine is asleep. `StartInterval` fires 900 s after the last completion, which is correct for a background sync poll.

## Consequences

- Positive: Worktrees that are behind origin and idle are silently brought up to date, so developers return to current code without a manual pull.
- Positive: Fail-closed on unknown idle state means the sync never fires while the operator is at the keyboard.
- Positive: The straggler log accumulates evidence for the `ahead` and `cannot-ff` cases without requiring any action from the developer.
- Negative: A worktree that is genuinely idle but has an untracked file conflicting with the incoming commit is logged as STRAGGLER but not resolved automatically. Manual intervention is still required in that case.
- Negative: The presence gate requires `ioreg -c IOHIDSystem` (macOS only). This tool is not available on Linux; the driver is Mac-only by design (same as the launchd cron).

## References

- `bin/wt-sync-tick` — the poll-only driver
- `bin/wt-sync-cron-setup` — launchd LaunchAgent installer (StartInterval 900 s, label `com.ibl5.wt-sync`)
- `bin/test-wt-sync-tick` — regression harness (12 test cases)
- `bin/lib/wt-guards.sh` — `is_worktree_in_use`, `has_uncommitted_changes`
- `bin/lib/git-helpers.sh` — `worktrees_parent_dir`
- `bin/wt-rebase` — sibling script (rebase-onto-master); read for worktree enumeration pattern
- `bin/bug-pipeline-cron-setup` — launchd installer pattern this mirrors
- `.github/workflows/update-behind-prs.yml` — the cloud counterpart that keeps `origin/<branch>` current (PR #1924)
- `ibl5/docs/decisions/0046-worktrees-outside-repo.md` — worktree layout this driver navigates
