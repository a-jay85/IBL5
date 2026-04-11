---
description: Analysis comparing PLR snapshots against boxscore tables.
last_verified: 2026-04-11
---

# PLR Snapshots vs Box Scores: Data Accuracy Analysis

Comparison of cumulative player statistics in `ibl_plr_snapshots` against aggregated per-game statistics in `ibl_box_scores` across all 19 IBL seasons (1989-2007).

## Summary

**6,392 / 6,482 player-seasons (98.61%) match perfectly** across all 15 stat columns (GM, MIN, FGM, FGA, FTM, FTA, 3GM, 3GA, ORB, DRB, REB, AST, STL, TO, BLK, PF, PTS). 16 of 19 seasons achieve 100%.

## Methodology

### Correct PLR Phase Selection

Use the **`finals`** snapshot phase (or `end-of-season` for 1997 and 2002 where `finals` is unavailable). The `reg-sim##` phases are mid-season checkpoints and will always undercount.

The PLR `stats_*` fields in the `finals` phase represent **complete regular season totals**. HEAT tournament stats are tracked separately in `heat-end` phase and do not contaminate regular season fields. Playoff stats are in separate `playoff_*` columns.

### Column Mapping

| PLR Column | Box Score Aggregate | Notes |
|-----------|-------------------|-------|
| `stats_gm` | `SUM(CASE WHEN gameMIN > 0 THEN 1 ELSE 0 END)` | PLR counts only games with minutes played |
| `stats_fgm` | `SUM(game2GM + game3GM)` | PLR FGM = total FG (2PT + 3PT combined) |
| `stats_fga` | `SUM(game2GA + game3GA)` | PLR FGA = total FGA (2PT + 3PT combined) |
| `stats_3gm` | `SUM(game3GM)` | Direct match |
| `stats_ftm` | `SUM(gameFTM)` | Direct match |
| `stats_pts` | `SUM(calc_points)` | Direct match |
| `stats_reb` | `SUM(gameORB + gameDRB)` | Direct match |
| `stats_min` | `SUM(gameMIN)` | Direct match |
| All others | Direct SUM of corresponding column | Direct match |

### Box Score Filters Required

```sql
WHERE game_type = 1                          -- Regular season only
  AND teamID NOT IN (40, 41, 50, 51)         -- Exclude All-Star & Rookie/Sophomore
  AND visitorTID NOT IN (40, 41, 50, 51)
  AND homeTID NOT IN (40, 41, 50, 51)
```

**Why these filters matter:**
- `game_type = 1`: Excludes preseason (October) and playoffs (June)
- Team IDs 40/41: Rookie/Sophomore game (10 players per side)
- Team IDs 50/51: All-Star game (12 players per side)
- Every season except 2007 has 44 All-Star/Rookie-Sophomore entries classified as `game_type = 1`. Failing to exclude them causes ~12% false mismatch rate.
- 2007 has zero such entries, which is why it was the only season to match 100% without this filter.

### Zero-Minute Entries

Box scores contain ~3,400 zero-minute entries per season (players on the roster who didn't play). PLR `stats_gm` does **not** count these. Use `SUM(CASE WHEN gameMIN > 0 THEN 1 ELSE 0 END)` instead of `COUNT(*)` for game counts.

## Per-Season Results

| Season | Players | Match | Rate |
|--------|---------|-------|------|
| 1989 | 297 | 297 | 100% |
| 1990 | 314 | 314 | 100% |
| 1991 | 308 | 308 | 100% |
| **1992** | **318** | **307** | **96.5%** |
| 1993 | 330 | 330 | 100% |
| 1994 | 337 | 337 | 100% |
| 1995 | 345 | 345 | 100% |
| 1996 | 347 | 347 | 100% |
| 1997 | 346 | 346 | 100% |
| 1998 | 353 | 353 | 100% |
| 1999 | 355 | 355 | 100% |
| **2000** | **355** | **296** | **83.4%** |
| 2001 | 353 | 353 | 100% |
| 2002 | 353 | 353 | 100% |
| 2003 | 358 | 358 | 100% |
| **2004** | **358** | **338** | **94.4%** |
| 2005 | 352 | 352 | 100% |
| 2006 | 354 | 354 | 100% |
| 2007 | 349 | 349 | 100% |

## The 90 Mismatched Player-Seasons

All 90 mismatches share the same pattern: PLR has exactly **+1 game** with unique per-player stat diffs (real production from an actual game, not a counter error).

### Root Cause: Re-Simulated Games in JSB Engine

The `.sco` files for these seasons were checked — no regular season games (November-April) are missing from `ibl_box_scores`. The only "missing" entries in the `.sco` are October HEAT tournament games, which are already imported as `game_type = 3`.

The extra stats do not correspond to any single box score entry (regular or preseason). The most likely explanation is a **re-simulated game** in the JSB engine:

1. A sim was run, and the PLR accumulated those stats cumulatively
2. The same sim was re-run (crash recovery, GM correction, or sim restart)
3. The `.sco` file overwrote the original game's box scores with the re-run results
4. The PLR kept cumulative totals from **both** runs, but the `.sco` only retained the second run

This is unrecoverable — the original per-game box score data was overwritten.

### Affected Players

#### 1992 — Team 7 (Bulls), 11 players

| PID | Name | PLR GM | Box GM | d_pts | d_min | d_fgm | d_ftm | d_3gm | d_reb | d_ast | d_stl | d_to | d_blk | d_pf |
|-----|------|--------|--------|-------|-------|-------|-------|-------|-------|-------|-------|------|-------|------|
| 1237 | Brandon Ingram | 83 | 82 | +20 | +28 | +8 | +1 | +3 | +5 | +3 | 0 | +1 | +1 | +3 |
| 666 | Dickey Simpkins | 74 | 73 | 0 | +2 | 0 | 0 | 0 | +1 | 0 | 0 | 0 | 0 | 0 |
| 1278 | Georgios Papagiannis | 14 | 13 | +2 | +2 | +1 | 0 | 0 | +1 | 0 | 0 | 0 | 0 | 0 |
| 131 | Kelly Tripucka | 83 | 82 | +25 | +28 | +10 | +4 | +1 | +10 | 0 | 0 | +3 | +1 | +1 |
| 949 | Leon Powe | 82 | 81 | +1 | +19 | 0 | +1 | 0 | +5 | 0 | 0 | +1 | +3 | 0 |
| 1235 | Nick Galis | 83 | 82 | +22 | +34 | +8 | +4 | +2 | +2 | +6 | +1 | +2 | +1 | +1 |
| 104 | Ron Harper | 87 | 86 | +18 | +34 | +8 | +2 | 0 | +4 | +4 | +4 | +3 | 0 | +1 |
| 33 | Rony Seikaly | 81 | 80 | +16 | +36 | +8 | 0 | 0 | +11 | +1 | +1 | +3 | +1 | +3 |
| 955 | Shelden Williams | 59 | 58 | 0 | +2 | 0 | 0 | 0 | +1 | +1 | 0 | 0 | 0 | 0 |
| 303 | Winston Garland | 83 | 82 | +7 | +15 | +3 | +1 | 0 | +3 | +2 | +1 | +2 | 0 | +1 |
| 649 | Yinka Dare | 83 | 82 | +10 | +35 | +4 | +2 | 0 | +15 | +1 | 0 | +1 | +4 | +2 |

#### 2000 — Teams 3, 4, 19, 20, 24, 25 (+ traded players on 7, 10, 13, 21), 59 players

| PID | Name | TID | PLR GM | Box GM | d_pts | d_min | d_fgm | d_ftm | d_3gm | d_reb | d_ast |
|-----|------|-----|--------|--------|-------|-------|-------|-------|-------|-------|-------|
| 2721 | Anderson Varejao | 3 | 83 | 82 | +6 | +13 | +3 | 0 | 0 | +3 | 0 |
| 3579 | Boban Marjanovic | 3 | 83 | 82 | +2 | +7 | 0 | +2 | 0 | +3 | +1 |
| 2422 | Brian Cardinal | 3 | 82 | 81 | +29 | +42 | +11 | +7 | 0 | +4 | +3 |
| 1236 | Dejounte Murray | 3 | 79 | 78 | +18 | +34 | +8 | +2 | 0 | +3 | +5 |
| 950 | J.J. Redick | 3 | 73 | 72 | +3 | +5 | +1 | 0 | +1 | 0 | 0 |
| 620 | Mark Aguirre | 3 | 83 | 82 | +7 | +13 | +3 | 0 | +1 | +4 | +1 |
| 3290 | Sherman Douglas | 3 | 83 | 82 | +22 | +35 | +10 | +1 | +1 | +8 | +3 |
| 1479 | Tim Duncan | 3 | 81 | 80 | +26 | +40 | +12 | +2 | 0 | +17 | +2 |
| 2720 | Tree Rollins II | 3 | 83 | 82 | +10 | +34 | +5 | 0 | 0 | +9 | 0 |
| 1523 | Wat Misaka | 3 | 54 | 53 | 0 | +12 | 0 | 0 | 0 | 0 | +2 |
| 3289 | Allie Quigley | 4 | 16 | 15 | +5 | +3 | +2 | 0 | +1 | 0 | +1 |
| 3577 | Antoine Walker | 4 | 65 | 64 | 0 | +6 | 0 | 0 | 0 | +1 | +2 |
| 1510 | Antonio Daniels | 4 | 82 | 81 | +7 | +36 | +1 | +4 | +1 | +2 | +7 |
| 2435 | Darius Miles | 4 | 82 | 81 | +14 | +34 | +7 | 0 | 0 | +8 | +2 |
| 2712 | J.R. Smith | 4 | 80 | 79 | +10 | +40 | +2 | +5 | +1 | +8 | +3 |
| 3555 | Jermaine ONeal | 4 | 83 | 82 | +27 | +33 | +12 | +3 | 0 | +19 | 0 |
| 2439 | Josip Sesar | 4 | 80 | 79 | +4 | +9 | +2 | 0 | 0 | +3 | 0 |
| 926 | Len Bias | 4 | 83 | 82 | +8 | +25 | +3 | +2 | 0 | +5 | +1 |
| 1485 | Stephen Jackson | 4 | 80 | 79 | +30 | +40 | +12 | 0 | +6 | +3 | +1 |
| 2991 | Tracy Murray | 4 | 52 | 51 | 0 | +1 | 0 | 0 | 0 | 0 | 0 |
| 3581 | Vitaly Potapenko | 4 | 81 | 80 | +4 | +7 | +2 | 0 | 0 | +2 | 0 |
| 1235 | Nick Galis | 7 | 77 | 76 | +38 | +34 | +16 | +3 | +3 | +2 | +1 |
| 1230 | Michael Jordan | 10 | 69 | 68 | +43 | +40 | +17 | +7 | +2 | +7 | +11 |
| 2998 | Oliver Miller | 13 | 73 | 72 | +2 | +33 | +1 | 0 | 0 | +11 | 0 |
| 2700 | Andre Iguodala | 19 | 83 | 82 | +15 | +33 | +6 | +2 | +1 | +9 | +3 |
| 3282 | Brandon Tomyoy | 19 | 83 | 82 | +18 | +25 | +8 | +1 | +1 | +4 | +3 |
| 2987 | Doug Christie | 19 | 81 | 80 | +12 | +33 | +4 | +4 | 0 | +3 | +4 |
| 627 | Hanamichi Sakuragi | 19 | 81 | 80 | +19 | +37 | +9 | +1 | 0 | +19 | +1 |
| 2979 | Maurice Stokes | 19 | 83 | 82 | +13 | +36 | +5 | +3 | 0 | +7 | +3 |
| 936 | Robert Jaworski | 19 | 62 | 61 | +16 | +27 | +5 | +2 | +4 | +2 | +10 |
| 1480 | Tracy McGrady | 19 | 83 | 82 | +20 | +36 | +8 | +2 | +2 | +5 | +5 |
| 2016 | Tyrone Hill | 19 | 82 | 81 | +5 | +9 | +2 | +1 | 0 | +1 | 0 |
| 1762 | DeSagana Diop | 20 | 83 | 82 | +5 | +25 | +2 | +1 | 0 | +7 | 0 |
| 2010 | Dino Radja | 20 | 81 | 80 | +24 | +38 | +10 | +4 | 0 | +17 | 0 |
| 1757 | Mehmet Okur | 20 | 83 | 82 | +26 | +43 | +11 | +2 | +2 | +8 | +1 |
| 3297 | Michael Ansley | 20 | 76 | 75 | +2 | +4 | +1 | 0 | 0 | +3 | 0 |
| 304 | Mitch Richmond | 20 | 81 | 80 | +14 | +18 | +5 | +2 | +2 | +6 | +1 |
| 3556 | Nancy Lieberman | 20 | 79 | 78 | +11 | +41 | +4 | +3 | 0 | +6 | +9 |
| 3564 | Zydrunas Ilgauskas | 20 | 83 | 82 | +10 | +27 | +3 | +4 | 0 | +4 | 0 |
| 1484 | Chauncey Billups | 21 | 74 | 73 | +2 | +6 | 0 | +2 | 0 | 0 | +1 |
| 930 | Arvydas Macijauskas | 24 | 58 | 57 | +26 | +32 | +11 | +2 | +2 | +1 | 0 |
| 3285 | Clifford Robinson | 24 | 80 | 79 | +9 | +18 | +4 | +1 | 0 | +4 | 0 |
| 2982 | Clyde Lovellette | 24 | 83 | 82 | +28 | +40 | +12 | +4 | 0 | +23 | +1 |
| 1239 | Earl Manigault | 24 | 75 | 74 | +7 | +19 | +2 | +2 | +1 | +2 | +7 |
| 1245 | Malcolm Brogdon | 24 | 80 | 79 | +2 | +7 | +1 | 0 | 0 | +1 | +1 |
| 2445 | Marko Jaric | 24 | 83 | 82 | +17 | +34 | +6 | +3 | +2 | +4 | +4 |
| 2709 | Rickey Green II | 24 | 83 | 82 | +2 | +21 | +1 | 0 | 0 | +1 | +6 |
| 3569 | Sergio Llull | 24 | 75 | 74 | +8 | +14 | +4 | 0 | 0 | +4 | +4 |
| 2007 | Vern Mikkelsen | 24 | 83 | 82 | +22 | +27 | +8 | +6 | 0 | +9 | 0 |
| 1243 | Vladimir Tkachenko | 24 | 81 | 80 | +4 | +9 | +2 | 0 | 0 | +1 | 0 |
| 649 | Yinka Dare | 24 | 83 | 82 | +2 | +14 | +1 | 0 | 0 | +2 | 0 |
| 626 | Brian Grant | 25 | 83 | 82 | +30 | +35 | +11 | +8 | 0 | +11 | +1 |
| 1767 | Jamaal Tinsley | 25 | 82 | 81 | 0 | +5 | 0 | 0 | 0 | 0 | +1 |
| 1253 | Pierluigi Marzorati | 25 | 81 | 80 | +8 | +38 | +3 | +2 | 0 | +4 | +8 |
| 636 | Randolph Lillehammer | 25 | 76 | 75 | +9 | +24 | +4 | 0 | +1 | +4 | 0 |
| 1262 | Taurean Prince | 25 | 83 | 82 | +4 | +13 | +2 | 0 | 0 | +1 | 0 |
| 937 | Thabo Sefolosha | 25 | 73 | 72 | +9 | +34 | +4 | 0 | +1 | +5 | +1 |
| 3592 | Tiffany Hayes | 25 | 81 | 80 | +2 | +12 | +1 | 0 | 0 | +3 | +2 |
| 3596 | Travis Knight | 25 | 64 | 63 | 0 | +2 | 0 | 0 | 0 | 0 | 0 |

#### 2004 — Teams 1, 2, 23, 20 players

| PID | Name | TID | PLR GM | Box GM | d_pts | d_min | d_fgm | d_ftm | d_3gm | d_reb | d_ast |
|-----|------|-----|--------|--------|-------|-------|-------|-------|-------|-------|-------|
| 4843 | Nick Richards | 1 | 84 | 83 | +12 | +26 | +5 | +2 | 0 | +11 | 0 |
| 4826 | Anthony Edwards | 2 | 62 | 61 | +31 | +39 | +13 | +2 | +3 | +3 | +6 |
| 2983 | Ben Wallace | 2 | 77 | 76 | +4 | +30 | +2 | 0 | 0 | +18 | 0 |
| 3285 | Clifford Robinson | 2 | 82 | 81 | +41 | +38 | +17 | +3 | +4 | +5 | 0 |
| 4845 | Devin Vassell | 2 | 77 | 76 | +13 | +20 | +5 | +1 | +2 | +1 | +2 |
| 4852 | Immanuel Quickley | 2 | 30 | 29 | 0 | +1 | 0 | 0 | 0 | +1 | +2 |
| 3279 | Maurice Cheeks | 2 | 82 | 81 | +11 | +40 | +5 | 0 | +1 | +2 | +11 |
| 1235 | Nick Galis | 2 | 70 | 69 | +3 | +1 | +1 | 0 | +1 | 0 | 0 |
| 3857 | Ralph Sampson | 2 | 79 | 78 | +21 | +41 | +8 | +5 | 0 | +17 | 0 |
| 4496 | Alex English | 23 | 64 | 63 | +8 | +11 | +3 | +2 | 0 | +4 | +1 |
| 4167 | Allan Houston | 23 | 65 | 64 | +2 | +1 | +1 | 0 | 0 | 0 | 0 |
| 3553 | Brittney Griner | 23 | 49 | 48 | +4 | +5 | +2 | 0 | 0 | +3 | 0 |
| 4834 | Chet Walker | 23 | 50 | 49 | 0 | +3 | 0 | 0 | 0 | 0 | 0 |
| 1758 | Darryl Dawkins | 23 | 73 | 72 | +8 | +27 | +4 | 0 | 0 | +10 | 0 |
| 4844 | Jalen Smith | 23 | 65 | 64 | +6 | +22 | +2 | +1 | +1 | +5 | +1 |
| 3280 | Mookie Blaylock | 23 | 82 | 81 | +15 | +43 | +7 | 0 | +1 | +4 | +6 |
| 2714 | Purvis Short | 23 | 82 | 81 | +21 | +38 | +7 | +6 | +1 | +8 | +1 |
| 2989 | Todd Day | 23 | 46 | 45 | +2 | +11 | +1 | 0 | 0 | 0 | 0 |
| 1480 | Tracy McGrady | 23 | 75 | 74 | +26 | +44 | +12 | 0 | +2 | +8 | +2 |
| 4164 | Vin Baker | 23 | 81 | 80 | +16 | +30 | +8 | 0 | 0 | +12 | 0 |

## Implications

- The `ibl_hist` VIEW (migration 094) sources from PLR snapshots and is therefore accurate to the PLR data, which includes these phantom extra games for 90 player-seasons.
- Box score aggregations will produce values 1 game lower for these players in those seasons.
- The discrepancy is a JSB engine artifact, not a data import issue. No corrective action is possible or necessary.
