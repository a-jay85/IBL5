---
description: Scheduled launchd sweep that reaps merged worktrees via bin/cleanup --all and only surfaces stalled, unpushed and empty ones.
last_verified: 2026-10-03
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
