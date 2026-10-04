---
description: Displays season-high statistical performances for players and teams.
last_verified: 2026-10-03
---

# SeasonHighs

Tracks and displays the best single-game statistical performances of the current season for players and teams. `SeasonHighsService` assembles the data from `SeasonHighsRepository` and passes it to `SeasonHighsView` for rendering. Entry point: `ibl5/modules/SeasonHighs/index.php`.

| Class | Role |
|---|---|
| `SeasonHighsRepository` | Queries for peak single-game performances |
| `SeasonHighsService` | Assembles season-high data for display |
| `SeasonHighsView` | Renders the season highs page |

## Caching

`ibl5/modules/SeasonHighs/index.php` wraps `SeasonHighsRepository` in `CachedSeasonHighsRepository`. The decorator caches each `getSeasonHighsBatch` result for 15 minutes under the `season_highs:v1:` key prefix, keyed by league, date range, limit, location filter, and a hash of the stat map. `getSeasonHighs` and `getRcbSeasonHighs` pass through uncached.

Build every new `SeasonHighsRepository` inside a `CachedSeasonHighsRepository`. `ibl5/tests/SeasonHighs/SeasonHighsRepositoryConstructionSitesTest.php` fails on any bare construction under `ibl5/classes` or `ibl5/modules`.
