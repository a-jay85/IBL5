---
description: Why nightly prod DB dumps are pulled offsite to the owner's Mac by a launchd job, with grandfather-father-son retention and a freshness alert.
last_verified: 2026-09-30
---

# ADR-0152: Pull prod DB dumps offsite to the owner's Mac

**Status:** Accepted
**Date:** 2026-09-30

## Context

`.github/workflows/db-backup.yml` writes a nightly dump to `~/backups/db/` on the prod host and keeps 14. The dumps lived only on that host, so losing the host lost the backups too. The repo is public, so GitHub artifacts are ruled out: anyone signed in can download them, and the dumps hold emails and password hashes. The hosting provider's JetBackup weekly and monthly snapshots exist, but they are not ours to inspect. PR #731 removed a freshness check for exactly that reason. This decision adds a different check, of our own dumps. A GitHub cron that never fires also sends no failure alert today.

## Decision

Run `bin/db-backups-pull` daily at 10:30 local from a launchd job (`com.ibl5.db-backups-pull`). It rsyncs `backups/db/` and `backups/db-predeploy/` from prod into `~/Backups/ibl5-db` (mode 700). It never passes `--delete`, so a compromised host cannot wipe the offsite copy. It verifies each file with `gzip -t`. A file that fails sends a Discord DM and makes the run exit 1. Retention keeps the newest 30 dailies plus the earliest dump of every month, and the newest 20 pre-deploy dumps. After the pull it checks the newest dump date. If that dump is older than 36 hours, it sends a Discord DM through `bin/discord-dm` and exits 1. A failed rsync, or an empty local dump dir, also sends a DM and exits 1. Every exit re-locks the local copy to owner-only, including a failed pull. `bin/test-db-backups-pull` covers the behavior in CI.

## Alternatives Considered

- **GitHub artifacts**: upload the dump from the workflow. Rejected because: the repo is public and the dumps hold PII.
- **Cloud bucket**: push dumps to object storage. Rejected because: it adds an account, credentials on the prod host, and a recurring cost for a single-owner project.
- **Fold into `ibl5/bin/db-sync-now`**: reuse its download path. Rejected because: its freshness skip exits before the download, so a stale or skipped run would never pull or alert.

## Consequences

- Positive: a second copy of the dumps exists off the prod host, with months of history.
- Positive: a dead GitHub cron now produces an alert.
- Negative: the Mac must be awake near 10:30. `bin/launchd-health-check` watches the job and reports a missed run.
- Negative: the local copy holds PII and relies on the Mac's disk protection.

## References

- `bin/db-backups-pull`: the pull, retention, and freshness script.
- `bin/test-db-backups-pull`: the stubbed regression harness.
- `bin/backups-sync`: the SSH host and `--rsync-path` convention reused here.
- `bin/db-sync-cron-setup`: the launchd schedule pattern reused here.
- `bin/lib/launchd-expected-jobs.sh`: lists the job for the health check.
- `.github/workflows/db-backup.yml`: writes the dumps on the prod host.
