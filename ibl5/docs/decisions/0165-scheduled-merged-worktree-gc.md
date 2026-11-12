---
description: Scheduled launchd sweep that reaps merged worktrees via bin/cleanup --all and only surfaces stalled, unpushed and empty ones. The EMPTY rejection is superseded in part by ADR-0212. Safe-class reaping with archive-first restore added 2026-11-01.
last_verified: 2026-11-11
---

# ADR-0165: Scheduled merged-worktree GC

**Status:** Accepted
**Date:** 2026-10-03
**Deciders:** A-Jay

## Context

Merged worktrees pile up between production merges. `bin/cleanup --all` already reaps
only worktrees proven merged (PR MERGED, deleted remote that is an ancestor of master,
or own commits landed on master), skips in-use and dirty directories, and surfaces
stalled and unpushed work. Its only caller was `bin/merge-master-to-prod`, so the sweep
ran only on a prod merge. On 2026-10-03 the main checkout held 60 worktree directories
and 53 containers; a dry run found 17 stalled or unpushed worktrees that nothing
reported on a schedule. Backlog issue a-jay85/IBL5-backlog#25 asked for a scheduled GC.

## Decision

`bin/wt-gc-tick` runs hourly under the LaunchAgent `com.ibl5.wt-gc`, installed by
`bin/wt-gc-cron-setup --install-schedule`. It copies the `bin/wt-sync-tick` gates
(ADR-0106): a `WT_GC_ENABLED=0` kill switch, a fail-closed HID-idle gate (1800 s), a
single-flight PID lock, and `--dry-run` / `--force`. It adds a 6 h minimum interval
between real sweeps. Each sweep calls `bin/cleanup --all` with no destructive flag:
`--discard-generated`, `--discard-closed` and `--drop-merged-stashes` stay manual
opt-ins, because their own comments say a wrong merged verdict would destroy work.
The tick then runs `bin/wt-status` and logs STALLED and UNPUSHED worktrees by name
plus counts of STALLED, UNPUSHED, EMPTY, OPEN-PR and MERGED rows. It never reaps
EMPTY worktrees: a worktree that `bin/wt-new` created minutes ago has no commits and
classifies as EMPTY, and a timed sweep would race the session about to use it. The
label is registered in `bin/lib/launchd-expected-jobs.sh`, so
`bin/launchd-health-check` alerts when the job is missing or its last run failed.

## Alternatives Considered

- **Reap EMPTY worktrees too.** Rejected because it races fresh `bin/wt-new` scaffolds. A new worktree is EMPTY until its first commit, and an hourly sweep could delete it just before a session starts using it.
- **Pass the destructive opt-ins on a schedule.** Rejected because an unattended run would lose data on a wrong merged verdict.
- **Fold the sweep into `bin/wt-sync-tick`.** Rejected because that tick runs every 900 s with a zero-write cost guard. A sweep makes many `gh` calls and deletes directories, so mixing them breaks the tick's single responsibility and its ADR-0106 contract.
- **A daily `StartCalendarInterval`.** Rejected because a slot missed during sleep fires on wake while the operator is present, so the idle gate would skip it.

## Consequences

- Positive: Merged worktrees are reaped on the host without waiting for a prod merge.
- Positive: Stalled and unpushed worktrees show up by name in `wt-gc.log` on a schedule.
- Negative: The health check reports `missing:com.ibl5.wt-gc` on the host until `bin/wt-gc-cron-setup --install-schedule` runs after merge. That alert is the install reminder.
- Negative: Abandoned EMPTY scaffolds linger. The log reports their count, and the operator removes them by hand with `bin/cleanup <name>`.
- Negative: The tick parses the `Summary: N cleaned, M skipped` line of `bin/cleanup`. A format change makes the tick exit 1, which the health check reports.

## References

- `bin/wt-gc-tick`: the scheduled sweep driver.
- `bin/wt-gc-cron-setup`: launchd LaunchAgent installer (StartInterval 3600 s, label `com.ibl5.wt-gc`).
- `bin/test-wt-gc-tick`: regression harness for the tick, the emitter and worktree survival.
- `bin/cleanup`: the unchanged sweep the tick calls.
- `bin/wt-status`: the read-only classifier the tick counts.
- `bin/lib/launchd-expected-jobs.sh`: health-check registry that lists the label.
- `ibl5/docs/decisions/0106-local-worktree-sync-fast-forward.md`: the gate set this driver copies.

## Addendum 2026-10-22: Superseded in part by ADR-0212

The Alternatives Considered entry that rejected reaping EMPTY worktrees is superseded for worktrees older than 7 days. ADR-0212 reaps them only after re-proving at reap time that they have no own commits, a clean tracked tree, and no live-work signal. EMPTY worktrees younger than 7 days are still only surfaced.

**Superseded by:** `ibl5/docs/decisions/0212-wt-gc-reap-stale-junk.md`

## Addendum 2026-11-01: Archive-first reaping of safe classes

The tick now removes six more classes of leftover state. Each removal is preceded by an archive, and each class has its own switch and age floor. ADR-0212 still governs the empty-worktree and stale-ref steps. This addendum extends them.

| Class | Switch | Age var (default) | Archive |
|---|---|---|---|
| Merged worktree with dirt | `WT_GC_REAP_DIRTY_ARCHIVE` | `WT_GC_DIRTY_ARCHIVE_DAYS` (3, days since merge) | `refs/wt-gc-archive/<stamp>/wt/<name>` (dirt commit on the tip) |
| Detached master snapshot | `WT_GC_REAP_DETACHED` | `WT_GC_DETACHED_DAYS` (3) | `refs/wt-gc-archive/<stamp>/wt/<name>` |
| Closed-PR worktree, remote gone | `WT_GC_REAP_CLOSED` | `WT_GC_CLOSED_DAYS` (7, days since close) | `wt/<name>` plus `heads/<branch>` |
| Stash on a gone branch or provably in master | `WT_GC_DROP_STASHES` | `WT_GC_STASH_DAYS` (7) | `refs/wt-gc-archive/<stamp>/stash/<sha>` |
| Backup ref with no matching PR | `WT_GC_REF_FALLBACK` | `WT_GC_BACKUP_FALLBACK_DAYS` (14) | `refs/wt-gc-archive/<stamp>/heads/<branch>` |
| Stopped `ibl5-*` container | `WT_GC_DOCKER_CONTAINERS` | `WT_GC_CONTAINER_DAYS` (14, days since exit) | `$WT_GC_TIPS_DIR/containers-<stamp>-<name>.json` |

`<stamp>` is the tick's start time in epoch seconds. `WT_GC_ARCHIVE_PRUNE` drops archive refs and container JSON files older than `WT_GC_ARCHIVE_DAYS` (90). A stamp that does not parse is kept. The tips-*.txt files stay as the human-readable index.

Restore:

- List archives: `git for-each-ref refs/wt-gc-archive/ --format='%(refname) %(objectname)'`
- Branch: `git branch <b> refs/wt-gc-archive/<stamp>/heads/<b>`
- Other ref: `git update-ref refs/<rest> refs/wt-gc-archive/<stamp>/<rest>`
- Worktree: `git worktree add ../<name>-restored refs/wt-gc-archive/<stamp>/wt/<name>`. If `git log -1 --format=%an` there prints `wt-gc`, the top commit holds the dirt, and `git reset --soft HEAD~1` returns it to the index.
- Stash: `git stash store -m restored refs/wt-gc-archive/<stamp>/stash/<sha>`
- Container: `bin/wt-up` recreates it. The JSON file records its config and mounts.

Never removed: open-PR worktrees, nightly autostashes, protected names, and anything a live-work probe reports in use. A probe error is a keep. The stopped-container step and the image prune never touch docker volumes. The dirty, detached and closed-PR worktree reaps call `wt-remove <name> --volumes`, so they do drop that worktree's own docker volumes. The closed-PR reap skips only OPEN-PR rows. An UNPUSHED or STALLED worktree whose PR was closed is archived and removed once it passes the closed-PR checks. Other UNPUSHED and STALLED worktrees stay. Every switch accepts `0` to disable its class without touching the others.

## Addendum 2026-11-11: idle worktree stacks are stopped

The tick stops a worktree's Docker stack when nobody has used it for a while. The stop keeps the DB volume, the images, the containers and `.wt-slug`.

| Class | Switch | Threshold | Archive |
|-------|--------|-----------|---------|
| Running worktree stack, no live work | WT_GC_STOP_IDLE_STACKS | WT_GC_STACK_IDLE_HOURS (24, hours since the php container started) | none (stop deletes nothing) |

The step reads `.wt-slug` files under the worktrees parent, so it never sees the main stack. It keeps any worktree where `_gc_live_work` finds work or fails. It stops through `bin/wt-down --stop`. Automouse calls the same command when it moves a plan to skipped/. `bin/wt-up` still gives a fresh DB. To resume a stopped stack with its DB, start its containers with `docker start`.
