---
description: Rationale for removing the DuckDB OLAP analytics layer, write-back tables, and associated tooling.
last_verified: 2026-04-28
---

# ADR-0016: Remove DuckDB Analytics Layer

**Status:** Accepted
**Date:** 2026-04-28

## Context

The DuckDB OLAP layer was built for cross-season statistical analysis when the JSB engine was a black box. Now that the JSB engine source code has been decompiled, analysis can be done at the source-code level. The DuckDB layer, its write-back tables, export/build/setup scripts, and associated `bin/` helpers are unused overhead that accrues `bin/check-docs` freshness debt and confuses agents.

## Decision

Remove the entire DuckDB analytics surface: the analytics directory, five bin helper scripts (analytics-setup, analytics-build, analytics-export, db-import-boxscores, import-plr-snapshots), two PHP scripts (analyticsWriteback.php, importPlrSnapshotsFromCsv.php), the duckdb-analytics agent rule, and the two write-back MariaDB tables (ibl_analytics_tsi_bands, ibl_analytics_player_peaks) via `ibl5/migrations/125_drop_analytics_tables.sql`.

## Alternatives Considered

- **Keep dormant with deprecation notice** — rejected, dead code accrues `bin/check-docs` freshness debt and confuses agents.
- **Keep db-import-boxscores --remote as standalone** — rejected, `bin/db-query` + mariadb-dump already cover the use case.

## Consequences

- Positive: removes ~40 tracked files, 5 bin scripts, 1 rule file, 2 unused tables.
- Negative: historical analytics queries no longer in tree (git history preserves them).

## References

- `ibl5/migrations/125_drop_analytics_tables.sql`
