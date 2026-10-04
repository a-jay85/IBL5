---
description: Provides database-backed key-value caching and file-based full-page HTML caching.
last_verified: 2026-10-03
---

# Cache

Provides two complementary caching strategies. `DatabaseCache` is a generic key-value store backed by the `cache` database table, used as a decorator by services like `CachedCareerLeaderboardsRepository`. `PageCache` caches full-page HTML responses for anonymous GET requests as files under `ibl5/cache/page/`, with a default TTL of 900 seconds.

| Class | Purpose |
|-------|---------|
| `DatabaseCache` | Key-value cache backed by the `cache` DB table |
| `PageCache` | File-based full-page HTML cache for anonymous GET requests |

## When a module gets a DB-level decorator

`PageCache` serves anonymous GET requests only, so every logged-in GM request runs the module's queries cold. A DB-level decorator over `Cache\Contracts\DatabaseCacheInterface` covers those requests.

Add a decorator when one cold page load measures about 1 second or more of database time. Below that bar, `PageCache` alone is enough. Measure with `SHOW PROFILES` against a full-history database before adding or removing one.

Decorated repositories: CareerLeaderboards, SeasonLeaderboards, RecordHolders (service level), HeadToHeadRecords, and SeasonHighs (`ibl5/classes/SeasonHighs/CachedSeasonHighsRepository.php`).

Measured on 2026-10-03 against the main-stack database (1,627 players, 623,335 box-score rows, 3,449 awards):

| Module | Cold DB time per page | Cache layer |
|--------|-----------------------|-------------|
| SeasonHighs | about 1.5 s (34 UNION ALL branches over box scores) | PageCache plus `CachedSeasonHighsRepository`, 900 s TTL |
| FranchiseHistory | about 0.5 s (`vw_franchise_summary` plus the `ibl_team_win_loss` group-by) | PageCache only |
| DraftHistory | about 0.001 s | PageCache only |
| AwardHistory | about 0.005 s | PageCache only |
| FranchiseRecordBook | about 0.001 s (reads the precomputed `ibl_rcb_alltime_records`) | PageCache only |

The SeasonHighs decorator expires entries by TTL alone. Its key carries the league, the date range, the location filter, and a hash of the stat-expression map, so an edit to `SeasonHighsService::STATS` starts a fresh key set with no manual version bump. The warm-cache script does not pre-warm SeasonHighs.
