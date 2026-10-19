---
description: Rivalries module, scoring franchise and GM head-to-head pairs and rendering league, pair, GM, and Team-card views.
last_verified: 2026-10-19
---

# Rivalries

## Routes

All routes live under `modules.php?name=Rivalries`.

- **Default (no `op`).** The league page lists the top franchise rivalries and the top GM rivalries.
- **`op=teams&a=<id>&b=<id>`.** The franchise pair page. Each id must be an integer from 1 to `League::MAX_REAL_TEAMID`. The two ids must differ.
- **`op=gms&a=<name>&b=<name>`.** The GM pair page. Each name must be 60 characters or fewer and must exist in `ibl_gm_tenures`. The two names must differ.
- **`op=gm&gm=<name>`.** One GM's rival list. The name follows the same rules as the GM pair page.

Any input that fails these rules renders `.ibl-empty-state`.
`Rivalries\RivalriesController` owns the validation.

## Data sources

Win and loss records come from the Head-to-Head Records matrix in phase `all`.
That phase counts HEAT, regular season, and playoff games.
So a rivalry's record always matches the H2H grid cell.

`RivalriesRepository` supplies the game rows, playoff series, and top performers.
It copies the H2H `unique_games` dedupe and the GM tenure rule.
Players are keyed on `pid` and named from `ibl_plr`.

## Scoring

`RivalryScorer` scores each pair from 0 to 100.

| Component | Weight | Cap |
|-----------|-------:|-----|
| Playoff history | 30 | 3 series, 15 playoff games |
| Volume | 20 | 60 games |
| Closeness of record | 20 | an even record scores full |
| Close games | 15 | margin of 5 points or fewer |
| Recency | 15 | 20 games in the last 5 seasons |

A pair needs `MIN_GAMES = 10` games to rank.
Ties break on games played, then on the pair keys in string order.
The recency window starts at `Season::$endingYear - 4`.

## Cache

`CachedRivalriesRepository` stores results in the shared DatabaseCache table for 24 hours.

- `rivalries:franchises:<windowStart>` and `rivalries:gms:<windowStart>` hold the pair stats.
- `rivalries:pair:teams:<lo>-<hi>:<part>` holds one franchise pair's games, series, or performers.
- `rivalries:pair:gms:<part>:<hash>` holds one GM pair's data. The hash is the first 32 hex characters of a SHA-1 of the two names.

`RefreshHeadToHeadRecordsStep` rebuilds the stats keys after each import and drops the pair keys.

## Team card

`Team\RivalsCardBuilder` renders the Top Rivals card on the Team page.
It is an optional `TeamService` dependency.
`modules/Team/index.php` wires it only for the IBL league.

## Files

- `ibl5/classes/Rivalries/RivalryScorer.php` scores and ranks pairs.
- `ibl5/classes/Rivalries/RivalriesRepository.php` runs the pair SQL.
- `ibl5/classes/Rivalries/CachedRivalriesRepository.php` caches the repository results.
- `ibl5/classes/Rivalries/RivalriesService.php` merges H2H records with repository stats.
- `ibl5/classes/Rivalries/RivalriesServiceFactory.php` builds the service and the cached repository.
- `ibl5/classes/Rivalries/RivalriesController.php` validates GET input and routes.
- `ibl5/classes/Rivalries/RivalriesView.php` renders the pages.
- `ibl5/classes/Rivalries/Contracts/` holds the four interfaces.
- `ibl5/tests/Rivalries/` holds the unit tests.
- `ibl5/tests/DatabaseIntegration/RivalriesRepositoryIntegrationTest.php` checks the SQL against a real database.
