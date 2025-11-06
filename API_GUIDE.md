# IBL5 API Guide

**Last Updated:** November 6, 2025  
**Status:** Database is API-Ready ✅

## Quick Start

### Database Status
The database schema is fully prepared for API development:
- ✅ InnoDB tables with ACID transactions
- ✅ Foreign key constraints for data integrity
- ✅ Timestamps (`created_at`, `updated_at`) for caching
- ✅ UUIDs for secure public identifiers
- ✅ Database views for optimized queries

### Core API Entities

#### Players (`ibl_plr`)
- **Primary Key:** `pid` (int, internal)
- **Public ID:** `uuid` (varchar(36), for API)
- **Key Fields:** name, pos, tid, contract details, stats
- **View:** `vw_player_current` (current season data)

#### Teams (`ibl_team_info`)
- **Primary Key:** `teamid` (int, internal)
- **Public ID:** `uuid` (varchar(36), for API)
- **Key Fields:** team_name, team_city, owner, cap info
- **View:** `vw_team_standings` (real-time standings)

#### Games (`ibl_schedule`)
- **Primary Key:** `Date` (varchar, internal)
- **Public ID:** `uuid` (varchar(36), for API)
- **Key Fields:** Visitor, Home, VScore, HScore
- **View:** `vw_game_schedule` (with team details)

#### Statistics
- **Tables:** `ibl_*_stats`, `ibl_*_career_avgs`, `ibl_*_career_totals`
- **View:** `vw_player_stats_summary` (aggregated)

#### Trades
- **Tables:** `ibl_trade_info`, `ibl_trade_players`, `ibl_trade_picks`, `ibl_trade_cash`
- **View:** `vw_trade_history` (complete records)

## API Design Best Practices

### Use Database Views
Query optimized views instead of joining tables:
```php
// ✅ Good - Use database view
$query = "SELECT * FROM vw_player_current WHERE uuid = ?";

// ❌ Avoid - Complex joins in API
$query = "SELECT p.*, h.*, t.* FROM ibl_plr p 
          JOIN ibl_hist h ON p.pid = h.pid 
          JOIN ibl_team_info t ON p.tid = t.teamid 
          WHERE p.uuid = ?";
```

### Use UUIDs for Public IDs
Never expose internal integer IDs in API responses:
```php
// ✅ Good
return json_encode(['player_id' => $player['uuid']]);

// ❌ Bad - Exposes internal ID
return json_encode(['player_id' => $player['pid']]);
```

### Implement ETags for Caching
Use `updated_at` timestamps for efficient caching:
```php
$etag = md5($player['updated_at']);
header("ETag: \"{$etag}\"");

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && 
    $_SERVER['HTTP_IF_NONE_MATCH'] === "\"{$etag}\"") {
    http_response_code(304);
    exit;
}
```

### Use Prepared Statements
Always use prepared statements for security:
```php
// ✅ Good
$stmt = $db->prepare("SELECT * FROM vw_player_current WHERE uuid = ?");
$stmt->bind_param("s", $uuid);
$stmt->execute();

// ❌ Bad - SQL injection risk
$query = "SELECT * FROM vw_player_current WHERE uuid = '$uuid'";
```

## RESTful Endpoint Structure

### Players
```
GET    /api/v1/players              - List players
GET    /api/v1/players/{uuid}       - Get player details
GET    /api/v1/players/{uuid}/stats - Get player statistics
POST   /api/v1/players/{uuid}/sign  - Sign player (protected)
```

### Teams
```
GET    /api/v1/teams                - List teams
GET    /api/v1/teams/{uuid}         - Get team details
GET    /api/v1/teams/{uuid}/roster  - Get team roster
GET    /api/v1/teams/{uuid}/salary  - Get salary cap info
```

### Games
```
GET    /api/v1/games                - List games
GET    /api/v1/games/{uuid}         - Get game details
GET    /api/v1/games/{uuid}/boxscore - Get box score
```

### Statistics
```
GET    /api/v1/stats/leaders        - League leaders
GET    /api/v1/stats/team/{uuid}    - Team statistics
```

### Trades
```
GET    /api/v1/trades               - List trades
GET    /api/v1/trades/{uuid}        - Get trade details
POST   /api/v1/trades               - Propose trade (protected)
```

## Response Format

### Success Response
```json
{
  "status": "success",
  "data": {
    "player_id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Michael Jordan",
    "position": "SG",
    "team_id": "660e8400-e29b-41d4-a716-446655440001"
  },
  "meta": {
    "timestamp": "2025-11-06T19:53:45Z",
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
    "message": "Player with UUID not found",
    "details": "UUID: 550e8400-e29b-41d4-a716-446655440000"
  },
  "meta": {
    "timestamp": "2025-11-06T19:53:45Z",
    "version": "v1"
  }
}
```

## Authentication & Authorization

### API Key Authentication
```php
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!validateApiKey($apiKey)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}
```

### Permission Levels
- **Public:** Read-only access (players, teams, games, stats)
- **Team Owner:** Manage own team (depth chart, contract offers)
- **Commissioner:** Full access (trades, salary cap adjustments)

## Rate Limiting

### Recommended Limits
- Public endpoints: 100 requests/minute
- Authenticated endpoints: 1000 requests/minute
- Write operations: 10 requests/minute

### Implementation
```php
$key = "ratelimit:{$apiKey}:" . date('YmdHi');
$count = $redis->incr($key);
$redis->expire($key, 60);

if ($count > $limit) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit;
}
```

## Pagination

### Query Parameters
```
?page=1&per_page=25&sort=name&order=asc
```

### Response Headers
```
X-Total-Count: 450
X-Page: 1
X-Per-Page: 25
X-Total-Pages: 18
```

### Link Header (RFC 5988)
```
Link: <https://api.ibl.com/v1/players?page=2>; rel="next",
      <https://api.ibl.com/v1/players?page=18>; rel="last"
```

## Filtering & Searching

### Query Parameters
```
GET /api/v1/players?position=PG&team=550e8400&min_ppg=15
GET /api/v1/players?search=jordan&active=true
```

### Implementation Tips
- Use database indexes for filtered fields
- Limit search results to prevent slow queries
- Validate filter parameters

## CORS Configuration

### Headers for Public API
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Access-Control-Max-Age: 3600');
```

## Versioning Strategy

### URL Versioning (Recommended)
```
/api/v1/players
/api/v2/players
```

### Header Versioning (Alternative)
```
Accept: application/vnd.ibl.v1+json
```

## Error Codes

### HTTP Status Codes
- **200** OK - Success
- **201** Created - Resource created
- **204** No Content - Success, no body
- **304** Not Modified - ETag match
- **400** Bad Request - Invalid parameters
- **401** Unauthorized - Missing/invalid auth
- **403** Forbidden - Insufficient permissions
- **404** Not Found - Resource not found
- **409** Conflict - Business rule violation
- **429** Too Many Requests - Rate limit
- **500** Internal Server Error - Server error

### Custom Error Codes
```
PLAYER_NOT_FOUND
INVALID_CONTRACT
SALARY_CAP_EXCEEDED
ROSTER_FULL
INVALID_TRADE
PERMISSION_DENIED
```

## Performance Optimization

### Caching Strategy
1. **ETags:** Use timestamps for conditional requests
2. **Redis:** Cache frequently accessed data (5-15 min TTL)
3. **Database Views:** Pre-join common queries
4. **CDN:** Cache static responses

### Query Optimization
- Use database views for complex queries
- Leverage composite indexes
- Implement query result pagination
- Monitor slow query log

## Security Checklist

- [ ] All input validated and sanitized
- [ ] Prepared statements for all queries
- [ ] Output escaped (HTML, JSON)
- [ ] API keys stored securely (hashed)
- [ ] HTTPS enforced for all endpoints
- [ ] Rate limiting implemented
- [ ] SQL injection prevented
- [ ] XSS prevention in responses
- [ ] CSRF tokens for state changes
- [ ] Authorization checks on protected endpoints

## Testing API Endpoints

### Example cURL Commands
```bash
# Get player by UUID
curl -X GET "https://api.ibl.com/v1/players/550e8400-e29b-41d4-a716-446655440000"

# Get players with ETag
curl -X GET "https://api.ibl.com/v1/players" \
  -H "If-None-Match: \"abc123\""

# Create trade (authenticated)
curl -X POST "https://api.ibl.com/v1/trades" \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{"team1": "uuid1", "team2": "uuid2"}'
```

## OpenAPI Documentation

### Tools
- **Swagger UI:** Interactive API documentation
- **Postman:** API testing and documentation
- **OpenAPI Spec:** Generate from annotations

### Example OpenAPI Schema
```yaml
openapi: 3.0.0
info:
  title: IBL5 API
  version: 1.0.0
paths:
  /api/v1/players/{uuid}:
    get:
      summary: Get player by UUID
      parameters:
        - name: uuid
          in: path
          required: true
          schema:
            type: string
            format: uuid
      responses:
        200:
          description: Success
```

## Deployment Considerations

### Environment Configuration
- Use environment variables for sensitive data
- Separate configs for dev/staging/production
- Never commit API keys or secrets

### Monitoring
- Log all API requests (anonymized)
- Monitor response times
- Alert on error rate spikes
- Track rate limit hits

## Next Steps

1. Review database views in `schema.sql`
2. Implement authentication system
3. Create base API controller class
4. Build player endpoints first (most requested)
5. Add comprehensive tests
6. Document with OpenAPI/Swagger
7. Set up monitoring and logging
8. Deploy to staging environment

## Additional Resources
- **[Database Guide](DATABASE_GUIDE.md)** - Schema reference and best practices
- **[Development Guide](DEVELOPMENT_GUIDE.md)** - Refactoring and testing standards
- **[Copilot Instructions](COPILOT_AGENT.md)** - Coding standards and security
- **[Production Deployment](PRODUCTION_DEPLOYMENT_GUIDE.md)** - Deployment procedures
