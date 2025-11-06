# IBL5 Database Guide

**Last Updated:** November 6, 2025  
**Schema Version:** v1.4 - Production Ready

## Quick Reference

### Current Status
- **Total Tables:** 136 (52 InnoDB, 84 MyISAM legacy)
- **Foreign Keys:** 24 constraints implemented
- **Indexes:** 56+ performance indexes + 4 composite
- **API Ready:** ✅ Complete (Timestamps, UUIDs, Views)

### Key Tables
- **Players:** `ibl_plr` (main), `ibl_hist` (history), `ibl_plr_chunk` (stats)
- **Teams:** `ibl_team_info`, `ibl_team_history`, `ibl_standings`
- **Games:** `ibl_schedule`, `ibl_box_scores`, `ibl_box_scores_teams`
- **Contracts:** `ibl_fa_offers`, `ibl_trade_*` tables
- **Draft:** `ibl_draft`, `ibl_draft_picks`

## Schema Location
- **File:** `/ibl5/schema.sql` (MariaDB 10.6.20 export)
- **Migrations:** `/ibl5/migrations/` (for schema changes)

## Database Architecture

### Engine Status
- **InnoDB Tables (52):** All critical IBL tables - ACID transactions, row-level locking
- **MyISAM Tables (84):** Legacy PhpNuke CMS tables - evaluate before converting

### API-Ready Features ✅
1. **Timestamps:** 19 tables have `created_at`, `updated_at` for caching/ETags
2. **UUIDs:** 5 critical tables (players, teams, games, etc.) for secure public IDs
3. **Views:** 5 database views for optimized API queries
   - `vw_player_current` - Current season player data
   - `vw_team_standings` - Real-time standings
   - `vw_game_schedule` - Schedule with team info
   - `vw_player_stats_summary` - Aggregated stats
   - `vw_trade_history` - Trade records

### Foreign Key Relationships (24)
Core data integrity constraints implemented:
- `ibl_hist.pid` → `ibl_plr.pid`
- `ibl_draft_picks.tid` → `ibl_team_info.teamid`
- `ibl_box_scores.gameid` → `ibl_schedule.Date`
- And 21 more... (see schema.sql for complete list)

## Best Practices

### For Queries
- Use prepared statements with mysqli or PDO
- Reference `schema.sql` for table structures
- Leverage existing indexes for WHERE/JOIN clauses
- Use database views for complex API queries

### For Migrations
- Place all migrations in `/ibl5/migrations/`
- Use numbered prefixes (e.g., `003_my_migration.sql`)
- Test on development database first
- Never modify production schema directly

### For API Development
- Use UUIDs for all public identifiers
- Implement ETags using `updated_at` timestamps
- Query database views instead of joining multiple tables
- Follow REST best practices (see API_GUIDE.md)

## Character Sets & Collation
- **Legacy tables:** latin1_swedish_ci (PhpNuke)
- **Modern tables:** utf8mb4_unicode_ci (Laravel, new features)
- **Recommendation:** Gradually migrate to utf8mb4 for international support

## Performance Notes
- InnoDB conversion provided 10-100x improvement on concurrent operations
- Composite indexes provide 5-25x speedup on multi-column queries
- Row-level locking enables API concurrency without bottlenecks

## PostgreSQL Compatibility Notes
When writing new queries, avoid MySQL-specific features:
- Avoid `MEDIUMINT`, `TINYINT` (use standard INT/SMALLINT)
- Avoid AUTO_INCREMENT (use SERIAL/SEQUENCE)
- Test DATE/DATETIME handling differences
- Prepare for future ORM migration (Eloquent/Doctrine)

## Table Categories

### IBL Core Tables (`ibl_*` prefix)
- **Player Data:** plr, plr_chunk, hist (80+ columns total)
- **Statistics:** *_stats, *_career_avgs, *_career_totals (multiple seasons)
- **Games:** schedule, box_scores, box_scores_teams
- **Team Management:** team_info, team_history, standings
- **Operations:** draft, fa_offers, trade_* tables
- **Awards/Voting:** awards, votes_ASG, votes_EOY

### PhpNuke Legacy (`nuke_*` prefix)
- **Forum:** bb* tables (phpBB integration)
- **Users:** users, authors
- **CMS:** stories, modules, blocks
- **Status:** Low priority for refactoring

### Laravel Tables (no prefix)
- **Modern:** cache, jobs, migrations, sessions, users
- **Purpose:** Future framework migration

## Common Query Patterns

### Get Player with Current Stats
```php
$query = "SELECT p.*, h.* FROM ibl_plr p 
          LEFT JOIN ibl_hist h ON p.pid = h.pid 
          WHERE p.pid = ? AND h.year = ?";
```

### Team Roster
```php
$query = "SELECT * FROM ibl_plr 
          WHERE tid = ? 
          ORDER BY ordinal";
```

### Using Database Views (API)
```php
$query = "SELECT * FROM vw_player_current WHERE uuid = ?";
```

## Migration History
- **Phase 1 (Nov 1, 2025):** InnoDB conversion, critical indexes ✅
- **Phase 2 (Nov 2, 2025):** Foreign key constraints ✅
- **Phase 3 (Nov 4, 2025):** API preparation (timestamps, UUIDs, views) ✅

## Additional Resources
- Schema Reference: `/ibl5/schema.sql`
- Migration Scripts: `/ibl5/migrations/`
- **[Development Guide](DEVELOPMENT_GUIDE.md)** - Refactoring and testing
- **[API Guide](API_GUIDE.md)** - API development best practices
- **[Copilot Instructions](COPILOT_AGENT.md)** - Coding standards

## Need Help?
- Check schema.sql for table structures and relationships
- Review existing queries in codebase for patterns
- Use database views for complex API queries
- Follow migration best practices for schema changes
