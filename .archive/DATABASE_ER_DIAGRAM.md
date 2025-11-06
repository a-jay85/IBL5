# Database Schema ER Diagram

This document provides entity-relationship diagrams for the IBL5 database schema.

## Core Entities Diagram

```mermaid
erDiagram
    ibl_team_info ||--o{ ibl_plr : "has players"
    ibl_team_info ||--o{ ibl_schedule : "plays games (visitor)"
    ibl_team_info ||--o{ ibl_schedule : "plays games (home)"
    ibl_team_info ||--|| ibl_standings : "has standing"
    ibl_team_info ||--o{ ibl_team_offense_stats : "has stats"
    ibl_team_info ||--o{ ibl_team_defense_stats : "has stats"
    
    ibl_plr ||--o{ ibl_hist : "has history"
    ibl_plr ||--o{ ibl_box_scores : "has performances"
    ibl_plr ||--o{ ibl_playoff_stats : "has playoff stats"
    
    ibl_schedule ||--o{ ibl_box_scores : "has box scores"
    ibl_schedule ||--|| ibl_box_scores_teams : "has team stats"
    
    ibl_team_info {
        int teamid PK
        varchar team_city
        varchar team_name UK
        varchar owner_name
        varchar owner_email
        bigint discordID
    }
    
    ibl_plr {
        int pid PK
        varchar name UK
        int tid FK
        varchar pos
        int age
        int active
        int retired
        int stats_gm
        int stats_min
    }
    
    ibl_hist {
        int nuke_iblhist PK
        int pid FK
        varchar name
        int year
        int teamid FK
        int games
        int minutes
    }
    
    ibl_schedule {
        int SchedID PK
        year Year
        date Date
        int Visitor FK
        int Home FK
        int VScore
        int HScore
        int BoxID
    }
    
    ibl_standings {
        int tid PK,FK
        varchar team_name
        float pct
        varchar conference
        varchar division
        int homeWins
        int homeLosses
    }
    
    ibl_box_scores {
        date Date
        int pid FK
        varchar name
        int visitorTID FK
        int homeTID FK
        int gameMIN
        int game2GM
    }
```

## Draft System Diagram

```mermaid
erDiagram
    ibl_team_info ||--o{ ibl_draft : "drafts players"
    ibl_team_info ||--o{ ibl_draft_picks : "owns picks"
    ibl_team_info ||--o{ ibl_draft_picks : "original pick"
    ibl_draft_class ||--o| ibl_draft : "gets drafted"
    
    ibl_draft {
        int draft_id PK
        int year
        varchar team FK
        varchar player
        int round
        int pick
        datetime date
    }
    
    ibl_draft_picks {
        int pickid PK
        varchar ownerofpick FK
        varchar teampick FK
        varchar year
        char round
        varchar notes
    }
    
    ibl_draft_class {
        int id PK
        varchar name
        char pos
        int age
        float ranking
        int drafted
    }
```

## Free Agency and Trading Diagram

```mermaid
erDiagram
    ibl_plr ||--o{ ibl_fa_offers : "receives offers"
    ibl_plr ||--o| ibl_demands : "has demands"
    ibl_team_info ||--o{ ibl_fa_offers : "makes offers"
    
    ibl_trade_info }o--|| ibl_trade_autocounter : "part of trade"
    
    ibl_fa_offers {
        int primary_key PK
        varchar name FK
        varchar team FK
        int offer1
        int offer2
        int offer3
        float modifier
        int MLE
        int LLE
    }
    
    ibl_demands {
        varchar name PK,FK
        int dem1
        int dem2
        int dem3
        int dem4
        int dem5
        int dem6
    }
    
    ibl_trade_info {
        int tradeofferid FK
        int itemid
        varchar itemtype
        varchar from
        varchar to
        varchar approval
    }
    
    ibl_trade_autocounter {
        int counter PK
    }
```

## Statistics and Awards Diagram

```mermaid
erDiagram
    ibl_plr ||--o{ ibl_season_career_avgs : "career averages"
    ibl_plr ||--o{ ibl_playoff_career_avgs : "playoff averages"
    ibl_plr ||--o{ ibl_heat_career_avgs : "heat averages"
    ibl_plr ||--o{ ibl_olympics_career_avgs : "olympics averages"
    
    ibl_plr ||--o{ ibl_awards : "wins awards"
    ibl_team_info ||--o{ ibl_team_awards : "wins awards"
    
    ibl_awards {
        int table_ID PK
        int year
        varchar Award
        varchar name FK
    }
    
    ibl_team_awards {
        int ID PK
        varchar year
        varchar name
        varchar Award
    }
    
    ibl_season_career_avgs {
        int pid PK,FK
        varchar name
        int games
        decimal minutes
        decimal fgm
        decimal fga
        decimal fgpct
    }
```

## Voting System Diagram

```mermaid
erDiagram
    ibl_team_info ||--|| ibl_votes_ASG : "votes for All-Stars"
    ibl_team_info ||--|| ibl_votes_EOY : "votes for awards"
    
    ibl_votes_ASG {
        int teamid PK,FK
        varchar team_city
        varchar team_name
        varchar East_F1
        varchar East_F2
        varchar East_B1
        varchar West_F1
        varchar West_F2
    }
    
    ibl_votes_EOY {
        int teamid PK,FK
        varchar team_city
        varchar team_name
        varchar MVP_1
        varchar MVP_2
        varchar MVP_3
        varchar Six_1
        varchar ROY_1
        varchar GM_1
    }
```

## Key Relationships Summary

### Primary Entities

1. **ibl_team_info** (Teams)
   - Central entity for league teams
   - Referenced by: players, schedule, standings, stats, draft, trades, votes
   - Primary Key: `teamid`
   - Unique Key: `team_name`

2. **ibl_plr** (Players)
   - Central entity for player information
   - References: team (via `tid`)
   - Referenced by: history, box scores, stats, offers, awards
   - Primary Key: `pid`
   - Unique Key: `name`

3. **ibl_schedule** (Schedule)
   - Games and matchups
   - References: teams (visitor and home)
   - Referenced by: box scores
   - Primary Key: `SchedID`

### Relationship Cardinality

- **Team → Players**: One-to-Many (one team has many players)
- **Team → Schedule**: One-to-Many (one team plays many games)
- **Player → History**: One-to-Many (one player has many seasons)
- **Player → Box Scores**: One-to-Many (one player has many games)
- **Schedule → Box Scores**: One-to-Many (one game has many player performances)
- **Team → Standings**: One-to-One (one team has one current standing)

### Foreign Key Constraints (After Phase 2 Migration)

| Child Table | Column | Parent Table | Parent Column | On Delete | On Update |
|-------------|--------|--------------|---------------|-----------|-----------|
| ibl_plr | tid | ibl_team_info | teamid | RESTRICT | CASCADE |
| ibl_hist | pid | ibl_plr | pid | CASCADE | CASCADE |
| ibl_box_scores | pid | ibl_plr | pid | CASCADE | CASCADE |
| ibl_box_scores | visitorTID | ibl_team_info | teamid | RESTRICT | CASCADE |
| ibl_box_scores | homeTID | ibl_team_info | teamid | RESTRICT | CASCADE |
| ibl_schedule | Visitor | ibl_team_info | teamid | RESTRICT | CASCADE |
| ibl_schedule | Home | ibl_team_info | teamid | RESTRICT | CASCADE |
| ibl_standings | tid | ibl_team_info | teamid | CASCADE | CASCADE |
| ibl_draft | team | ibl_team_info | team_name | RESTRICT | CASCADE |
| ibl_fa_offers | name | ibl_plr | name | CASCADE | CASCADE |
| ibl_fa_offers | team | ibl_team_info | team_name | CASCADE | CASCADE |

## Indexes Overview

### Critical Indexes (Added in Phase 1)

**ibl_plr (Players)**
- `PRIMARY KEY (pid)`
- `KEY name (name)`
- `KEY teamname (teamname)`
- `KEY idx_tid (tid)` ← NEW
- `KEY idx_active (active)` ← NEW
- `KEY idx_retired (retired)` ← NEW
- `KEY idx_tid_active (tid, active)` ← NEW
- `KEY idx_pos (pos)` ← NEW

**ibl_hist (Player History)**
- `PRIMARY KEY (nuke_iblhist)`
- `UNIQUE KEY unique_composite_key (pid, name, year)`
- `KEY idx_pid_year (pid, year)` ← NEW
- `KEY idx_team_year (team, year)` ← NEW
- `KEY idx_year (year)` ← NEW

**ibl_schedule (Games)**
- `PRIMARY KEY (SchedID)`
- `KEY BoxID (BoxID)`
- `KEY idx_year (Year)` ← NEW
- `KEY idx_date (Date)` ← NEW
- `KEY idx_visitor (Visitor)` ← NEW
- `KEY idx_home (Home)` ← NEW
- `KEY idx_year_date (Year, Date)` ← NEW

**ibl_box_scores (Game Stats)**
- `KEY idx_date (Date)` ← NEW
- `KEY idx_pid (pid)` ← NEW
- `KEY idx_visitor_tid (visitorTID)` ← NEW
- `KEY idx_home_tid (homeTID)` ← NEW
- `KEY idx_date_pid (Date, pid)` ← NEW

## Common Query Patterns

### Get Team Roster
```sql
SELECT * FROM ibl_plr 
WHERE tid = ? AND active = 1
ORDER BY pos, ordinal;
-- Uses: idx_tid_active
```

### Get Player History
```sql
SELECT * FROM ibl_hist 
WHERE pid = ? AND year = ?;
-- Uses: idx_pid_year
```

### Get Team Schedule
```sql
SELECT * FROM ibl_schedule 
WHERE (Home = ? OR Visitor = ?) 
AND Year = ?
ORDER BY Date;
-- Uses: idx_home or idx_visitor, idx_year
```

### Get Daily Box Scores
```sql
SELECT bs.*, p.name, p.pos
FROM ibl_box_scores bs
INNER JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.Date = ?;
-- Uses: idx_date on box_scores, PRIMARY on plr
```

### Get Conference Standings
```sql
SELECT * FROM ibl_standings 
WHERE conference = 'Eastern'
ORDER BY pct DESC;
-- Uses: idx_conference
```

## Data Flow for Common Operations

### Player Trade Flow
```mermaid
graph TD
    A[Trade Initiated] --> B[Record in ibl_trade_info]
    B --> C[Update ibl_plr.tid]
    C --> D[Verify Foreign Key]
    D --> E{Valid Team?}
    E -->|Yes| F[Update Successful]
    E -->|No| G[Foreign Key Error]
    F --> H[Update ibl_standings]
    F --> I[Recalculate Team Stats]
```

### Game Simulation Flow
```mermaid
graph TD
    A[Simulate Game] --> B[Create ibl_schedule entry]
    B --> C[Generate Box Scores]
    C --> D[Insert ibl_box_scores]
    C --> E[Insert ibl_box_scores_teams]
    D --> F[Update ibl_plr stats]
    E --> G[Update ibl_team_offense_stats]
    E --> H[Update ibl_team_defense_stats]
    F --> I[Update ibl_hist for season]
    G --> J[Update ibl_standings]
    H --> J
```

## Schema Evolution Considerations

### Short-term (After Phase 1 & 2)
- ✅ InnoDB for ACID transactions
- ✅ Foreign key integrity
- ✅ Performance indexes
- ✅ Audit timestamps

### Medium-term (Next 6 months)
- 📋 Add UUIDs for public API
- 📋 Create materialized views
- 📋 Normalize depth chart data
- 📋 Standardize naming conventions

### Long-term (Next 12 months)
- 📋 Partition large historical tables
- 📋 Separate read/write replicas
- 📋 Implement time-series for stats
- 📋 Archive old seasons

## Notes

1. **Mixed Identifier Types**: Some relationships use `name` (VARCHAR) instead of numeric IDs. Consider migrating to numeric IDs in future.

2. **Free Agents**: Players with `tid = 0` are free agents and don't reference a team.

3. **Legacy Tables**: PhpNuke tables (nuke_*) are not shown in these diagrams but exist in the schema.

4. **Compound Keys**: Some tables use composite keys (pid, name, year) which is redundant. Consider simplifying.

5. **Naming Consistency**: After Phase 2, consider standardizing all ID columns to follow `*_id` pattern.

## References

- Full schema: `ibl5/schema.sql`
- Improvements doc: `DATABASE_SCHEMA_IMPROVEMENTS.md`
- Migrations: `ibl5/migrations/`
- API Guide: `API_DEVELOPMENT_GUIDE.md`
