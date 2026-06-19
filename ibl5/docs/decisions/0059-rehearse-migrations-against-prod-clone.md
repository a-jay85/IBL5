---
description: Deploy Rehearsal dry-runs pending migrations against a filtered clone of production data, catching content-dependent failures before the live deploy.
last_verified: 2026-06-11
---

# ADR-0059: Rehearse Migrations Against a Filtered Production Clone

**Status:** Accepted
**Date:** 2026-06-11

## Context

On 2026-06-11 the production deploy (GitHub Actions run 27371947228) failed at the migration step: migration 144's foreign-key add (`ibl_demands.pid` → `ibl_plr.pid`) was rejected with errno 1452 by a real orphan row — a demand for CJ Elleby (pid 4891), a player deleted from `ibl_plr`. The migration's "zero orphans" precondition had been verified against a stale 2026-03-05 dump; live prod had drifted since. CI was green because the Deploy Rehearsal job applied `000_baseline_schema.sql` (a schema-only snapshot) plus a tiny fixture and ran `ibl5/bin/migrate` against empty/synthetic data — so any migration whose failure depends on accumulated data content (FK, UNIQUE, NOT-NULL backfills) was structurally invisible to it.

## Decision

The Deploy Rehearsal job (`.github/workflows/deploy-rehearsal.yml`) clones **production data** into the CI MariaDB and runs `ibl5/bin/migrate` against prod's real schema, real rows, and prod's own `migrations` tracking table — a true dry-run of the imminent deploy with foreign-key checks ON. Because prod MySQL is not reachable from GitHub runners, the dump runs server-side over SSH (the `db-backup.yml` pattern) via the committed read-only script `bin/rehearsal-prod-dump`, reusing the existing `HOST`/`PORT`/`USERNAME`/`PRIVATE_KEY` deploy secrets — no new secrets. To bound dump size, four bloat tables (`ibl_box_scores`, `ibl_box_scores_teams`, `ibl_plr_snapshots`, `ibl_plb_snapshots`) are sampled to the two most recent seasons (`season_year >= MAX(season_year)-1`). A "false-green guard" step asserts the clone is bounded and carries prod's `batch=0` migration seed — without it, `MigrationRepository::hasSeededMigrations()` returns false and `ibl5/bin/migrate` silently skips the entire pending set.

## Alternatives Considered

- **Full unfiltered clone** — dump every prod table in full. Rejected because: `ibl_box_scores` alone is ~601K rows / 410 MB; full dumps blow CI wall-clock and runner storage on every migration PR.
- **Filter all `season_year` tables dynamically** — apply the season `--where` to every table that has the column. Rejected because: filtering a parent table while its children stay full manufactures clone-internal orphans → false RED that blocks legitimate PRs. Only leaf bloat tables (nothing references them) are safe to sample.
- **Keep the synthetic baseline + add hand-written orphan checks per migration** — Rejected because: it does not generalize to UNIQUE / NOT-NULL / CHECK content failures and relies on author discipline rather than an automated gate.

## Consequences

- Positive: content-dependent migration failures (the errno-1452 class that broke run 27371947228) now surface on the PR's own Deploy Rehearsal run, not on the live production deploy.
- Positive: the clone carries prod's current schema, so the stale-`000_baseline_schema.sql` class of rehearsal drift disappears; the two baseline-apply steps are removed.
- Negative: sampling `ibl_box_scores`, `ibl_box_scores_teams`, `ibl_plr_snapshots`, and `ibl_plb_snapshots` to `season_year >= MAX-1` re-blinds the rehearsal to migrations whose failure depends ONLY on pre-previous-season rows in those four tables (e.g. an FK-add an ancient orphan would violate). Accepted because box_scores-touching migrations are rare and the size win is ~92%. Note that CJ Elleby's orphan was a recent-season `ibl_demands` row referencing `ibl_plr` — `ibl_demands` is unfiltered, and even the sampled tables retain the recent window — so this gate catches that failure class; only an orphan confined to old rows of the four sampled tables would slip through.

## References

- `.github/workflows/deploy-rehearsal.yml` — the rehearsal job that clones prod and dry-runs migrations.
- `bin/rehearsal-prod-dump` — the read-only, season-filtered server-side dump script.
- `.github/workflows/db-backup.yml` — the SSH server-side dump pattern reused here.
- `bin/db-sync-prod` — the multi-pass generated-column / routines dump logic this script mirrors.
- `ibl5/docs/DATABASE_GUIDE.md` — migration guidance cross-referencing this gate and its blind spot.
