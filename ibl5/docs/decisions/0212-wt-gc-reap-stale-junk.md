---
description: wt-gc-tick gains six kill-switched reap steps (old EMPTY worktrees, merged worktrees with master-identical dirt, aged refs, tmp_pack files, Docker image and build cache) plus a stale-worktree list for the morning digest.
last_verified: 2026-10-22
---

# ADR-0212: wt-gc-tick reaps stale junk and surfaces stuck worktrees

**Status:** Accepted. Supersedes one Alternatives Considered entry of ADR-0165 (the "never reap EMPTY" rejection) for worktrees older than 7 days.
**Date:** 2026-10-22
**Deciders:** A-Jay

## Context

ADR-0165 reaps proven-merged worktrees hourly. It rejected reaping EMPTY worktrees because a fresh `bin/wt-new` is EMPTY for its first minutes. The 2026-10-22 branch and worktree audit found the junk that sweep leaves behind:

- EMPTY worktrees weeks old.
- Merged worktrees skipped as dirty whose only dirt is content already on master.
- `backup/*` branches whose PR merged long ago.
- `refs/tmp` and `refs/wt-sync-backup` snapshots.
- 3,383 refs under a remote that no longer exists. `fetch --prune` never touches a remote that is not configured.
- Aborted-fetch `tmp_pack_*` files.
- About 22 GB of prunable Docker images.

Each class was cleaned by hand once. Nothing prevented it from coming back.

## Decision

`bin/wt-gc-tick` runs six steps after the unchanged `bin/cleanup --all`. The logic lives in the sourced `bin/lib/wt-gc-reap.sh`. Each step has its own `WT_GC_*` kill switch and runs in a subshell. A failing step logs, sets exit 1 for `bin/launchd-health-check`, and withholds the sweep stamp. The remaining steps still run.

1. **EMPTY worktrees.** Reaped only when older than `WT_GC_EMPTY_DAYS` (7) and re-proven at reap time. The checks are `rev-list --count origin/master..HEAD` equal to 0, a clean tracked tree, and no live-work signal. Live-work signals come from the automouse queue, loaded launchd labels, the session registry, and `is_worktree_in_use`. The re-check closes the race that ADR-0165 named.
2. **Merged-but-dirty worktrees.** Reaped only when cleanup named the worktree as dirty-skipped, the PR is MERGED at the branch tip, and every dirty path is byte-identical to `origin/master`.
3. **Refs.** Deleted only from a literal prefix whitelist. The `<sha> <ref>` line must first be in a dated tips file. The delete is `git update-ref -d <ref> <sha>`, so a ref that moved is kept.
4. **`tmp_pack_*` files** older than a day that no process holds open.
5. **Docker** `image prune -af` and `builder prune -f`, filtered to 168 hours. Volumes are never pruned.
6. **Stale list.** STALLED and UNPUSHED worktrees older than 7 days go to a file. `bin/automouse/morning-digest` renders it.

`refs/t3/*`, `refs/remotes/origin/*`, protected and checked-out branches, pool worktrees, and Docker volumes are never touched. STALLED and UNPUSHED worktrees are only listed. Every probe that gates a delete keeps on error (`.claude/rules/shell-fail-open-probe.md`). Every destructive command passes through one `_gc_do` choke point, so `--dry-run` only logs. `bin/test-wt-gc-tick` covers each step against a real git fixture.

## Alternatives Considered

- **A new `bin/cleanup` subcommand.** Rejected because cleanup's merged-reap contract and its 923 lines stay untouched, and the reap predicates need the tick's stub seams for testing.
- **A standalone `bin/wt-gc-reap` (example) script.** Rejected under `.claude/rules/meta-tooling-bar.md`. The tick already owns this surface. A sourced library keeps one driver.
- **`git gc --prune=now`.** Rejected because live sessions write objects.

## Consequences

- Positive: each junk class is removed on the next idle tick after it ages out, with no manual pass.
- Positive: every reaped branch and ref tip is recorded in a dated tips file, so a mistaken delete is recoverable with `git update-ref`.
- Positive: stuck STALLED and UNPUSHED worktrees reach the operator each morning.
- Negative: a persistent step failure (for example a Docker prune error) exits 1 every idle hour until fixed. The health check stays loud on purpose.
- Negative: any slug-less `plan-now` or `pr-cycle` launchd job defers every worktree reap for that sweep.

## Lineage

ADR-0165 rejected reaping EMPTY worktrees. That rejection now holds only for worktrees younger than `WT_GC_EMPTY_DAYS`. Older ones are reaped under the re-verified predicate above.

## References

- `bin/wt-gc-tick`: the scheduled driver and its six `run_step` calls.
- `bin/lib/wt-gc-reap.sh`: the reap predicates and the `_gc_do` choke point.
- `bin/test-wt-gc-tick`: regression harness for every step.
- `bin/automouse/morning-digest`: renders the stale-worktree list.
- `ibl5/docs/decisions/0165-scheduled-merged-worktree-gc.md`: the sweep this extends.
