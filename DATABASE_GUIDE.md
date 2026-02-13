# IBL5 Database Guide

**Last Updated:** February 12, 2026
**Schema Version:** v1.5 - Production Ready

## Quick Reference

### ⚠️ SCHEMA VERIFICATION REQUIREMENT
**Always reference `ibl5/schema.sql` for table/column names and relationships.** Never assume database structures exist without verification. This prevents hallucination of non-existent tables.

### Current Status
- **Total Tables:** 136 (51 InnoDB, 84 MyISAM legacy, 23 views)
- **Foreign Keys:** 24 constraints implemented
- **Indexes:** 56+ performance indexes + 4 composite
- **API Ready:** ✅ Complete (Timestamps, UUIDs, Views)

### Key Tables
- **Players:** `ibl_plr` (main), `ibl_hist` (history), `ibl_plr_chunk` (stats)
- **Teams:** `ibl_team_info`, `ibl_standings`
- **Games:** `ibl_schedule`, `ibl_box_scores`, `ibl_box_scores_teams`
- **Contracts:** `ibl_fa_offers`, `ibl_trade_*` tables
- **Draft:** `ibl_draft`, `ibl_draft_picks`

## Schema Location
- **File:** `/ibl5/schema.sql` (MariaDB 10.6.20 export)
- **Migrations:** `/ibl5/migrations/` (for schema changes)

## Database Architecture

### Engine Status
- **InnoDB Tables (51):** All critical IBL tables - ACID transactions, row-level locking
- **MyISAM Tables (84):** Legacy PhpNuke CMS tables - evaluate before converting
- **Views (23):** Computed views replacing denormalized tables (stats, win/loss, awards, franchise history)

### API-Ready Features ✅
1. **Timestamps:** 19 tables have `created_at`, `updated_at` for caching/ETags
2. **UUIDs:** 5 critical tables (players, teams, games, etc.) for secure public IDs
3. **Views:** 23 database views replacing denormalized tables and optimizing API queries
   - `vw_player_current` - Current season player data
   - `vw_team_standings` - Real-time standings
   - `vw_team_awards` - All team awards (Div/Conf/Lottery from `ibl_team_awards`, IBL Champions from `vw_playoff_series_results`, HEAT Champions from `ibl_box_scores_teams`)
   - `vw_franchise_summary` - All-time wins/losses/winpct/playoffs/titles per team
   - `vw_playoff_series_results` - Playoff series outcomes derived from box scores
   - `vw_current_salary` - Salary resolution (replaces CASE pattern)
   - `vw_career_totals` - Regular season career totals
   - `vw_series_records` - Head-to-head records
   - Plus 15 additional views for stats, win/loss, and schedule data

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

### For Refactoring Validation
- **Always verify refactored code against production (iblhoops.net)**
- Compare database queries and output between localhost and production
- Ensure query results return identical data (same rows, same order, same values)
- Check that data transformations produce identical output
- If results don't match exactly, refactoring is not complete
- Use database tools (e.g., query comparison, row diffing) to identify discrepancies
- This prevents unintended behavior changes from refactoring

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
- **Team Management:** team_info, standings (team_history dropped Feb 2026, replaced by `vw_franchise_summary` and `vw_team_awards` views)
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
- **Migration 026 (Feb 2026):** Created `vw_playoff_series_results` view ✅
- **Migration 027 (Feb 2026):** Replaced win/loss tables with computed views ✅
- **Migration 028 (Feb 2026):** Replaced 9 stats tables with computed views ✅
- **Migration 030 (Feb 2026):** Dropped `ibl_team_history`, created `vw_team_awards` and `vw_franchise_summary` views ✅

## Additional Resources
- Schema Reference: `/ibl5/schema.sql`
- Migration Scripts: `/ibl5/migrations/`
- **[Development Guide](DEVELOPMENT_GUIDE.md)** - Refactoring and testing
- **[API Guide](API_GUIDE.md)** - API development best practices
- **[Refactoring History](ibl5/docs/REFACTORING_HISTORY.md)** - Complete refactoring timeline

## Need Help?
- Check schema.sql for table structures and relationships
- Review existing queries in codebase for patterns
- Use database views for complex API queries
- Follow migration best practices for schema changes
