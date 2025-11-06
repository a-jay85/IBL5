# API Quick Start Guide - Using Phase 3 Features

**Last Updated:** November 6, 2025  
**Database Version:** v1.4 (Phase 3 Complete)

This quick guide shows API developers how to use the new Phase 3 features: UUIDs, timestamps, and database views.

---

## Overview of Phase 3 Features

Phase 3 added three critical API features to the database:

1. **UUIDs** - Secure, non-enumerable public identifiers
2. **Timestamps** - For ETags and Last-Modified headers
3. **Database Views** - Simplified, optimized queries

These features enable you to build a modern, secure, performant API following industry best practices.

---

## Using UUIDs for Public API Endpoints

### Why UUIDs?

**DON'T** expose sequential integer IDs in your API:
```
❌ GET /api/v1/players/123
❌ GET /api/v1/teams/5
```

**Why not?**
- Exposes how many players/teams exist
- Easy to enumerate all resources
- Security vulnerability
- Not distributed-system friendly

**DO** use UUIDs:
```
✅ GET /api/v1/players/550e8400-e29b-41d4-a716-446655440000
✅ GET /api/v1/teams/6ba7b810-9dad-11d1-80b4-00c04fd430c8
```

**Benefits:**
- Secure, non-guessable identifiers
- Prevents resource enumeration
- Industry standard practice
- Works with distributed systems

### Tables with UUIDs

| Table | UUID Column | Public API Resource |
|-------|-------------|-------------------|
| `ibl_plr` | `uuid` | Players |
| `ibl_team_info` | `uuid` | Teams |
| `ibl_schedule` | `uuid` | Games/Schedule |
| `ibl_draft` | `uuid` | Draft Picks |
| `ibl_box_scores` | `uuid` | Box Scores |

### Query Examples

**Get player by UUID:**
```sql
SELECT * FROM ibl_plr WHERE uuid = '550e8400-e29b-41d4-a716-446655440000';
```

**Get team by UUID:**
```sql
SELECT * FROM ibl_team_info WHERE uuid = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
```

**Get game by UUID:**
```sql
SELECT * FROM ibl_schedule WHERE uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
```

### API Implementation Example (PHP/Laravel)

```php
// routes/api.php
Route::get('/players/{uuid}', [PlayerController::class, 'show']);

// app/Http/Controllers/PlayerController.php
public function show(string $uuid)
{
    $player = DB::table('ibl_plr')
        ->where('uuid', $uuid)
        ->first();
        
    if (!$player) {
        return response()->json(['error' => 'Player not found'], 404);
    }
    
    return response()->json($player);
}
```

### Important Notes

- UUIDs are indexed with UNIQUE constraint for fast lookups
- Use `uuid` column for **all** public API endpoints
- Keep integer IDs (`pid`, `teamid`) for **internal** database joins only
- Never expose integer IDs in API responses

---

## Using Timestamps for Caching

### Why Timestamps?

Timestamps enable efficient HTTP caching through:
- **ETags** - Entity tags for cache validation
- **Last-Modified** headers - When resource last changed
- **Conditional requests** - If-None-Match, If-Modified-Since

This dramatically improves API performance by avoiding unnecessary data transfers.

### Tables with Timestamps

**19 tables** now have `created_at` and `updated_at` columns:
- `ibl_plr`, `ibl_team_info`, `ibl_schedule`
- `ibl_hist`, `ibl_box_scores`, `ibl_standings`
- `ibl_draft`, `ibl_fa_offers`, `ibl_trade_info`
- And 10 more tables

### Implementing ETags

**Example: Player API with ETags**

```php
public function show(string $uuid)
{
    $player = DB::table('ibl_plr')
        ->where('uuid', $uuid)
        ->first();
        
    if (!$player) {
        return response()->json(['error' => 'Player not found'], 404);
    }
    
    // Generate ETag from updated_at timestamp
    $etag = md5($player->updated_at);
    
    // Check If-None-Match header
    if (request()->header('If-None-Match') === $etag) {
        return response()->noContent(304); // Not Modified
    }
    
    return response()
        ->json($player)
        ->header('ETag', $etag)
        ->header('Last-Modified', $player->updated_at)
        ->header('Cache-Control', 'max-age=3600, must-revalidate');
}
```

### Client Usage

**First Request:**
```http
GET /api/v1/players/550e8400-e29b-41d4-a716-446655440000
Authorization: Bearer token...

HTTP/1.1 200 OK
ETag: "abc123xyz"
Last-Modified: Wed, 06 Nov 2025 12:00:00 GMT
Cache-Control: max-age=3600, must-revalidate

{
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "name": "John Doe",
  ...
}
```

**Subsequent Request (with cached ETag):**
```http
GET /api/v1/players/550e8400-e29b-41d4-a716-446655440000
Authorization: Bearer token...
If-None-Match: "abc123xyz"

HTTP/1.1 304 Not Modified
ETag: "abc123xyz"
Last-Modified: Wed, 06 Nov 2025 12:00:00 GMT
```

**Benefits:**
- ✅ Reduces bandwidth usage
- ✅ Improves response times
- ✅ Reduces server load
- ✅ Better user experience

---

## Using Database Views

### Why Database Views?

**Before Phase 3:** Complex joins in application code
```php
// Complex query with joins
$players = DB::table('ibl_plr as p')
    ->leftJoin('ibl_team_info as t', 'p.tid', '=', 't.teamid')
    ->select('p.*', 't.team_city', 't.team_name', 't.owner_name')
    ->selectRaw('ROUND(p.stats_fgm / NULLIF(p.stats_fga, 0), 3) as fg_pct')
    ->selectRaw('ROUND(p.stats_ftm / NULLIF(p.stats_fta, 0), 3) as ft_pct')
    ->where('p.active', 1)
    ->where('p.retired', 0)
    ->get();
```

**After Phase 3:** Simple view query
```php
// Simple query using view
$players = DB::table('vw_player_current')->get();
```

**Benefits:**
- ✅ Simpler application code
- ✅ Consistent formatting
- ✅ Optimized by database
- ✅ Calculated fields included
- ✅ Single source of truth

### Available Views

#### 1. vw_player_current

**Purpose:** Active players with team information and current stats

**Use for:**
- `GET /api/v1/players` - List all active players
- `GET /api/v1/players/{uuid}` - Get player details
- `GET /api/v1/teams/{uuid}/roster` - Get team roster

**Columns include:**
- `player_uuid` - Player UUID (for API)
- `pid` - Player ID (internal only)
- `name`, `nickname`, `age`, `position`
- `active`, `retired`, `experience`, `bird_rights`
- `team_uuid`, `teamid`, `team_city`, `team_name`, `owner_name`, `full_team_name`
- `current_salary`, `year1_salary`, `year2_salary`
- `games_played`, `minutes_played`, `field_goals_made`, etc.
- `fg_percentage`, `ft_percentage`, `three_pt_percentage`, `points_per_game` (calculated!)
- `created_at`, `updated_at` (for ETags)

**Example:**
```php
// Get all active players
$players = DB::table('vw_player_current')
    ->orderBy('points_per_game', 'desc')
    ->limit(25)
    ->get();

// Get player by UUID
$player = DB::table('vw_player_current')
    ->where('player_uuid', $uuid)
    ->first();

// Get team roster
$roster = DB::table('vw_player_current')
    ->where('team_uuid', $teamUuid)
    ->orderBy('position')
    ->get();
```

#### 2. vw_team_standings

**Purpose:** Complete standings with formatted records

**Use for:**
- `GET /api/v1/standings` - Full league standings
- `GET /api/v1/standings/{conference}` - Conference standings
- `GET /api/v1/teams/{uuid}/standing` - Team standing

**Columns include:**
- `team_uuid` - Team UUID
- `teamid`, `team_city`, `team_name`, `full_team_name`, `owner_name`
- `league_record`, `win_percentage`
- `conference`, `conference_record`, `conference_games_back`
- `division`, `division_record`, `division_games_back`
- `home_wins`, `home_losses`, `home_record` (formatted!)
- `away_wins`, `away_losses`, `away_record` (formatted!)
- `games_remaining`
- `clinched_conference`, `clinched_division`, `clinched_playoffs`
- `conference_magic_number`, `division_magic_number`
- `created_at`, `updated_at`

**Example:**
```php
// Get full league standings
$standings = DB::table('vw_team_standings')
    ->orderByRaw("FIELD(conference, 'Eastern', 'Western')")
    ->orderBy('win_percentage', 'desc')
    ->get();

// Get conference standings
$eastStandings = DB::table('vw_team_standings')
    ->where('conference', 'Eastern')
    ->orderBy('win_percentage', 'desc')
    ->get();
```

#### 3. vw_schedule_upcoming

**Purpose:** Schedule with full team information

**Use for:**
- `GET /api/v1/schedule` - List games by date range
- `GET /api/v1/schedule/upcoming` - Upcoming games
- `GET /api/v1/games/{uuid}` - Get game details

**Columns include:**
- `game_uuid` - Game UUID
- `schedule_id`, `season_year`, `game_date`, `box_score_id`
- `visitor_uuid`, `visitor_team_id`, `visitor_city`, `visitor_name`, `visitor_full_name`, `visitor_score`
- `home_uuid`, `home_team_id`, `home_city`, `home_name`, `home_full_name`, `home_score`
- `game_status` ('scheduled' or 'completed')
- `created_at`, `updated_at`

**Example:**
```php
// Get upcoming games
$upcoming = DB::table('vw_schedule_upcoming')
    ->where('game_date', '>=', now())
    ->where('game_status', 'scheduled')
    ->orderBy('game_date')
    ->limit(10)
    ->get();

// Get games by date range
$games = DB::table('vw_schedule_upcoming')
    ->whereBetween('game_date', [$startDate, $endDate])
    ->orderBy('game_date')
    ->get();
```

#### 4. vw_player_career_stats

**Purpose:** Career statistics summary

**Use for:**
- `GET /api/v1/players/{uuid}/career` - Player career stats

**Columns include:**
- `player_uuid` - Player UUID
- `pid`, `name`
- `career_games`, `career_minutes`, `career_points`, `career_rebounds`, `career_assists`, etc.
- `ppg_career`, `rpg_career`, `apg_career` (calculated averages!)
- `fg_pct_career`, `ft_pct_career`, `three_pt_pct_career`
- `playoff_minutes`
- `draft_year`, `draft_round`, `draft_pick`, `drafted_by_team`
- `created_at`, `updated_at`

**Example:**
```php
$careerStats = DB::table('vw_player_career_stats')
    ->where('player_uuid', $uuid)
    ->first();
```

#### 5. vw_free_agency_offers

**Purpose:** Free agency market overview

**Use for:**
- `GET /api/v1/free-agency/offers` - Current FA offers
- `GET /api/v1/free-agency/offers/{uuid}` - Specific offer

**Columns include:**
- `offer_id`
- `player_uuid`, `pid`, `player_name`, `position`, `age`
- `team_uuid`, `teamid`, `team_city`, `team_name`, `full_team_name`
- `year1_amount`, `year2_amount`, ..., `year6_amount`
- `total_contract_value` (calculated!)
- `modifier`, `random`, `perceived_value`
- `is_mle`, `is_lle`
- `created_at`, `updated_at`

**Example:**
```php
$offers = DB::table('vw_free_agency_offers')
    ->orderBy('total_contract_value', 'desc')
    ->get();
```

### View Performance Tips

1. **Views are not materialized** - They execute the underlying query each time
2. **Use WHERE clauses** - Filter in your query to leverage indexes
3. **Use LIMIT** - Always paginate results
4. **Cache results** - Use Redis/Memcached for frequently accessed data
5. **Monitor performance** - Use EXPLAIN to verify query plans

---

## Complete API Endpoint Examples

### Player API

```php
// List all active players (with pagination)
Route::get('/api/v1/players', function (Request $request) {
    $perPage = $request->input('per_page', 25);
    $page = $request->input('page', 1);
    
    $players = DB::table('vw_player_current')
        ->orderBy('name')
        ->paginate($perPage);
    
    return response()->json($players)
        ->header('Cache-Control', 'max-age=300');
});

// Get specific player by UUID
Route::get('/api/v1/players/{uuid}', function (string $uuid) {
    $player = DB::table('vw_player_current')
        ->where('player_uuid', $uuid)
        ->first();
        
    if (!$player) {
        return response()->json(['error' => 'Player not found'], 404);
    }
    
    $etag = md5($player->updated_at);
    
    if (request()->header('If-None-Match') === $etag) {
        return response()->noContent(304);
    }
    
    return response()
        ->json($player)
        ->header('ETag', $etag)
        ->header('Last-Modified', $player->updated_at)
        ->header('Cache-Control', 'max-age=3600');
});

// Get player career stats
Route::get('/api/v1/players/{uuid}/career', function (string $uuid) {
    $stats = DB::table('vw_player_career_stats')
        ->where('player_uuid', $uuid)
        ->first();
        
    if (!$stats) {
        return response()->json(['error' => 'Player not found'], 404);
    }
    
    return response()
        ->json($stats)
        ->header('Cache-Control', 'max-age=86400'); // Cache for 24 hours
});
```

### Team API

```php
// List all teams
Route::get('/api/v1/teams', function () {
    $teams = DB::table('ibl_team_info')
        ->select('uuid', 'teamid', 'team_city', 'team_name', 'owner_name')
        ->orderBy('team_city')
        ->get();
        
    return response()
        ->json($teams)
        ->header('Cache-Control', 'max-age=3600');
});

// Get team roster
Route::get('/api/v1/teams/{uuid}/roster', function (string $uuid) {
    $roster = DB::table('vw_player_current')
        ->where('team_uuid', $uuid)
        ->orderBy('position')
        ->orderBy('name')
        ->get();
        
    return response()->json($roster);
});
```

### Schedule API

```php
// Get schedule by date range
Route::get('/api/v1/schedule', function (Request $request) {
    $startDate = $request->input('start_date', now()->toDateString());
    $endDate = $request->input('end_date', now()->addDays(30)->toDateString());
    
    $games = DB::table('vw_schedule_upcoming')
        ->whereBetween('game_date', [$startDate, $endDate])
        ->orderBy('game_date')
        ->get();
        
    return response()->json($games);
});

// Get upcoming games
Route::get('/api/v1/schedule/upcoming', function () {
    $upcoming = DB::table('vw_schedule_upcoming')
        ->where('game_date', '>=', now())
        ->where('game_status', 'scheduled')
        ->orderBy('game_date')
        ->limit(10)
        ->get();
        
    return response()
        ->json($upcoming)
        ->header('Cache-Control', 'max-age=300');
});
```

### Standings API

```php
// Get full league standings
Route::get('/api/v1/standings', function () {
    $standings = DB::table('vw_team_standings')
        ->orderByRaw("FIELD(conference, 'Eastern', 'Western')")
        ->orderBy('win_percentage', 'desc')
        ->get();
        
    return response()
        ->json($standings)
        ->header('Cache-Control', 'max-age=600');
});

// Get conference standings
Route::get('/api/v1/standings/{conference}', function (string $conference) {
    $standings = DB::table('vw_team_standings')
        ->where('conference', $conference)
        ->orderBy('win_percentage', 'desc')
        ->get();
        
    return response()
        ->json($standings)
        ->header('Cache-Control', 'max-age=600');
});
```

---

## Best Practices Summary

### 1. Always Use UUIDs in Public APIs
```php
✅ /api/v1/players/{uuid}
❌ /api/v1/players/{id}
```

### 2. Implement ETags for Caching
```php
$etag = md5($resource->updated_at);
if (request()->header('If-None-Match') === $etag) {
    return response()->noContent(304);
}
return response()->json($resource)->header('ETag', $etag);
```

### 3. Use Database Views for Complex Queries
```php
✅ DB::table('vw_player_current')->get()
❌ Complex multi-table joins in application
```

### 4. Always Paginate List Endpoints
```php
->paginate($perPage)
```

### 5. Set Appropriate Cache Headers
```php
->header('Cache-Control', 'max-age=3600, must-revalidate')
```

### 6. Never Expose Internal IDs
```php
✅ 'uuid' => $player->player_uuid
❌ 'id' => $player->pid
```

---

## Additional Resources

- **Database Schema Guide:** `/DATABASE_SCHEMA_GUIDE.md`
- **Schema Improvements:** `/DATABASE_SCHEMA_IMPROVEMENTS.md`
- **Migration Files:** `/ibl5/migrations/`
- **ER Diagrams:** `/DATABASE_ER_DIAGRAM.md`
- **Future Phases:** `/DATABASE_FUTURE_PHASES.md`

---

## Support

For questions or issues:
1. Review the database documentation
2. Check migration README for troubleshooting
3. Verify queries with EXPLAIN
4. Monitor slow query log

**Database Status:** ✅ FULLY API-READY 🚀
