---
description: Introduces ibl5/bin/db-sync-now (lock, throttle, marker, --from-backup), a detached sim-hook in bin/sim-recap-tick, a main-stack guard in bin/db-test-up, and a bin/db-sync-cron-setup nightly launchd agent — keeping the local developer DB within one sim or one night of prod.
last_verified: 2026-09-02
---

# ADR-0114: Local DB snapshot freshness

**Status:** Accepted
**Date:** 2026-08-16

## Context

The local developer database has been stale for months — newest `ibl_box_scores.game_date` for `game_type=1` was `2008-01-24` while prod continues to accumulate regular-season results. A stale local DB means every local sim recap, every bug hunt that reads box scores, and every dev session querying recent results is operating on data that no longer reflects prod.

Two triggers exist for staleness to close:

1. **In-season:** a new sim is stored on prod — the local DB is now behind by exactly one recap cycle.
2. **Off-season / overnight:** prod is ahead by however many off-season transactions have accumulated since the last sync.

`bin/db-sync-prod` already exists and is the canonical seed path for `bin/dev-up` and `bin/wt-up --prod`. What was missing: a wrapper that guards it (mutual exclusion with `bin/db-test-up`, throttle, `BROKEN` detection) and the two automated triggers that call it.

## Decisions

### 1. Trigger on sim-store, not on a fixed timer

`bin/sim-recap-tick` fires a detached `ibl5/bin/db-sync-now` in the `generate()` branch immediately after a sim is successfully stored — the exact moment prod has data the local DB does not.

**Rejected:** polling every N minutes unconditionally. A 5-minute poll syncs constantly in the offseason for nothing and still lags a stored sim by up to 5 minutes in-season. A 15-minute poll reduces the overhead but keeps the lag and produces a constant stream of throttle-skip log lines during hunts.

### 2. `mkdir` lock, not `flock`

`acquire_lock()` uses `mkdir "$DB_SYNC_LOCK_DIR"` plus a pid file. Both `ibl5/bin/db-sync-now` and `bin/db-test-up`'s main-stack guard use the same `DB_SYNC_LOCK_DIR` default (`/tmp/ibl5-db-sync.lock`), giving true mutual exclusion across the two scripts.

**Rejected:** `flock(1)`. It is a util-linux binary and is **absent on macOS** (`command -v flock` returns nothing on the developer machine). An `flock`-based guard would pass the ubuntu CI harness (`flock` is present on ubuntu) and silently no-op on the machine that needs it — a gate that passes everywhere it can be tested and fails where it must work. Also rejected: `/usr/bin/shlock` (present on macOS but absent on ubuntu — same inversion, opposite direction) and `brew install util-linux` (a machine-setup prerequisite for an unattended launchd job is a latent failure).

### 3. Skip, never queue; no retry

When a sync is already in progress, `ibl5/bin/db-sync-now` logs `skip: sync already running` and exits 0. `bin/db-test-up` exits 1 loudly on the interactive side (a test run that silently no-ops reads as "tests ran"). Neither queues nor retries.

**Rejected:** queuing a pending sync. A queued sync fires against DB state the developer has moved past — it resolves a race that has already resolved itself. The nightly job is the natural catch-up; queuing adds complexity for a benefit that evaporates by the time the lock is released.

### 4. 30-minute throttle

`ibl5/bin/db-sync-now` skips (exit 0, no marker update) when the last successful sync was less than 1800 seconds ago. In-season sims arrive in bursts — a multi-day catch-up can store several within minutes — and without a throttle each store fires a back-to-back sync. 1800s collapses a burst into one sync while still allowing a genuine second sim later the same evening to refresh that night.

**Rejected:** 300s (barely longer than one sync runtime — collapses nothing; every sim in a burst still fires its own sync). Rejected: 4h (a normal evening's second sim skips the throttle only if the gap exceeds 4h — most don't, so the nightly restore becomes the de-facto refresh for the second game of a split doubleheader). The value is a single named constant (`DB_SYNC_MIN_INTERVAL`), env-overridable, so changing it is a one-line edit and a new harness case.

### 5. Wrap `bin/db-sync-prod`; do not change it

`ibl5/bin/db-sync-now` calls `bin/db-sync-prod` with no argument (target resolution to `ibl5-mariadb`). `bin/db-sync-prod` is not modified.

**Rejected:** modifying `bin/db-sync-prod` to add locking or atomic swap (staging database + rename). `bin/db-sync-prod` is the canonical seed path invoked by `bin/dev-up` and `bin/wt-up --prod`; its non-atomicity is a known, documented property (it streams `DROP/CREATE` before data). Adding locking inside it would make those callers take the sync lock inadvertently. Atomicity via a staging database is a separate design decision with its own migration surface and is deferred.

The non-atomicity is mitigated instead by a post-sync row assertion (`SELECT MAX(game_date) FROM ibl_box_scores WHERE game_type = 1`): a stream death that leaves the DB empty is detected, the marker is set to `BROKEN` with a loudly logged error, the freshness timestamp is not updated (so the throttle does not treat a failed run as fresh), and `exit 1` signals the caller. `--status` surfaces the `BROKEN` state with a recovery hint.

### 6. Nightly restore consumes the `db-backup.yml` prod artifact, not a live stream

The `com.ibl5.db-sync-nightly` LaunchAgent calls `ibl5/bin/db-sync-now --from-backup`, which scp's the newest `ibl5-<date>.sql.gz` artifact from prod and restores it locally.

**Rejected:** running `ibl5/bin/db-sync-now` (live mode) on the nightly schedule. The live mode streams via `bin/db-sync-prod`, which requires the prod DB to be responsive at 03:20. The `db-backup.yml` artifact is already produced at 07:30 UTC by a CI workflow; it is a verified, gzip-integrity-checked file whose date is part of its name. Freshness skip is keyed to the artifact date: if the marker is already newer than the artifact's creation time, the restore is skipped — so a live sync earlier in the evening prevents a redundant restore at 03:20.

### 7. Main stack only

`ibl5/bin/db-sync-now` touches only `ibl5-mariadb`. `bin/db-test-up`'s lock guard fires only in the `if [ -z "$WORKTREE_NAME" ]` arm. Worktree databases (`ibl5-db-<slug>`) are never locked, synced, or restored by any script in this plan.

**Rejected:** syncing worktree databases on the same trigger. A worktree DB is a disposable copy seeded at `bin/wt-up --prod` time; it diverges as the worktree's migrations run, and a mid-session prod sync would overwrite those migration changes. Worktrees that want a fresh seed call `bin/wt-up --prod` explicitly.

### 8. Detached `nohup … & disown` from the tick; not a queue file

The sim-hook in `generate()` fires `ibl5/bin/db-sync-now` as a background process (`nohup … >> log 2>&1 &; disown`) and returns immediately. The tick must return well inside launchd's 300s `StartInterval`; a sync takes minutes.

**Rejected:** writing a "sync wanted" flag file that a separate drainer processes. That design requires a second scheduled job, a second failure surface (what if the drainer crashes?), and delays in-season freshness by however long the drain interval is — for no benefit beyond avoiding the `nohup` pattern. The sync self-guards (lock, throttle, container check), so firing it per-sim from multiple concurrent ticks is safe: the second invocation exits 0 with `already running`, the others are throttled.

## Consequences

- Positive: the local developer DB refreshes within one sim-tick cycle (~5 minutes) of a new sim landing on prod during the active season.
- Positive: an overnight restore catches off-season accumulation with no user action.
- Positive: `ibl5/bin/db-sync-now --status` and the marker file give instant human-readable observability at any time; the `BROKEN` state is never silent.
- Positive: `bin/db-test-up` on the main stack can no longer silently race a running sync.
- Negative (non-atomic sync): a sync killed mid-stream leaves the local DB empty. The `BROKEN` state surfaces this loudly; recovery is `ibl5/bin/db-sync-now --force`. Accepted: the plan's guarantee is *never silently stale or silently empty*, not *never empty*.
- Negative (one-sided collision detection with bug-pipeline): `pgrep -f 'bin/bug-pipeline-tick'` from the sync side is over-broad (it skips during any tick, not just a hunt that touches the DB). Over-skipping is free — the nightly job and the next sim both retry. Under-detecting means a DROP under a running hunt, which is the failure being avoided.
- Negative (nightly agent installed manually): `bin/db-sync-cron-setup --install-schedule` is the developer's explicit step after the PR merges; it is not armed automatically. The nightly restore does not start until the developer runs it.

## References

- `ibl5/bin/db-sync-now` — lock, throttle, marker, `--from-backup` restore, `--status`, `--dry-run`.
- `ibl5/bin/test-db-sync-now` — full guard-matrix harness; wired into the `harness-tests` CI job.
- `bin/db-sync-cron-setup` — launchd LaunchAgent install/uninstall/`--print-schedule` for the nightly restore.
- `bin/sim-recap-tick` — gains `SIM_RECAP_DB_SYNC_BIN` env seam and the detached hook in `generate()`.
- `bin/test-sim-recap-tick` — gains DB-SYNC-1 through DB-SYNC-7 covering the hook.
- `bin/db-test-up` — gains the main-stack-only lock guard in the `if [ -z "$WORKTREE_NAME" ]` arm.
- `bin/db-sync-prod` (reference — called, never modified) — the canonical seed path whose non-atomicity this plan works around.
- `.github/workflows/db-backup.yml` (reference — not modified) — produces `ibl5-<date>.sql.gz` at 07:30 UTC; the nightly path consumes it.
