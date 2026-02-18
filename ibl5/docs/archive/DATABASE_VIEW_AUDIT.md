# Database View Performance Audit

**Date:** 2026-02-12
**Database:** iblhoops_ibl5 (MariaDB/MySQL 8.0 via MAMP)

---

## Baseline Data

### Table Row Counts

| Table | Rows | Notes |
|-------|------|-------|
| `ibl_box_scores` | 576,751 | 482K season, 34K playoff, 60K HEAT |
| `ibl_box_scores_teams` | 49,115 | ~24K season (game_type=1), ~3K playoff (game_type=2) |
| `ibl_schedule` | 1,148 | |
| `ibl_hist` | 7,335 | |
| `ibl_plr` | 1,534 | |
| `ibl_franchise_seasons` | 512 | |
| `ibl_team_awards` | 126 | |

### View Output Row Counts

| View | Rows |
|------|------|
| `vw_franchise_summary` | 28 |
| `vw_playoff_series_results` | 270 |
| `vw_team_awards` | 163 |
| `ibl_team_win_loss` | 584 |
| `ibl_heat_win_loss` | 512 |
| `ibl_team_offense_stats` | 512 |
| `ibl_team_defense_stats` | 512 |
| `ibl_season_career_avgs` | 1,254 |
| `ibl_playoff_career_avgs` | 980 |
| `ibl_heat_career_avgs` | 1,213 |

---

## Query Timing Results

| Query | Time (s) | Severity |
|-------|----------|----------|
| `ibl_season_career_avgs ORDER BY pts LIMIT 25` | **2.66** | CRITICAL |
| `getAllFranchiseHistory()` (main query) | **0.54** | HIGH |
| `ibl_heat_career_avgs ORDER BY pts LIMIT 25` | 0.31 | MEDIUM |
| `ibl_playoff_career_avgs ORDER BY pts LIMIT 25` | 0.23 | MEDIUM |
| `ibl_heat_career_totals ORDER BY pts LIMIT 25` | 0.24 | LOW |
| `ibl_playoff_career_totals ORDER BY pts LIMIT 25` | 0.20 | LOW |
| `getAllHEATTotals()` | 0.18 | LOW |
| `vw_schedule_upcoming LIMIT 10` | 0.18 | LOW |
| `getAllTitleCounts()` | 0.16 | LOW |
| `getAllPlayoffTotals()` | 0.14 | LOW |
| `vw_series_records` | 0.11 | LOW |
| `ibl_team_defense_stats WHERE season=2024` | 0.11 | LOW |
| Tier 4 views (standings, salary, etc.) | <0.05 | OK |

---

## Findings by Tier

### CRITICAL: `ibl_season_career_avgs` (2.66s)

**Root Cause:** This view aggregates 482,263 rows from `ibl_box_scores` (game_type=1) with GROUP BY pid. The entire view must materialize (186,932 estimated rows) before ORDER BY / LIMIT can apply.

**EXPLAIN Summary:**
- Outer query: type=ALL on derived table (186,932 rows), Using filesort
- Inner query: uses `idx_gt_pid(game_type, pid)` to filter by game_type=1, but still scans 482K rows
- JOIN to `ibl_plr` via eq_ref on PRIMARY (efficient)

**Why playoff/HEAT are faster:** Playoff has only 34K rows (game_type=2), HEAT has 60K (game_type=3). Season has 14x more data.

**Impact:** Career Leaderboards page for season stats. Every sort column change re-runs this 2.66s query.

### HIGH: `vw_franchise_summary` + `getAllFranchiseHistory()` (0.54s + redundant materializations)

**Root Cause:** The `getAllFranchiseHistory()` query JOINs `vw_franchise_summary` with `ibl_team_win_loss`, but `vw_franchise_summary` already contains `ibl_team_win_loss` internally. This causes **double materialization** of `ibl_team_win_loss` (each materializing 24K+ rows from `ibl_box_scores_teams`).

**EXPLAIN Summary:**
- 54 rows in EXPLAIN plan (deepest nesting in the audit)
- `ibl_box_scores_teams` scanned 6 separate times (for win/loss CTE, playoff series, team awards)
- `vw_playoff_series_results` materialized 3x within `vw_franchise_summary` (via `po` subquery + `vw_team_awards`)
- Additional `ibl_team_win_loss` materialization at row 51-54 (the explicit JOIN)

**Franchise History Page Total Cost:**
- `getAllFranchiseHistory()`: 0.54s (main query, double-materializes win/loss)
- `getAllPlayoffTotals()`: 0.14s (double-materializes `vw_playoff_series_results` via UNION ALL)
- `getAllHEATTotals()`: 0.18s (materializes `ibl_heat_win_loss`)
- `getAllTitleCounts()`: 0.16s (materializes `vw_team_awards` which internally materializes `vw_playoff_series_results`)
- **Total: ~1.02s** just for SQL (plus PHP overhead)

**Redundant Materialization Map:**
| View | Materialized in... | Times |
|------|-------------------|-------|
| `vw_playoff_series_results` | `vw_franchise_summary.po`, `vw_franchise_summary.tc` (via `vw_team_awards`), `getAllPlayoffTotals()`, `getAllTitleCounts()` | **4x** |
| `ibl_team_win_loss` | `vw_franchise_summary.wl`, `getAllFranchiseHistory()` explicit JOIN | **2x** |

### MEDIUM: `ibl_team_defense_stats` self-join (inefficient opponent lookup)

**Root Cause:** Self-join on `ibl_box_scores_teams` to find opponent stats uses only `idx_date` for the `opp` table, achieving 0.40% filter rate.

**EXPLAIN (filtered by season_year):**
- `my` row: uses `idx_gt_name_season(game_type, name, season_year)` — efficient (34 rows)
- `opp` row: uses `idx_date` — finds 12 rows by Date, then filters by visitorTeamID, homeTeamID, gameOfThatDay, name<>opp.name in WHERE
- The existing `idx_gt_date_teams(game_type, Date, visitorTeamID, homeTeamID)` isn't used because `game_type` isn't in the opp's WHERE clause

**Impact:** When filtered by season_year (Standings page), this is fast (0.11s). Full scan is still manageable (512 rows output). No action needed currently.

### MEDIUM: `vw_schedule_upcoming` (full `ibl_box_scores_teams` scan in subquery)

**Root Cause:** LEFT JOIN subquery scans ALL 48,648 rows of `ibl_box_scores_teams` with GROUP BY to find gameOfThatDay. The `idx_gt_date_teams` index exists but optimizer chooses full scan.

**EXPLAIN:**
- Schedule: type=ALL (1,148 rows) — acceptable for small table
- Subquery: type=ALL on `ibl_box_scores_teams` (48,648 rows), Using temporary

**Impact:** 0.18s — acceptable for API usage with LIMIT/OFFSET. But the subquery design means the full 49K-row GROUP BY happens even when only 10 schedule rows are needed.

### LOW: Tier 3-4 Views

- `vw_series_records`: 4x UNION ALL on 1,148-row table. Each branch scans full table (no index used). 0.11s — acceptable given small table size.
- `vw_free_agency_offers`: Simple JOINs, uses indexes on `name` and `team_name`. Only 1 row currently. No issues.
- `vw_current_salary`, `vw_team_standings`, `vw_player_career_stats`: All use PK lookups. <0.05s. No issues.
- Per-season stats views (`ibl_playoff_stats`, `ibl_heat_stats`): Single-player lookups use `idx_gt_pid` + JOIN to `ibl_franchise_seasons`. Efficient for point queries.

---

## Index Coverage Assessment

### Well-Covered
- `ibl_box_scores_teams`: `idx_gt_name_season(game_type, name, season_year)` covers most filtered views
- `ibl_box_scores_teams`: `idx_gt_date_teams(game_type, Date, visitorTeamID, homeTeamID)` covers playoff series deduplication
- `ibl_box_scores`: `idx_gt_pid(game_type, pid)` covers career stats views
- `ibl_franchise_seasons`: `uq_franchise_season(franchise_id, season_year)` covers most JOINs

### Missing/Suboptimal
1. **`ibl_box_scores_teams` opponent lookup:** Self-join in `ibl_team_defense_stats` uses only `idx_date` for opponent. A composite index `(Date, visitorTeamID, homeTeamID, gameOfThatDay)` would improve the 0.40% filter rate. However, `idx_gt_date_teams` already has these columns with `game_type` prefix — the optimizer just can't use it for the `opp` alias since game_type isn't constrained on opp.
2. **`ibl_team_awards`:** Only has `idx_award(Award)`. No index on `name` or `year` for GROUP BY / WHERE patterns. Table is tiny (126 rows) so impact is negligible.
3. **`ibl_box_scores` season career:** The `idx_gt_pid` index covers the game_type + pid lookup, but the GROUP BY on 482K rows for season stats is the bottleneck — no index can fix the full-materialization-before-LIMIT problem.

---

## Recommended Fixes

### Fix 1: Add index for `ibl_team_defense_stats` opponent self-join (LOW priority)

```sql
CREATE INDEX idx_date_teams_gotd ON ibl_box_scores_teams (Date, visitorTeamID, homeTeamID, gameOfThatDay);
```

This covers the opponent lookup without requiring `game_type` as leading column. However, since defense stats are already fast when filtered by season (0.11s), this is low priority.

### Fix 2: Eliminate redundant `ibl_team_win_loss` JOIN in `getAllFranchiseHistory()` (HIGH priority)

The `getAllFranchiseHistory()` query JOINs `ibl_team_win_loss` directly, but `vw_franchise_summary` already contains the all-time totals derived from the same data. The five-season window calculation should query `ibl_team_win_loss` directly without also materializing `vw_franchise_summary` — OR the `vw_franchise_summary` data should be sufficient.

**Current:** Materializes `ibl_team_win_loss` 2x (0.54s)
**Proposed:** Separate into two queries — one for `vw_franchise_summary` (all-time totals, playoff, titles), one for rolling 5-season window directly from `ibl_team_win_loss`. This avoids double-materializing the win/loss CTE.

### Fix 3: Reduce `vw_playoff_series_results` materializations on Franchise History page (HIGH priority)

Currently materialized 4x per page load:
1. Inside `vw_franchise_summary` (for playoff appearance count)
2. Inside `vw_franchise_summary` (via `vw_team_awards` for title counts)
3. In `getAllPlayoffTotals()` (via UNION ALL — materializes 2x)
4. In `getAllTitleCounts()` (via `vw_team_awards`)

**Proposed:** Cache `vw_team_awards` results in PHP since it's queried in both `vw_franchise_summary` and `getAllTitleCounts()`. Or restructure `getAllFranchiseHistory()` to avoid using `vw_franchise_summary` entirely — query the underlying data directly with a single pass.

### Fix 4: Add `ibl_box_scores(season_year)` index for career views (MEDIUM priority)

The `ibl_season_career_avgs` view takes 2.66s because it GROUP BYs 482K rows. While we can't avoid the full materialization for `ORDER BY random_stat LIMIT 25`, adding a season_year index could help per-season queries.

However, the career views don't filter by season_year (they're lifetime aggregates), so an index won't help the leaderboard query. The real fix is architectural:

**Proposed:** Consider materializing `ibl_season_career_avgs` as a table refreshed after game sims. Season box scores change infrequently (only during sim days). A materialized table with proper indexes would turn 2.66s queries into <0.01s lookups.

### Fix 5: Optimize `vw_schedule_upcoming` subquery (LOW priority)

The LEFT JOIN subquery scans all `ibl_box_scores_teams` to find `gameOfThatDay`. This could be optimized to only scan matching dates.

**Current time:** 0.18s — acceptable for now.

---

## Priority Summary

| Priority | Fix | Est. Savings | Effort | Status |
|----------|-----|-------------|--------|--------|
| CRITICAL | Materialized table for `ibl_season_career_avgs` | 2.6s per leaderboard query | Medium (migration + refresh trigger) | DEFERRED |
| HIGH | Eliminate double `ibl_team_win_loss` materialization in `getAllFranchiseHistory()` | ~0.2s | Low (PHP refactor) | DONE |
| HIGH | Reduce `vw_playoff_series_results` materializations (4x -> 1x) | ~0.16s | Medium (PHP refactor) | DONE (eliminated `getAllTitleCounts()`) |
| LOW | Add opponent self-join index | filter rate 0.40% -> 90% | Trivial (migration) | DONE (migration 031) |
| LOW | Optimize `vw_schedule_upcoming` subquery | ~0.03s (covering index scan) | Trivial (migration) | DONE (side-effect of migration 031) |

---

## Changes Made

### FranchiseHistoryRepository.php
- **Eliminated redundant `ibl_team_win_loss` double-materialization:** Split the single monolithic query into two: (1) `vw_franchise_summary` for all-time totals + titles, (2) `ibl_team_win_loss` for 5-season window only
- **Removed `getAllTitleCounts()` method:** Title counts are now sourced directly from `vw_franchise_summary` (which internally derives them from `vw_team_awards`), eliminating a redundant `vw_team_awards` query that re-materialized `vw_playoff_series_results`
- **Net effect:** Franchise History page now runs 3 queries instead of 4, with no redundant view materializations

### Migration 031: `031_view_performance_indexes.sql`
- Added `idx_date_visitor_home_gotd(Date, visitorTeamID, homeTeamID, gameOfThatDay)` on `ibl_box_scores_teams` — improves `ibl_team_defense_stats` self-join from 0.40% to 90% filter rate
- Added `idx_name` on `ibl_team_awards` — covers GROUP BY name patterns

### Schema snapshot
- Updated `schema.sql` with `idx_gt_name_season` (from migration 028, was missing) and new migration 031 indexes

### Deferred: `ibl_season_career_avgs` materialization
The 2.66s career leaderboard query remains the single biggest bottleneck. It requires materializing 482K rows before ORDER BY/LIMIT. No index can fix this — the fix requires either:
1. A materialized table refreshed after game sims
2. Application-level caching
This is deferred as it requires architectural discussion.
