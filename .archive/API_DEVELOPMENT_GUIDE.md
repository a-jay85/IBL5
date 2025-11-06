# API Development Quick Reference

This guide provides essential information for developing an API backend for the IBL5 system.

## Current Schema State

- **Total Tables:** 136
- **IBL-Specific Tables:** ~65
- **Legacy PhpNuke Tables:** ~71
- **Engine:** Mostly MyISAM (125 tables) - **NEEDS CONVERSION TO InnoDB**
- **Foreign Keys:** None currently - **CRITICAL TO ADD**

## Priority Actions Before API Development

### 1. Run Database Migrations (CRITICAL)

**Phase 1: Critical Infrastructure** (Required)
```bash
mysql -u username -p database < ibl5/migrations/001_critical_improvements.sql
```

This provides:
- ✅ InnoDB for ACID transactions
- ✅ Row-level locking for API concurrency
- ✅ Critical indexes for performance
- ✅ Timestamps for caching/ETags

**Phase 2: Foreign Keys** (Highly Recommended)
```bash
mysql -u username -p database < ibl5/migrations/002_add_foreign_keys.sql
```

This provides:
- ✅ Data integrity
- ✅ Referential constraint enforcement
- ✅ Cascade operations

### 2. Review Schema Documentation

See `DATABASE_SCHEMA_IMPROVEMENTS.md` for:
- Detailed improvement recommendations
- Ranking by priority and impact
- API-specific considerations
- Implementation roadmap

## Core API Entities

### Players (`ibl_plr`)

**Primary Key:** `pid` (int)

**Recommended Endpoints:**
```
GET    /api/v1/players              # List all active players
GET    /api/v1/players/{id}         # Get player details
GET    /api/v1/players/{id}/stats   # Get player statistics
GET    /api/v1/teams/{id}/players   # Get team roster
```

**Key Columns:**
- `pid` - Player ID (primary key)
- `name` - Player name
- `tid` - Team ID (foreign key to ibl_team_info)
- `pos` - Position (PG, SG, SF, PF, C)
- `active` - Is player active (0/1)
- `retired` - Is player retired (0/1)
- Stats columns: `stats_gm`, `stats_min`, `stats_fgm`, etc.

**Important Indexes:**
- `idx_tid` - For team roster queries
- `idx_tid_active` - For active team roster
- `idx_name` - For player search

**After Migration:**
- `created_at`, `updated_at` - For API caching

### Teams (`ibl_team_info`)

**Primary Key:** `teamid` (int)

**Recommended Endpoints:**
```
GET    /api/v1/teams                # List all teams
GET    /api/v1/teams/{id}           # Get team details
GET    /api/v1/teams/{id}/stats     # Get team statistics
GET    /api/v1/teams/{id}/schedule  # Get team schedule
```

**Key Columns:**
- `teamid` - Team ID (primary key)
- `team_city` - City name
- `team_name` - Team name
- `owner_name` - General manager name
- `owner_email` - GM email
- `discordID` - Discord integration

**Key Indexes:**
- `PRIMARY KEY` on `teamid`
- `team_name` index (existing)
- `idx_owner_email` (after migration)

### Schedule (`ibl_schedule`)

**Primary Key:** `SchedID` (int)

**Recommended Endpoints:**
```
GET    /api/v1/schedule             # List games by date range
GET    /api/v1/schedule/{id}        # Get game details
GET    /api/v1/teams/{id}/schedule  # Team schedule
```

**Key Columns:**
- `SchedID` - Schedule ID (primary key)
- `Year` - Season year
- `Date` - Game date
- `Visitor` - Visiting team ID
- `Home` - Home team ID
- `VScore` - Visitor score
- `HScore` - Home score
- `BoxID` - Link to box scores

**Important Indexes:**
- `idx_year_date` - For date range queries
- `idx_visitor`, `idx_home` - For team schedule

**Foreign Keys (after Phase 2):**
- `Visitor` → `ibl_team_info.teamid`
- `Home` → `ibl_team_info.teamid`

### Standings (`ibl_standings`)

**Primary Key:** `tid` (int)

**Recommended Endpoints:**
```
GET    /api/v1/standings            # Full league standings
GET    /api/v1/standings/conference/{conf}  # Conference standings
GET    /api/v1/standings/division/{div}     # Division standings
```

**Key Columns:**
- `tid` - Team ID (primary key, foreign key to ibl_team_info)
- `team_name` - Team name
- `leagueRecord` - W-L record (string)
- `pct` - Win percentage
- `conference` - Eastern or Western
- `division` - Division name
- Win/loss breakdown by home/away/conference/division

**Important Indexes:**
- `idx_conference` - For conference standings
- `idx_division` - For division standings

### Historical Stats (`ibl_hist`)

**Primary Key:** `nuke_iblhist` (auto_increment)

**Recommended Endpoints:**
```
GET    /api/v1/players/{id}/history         # Player career history
GET    /api/v1/players/{id}/history/{year}  # Player season stats
GET    /api/v1/teams/{id}/history/{year}    # Team season roster
```

**Key Columns:**
- `pid` - Player ID (foreign key to ibl_plr)
- `year` - Season year
- `team` - Team name
- `teamid` - Team ID
- Season statistics: `games`, `minutes`, `fgm`, `fga`, etc.
- Ratings: `r_2ga`, `r_2gp`, `r_fta`, etc.

**Important Indexes:**
- `idx_pid_year` - For player season history
- `idx_teamid_year` - For team rosters by season
- `unique_composite_key` on (pid, name, year)

### Box Scores (`ibl_box_scores`)

**No Primary Key** (should add one!)

**Recommended Endpoints:**
```
GET    /api/v1/games/{date}/boxscores       # All box scores for date
GET    /api/v1/games/{id}/boxscore          # Game box score
GET    /api/v1/players/{id}/gamelog         # Player game log
```

**Key Columns:**
- `Date` - Game date
- `pid` - Player ID
- `name` - Player name
- `visitorTID`, `homeTID` - Team IDs
- Game stats: `gameMIN`, `game2GM`, `game2GA`, etc.

**Important Indexes:**
- `idx_date` - For daily box scores
- `idx_pid` - For player game logs
- `idx_date_pid` - Combined lookup

### Draft System (`ibl_draft`, `ibl_draft_picks`, `ibl_draft_class`)

**Recommended Endpoints:**
```
GET    /api/v1/draft/{year}                 # Draft results by year
GET    /api/v1/draft/{year}/picks           # Available picks
GET    /api/v1/draft/{year}/prospects       # Draft class
GET    /api/v1/teams/{id}/draft-picks       # Team's draft assets
```

## API Design Recommendations

### 1. Use RESTful Conventions

```
GET     /api/v1/resource       # List
POST    /api/v1/resource       # Create
GET     /api/v1/resource/{id}  # Get one
PUT     /api/v1/resource/{id}  # Update
PATCH   /api/v1/resource/{id}  # Partial update
DELETE  /api/v1/resource/{id}  # Delete
```

### 2. Implement Pagination

```
GET /api/v1/players?page=1&per_page=25
```

Response:
```json
{
  "data": [...],
  "pagination": {
    "current_page": 1,
    "per_page": 25,
    "total": 450,
    "total_pages": 18,
    "has_next": true,
    "has_prev": false
  }
}
```

### 3. Use Filtering

```
GET /api/v1/players?team_id=5&active=1&position=PG
GET /api/v1/schedule?start_date=2024-01-01&end_date=2024-01-31
GET /api/v1/standings?conference=Eastern
```

### 4. Support Field Selection (Sparse Fieldsets)

```
GET /api/v1/players?fields=pid,name,pos,team_name
```

### 5. Include Related Resources

```
GET /api/v1/players/123?include=team,stats
```

Response:
```json
{
  "player": {
    "pid": 123,
    "name": "John Doe",
    "team": {
      "teamid": 5,
      "team_name": "Lakers"
    },
    "stats": {
      "ppg": 20.5,
      "rpg": 8.2
    }
  }
}
```

### 6. Use Standard HTTP Status Codes

- `200 OK` - Success
- `201 Created` - Resource created
- `204 No Content` - Success with no body
- `400 Bad Request` - Validation error
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Not authorized
- `404 Not Found` - Resource doesn't exist
- `422 Unprocessable Entity` - Semantic error
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - Server error

### 7. Implement Caching

Use `updated_at` timestamps (added in Phase 1 migration):

```http
GET /api/v1/players/123
ETag: "abc123xyz"
Last-Modified: Wed, 31 Oct 2024 12:00:00 GMT

# Client subsequent request
GET /api/v1/players/123
If-None-Match: "abc123xyz"
If-Modified-Since: Wed, 31 Oct 2024 12:00:00 GMT

# Response if not modified
304 Not Modified
```

### 8. Rate Limiting

```http
HTTP/1.1 200 OK
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1698764400
```

### 9. Versioning

Use URL versioning:
```
/api/v1/players
/api/v2/players  # Future version
```

### 10. Error Responses

Standard error format:
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid player ID",
    "details": {
      "field": "player_id",
      "value": "abc",
      "expected": "integer"
    }
  }
}
```

## Performance Optimization

### 1. Query Optimization

**Use Indexes:**
```sql
-- Good - uses index
SELECT * FROM ibl_plr WHERE tid = 5 AND active = 1;

-- Bad - doesn't use index (wildcard at start)
SELECT * FROM ibl_plr WHERE name LIKE '%Smith';

-- Good - uses index (wildcard at end)
SELECT * FROM ibl_plr WHERE name LIKE 'Smith%';
```

**Use LIMIT:**
```sql
-- Always paginate
SELECT * FROM ibl_plr WHERE active = 1 LIMIT 25 OFFSET 0;
```

**Select Only Needed Columns:**
```sql
-- Good
SELECT pid, name, pos FROM ibl_plr WHERE tid = 5;

-- Bad (transfers unnecessary data)
SELECT * FROM ibl_plr WHERE tid = 5;
```

### 2. Use Database Views

Create views for complex queries (after Phase 1 migration):

```sql
CREATE VIEW vw_active_players AS
SELECT 
  p.pid,
  p.name,
  p.pos,
  p.age,
  t.team_city,
  t.team_name,
  p.stats_gm AS games,
  ROUND(p.stats_fgm / NULLIF(p.stats_fga, 0), 3) AS fg_pct,
  ROUND(p.stats_ftm / NULLIF(p.stats_fta, 0), 3) AS ft_pct
FROM ibl_plr p
INNER JOIN ibl_team_info t ON p.tid = t.teamid
WHERE p.active = 1 AND p.retired = 0;
```

Then use in API:
```php
$players = DB::select('SELECT * FROM vw_active_players');
```

### 3. Connection Pooling

Use persistent connections:
```php
$pdo = new PDO(
    "mysql:host=localhost;dbname=ibl5",
    "username",
    "password",
    [PDO::ATTR_PERSISTENT => true]
);
```

### 4. Caching Layer

Implement Redis/Memcached for frequently accessed data:

```php
// Check cache first
$players = Cache::remember('active_players', 600, function () {
    return DB::select('SELECT * FROM vw_active_players');
});
```

### 5. Eager Loading

Avoid N+1 queries:

```php
// Bad - N+1 queries
$players = Player::all();
foreach ($players as $player) {
    echo $player->team->name; // Separate query for each player
}

// Good - 2 queries total
$players = Player::with('team')->get();
foreach ($players as $player) {
    echo $player->team->name;
}
```

## Security Considerations

### 1. SQL Injection Prevention

**Use Prepared Statements:**
```php
// Good
$stmt = $pdo->prepare('SELECT * FROM ibl_plr WHERE pid = ?');
$stmt->execute([$player_id]);

// Bad
$sql = "SELECT * FROM ibl_plr WHERE pid = $player_id";
$result = $pdo->query($sql);
```

### 2. Authentication

Implement JWT or OAuth2:
```http
GET /api/v1/players
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
```

### 3. Input Validation

Validate all inputs:
```php
$validator = validator($request->all(), [
    'team_id' => 'required|integer|exists:ibl_team_info,teamid',
    'active' => 'boolean',
    'page' => 'integer|min:1',
    'per_page' => 'integer|min:1|max:100'
]);
```

### 4. CORS

Configure CORS headers:
```http
Access-Control-Allow-Origin: https://iblhoops.net
Access-Control-Allow-Methods: GET, POST, PUT, DELETE
Access-Control-Allow-Headers: Content-Type, Authorization
```

### 5. Rate Limiting

Implement rate limiting per user/IP:
```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

## Testing

### 1. Unit Tests

Test individual components:
```php
public function test_can_get_active_players()
{
    $response = $this->get('/api/v1/players?active=1');
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => ['pid', 'name', 'pos', 'team_name']
        ]
    ]);
}
```

### 2. Integration Tests

Test API endpoints:
```php
public function test_can_get_player_details()
{
    $player = Player::factory()->create();
    
    $response = $this->get("/api/v1/players/{$player->pid}");
    
    $response->assertStatus(200);
    $response->assertJson([
        'pid' => $player->pid,
        'name' => $player->name
    ]);
}
```

### 3. Performance Tests

Test query performance:
```php
public function test_player_list_performance()
{
    Player::factory()->count(1000)->create();
    
    $start = microtime(true);
    $response = $this->get('/api/v1/players');
    $duration = microtime(true) - $start;
    
    $this->assertLessThan(1.0, $duration); // < 1 second
}
```

## Monitoring

### 1. Logging

Log all API requests:
```php
Log::info('API Request', [
    'endpoint' => $request->path(),
    'method' => $request->method(),
    'user' => $request->user()?->id,
    'ip' => $request->ip(),
    'duration' => $duration
]);
```

### 2. Metrics

Track key metrics:
- Request count
- Response time
- Error rate
- Cache hit rate
- Database query time

### 3. Alerting

Set up alerts for:
- High error rate (> 5%)
- Slow response time (> 2s)
- High database load
- Failed migrations

## Next Steps

1. **Run Phase 1 migration** (critical infrastructure)
2. **Test application** with InnoDB and new indexes
3. **Run Phase 2 migration** (foreign keys)
4. **Create API routes** using recommended endpoints
5. **Implement authentication** (JWT/OAuth2)
6. **Add caching layer** (Redis/Memcached)
7. **Write tests** (unit, integration, performance)
8. **Deploy to staging** and test
9. **Monitor and optimize** based on real usage
10. **Deploy to production** with monitoring

## Resources

- Full schema analysis: `DATABASE_SCHEMA_IMPROVEMENTS.md`
- Migration scripts: `ibl5/migrations/`
- Schema file: `ibl5/schema.sql`

## Support

For questions or issues with database schema:
1. Review schema documentation
2. Check migration README
3. Test queries with EXPLAIN
4. Monitor slow query log
