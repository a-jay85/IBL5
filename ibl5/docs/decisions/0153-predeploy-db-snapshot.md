---
description: The prod deploy takes a full database dump on the prod host before the code reset whenever the deploy brings new migration files, and fails the deploy if the dump fails.
last_verified: 2026-09-30
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0153: Dump the Prod Database Before a Deploy That Brings New Migrations

**Status:** Accepted
**Date:** 2026-09-30
**Deciders:** post-plan harness (auto-draft)

## Context

The production deploy in `.github/workflows/main.yml` fetches and resets the prod checkout, then applies migrations. `ibl5/migrations/README.md` describes the recovery path when a deploy goes wrong. The workflow reverts the last commit when post-deploy smoke tests fail. A revert restores code only. When a migration drops or rewrites data, the newest copy of that data is the nightly dump from `.github/workflows/db-backup.yml`, which can be close to a day old. `bin/check-destructive-migrations` blocks known destructive patterns at PR time, and a migration that passes it can still corrupt data through a bad `UPDATE` or a wrong join.

`bin/adr-check` flagged this PR because it adds two new developer tools under `bin/`: `bin/predeploy-db-snapshot` and its harness `bin/test-predeploy-db-snapshot`.

## Decision

The deploy runs `bin/predeploy-db-snapshot` on the prod host in a new step between `git fetch origin` and `git reset --hard origin/production`. At that point the prod checkout still holds the old commit, so the workflow copies the script from the runner checkout with `scp` into `~/.predeploy-snapshot/` and runs it from there.

The script compares `ibl5/migrations/` between `HEAD` and `origin/production`. When nothing differs, it prints a skip line and exits 0. When migration files differ, it dumps `REMOTE_DATABASE` with `mariadb-dump` (or `mysqldump` when that binary is missing) using the same flags as the nightly backup: `--single-transaction --skip-lock-tables --routines --triggers`. The dump is gzipped to a `.tmp` file, checked with `gzip -t`, and renamed to `predeploy-<UTC stamp>-<short sha>.sql.gz` in `~/backups/db-predeploy/`. A trap removes the `.tmp` file on any exit. The script then keeps the newest 10 dumps and deletes the rest.

Any failure exits non-zero. The workflow step has no `continue-on-error`, so a failed snapshot stops the deploy before the code reset and before any migration runs.

`bin/test-predeploy-db-snapshot` builds a throwaway git repo with an `origin/production` ref and a stub dump binary. It covers the skip case, a good dump, a failing dump that leaves no partial file, rotation, a missing `REMOTE_DATABASE`, and `--help`. The shell harness job in `.github/workflows/tests.yml` runs it in CI.

## Alternatives Considered

- **Dump on every deploy.** Rejected because most deploys bring no migration, and a full dump on each one adds deploy time and disk use with no recovery value.
- **Use the nightly dump.** Rejected because a migration that runs late in the day would lose up to a day of league activity on restore.
- **Share the nightly directory.** Rejected because the nightly rotation and the local sync tooling both match `ibl5-*.sql.gz` there, so a shared directory would let one rotation delete the other's files.
- **Best-effort snapshot.** A step with `continue-on-error`. Rejected because a deploy that applies migrations with no fresh dump is the exact case this change exists to prevent.
- **Run after the reset.** Rejected because the reset must come after the snapshot, and before the reset the prod checkout does not yet contain the script.

## Consequences

- Positive: a bad migration can be restored from data taken minutes before it ran, with `gunzip -c <file> | mysql <db>` on the host.
- Positive: deploys with no migration change skip the dump and run as fast as before.
- Negative: a deploy with new migrations now takes as long as a full dump of the prod database.
- Negative: a dump failure (disk full, auth change, missing binary) blocks the deploy until someone fixes the host.
- Negative: the prod host holds up to 10 extra full dumps in `~/backups/db-predeploy/`.
- Negative: one more `bin/` script and one more `test-*` harness to maintain.

## References

- `bin/predeploy-db-snapshot`
- `bin/test-predeploy-db-snapshot`
- `.github/workflows/main.yml` (step "Snapshot database before migrations")
- `.github/workflows/tests.yml` (runs the harness in CI)
- `.github/workflows/db-backup.yml` (nightly dump whose flags the snapshot copies)
- `bin/check-destructive-migrations` (the PR-time scan this complements)
- `ibl5/migrations/README.md` (restore instructions)
