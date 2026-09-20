---
description: On-demand single-worktree sync via --only flag on bin/wt-sync-tick, bypassing idle and in-use gates.
last_verified: 2026-09-20
---

# ADR-0133: On-Demand Worktree Sync via `--only`

**Status:** Accepted
**Date:** 2026-09-20
**Deciders:** A-Jay

## Context

ADR-0106's `bin/wt-sync-tick` syncs all local worktrees on a 900-second poll. The poll is correct for an unattended fleet and wrong for the moment an operator opens one worktree: the HID-idle presence gate refuses to run while they are at the keyboard, the in-use gate refuses because their shell is in the tree, and the 900-second interval means a resumed worktree can be a quarter-hour stale at session open.

The two gates exist for good reasons in fleet mode. They are the wrong policy for a targeted, operator-visible sync.

## Decision

`bin/wt-sync-tick` gains a `--only <path>` flag. When set, the script enters on-demand mode:

- The HID-idle presence gate and the in-use gate are skipped.
- The script waits up to `WT_SYNC_ONDEMAND_LOCK_WAIT` seconds (default 3) for the fleet lock to be free, then proceeds without acquiring it. It never takes the lock.
- Fetch is bounded by `WT_SYNC_FETCH_TIMEOUT` seconds (default 10).
- One verdict from a closed nine-token set is printed to stdout: `CURRENT`, `SYNCED <n>`, `MERGED`, `AHEAD-ONLY`, `BEHIND-DIRTY`, `DIVERGED-DIRTY`, `DIVERGED-CONFLICT`, `NO-REMOTE`, `FETCH-FAILED`.
- Exit code is 0 on any verdict; 2 on invalid input (path is not a worktree directory or HEAD is detached).

A `bin/hooks/wt-sync-session-start.sh` hook invokes `--only` on the current worktree when Claude Code opens a session. `bin/wt-sync-cron-setup --install-hook` installs it as a symlink in `~/.claude/hooks/` and registers it in `~/.claude/settings.json`.

### Named exception to ADR-0106's never-list

ADR-0106 forbids non-fast-forward writes. `--only` mode permits exactly one: `git merge origin/<branch>` when the worktree is both diverged from origin and clean of uncommitted and untracked changes. The merge is aborted if it produces a conflict; the verdict is `DIVERGED-CONFLICT` and the tree is left clean. Every other entry on ADR-0106's never-list stands unchanged in both modes.

### Why cron stays fast-forward-only

A merge commit written by an unattended cron tick appears in a tree nobody is watching. A merge written at session open appears in the session's first output lines, where the operator sees it. On-demand merging puts the result in front of the operator immediately; cron merging produces a surprise they find later. `STRAGGLER` log entries written by the fleet tick are resolved on demand rather than in the background.

### Why extend the existing script

One implementation of the ahead/behind analysis, the env seams, and the test harness. A separate `bin/wt-sync-now` (example) sibling would duplicate the `rev-list --left-right` idiom and its inverted-ordering trap, and would add a governance surface that `.claude/rules/meta-tooling-bar.md` asks to avoid when an owner already exists.

## Alternatives Considered

- **A separate `bin/wt-sync-now` (example) sibling.** Rejected; see above.
- **Skip the lock wait entirely.** Rejected. Without the wait, an on-demand sync during a fleet-lock window could fetch while the fleet tick is mid-write, producing a stale or conflicted index. A 3-second bounded wait is unnoticeable at session open and closes the race for typical fleet-tick durations.
- **Take the fleet lock during on-demand.** Rejected. A crashed session leaves an orphaned lock that stalls the cron for up to 15 minutes. The per-worktree write is already arbitrated by git's own index lock.

## Consequences

- Positive: Worktrees are current at the moment a session opens. The fleet poll can delay sync by up to 900 seconds.
- Positive: Diverged-but-clean worktrees are automatically merged forward, with the result visible in session context.
- Positive: `--only` reuses the fleet driver's fetch, ahead/behind analysis, and env seams; no duplicate logic.
- Negative: The `--dry-run` behind-only path cannot see an untracked-file collision and reports `SYNCED <n>` where a live run reports `BEHIND-DIRTY`.
- Negative: The hook fires on every session open, including quick inspections. Fetching on every open adds a brief network round-trip. The 10-second fetch timeout and 3-second lock wait bound the worst case.

## References

- `bin/wt-sync-tick`: the fleet driver; `--only` mode is dispatched from the same entry point
- `bin/hooks/wt-sync-session-start.sh`: the SessionStart hook
- `bin/wt-sync-cron-setup`: extended with `--install-hook`, `--uninstall-hook`, `--print-hook`
- `bin/test-wt-sync-tick`: regression harness covering both modes
- `ibl5/docs/decisions/0106-local-worktree-sync-fast-forward.md`: the fleet-mode ADR this extends
- `ibl5/docs/decisions/0046-worktrees-outside-repo.md`: worktree layout
