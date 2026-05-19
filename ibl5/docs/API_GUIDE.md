---
description: REST API architectural overview — auth, rate limiting, ETag caching, controller inventory.
last_verified: 2026-05-19
---

# API Guide

**Status:** Implemented ✅ — 17 controllers, API key auth, rate limiting, ETag caching, pagination, CSV export.

## Architecture

```
ibl5/classes/Api/
├── Router.php                     # Route dispatch
├── Cache/
│   └── ETagHandler.php            # HTTP ETag caching
├── Controller/                    # 17 endpoint controllers
│   ├── GameBoxscoreController.php
│   ├── GameDetailController.php
│   ├── GameListController.php
│   ├── InjuriesController.php
│   ├── LeadersController.php
│   ├── PlayerDetailController.php
│   ├── PlayerExportController.php
│   ├── PlayerHistoryController.php
│   ├── PlayerListController.php
│   ├── PlayerStatsController.php
│   ├── SeasonController.php
│   ├── StandingsController.php
│   ├── TeamDetailController.php
│   ├── TeamListController.php
│   ├── TeamRosterController.php
│   ├── TradeAcceptController.php
│   └── TradeDeclineController.php
└── Middleware/
    ├── Contracts/
    ├── ApiKeyAuthenticator.php    # API key validation
    ├── RateLimiter.php            # Per-key rate limiting
    └── SystemClock.php
```

## Features

- **Authentication:** API key validation via `ApiKeyAuthenticator`
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

- OpenAPI/Swagger documentation generation (endpoint-by-endpoint reference deferred — see backlog 9.4 follow-up)
- Additional endpoints as IBL6 frontend needs arise
- Consider JWT auth alongside API keys for user-scoped operations

## Resources

- [DATABASE_GUIDE.md](DATABASE_GUIDE.md) — Schema reference and query patterns
- [DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md) — Development standards
- `ibl5/classes/Api/` — All API source code
