---
description: REST API architectural overview — auth, rate limiting, ETag caching, controller inventory, route table.
last_verified: 2026-09-05
---

# API Guide

**Status:** Implemented ✅ — 26 controllers, API key auth, rate limiting, ETag caching, pagination, CSV export.

## Architecture

```
ibl5/classes/Api/
├── Router.php                     # Route dispatch
├── Cache/
│   └── ETagHandler.php            # HTTP ETag caching
├── Contracts/                     # API interfaces
│   ├── AuthenticatorInterface.php
│   ├── ControllerInterface.php
│   ├── RateLimiterInterface.php
│   ├── RouterInterface.php
│   └── TransformerInterface.php
├── Controller/                    # 26 controllers
│   ├── EnqueueController.php
│   ├── GameBoxscoreController.php
│   ├── GameDetailController.php
│   ├── GameListController.php
│   ├── HealthController.php
│   ├── InjuriesController.php
│   ├── LastSeenController.php
│   ├── LeadersController.php
│   ├── PipelineStateController.php
│   ├── PlayerDetailController.php
│   ├── PlayerExportController.php
│   ├── PlayerHistoryController.php
│   ├── PlayerListController.php
│   ├── PlayerStatsController.php
│   ├── ReactionController.php
│   ├── SeasonController.php
│   ├── SourceDeletedController.php
│   ├── SourceUpdatedController.php
│   ├── StandingsController.php
│   ├── TeamDetailController.php
│   ├── TeamListController.php
│   ├── TeamRosterController.php
│   ├── ThreadByPrController.php
│   ├── ThreadReplyController.php
│   ├── TradeAcceptController.php
│   └── TradeDeclineController.php
├── Middleware/
│   ├── ApiKeyAuthenticator.php    # API key validation
│   └── RateLimiter.php            # Per-key rate limiting
├── Pagination/
│   └── Paginator.php
├── Repository/                    # Data access layer
├── Response/                      # JSON, CSV, HTML responders
└── Transformer/                   # Response shape transformers
```

## Route Inventory

All routes are registered in `ibl5/classes/Api/Router.php`.

### GET routes (API key required except `health`)

| Route | Controller |
|-------|------------|
| `health` | `HealthController` — public, no auth |
| `players` | `PlayerListController` |
| `players/export` | `PlayerExportController` |
| `players/{uuid}` | `PlayerDetailController` |
| `players/{uuid}/stats` | `PlayerStatsController` |
| `players/{uuid}/history` | `PlayerHistoryController` |
| `teams` | `TeamListController` |
| `teams/{uuid}` | `TeamDetailController` |
| `teams/{uuid}/roster` | `TeamRosterController` |
| `standings` | `StandingsController` |
| `standings/{conference}` | `StandingsController` |
| `games` | `GameListController` |
| `games/{uuid}` | `GameDetailController` |
| `games/{uuid}/boxscore` | `GameBoxscoreController` |
| `stats/leaders` | `LeadersController` |
| `injuries` | `InjuriesController` |
| `season` | `SeasonController` |

### POST routes (API key required)

| Route | Controller |
|-------|------------|
| `trades/{offerId}/accept` | `TradeAcceptController` |
| `trades/{offerId}/decline` | `TradeDeclineController` |
| `bug-pipeline/enqueue` | `EnqueueController` |
| `bug-pipeline/thread-reply` | `ThreadReplyController` |
| `bug-pipeline/reaction` | `ReactionController` |
| `bug-pipeline/last-seen` | `LastSeenController` |
| `bug-pipeline/state` | `PipelineStateController` |
| `bug-pipeline/thread-by-pr` | `ThreadByPrController` |
| `bug-pipeline/source-updated` | `SourceUpdatedController` |
| `bug-pipeline/source-deleted` | `SourceDeletedController` |

## Endpoint Reference

### Conventions

#### Base path

All 27 routes are served from `/ibl5/api/v1/` by `ibl5/api.php`, which delegates to `ibl5/classes/Api/Router.php`. A request for an unregistered path returns a router-level `404` with no `error` envelope — this is distinct from a controller-level `404 not_found` (which does include the error envelope and is documented per endpoint).

```
GET /ibl5/api/v1/players
```

#### Authentication

Every route requires an API key except `GET /ibl5/api/v1/health`, which is public. The key is accepted in either of two positions (header form is preferred):

```
X-API-Key: <key>
```

or as a query parameter `?key=<key>`. Missing or invalid credentials yield `401 Unauthorized`, raised by `ibl5/classes/Api/Middleware/ApiKeyAuthenticator.php` before any controller runs.

#### Rate limiting

Requests are throttled per API key by `ibl5/classes/Api/Middleware/RateLimiter.php`. The standard tier permits **60 requests per 60-second window**. On every successful request the response includes:

```
X-RateLimit-Limit: 60
```

When the limit is exceeded the response is `429 Too Many Requests` with:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: <unix-timestamp>
Retry-After: 60
```

#### Conditional requests / ETag

Responses from the 14 ETag-aware controllers carry an `ETag` response header derived from the record's `updated_at` timestamp (see `ibl5/classes/Api/Cache/ETagHandler.php`). Include the value in a subsequent request to receive `304 Not Modified` (empty body) when the resource has not changed:

```
If-None-Match: "<etag-value>"
```

Endpoint entries that do not list `**Conditional requests:**` as supported do not call `notModified()` and never emit an `ETag` header.

#### Pagination

Collection endpoints paginate via `ibl5/classes/Api/Pagination/Paginator.php`. Query parameters (all optional):

| Parameter | Default | Notes |
|-----------|---------|-------|
| `page` | `1` | 1-indexed |
| `per_page` | `25` | Maximum `100` |
| `sort` | endpoint-specific | Allowed values listed per endpoint |
| `order` | `asc` | `asc` or `desc` |

The pagination block appears inside `meta` in the success envelope alongside `timestamp` and `version`:

```json
{
  "status": "success",
  "data": [...],
  "meta": {
    "timestamp": "2026-09-04T00:00:00Z",
    "version": "v1",
    "page": 1,
    "per_page": 25,
    "total": 42,
    "total_pages": 2,
    "sort": "name",
    "order": "asc"
  }
}
```

#### Standard success envelope

24 of 26 controllers wrap their response in the standard envelope. The two exceptions are called out explicitly in their entries and do not follow this shape.

```json
{
  "status": "success",
  "data": { ... },
  "meta": {
    "timestamp": "2026-09-04T00:00:00Z",
    "version": "v1"
  }
}
```

`GET /ibl5/api/v1/health` returns a **flat** body with no envelope. `GET /ibl5/api/v1/players/export` returns `text/csv`, not JSON. Both carry an explicit note in their endpoint entries.

#### Error responses

All controllers use the same error envelope shape:

```json
{
  "status": "error",
  "error": {
    "code": "not_found",
    "message": "Player not found."
  }
}
```

The following failures apply to every authenticated endpoint and are **not** repeated in individual entries:

- `401 Unauthorized` — missing or invalid API key (raised by `ApiKeyAuthenticator` before the controller runs)
- `429 Too Many Requests` — rate limit exceeded (raised by `RateLimiter`)
- Router-level `404` — no route matches the path (no error envelope)

Per-endpoint entries list only the error codes that the controller itself raises. The full closed set is: `400 bad_request` · `403 forbidden` · `404 no_boxscore` · `404 not_found` · `500 processing_error`. See [HTTP Status Codes](#http-status-codes) for the complete status-code table.

### GET /health
Returns API liveness and database reachability status; does not use the standard envelope.
**Auth:** No auth required — public endpoint.
**Path parameters:** none.
**Query parameters:** none.
**Conditional requests:** not supported.
Example request:
```
GET /ibl5/api/v1/health
```
**Response `200`:** flat body (no envelope). Fields: `status` (string, `"ok"` or `"degraded"`), `db` (bool), `checkedAt` (ISO 8601 timestamp).
**Response `503`:** same flat shape with `status: "degraded"` and `db: false` — returned when the database is unreachable.
**Errors:** none defined.

### GET /players/export
Returns the full player roster as a CSV download; does not use the standard envelope.
**Auth:** API key required.
**Path parameters:** none.
**Query parameters:** none.
**Conditional requests:** not supported.
Example request:
```
GET /ibl5/api/v1/players/export
X-API-Key: <key>
```
**Response `200`:** `Content-Type: text/csv; charset=utf-8`. `Content-Disposition: attachment; filename="ibl-players.csv"`. UTF-8 BOM for Excel compatibility. CSV columns (header row): `PID`, `Name`, `Nickname`, `Age`, `Position`, `Height (ft)`, `Height (in)`, `Active`, `Retired`, `Experience`, `Bird Rights`, `Team ID`, `Team City`, `Team Name`, `Full Team Name`, `Owner`, `Contract Year`, `Current Salary`, `Year 1 Salary`, `Year 2 Salary`, `Year 3 Salary`, `Year 4 Salary`, `Year 5 Salary`, `Year 6 Salary`, `GP`, `MIN`, `FGM`, `FGA`, `FTM`, `FTA`, `3PM`, `3PA`, `ORB`, `DRB`, `AST`, `STL`, `TO`, `BLK`, `PF`, `PPG`, `FG%`, `FT%`, `3P%`.
**Errors:** none defined.

### GET /players/{uuid}/stats
Returns career statistics and draft information for a single player.
**Auth:** API key required.
**Path parameters:** `uuid` (string, RFC-4122 lowercase) — the player.
**Query parameters:** none.
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/players/{uuid}/stats
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is a player stats object. Fields: `uuid` (string), `pid` (int), `name` (string), `career_totals` (object: `games`, `minutes`, `points`, `rebounds`, `assists`, `steals`, `blocks`), `career_averages` (object: `points_per_game`, `rebounds_per_game`, `assists_per_game`), `career_percentages` (object: `fg_percentage`, `ft_percentage`, `three_pt_percentage`), `playoff_minutes` (int), `draft` (object: `year`, `round`, `pick`, `team`, `team_id`).
**Errors:** `404 not_found` — no player with that UUID.

### GET /players/{uuid}/history
Returns per-season statistics for a single player.
**Auth:** API key required.
**Path parameters:** `uuid` (string, RFC-4122 lowercase) — the player.
**Query parameters:** none.
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/players/{uuid}/history
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is an array of season history objects. Each item: `year` (int), `pid` (int), `player_name` (string), `team` (object: `uuid`, `city`, `name`, `team_id`), `games` (int), `minutes` (int), `stats` (object: `points`, `rebounds`, `offensive_rebounds`, `assists`, `steals`, `blocks`, `turnovers`, `personal_fouls`, `fg_made`, `fg_attempted`, `ft_made`, `ft_attempted`, `three_pt_made`, `three_pt_attempted`), `per_game` (object: `points`, `rebounds`, `assists`, `steals`, `blocks`, `turnovers`, `minutes`), `percentages` (object: `fg`, `ft`, `three_pt`), `salary` (int). `meta.total` — total season count.
**Errors:** `404 not_found` — no player with that UUID or player has no history.

### GET /players/{uuid}
Returns full detail for a single player.
**Auth:** API key required.
**Path parameters:** `uuid` (string, RFC-4122 lowercase) — the player.
**Query parameters:** none.
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/players/{uuid}
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is a player detail object. Fields: `uuid` (string), `pid` (int), `name` (string), `position` (string), `age` (int), `height` (string, e.g. `"6-6"`), `experience` (int), `bird_rights` (bool), `team` (object: `uuid`, `city`, `name`, `full_name`, `team_id`) or null, `contract` (object: `current_salary`, `year1`, `year2`), `stats` (object: `games_played`, `minutes_played`, `field_goals_made`, `field_goals_attempted`, `free_throws_made`, `free_throws_attempted`, `three_pointers_made`, `three_pointers_attempted`, `offensive_rebounds`, `defensive_rebounds`, `assists`, `steals`, `turnovers`, `blocks`, `personal_fouls`, `points_per_game`, `fg_percentage`, `ft_percentage`, `three_pt_percentage`).
**Errors:** `404 not_found` — no player with that UUID.

### GET /players
Returns a paginated list of players.
**Auth:** API key required.
**Path parameters:** none.
**Query parameters:** `position` (optional), `team` (optional), `search` (optional), `page`, `per_page`, `sort` (default `name`; allowed: `name`, `age`, `position`, `points_per_game`, `experience`), `order` (default `asc`).
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/players?position=PG&page=1&per_page=25
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is an array of player objects. Each item: `uuid` (string), `pid` (int), `name` (string), `position` (string), `age` (int), `height` (string), `experience` (int), `team` (object: `uuid`, `city`, `name`, `full_name`, `team_id`) or null, `contract` (object: `current_salary`, `year1`, `year2`), `stats` (object: `games_played`, `points_per_game`, `fg_percentage`, `ft_percentage`, `three_pt_percentage`). Paginator fields in `meta`.
**Errors:** none defined.

### GET /teams/{uuid}/roster
Returns a paginated list of players on a team.
**Auth:** API key required.
**Path parameters:** `uuid` (string, RFC-4122 lowercase) — the team.
**Query parameters:** `page`, `per_page`, `sort` (default `name`; allowed: `name`, `age`, `position`, `points_per_game`, `experience`), `order` (default `asc`).
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/teams/{uuid}/roster
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is an array of player objects (same shape as `GET /players` list items). Paginator fields in `meta`.
**Errors:** `404 not_found` — no team with that UUID or team has no players.

### GET /teams/{uuid}
Returns full detail for a single team.
**Auth:** API key required.
**Path parameters:** `uuid` (string, RFC-4122 lowercase) — the team.
**Query parameters:** none.
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/teams/{uuid}
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is a team detail object. Fields: `uuid` (string), `city` (string), `name` (string), `full_name` (string), `team_id` (int), `owner` (string), `owner_discord_id` (string), `arena` (string), `conference` (string), `division` (string), `record` (object: `league` (string "W-L"), `conference` (string), `division` (string), `home` (string or null), `away` (string or null)), `standings` (object: `win_percentage` (float), `conference_games_back` (float), `division_games_back` (float), `games_remaining` (int)).
**Errors:** `404 not_found` — no team with that UUID.

### GET /teams
Returns a paginated list of teams.
**Auth:** API key required.
**Path parameters:** none.
**Query parameters:** `page`, `per_page`, `sort` (default `team_name`; allowed: `team_name`, `team_city`, `owner_name`, `conference`, `division`), `order` (default `asc`).
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/teams?sort=conference&order=asc
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is an array of team objects. Each item: `uuid` (string), `city` (string), `name` (string), `full_name` (string), `team_id` (int), `owner` (string), `owner_discord_id` (string), `arena` (string), `conference` (string), `division` (string). Paginator fields in `meta`.
**Errors:** none defined.

### GET /standings/{conference}
Returns standings filtered by conference. Shares StandingsController with `GET /standings`.
**Auth:** API key required.
**Path parameters:** `conference` (string) — accepted values: `East` or `Eastern` (normalized to `Eastern`), `West` or `Western` (normalized to `Western`); matching is case-insensitive. An unrecognized value is passed as-is to the data layer and returns an empty result set — no `400` is raised.
**Query parameters:** none.
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/standings/Eastern
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is an array of standings objects. Each item: `team` (object: `uuid`, `city`, `name`, `full_name`, `team_id`), `conference` (string), `division` (string), `record` (object: `league` (string), `conference` (string), `division` (string), `home` (string), `away` (string)), `win_percentage` (float), `games_back` (object: `conference` (float), `division` (float)), `games_remaining` (int), `clinched` (object: `conference` (bool), `division` (bool), `playoffs` (bool)). `meta.total` (int), `meta.conference` (string).
**Errors:** none defined.

### GET /standings
Returns league-wide standings. Shares StandingsController with `GET /standings/{conference}`.
**Auth:** API key required.
**Path parameters:** none.
**Query parameters:** none.
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/standings
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is an array of standings objects. Each item: `team` (object: `uuid`, `city`, `name`, `full_name`, `team_id`), `conference` (string), `division` (string), `record` (object: `league` (string), `conference` (string), `division` (string), `home` (string), `away` (string)), `win_percentage` (float), `games_back` (object: `conference` (float), `division` (float)), `games_remaining` (int), `clinched` (object: `conference` (bool), `division` (bool), `playoffs` (bool)). `meta.total` (int).
**Errors:** none defined.

### GET /games/{uuid}/boxscore
Returns the full boxscore for a single game.
**Auth:** API key required.
**Path parameters:** `uuid` (string, RFC-4122 lowercase) — the game.
**Query parameters:** none.
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/games/{uuid}/boxscore
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is a boxscore object. Fields: `game` (game object matching the `GET /games/{uuid}` shape), `visitor` and `home` (each: `team_stats` (object: `name` (string), `quarter_scoring` (object: `q1`, `q2`, `q3`, `q4`, `ot` — each with `visitor` and `home` scores), `totals` (object: `fg_made`, `fg_attempted`, `two_pt_made`, `two_pt_attempted`, `ft_made`, `ft_attempted`, `three_pt_made`, `three_pt_attempted`, `offensive_rebounds`, `defensive_rebounds`, `rebounds`, `assists`, `steals`, `turnovers`, `blocks`, `personal_fouls`, `points`), `attendance` (int), `capacity` (int), `records` (object: `visitor` (string "W-L"), `home` (string "W-L"))), `players` (array of player stat lines: `uuid`, `name`, `position`, `minutes`, `two_pt_made`, `two_pt_attempted`, `ft_made`, `ft_attempted`, `three_pt_made`, `three_pt_attempted`, `fg_made`, `fg_attempted`, `offensive_rebounds`, `defensive_rebounds`, `rebounds`, `assists`, `steals`, `turnovers`, `blocks`, `personal_fouls`, `points`)).
**Errors:** `404 not_found` — no game with that UUID; `404 no_boxscore` — game exists but box score is not available (e.g. scheduled game).

### GET /games/{uuid}
Returns detail for a single game.
**Auth:** API key required.
**Path parameters:** `uuid` (string, RFC-4122 lowercase) — the game.
**Query parameters:** none.
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/games/{uuid}
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is a game object. Fields: `uuid` (string), `season` (int), `date` (string), `status` (string), `box_score_id` (string or null), `game_of_that_day` (int), `visitor` (object: `uuid`, `city`, `name`, `full_name`, `score`, `team_id`), `home` (object: `uuid`, `city`, `name`, `full_name`, `score`, `team_id`).
**Errors:** `404 not_found` — no game with that UUID.

### GET /games
Returns a paginated list of games.
**Auth:** API key required.
**Path parameters:** none.
**Query parameters:** `season` (optional), `status` (optional), `team` (optional), `date` (optional, exact date), `date_start` / `date_end` (optional date range), `page`, `per_page`, `sort` (default `game_date`; allowed: `game_date`, `visitor_score`, `home_score`), `order` (default `desc`).
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/games?season=2026&status=final&page=1
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is an array of game objects. Each item: `uuid` (string), `season` (int), `date` (string), `status` (string), `box_score_id` (string or null), `game_of_that_day` (int), `visitor` (object: `uuid`, `city`, `name`, `full_name`, `score`, `team_id`), `home` (object: `uuid`, `city`, `name`, `full_name`, `score`, `team_id`). Paginator fields in `meta`.
**Errors:** none defined.

### GET /stats/leaders
Returns paginated season leaders for a statistical category.
**Auth:** API key required.
**Path parameters:** none.
**Query parameters:** `category` (string; allowed: `ppg`, `rpg`, `apg`, `spg`, `bpg`, `fgp`, `ftp`, `tgp`, `qa`; default `ppg`; unrecognized values coerce to `ppg`), `season` (optional), `min_games` (optional), `page`, `per_page`.
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/stats/leaders?category=ppg&season=2026
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is an array of leader objects. Each item: `player` (object: `uuid`, `pid`, `name`), `team` (object: `uuid`, `city`, `name`, `team_id`), `season` (int), `stats` (object: `games`, `minutes_per_game`, `points_per_game`, `rebounds_per_game`, `assists_per_game`, `steals_per_game`, `blocks_per_game`, `turnovers_per_game`, `fg_percentage`, `ft_percentage`, `three_pt_percentage`). Paginator fields in `meta` plus `category` (string) and `season` (int, optional).
**Errors:** none defined.

### GET /injuries
Returns the current injury list.
**Auth:** API key required.
**Path parameters:** none.
**Query parameters:** none.
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/injuries
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is an array of injury objects. Each item: `player` (object: `uuid`, `pid`, `name`, `position`), `team` (object: `uuid`, `city`, `name`, `team_id`), `injury` (object: `days_remaining` (int)). `meta.total` (int).
**Errors:** none defined.

### GET /season
Returns current season phase and simulation state.
**Auth:** API key required.
**Path parameters:** none.
**Query parameters:** none.
**Conditional requests:** supported — `ETag` / `If-None-Match` / `304`.
Example request:
```
GET /ibl5/api/v1/season
X-API-Key: <key>
```
**Response `200`:** standard envelope; `data` is a season object. Fields: `phase` (string), `last_sim` (object: `number` (int), `phase_sim_number` (int), `start_date` (string), `end_date` (string)), `projected_next_sim_end_date` (string, `YYYY-MM-DD` format).
**Errors:** none defined.

## Features

- **Authentication:** API key validation via `ApiKeyAuthenticator` (the `health` route is public — no key required)
- **Rate Limiting:** Per-key enforcement via `RateLimiter`
- **Caching:** HTTP ETag support via `ETagHandler` using `updated_at` timestamps
- **Pagination:** Built into list controllers
- **CSV Export:** `PlayerExportController` for bulk data

## Database Resources

### Core Tables with UUIDs

| Entity | Table | Internal ID | Public ID |
|--------|-------|-------------|-----------|
| Players | `ibl_plr` | `pid` (int) | `uuid` (varchar) |
| Teams | `ibl_team_info` | `teamid` (int) | `uuid` (varchar) |
| Games | `ibl_schedule` | `SchedID` (int) | `uuid` (varchar) |
| Box Scores | `ibl_box_scores` | — | `uuid` (varchar) |
| Draft | `ibl_draft` | — | `uuid` (varchar) |

### Pre-Built Database Views

1. **`vw_player_current`** — Active players with current season stats and team info
2. **`vw_team_standings`** — Real-time standings with calculated fields
3. **`vw_schedule_upcoming`** — Schedule with team names and game status
4. **`vw_player_career_stats`** — Career statistics summary with averages
5. **`vw_free_agency_offers`** — Free agency market overview

## Implementation Guidelines

### Use Database Views

```php
// ✅ Recommended — Use optimized view
$query = "SELECT * FROM vw_player_current WHERE uuid = ?";

// ❌ Avoid — Complex joins in API code
$query = "SELECT p.*, h.*, t.* FROM ibl_plr p JOIN ...";
```

### Use UUIDs for Public IDs

Always expose UUIDs in API responses, never internal integer IDs:

```php
// ✅ Secure — UUID prevents ID enumeration
return json_encode(['player_id' => $player['uuid']]);

// ❌ Insecure — Exposes internal database ID
return json_encode(['player_id' => $player['pid']]);
```

### ETag Caching

Use the `updated_at` timestamps for HTTP caching (see `ibl5/classes/Api/Cache/ETagHandler.php` for the canonical implementation).

### Always Use Prepared Statements

```php
$stmt = $db->prepare("SELECT * FROM vw_player_current WHERE uuid = ?");
$stmt->bind_param("s", $uuid);
$stmt->execute();
```

## HTTP Status Codes

- **200** OK — Success
- **201** Created — Resource created
- **304** Not Modified — ETag cache hit
- **400** Bad Request — Invalid parameters
- **401** Unauthorized — Missing/invalid authentication
- **404** Not Found — Resource not found
- **429** Too Many Requests — Rate limit exceeded
- **500** Internal Server Error

## Remaining Work

- Additional endpoints as new module and UI needs arise
- Consider JWT auth alongside API keys for user-scoped operations

## Resources

- [DATABASE_GUIDE.md](DATABASE_GUIDE.md) — Schema reference and query patterns
- [DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md) — Development standards
- `ibl5/classes/Api/` — All API source code
