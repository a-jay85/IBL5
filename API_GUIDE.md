# API Development Guide

**Status:** Database is API-Ready ✅ | **API Endpoints:** Not Yet Implemented

## Current State

### ✅ Database Preparation Complete

The database has been optimized and prepared for API development:

- **UUIDs:** Added to 5 core tables (players, teams, games, box scores, draft)
- **Timestamps:** `created_at`/`updated_at` on 19 tables for ETag caching
- **Database Views:** 5 optimized views ready for API queries
- **Foreign Keys:** 21 constraints for data integrity
- **ACID Transactions:** InnoDB engine on all core tables

### ❌ API Server Not Yet Built

No API endpoints currently exist. This guide provides the design blueprint for future API development.

## Database Resources Ready for API Use

### Core Tables with UUIDs

**Players:** `ibl_plr`
- Internal ID: `pid` (int)
- Public ID: `uuid` (varchar)

**Teams:** `ibl_team_info`
- Internal ID: `teamid` (int)
- Public ID: `uuid` (varchar)

**Games:** `ibl_schedule`
- Internal ID: `SchedID` (int)
- Public ID: `uuid` (varchar)

**Box Scores:** `ibl_box_scores`
- Public ID: `uuid` (varchar)

**Draft:** `ibl_draft`
- Public ID: `uuid` (varchar)

### Pre-Built Database Views

These views are already created and optimized for API queries:

1. **`vw_player_current`** - Active players with current season stats and team info
2. **`vw_team_standings`** - Real-time standings with calculated fields
3. **`vw_schedule_upcoming`** - Schedule with team names and game status
4. **`vw_player_career_stats`** - Career statistics summary with averages
5. **`vw_free_agency_offers`** - Free agency market overview

## Proposed API Design (Not Yet Implemented)

### Suggested RESTful Endpoints

```
GET  /api/v1/players              - List players
GET  /api/v1/players/{uuid}       - Get player details
GET  /api/v1/players/{uuid}/stats - Get player statistics
GET  /api/v1/teams                - List teams
GET  /api/v1/teams/{uuid}         - Get team details
GET  /api/v1/teams/{uuid}/roster  - Get team roster
GET  /api/v1/games                - List games
GET  /api/v1/games/{uuid}         - Get game details
GET  /api/v1/stats/leaders        - League leaders
```

## Implementation Guidelines (For Future Development)

### 1. Use Database Views

When building API endpoints, query the pre-built views instead of joining tables:

```php
// ✅ Recommended - Use optimized view
$query = "SELECT * FROM vw_player_current WHERE uuid = ?";

// ❌ Avoid - Complex joins in API code
$query = "SELECT p.*, h.*, t.* FROM ibl_plr p JOIN ...";
```

### 2. Use UUIDs for Public IDs

Always expose UUIDs in API responses, never internal integer IDs:

```php
// ✅ Secure - UUID prevents ID enumeration
return json_encode(['player_id' => $player['uuid']]);

// ❌ Insecure - Exposes internal database ID
return json_encode(['player_id' => $player['pid']]);
```

### 3. Implement ETag Caching

Use the `updated_at` timestamps for HTTP caching:

```php
$etag = md5($player['updated_at']);
header("ETag: \"{$etag}\"");

if ($_SERVER['HTTP_IF_NONE_MATCH'] === "\"{$etag}\"") {
    http_response_code(304);
    exit;
}
```

### 4. Always Use Prepared Statements

```php
// ✅ Safe from SQL injection
$stmt = $db->prepare("SELECT * FROM vw_player_current WHERE uuid = ?");
$stmt->bind_param("s", $uuid);
$stmt->execute();
```

## Proposed Response Format

### Success Response
```json
{
  "status": "success",
  "data": {
    "player_id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Michael Jordan",
    "position": "SG",
    "team": {
      "id": "660e8400-e29b-41d4-a716-446655440001",
      "name": "Chicago Bulls"
    }
  },
  "meta": {
    "timestamp": "2025-11-10T00:00:00Z",
    "version": "v1"
  }
}
```

### Error Response
```json
{
  "status": "error",
  "error": {
    "code": "PLAYER_NOT_FOUND",
    "message": "Player with specified UUID not found"
  },
  "meta": {
    "timestamp": "2025-11-10T00:00:00Z"
  }
}
```

## Recommended Features

### Authentication
```php
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!validateApiKey($apiKey)) {
    http_response_code(401);
    exit;
}
```

**Suggested Permission Levels:**
- **Public:** Read-only access (players, teams, games, stats)
- **Team Owner:** Manage own team roster and settings
- **Commissioner:** Full administrative access

### Rate Limiting

Suggested limits:
- Public endpoints: 100 requests/minute
- Authenticated endpoints: 1000 requests/minute
- Write operations: 10 requests/minute

### Pagination

Query parameters: `?page=1&per_page=25&sort=name&order=asc`

Response headers:
```
X-Total-Count: 450
X-Page: 1
X-Per-Page: 25
Link: <.../players?page=2>; rel="next"
```

## HTTP Status Codes

- **200** OK - Success
- **201** Created - Resource created
- **304** Not Modified - ETag cache hit
- **400** Bad Request - Invalid parameters
- **401** Unauthorized - Missing/invalid authentication
- **404** Not Found - Resource not found
- **429** Too Many Requests - Rate limit exceeded
- **500** Internal Server Error

## Security Implementation Checklist

- [ ] Input validation on all parameters
- [ ] Prepared statements for all queries
- [ ] Output escaping for all responses
- [ ] API keys stored hashed in database
- [ ] HTTPS enforced on all endpoints
- [ ] Rate limiting implemented
- [ ] Authorization checks on protected endpoints
- [ ] SQL injection prevention verified
- [ ] XSS prevention in JSON responses

## Getting Started with API Development

### Prerequisites

1. Review the database schema: `ibl5/schema.sql`
2. Understand the existing database views (already created)
3. Familiarize yourself with UUID implementation

### Step 1: Create API Directory Structure

```bash
mkdir -p ibl5/api/v1
mkdir -p ibl5/api/v1/controllers
mkdir -p ibl5/api/v1/middleware
```

### Step 2: Example Player Endpoint

Here's a sample implementation for a player endpoint:

```php
<?php
// ibl5/api/v1/players.php
require_once '../../mainfile.php';

header('Content-Type: application/json');

$uuid = $_GET['uuid'] ?? null;

if (!$uuid) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => [
            'code' => 'MISSING_UUID',
            'message' => 'Player UUID is required'
        ]
    ]);
    exit;
}

// Use the pre-built database view
$stmt = $db->prepare("SELECT * FROM vw_player_current WHERE uuid = ?");
$stmt->bind_param("s", $uuid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'error' => [
            'code' => 'PLAYER_NOT_FOUND',
            'message' => 'Player not found'
        ]
    ]);
    exit;
}

$player = $result->fetch_assoc();

// Implement ETag caching
$etag = md5($player['updated_at']);
header("ETag: \"{$etag}\"");

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && 
    $_SERVER['HTTP_IF_NONE_MATCH'] === "\"{$etag}\"") {
    http_response_code(304);
    exit;
}

echo json_encode([
    'status' => 'success',
    'data' => [
        'player_id' => $player['player_uuid'],
        'name' => $player['name'],
        'position' => $player['position'],
        'age' => $player['age'],
        'team' => [
            'id' => $player['team_uuid'],
            'name' => $player['full_team_name']
        ],
        'stats' => [
            'games' => $player['games_played'],
            'ppg' => $player['points_per_game'],
            'fg_pct' => $player['fg_percentage']
        ]
    ],
    'meta' => [
        'timestamp' => date('c'),
        'version' => 'v1'
    ]
]);
```

### Step 3: Testing Your API

Once endpoints are created, test with curl:

```bash
# Test player endpoint (replace with actual UUID from database)
curl http://localhost/ibl5/api/v1/players.php?uuid=YOUR-UUID-HERE

# Test with ETag
curl -H "If-None-Match: \"etag-value\"" \
  http://localhost/ibl5/api/v1/players.php?uuid=YOUR-UUID-HERE
```

## Next Steps for Implementation

1. **Create base API structure** - Directory structure and routing
2. **Implement authentication** - API key validation
3. **Add rate limiting** - Prevent API abuse
4. **Build core endpoints** - Players, teams, games
5. **Add pagination** - Handle large result sets
6. **Implement caching** - Use ETags and Redis
7. **Add documentation** - OpenAPI/Swagger specs
8. **Write tests** - Unit and integration tests

## Resources

- [DATABASE_GUIDE.md](DATABASE_GUIDE.md) - Schema reference and query patterns
- [DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md) - Coding standards and security
- `ibl5/schema.sql` - Complete database schema with views
- `ibl5/migrations/` - Database migration history

## Notes

The database infrastructure is fully prepared and optimized for API development. All that remains is building the API endpoints themselves using the guidelines in this document.
