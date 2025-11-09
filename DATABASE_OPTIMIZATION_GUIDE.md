# IBL5 Database Optimization Guide

**Last Updated:** November 9, 2025  
**Schema Version:** Production (schema.sql dated November 9, 2025)

## Executive Summary

This guide provides the authoritative reference for database optimization efforts in the IBL5 project. It consolidates all previous documentation and provides a clear roadmap forward based on the current production schema.

### Current Production Schema Status

- **Total Tables:** 136 (52 InnoDB, 84 MyISAM legacy)
- **Foreign Keys:** 21 constraints (production verified)
- **CHECK Constraints:** 24 constraints (production verified)
- **Indexes:** 60+ performance indexes
- **Timestamp Columns:** 19 tables with created_at/updated_at
- **UUID Support:** 5 tables (players, teams, schedule, draft, box scores)
- **Database Views:** 5 API-optimized views

## Completed Optimizations

### ✅ Phase 1: Critical Infrastructure (Completed November 1, 2025)

**Status:** Fully implemented in production schema

**Achievements:**
- Converted 52 critical IBL tables from MyISAM to InnoDB
- Added 56+ indexes for query performance
- Enabled ACID transactions
- Row-level locking for better concurrency
- 10-100x performance improvement on common queries

**Tables Converted:** All `ibl_*` core tables including:
- `ibl_plr` (players)
- `ibl_team_info` (teams)
- `ibl_hist` (historical stats)
- `ibl_schedule` (games)
- `ibl_standings` (standings)
- `ibl_box_scores` (box scores)
- All draft, trade, and free agency tables

### ✅ Phase 2: Foreign Key Relationships (Completed November 2, 2025)

**Status:** Fully implemented in production schema

**Achievements:**
- Added 21 foreign key constraints
- Established referential integrity
- Cascading updates/deletes where appropriate
- Self-documenting table relationships

**Key Relationships:**
- `ibl_hist.pid` → `ibl_plr.pid` (player history)
- `ibl_box_scores.pid` → `ibl_plr.pid` (box score players)
- `ibl_draft_picks.ownerofpick` → `ibl_team_info.team_name` (draft picks)
- `ibl_fa_offers.name` → `ibl_plr.name` (free agency)
- And 17 more relationships

**Tables with Foreign Keys (16 total):**
- ibl_box_scores
- ibl_box_scores_teams
- ibl_demands
- ibl_draft
- ibl_draft_picks
- ibl_fa_offers
- ibl_heat_stats
- ibl_hist
- ibl_olympics_stats
- ibl_playoff_stats
- ibl_power
- ibl_standings
- ibl_team_defense_stats
- ibl_team_offense_stats
- ibl_votes_ASG
- ibl_votes_EOY

### ✅ Phase 3: API Preparation (Completed November 4, 2025)

**Status:** Fully implemented in production schema

**Achievements:**

**Part 1 - Audit Timestamps:**
- Added `created_at` and `updated_at` to 19 tables
- Enables ETag support for API caching
- Provides audit trail for all data changes

**Part 2 - UUID Support:**
- Added UUID columns to 5 critical tables
- Secure public identifiers (prevents ID enumeration)
- Indexed for fast lookups

**Part 3 - Database Views:**
Created 5 optimized views for API queries:
1. `vw_player_current` - Active players with team info
2. `vw_team_standings` - Standings with calculated fields
3. `vw_schedule_upcoming` - Schedule with team names
4. `vw_player_career_stats` - Career statistics summary
5. `vw_free_agency_offers` - Free agency market overview

### ✅ Phase 5.1: Composite Indexes (Completed)

**Status:** Fully implemented in production schema

**Achievements:**
- Added 4 composite indexes for multi-column queries
- 5-25x speedup on common query patterns
- Optimized for JOIN operations

## Current Schema Analysis

### Tables with Both Foreign Keys AND CHECK Constraints

These 4 tables require special consideration for future migrations:

1. **ibl_box_scores**
   - Foreign Keys: 3 (player, home team, visitor team)
   - CHECK Constraints: 1 (minutes validation)

2. **ibl_draft**
   - Foreign Keys: 1 (team)
   - CHECK Constraints: 2 (round, pick validation)

3. **ibl_power**
   - Foreign Keys: 1 (team)
   - CHECK Constraints: 2 (ranking validation)

4. **ibl_standings**
   - Foreign Keys: 1 (team)
   - CHECK Constraints: Multiple (percentage validations)

**Important:** When altering these tables, MySQL may require temporarily disabling foreign key checks:
```sql
SET FOREIGN_KEY_CHECKS=0;
-- ALTER TABLE operations
SET FOREIGN_KEY_CHECKS=1;
```

## Pending Optimizations

### 🔄 Phase 4: Data Type Refinements (Ready to Implement)

**Status:** Migration file prepared but NOT YET APPLIED to production

**Location:** `ibl5/migrations/004_data_type_refinements.sql`

**Known Issues:** Column name mismatches documented in `MIGRATION_004_FIXES.md`

**Prerequisites:**
- MySQL 8.0+ required for CHECK constraints
- Requires correcting column name references
- Must handle foreign key interactions

**Estimated Benefits:**
- 30-50% storage reduction on statistics columns
- 10-20% query performance improvement
- Data validation at database level
- Better API data quality

**Components:**

1. **Integer Size Optimizations**
   - Convert INT to SMALLINT for counts (games, wins, losses)
   - Convert INT to TINYINT for ratings (0-100 scale)
   - Convert INT to MEDIUMINT for large counters

2. **ENUM Type Conversions**
   - Player positions: `ENUM('PG','SG','SF','PF','C','G','F','GF','')`
   - Conference: `ENUM('Eastern','Western')`

3. **CHECK Constraints** (requires MySQL 8.0+)
   - Age constraints (18-50 years)
   - Rating bounds (0-100)
   - Percentage ranges (0.000-1.000)
   - Team ID ranges (0-32)

**Critical Considerations:**

1. **Foreign Key Interference:**
   - Tables with both FK and CHECK constraints need special handling
   - Use `SET FOREIGN_KEY_CHECKS=0` temporarily during migration
   - Re-enable immediately after: `SET FOREIGN_KEY_CHECKS=1`

2. **Column Name Corrections Needed:**
   - `ibl_schedule`: Remove references to non-existent `Day` and `Neutral` columns
   - `ibl_team_win_loss`: Fix case sensitivity issues
   - `ibl_draft_picks`: Remove reference to non-existent `pick` column
   - `ibl_power`: Use correct column name `ranking` (not `powerRanking`)
   - `ibl_team_history`: Table structure is completely different than migration expects

3. **Data Type Conversions:**
   - Some tables use VARCHAR for numeric data (year, round)
   - Requires data migration before type change
   - Should be handled in separate migration

## Re-Prioritized Optimization Roadmap

Based on current schema analysis and foreign key constraint considerations:

### Priority 1: Fix Migration 004 Column Names (Immediate)

**Estimated Time:** 1-2 hours  
**Risk:** Low  
**Value:** Critical for Phase 4 implementation

**Actions:**
1. Correct all column name references in 004_data_type_refinements.sql
2. Remove sections referencing non-existent tables/columns
3. Add foreign key handling for affected tables
4. Test migration on development database
5. Document rollback procedures

### Priority 2: Implement Phase 4 (Data Type Refinements)

**Estimated Time:** 2-3 hours production deployment  
**Risk:** Low (with corrections)  
**Value:** High - 30-50% storage savings, better performance

**Prerequisites:**
- Migration 004 corrections completed
- MySQL 8.0+ verified
- Full database backup
- Maintenance window scheduled

**Implementation Approach:**
```sql
-- For tables with both FK and CHECK constraints
SET FOREIGN_KEY_CHECKS=0;
-- Apply data type changes
-- Add CHECK constraints
SET FOREIGN_KEY_CHECKS=1;
-- Verify constraints
```

### Priority 3: Composite Index Expansion (Phase 5.2)

**Estimated Time:** 1-2 hours  
**Risk:** Low  
**Value:** Medium - 10-30% performance gains on specific queries

**Candidates:**
- Historical stats queries by player/year
- Box score queries by date/team
- Draft picks by year/round
- Team queries by conference/division

**Analysis Required:**
- Review actual query patterns from application logs
- Identify most expensive queries
- Add targeted composite indexes

### Priority 4: Legacy Table Evaluation (Phase 6)

**Estimated Time:** 1-2 weeks  
**Risk:** Medium  
**Value:** Medium - cleanup and maintenance

**Scope:**
- Review 84 MyISAM tables (PhpNuke CMS)
- Identify tables no longer in use
- Archive or remove obsolete tables
- Document remaining legacy dependencies

### Priority 5: Advanced Optimizations (Phase 7+)

**Lower Priority - Future Consideration:**

- Table partitioning for historical data
- Schema normalization opportunities
- Column naming standardization (BREAKING CHANGE - defer to API v2)
- PostgreSQL migration preparation

## Migration Best Practices

### Before Running Any Migration

1. **Verify Prerequisites:**
   ```sql
   SELECT VERSION(); -- Check MySQL version
   SELECT COUNT(*) FROM information_schema.TABLES 
   WHERE TABLE_SCHEMA = DATABASE() 
   AND ENGINE = 'InnoDB'; -- Verify InnoDB tables
   ```

2. **Full Backup:**
   ```bash
   mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

3. **Test on Development:**
   - Never run migrations on production first
   - Test all queries still work
   - Measure performance before/after

4. **Schedule Maintenance:**
   - Plan for 2-3 hour window
   - Some operations may lock tables
   - Communicate downtime to users

### Handling Foreign Key Constraints

For tables with both foreign keys and CHECK constraints:

```sql
-- Verify current constraints
SELECT 
  TABLE_NAME,
  CONSTRAINT_NAME,
  CONSTRAINT_TYPE
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'your_table'
ORDER BY CONSTRAINT_TYPE;

-- Temporarily disable FK checks
SET FOREIGN_KEY_CHECKS=0;

-- Apply your changes
ALTER TABLE your_table ...;

-- Re-enable FK checks
SET FOREIGN_KEY_CHECKS=1;

-- Verify integrity
SELECT COUNT(*) FROM your_table t
LEFT JOIN referenced_table r ON t.fk_column = r.pk_column
WHERE r.pk_column IS NULL; -- Should be 0
```

### Rollback Procedures

**For Data Type Changes:**
```sql
-- Revert column types (example)
ALTER TABLE ibl_plr 
  MODIFY stats_gm INT DEFAULT 0,
  MODIFY age INT DEFAULT NULL;
```

**For CHECK Constraints:**
```sql
-- Remove constraints individually
ALTER TABLE ibl_plr DROP CONSTRAINT chk_plr_age;
ALTER TABLE ibl_standings DROP CONSTRAINT chk_standings_pct;
```

**For Complete Rollback:**
```bash
# Stop application
mysql -u username -p database_name < backup_20251109_120000.sql
# Restart application
```

## Performance Monitoring

### Query Analysis

```sql
-- Enable profiling
SET profiling = 1;

-- Run your query
SELECT * FROM ibl_plr WHERE tid = 1 AND active = 1;

-- View execution time
SHOW PROFILES;

-- Check if indexes are used
EXPLAIN SELECT * FROM ibl_plr WHERE tid = 1 AND active = 1;
```

**Look for:**
- `type: ref` or `type: range` (good - using indexes)
- `type: ALL` (bad - full table scan)
- `key: idx_*` (good - using our indexes)

### Table Statistics

```sql
-- Update table statistics after migrations
ANALYZE TABLE ibl_plr;
ANALYZE TABLE ibl_hist;
ANALYZE TABLE ibl_schedule;

-- Check table sizes
SELECT 
  TABLE_NAME,
  ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS Size_MB,
  ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE 'ibl_%'
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;
```

## Documentation Structure

### Active Documentation (Current)

1. **DATABASE_OPTIMIZATION_GUIDE.md** (this file)
   - Authoritative optimization reference
   - Current schema status
   - Re-prioritized roadmap

2. **DATABASE_GUIDE.md**
   - Quick reference for developers
   - Common query patterns
   - Table categories

3. **ibl5/migrations/README.md**
   - Migration execution procedures
   - Troubleshooting guide
   - Verification steps

4. **MIGRATION_004_FIXES.md**
   - Specific fixes needed for migration 004
   - Column name corrections
   - Technical details

### Archived Documentation

Moved to `.archive/` directory:

1. **DATABASE_SCHEMA_IMPROVEMENTS.md**
   - Original improvement recommendations
   - Historical reference

2. **DATABASE_SCHEMA_GUIDE.md**
   - Superseded by DATABASE_GUIDE.md

3. **DATABASE_FUTURE_PHASES.md**
   - Consolidated into this guide

4. **SCHEMA_IMPLEMENTATION_REVIEW.md**
   - Historical implementation review
   - Phase 1-3 completion details

## Troubleshooting Common Issues

### Foreign Key Constraint Errors

**Error:** "Cannot add or update a child row: a foreign key constraint fails"

**Cause:** Attempting to insert/update data that violates FK relationship

**Solution:**
```sql
-- Find orphaned records
SELECT pid, name, tid FROM ibl_plr 
WHERE tid NOT IN (SELECT teamid FROM ibl_team_info) AND tid != 0;

-- Fix: Update to valid team or free agent (0)
UPDATE ibl_plr SET tid = 0 WHERE tid NOT IN (SELECT teamid FROM ibl_team_info);
```

### CHECK Constraint Violations

**Error:** "Check constraint 'chk_plr_age' is violated"

**Cause:** Data doesn't meet CHECK constraint requirements

**Solution:**
```sql
-- Find violating data
SELECT pid, name, age FROM ibl_plr WHERE age < 18 OR age > 50;

-- Fix data before migration
UPDATE ibl_plr SET age = 18 WHERE age < 18;
```

### Data Type Overflow

**Error:** "Out of range value for column"

**Cause:** Existing data exceeds new type limits (e.g., TINYINT max is 255)

**Solution:**
```sql
-- Check for overflow before migration
SELECT pid, name, sta FROM ibl_plr WHERE sta > 255;

-- If found, use larger type or cap values
UPDATE ibl_plr SET sta = 100 WHERE sta > 100;
```

## Next Steps

### Immediate Actions

1. **Review and correct migration 004** (Priority 1)
   - Fix column name mismatches
   - Add foreign key handling
   - Test on development database

2. **Verify MySQL version** (Priority 1)
   - Ensure MySQL 8.0+ for CHECK constraints
   - Document version in deployment notes

3. **Update migrations README** (Priority 1)
   - Reflect corrected priorities
   - Add foreign key handling instructions

### Short-term (Next 2-4 weeks)

1. **Implement Phase 4** (Priority 2)
   - Schedule maintenance window
   - Execute corrected migration
   - Monitor performance improvements

2. **Analyze query patterns** (Priority 3)
   - Review slow query logs
   - Identify candidates for composite indexes
   - Implement targeted optimizations

### Long-term (Next 3-6 months)

1. **Legacy table evaluation** (Priority 4)
   - Audit PhpNuke tables
   - Remove obsolete tables
   - Document remaining dependencies

2. **Advanced optimizations** (Priority 5)
   - Consider table partitioning
   - Evaluate normalization opportunities
   - Plan for future schema evolution

## Support and Resources

### Files and Locations

- **Production Schema:** `ibl5/schema.sql`
- **Migrations:** `ibl5/migrations/`
- **Documentation:** Root directory and `.archive/`

### Key References

- [DATABASE_GUIDE.md](DATABASE_GUIDE.md) - Developer quick reference
- [MIGRATION_004_FIXES.md](MIGRATION_004_FIXES.md) - Migration 004 corrections
- [API_GUIDE.md](API_GUIDE.md) - API development guide
- [DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md) - General development guide

### Getting Help

1. Check this guide for current status and priorities
2. Review migration README for execution procedures
3. Check troubleshooting section for common issues
4. Consult archived documentation for historical context

---

**Document History:**
- November 9, 2025: Initial consolidation and re-prioritization
- Previous updates tracked in archived documentation
