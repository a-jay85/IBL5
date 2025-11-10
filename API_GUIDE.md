# API Development Guide

**Status:** Database is API-Ready ✅

## Database Preparation

**Ready:** InnoDB + Foreign Keys + Timestamps + UUIDs + Views

**Key Features:**
- UUIDs for secure public IDs (players, teams, games)
- Timestamps for ETag caching
- Database views for optimized queries
- ACID transactions

## Quick Reference

### Core Entities

**Players:** `ibl_plr` (internal ID: `pid`, public: `uuid`)  
**Teams:** `ibl_team_info` (internal: `teamid`, public: `uuid`)  
**Games:** `ibl_schedule` (public: `uuid`)

**Database Views:**
- `vw_player_current` - Current season data
- `vw_team_standings` - Real-time standings
- `vw_game_schedule` - Schedule with team details
- `vw_player_stats_summary` - Aggregated stats
- `vw_trade_history` - Trade records

### RESTful Endpoints

```
GET  /api/v1/players              - List players
GET  /api/v1/players/{uuid}       - Get player
GET  /api/v1/players/{uuid}/stats - Player stats
GET  /api/v1/teams                - List teams
GET  /api/v1/teams/{uuid}/roster  - Team roster
GET  /api/v1/games                - List games
GET  /api/v1/stats/leaders        - League leaders
```

## Best Practices

### 1. Use Database Views
```php
// ✅ Good - Use optimized view
$query = "SELECT * FROM vw_player_current WHERE uuid = ?";

// ❌ Avoid - Complex joins in code
$query = "SELECT p.*, h.*, t.* FROM ibl_plr p JOIN ...";
```

### 2. Use UUIDs for Public IDs
```php
// ✅ Good
return json_encode(['player_id' => $player['uuid']]);

// ❌ Bad - Exposes internal ID
return json_encode(['player_id' => $player['pid']]);
```

### 3. Implement ETags
```php
$etag = md5($player['updated_at']);
header("ETag: \"{$etag}\"");

if ($_SERVER['HTTP_IF_NONE_MATCH'] === "\"{$etag}\"") {
    http_response_code(304);
    exit;
}
```

### 4. Use Prepared Statements
```php
// ✅ Good
$stmt = $db->prepare("SELECT * FROM vw_player_current WHERE uuid = ?");
$stmt->bind_param("s", $uuid);
```

## Response Format

### Success
```json
{
  "status": "success",
  "data": {
    "player_id": "550e8400-...",
    "name": "Michael Jordan",
    "position": "SG"
  },
  "meta": {
    "timestamp": "2025-11-09T00:00:00Z"
  }
}
```

### Error
```json
{
  "status": "error",
  "error": {
    "code": "PLAYER_NOT_FOUND",
    "message": "Player not found"
  }
}
```

## Authentication

```php
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!validateApiKey($apiKey)) {
    http_response_code(401);
    exit;
}
```

**Permission Levels:**
- Public: Read-only (players, teams, games, stats)
- Team Owner: Manage own team
- Commissioner: Full access

## Rate Limiting

- Public: 100 requests/min
- Authenticated: 1000 requests/min
- Write operations: 10 requests/min

## Pagination

**Query:** `?page=1&per_page=25&sort=name&order=asc`

**Headers:**
```
X-Total-Count: 450
X-Page: 1
X-Per-Page: 25
Link: <.../players?page=2>; rel="next"
```

## HTTP Status Codes

- **200** OK
- **201** Created
- **304** Not Modified (ETag)
- **400** Bad Request
- **401** Unauthorized
- **404** Not Found
- **429** Too Many Requests
- **500** Internal Server Error

## Security Checklist

- [ ] Input validated
- [ ] Prepared statements (SQL injection)
- [ ] Output escaped (XSS)
- [ ] API keys hashed
- [ ] HTTPS enforced
- [ ] Rate limiting
- [ ] Authorization checks

## Testing

```bash
# Get player
curl https://api.ibl.com/v1/players/550e8400-...

# With ETag
curl -H "If-None-Match: \"abc123\"" https://api.ibl.com/v1/players

# Authenticated
curl -H "X-API-Key: key" -X POST https://api.ibl.com/v1/trades
```

## Resources

- [DATABASE_GUIDE.md](DATABASE_GUIDE.md) - Schema reference
- [DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md) - Coding standards
- Production schema: `ibl5/schema.sql`
