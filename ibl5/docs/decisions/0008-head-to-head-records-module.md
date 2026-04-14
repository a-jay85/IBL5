---
description: ADR for replacing SeriesRecords with HeadToHeadRecords — multi-axis matrix, phase filters, cached decorator, destructive migration.
last_verified: 2026-04-14
---

# ADR-0008: Head-to-Head Records Module

**Status:** Accepted
**Date:** 2026-04-14

## Context

The `SeriesRecords` module showed a single-axis (active teams) win-loss matrix for the current regular season only. It relied on `vw_series_records`, a view over `ibl_schedule`. Users wanted three axis dimensions (Active Teams, All-Time Teams, GMs), a phase filter (HEAT, Regular Season, Playoffs, All-Time), a scope toggle (Current Season vs All-Time), and row highlighting for the logged-in user's team/GM.

## Decision

Replace `SeriesRecords` with a new `HeadToHeadRecords` module that:

1. **Queries `ibl_box_scores_teams` directly** via UNION ALL (visitor/home perspectives) with CASE-based win determination from quarter-point totals.
2. **Supports three axis dimensions:** `active_teams` (current 28 franchises), `all_time_teams` (every franchise-season alias), `gms` (from `ibl_gm_tenures` with mid-season attribution).
3. **Supports four phase filters:** `heat` (game_type=3), `regular` (game_type=1), `playoffs` (game_type=2), `all`.
4. **Supports two scope toggles:** `current` (single season), `all_time` (historical).
5. **Uses `CachedHeadToHeadRecordsRepository`** decorator with `DatabaseCache` (24 keys, 86400s TTL).
6. **Drops `vw_series_records`** via migration 111 — the view is no longer consumed by any code.
7. **Decouples Standings** by inlining the simple `buildSeriesMatrix()` loop as a private method on `StandingsView`, with `StandingsRepository::getSeriesRecords()` rewritten to query `ibl_box_scores_teams` directly.

Enforcement: `HeadToHeadRecordsRepositoryInterface` in `Contracts/`; CSS in `ibl5/design/components/head-to-head-records.css` (no inline styles); cache warming in `ibl5/bin/warm-cache`.

## Consequences

- **SeriesRecords module deleted** (4 classes, 4 contracts, 1 module entry, 4 test files).
- **`vw_series_records` dropped** — irreversible after migration runs on production.
- **Standings no longer depends on SeriesRecords** — simpler dependency graph.
- **24 cache keys** added to `cache` table — warmed by `ibl5/bin/warm-cache` after sim processing.
- **New E2E test** (`head-to-head-records.spec.ts`) replaces `series-records.spec.ts`.
