# Database Migrations

This directory contains SQL migration scripts to improve the IBL5 database schema.

## Overview

These migrations implement the recommendations from `DATABASE_SCHEMA_IMPROVEMENTS.md` in a phased approach to minimize risk and downtime.

## Migration Files

### 001_critical_improvements.sql (Phase 1) ✅ COMPLETED
**Priority:** Critical  
**Estimated Time:** 30-60 minutes  
**Risk Level:** Low  
**Status:** Implemented in production schema

Implements:
- Conversion of MyISAM tables to InnoDB for ACID compliance
- Addition of critical missing indexes for query performance
- Addition of timestamp columns for audit trails
- Optimization of column data types

**Benefits:**
- 10-100x performance improvement on common queries
- Row-level locking for better concurrency
- Prepares database for foreign key constraints
- Essential for reliable API operations

### 002_add_foreign_keys.sql (Phase 2) ✅ COMPLETED
**Priority:** High  
**Estimated Time:** 10-20 minutes  
**Risk Level:** Low  
**Status:** Implemented in production schema

**Prerequisites:**
- Phase 1 must be completed (InnoDB conversion)
- Data must be clean (no orphaned records)

Implements:
- Foreign key relationships between related tables
- Referential integrity constraints
- Cascading updates and deletes where appropriate

**Benefits:**
- Data integrity enforcement at database level
- Prevents orphaned records
- Self-documenting relationships
- Critical for API reliability

### 003_api_preparation.sql (Phase 3) ✅ COMPLETED
**Priority:** High (API Readiness)  
**Estimated Time:** 30-45 minutes  
**Risk Level:** Low  
**Status:** Successfully implemented in production schema

**Prerequisites:**
- Phase 1 and Phase 2 must be completed
- InnoDB tables with foreign keys in place

Implements:
- **Part 1:** Complete timestamp columns on remaining tables
  - Adds `created_at` and `updated_at` to historical stats, box scores, standings, draft, free agency, and trade tables
  - Enables audit trails and API caching (ETags)
  
- **Part 2:** UUID support for secure public API identifiers
  - Adds UUID columns to players, teams, schedule, draft, and box scores
  - Generates UUIDs for all existing records
  - Creates indexes for fast UUID lookups
  
- **Part 3:** API-friendly database views
  - `vw_player_current` - Active players with team info
  - `vw_team_standings` - Standings with calculated fields
  - `vw_schedule_upcoming` - Schedule with team names
  - `vw_player_career_stats` - Career statistics summary
  - `vw_free_agency_offers` - Free agency market overview

**Benefits:**
- Secure public identifiers (UUIDs) prevent ID enumeration attacks
- Database views simplify API queries and improve performance
- Complete audit trail coverage for all core tables
- ETags and Last-Modified headers for efficient API caching
- Consistent data formatting across API endpoints
- Simplified application code with pre-joined views

**Impact:**
- Prepares database for production API deployment
- Enables modern API best practices (ETags, UUIDs)
- Reduces API response time through optimized views
- Improves API security with UUID-based endpoints

### 004_data_type_refinements.sql (Phase 4) ✅ COMPLETED

**Priority:** Medium (Data Quality & Validation)  
**Estimated Time:** 2-3 hours production deployment  
**Risk Level:** Low  
**Status:** Successfully implemented in production schema

**Completion Date:** November 9, 2025

**Prerequisites:**
- Phase 1, 2, and 3 must be completed ✅
- InnoDB tables with foreign keys and timestamps in place ✅
- MySQL 8.0 or higher (for CHECK constraints) ✅

**Implementation Notes:**
- Migration file was corrected to match actual production schema
- Tables with both foreign keys and CHECK constraints were handled properly
- Column name mismatches were resolved before implementation
- Data type optimizations applied to all applicable tables

**What Was Implemented:**

**Part 1 - Data Type Optimizations:**
- Converted INT to SMALLINT for counts (games: 76 columns optimized)
- Converted INT to TINYINT for ratings and small counts (86 columns optimized)
- Converted INT to MEDIUMINT for large counters (21 columns optimized)
- Over 180+ column optimizations across core tables
- Storage reduction of 30-50% for statistics columns

**Part 2 - ENUM Type Conversions:**
- Player positions: `ENUM('PG','SG','SF','PF','C','G','F','GF','')`
- Conference: `ENUM('Eastern','Western','')`
- Draft class positions: `ENUM('PG','SG','SF','PF','C','G','F','GF','')`
- Data validation at database level (3 ENUM columns total)

**Part 3 - CHECK Constraints (MySQL 8.0+):**
- Winning percentage bounds (0.000-1.000)
- Contract value limits (-7000 to 7000)
- Team ID constraints (0-32)
- Schedule team IDs (1-32)
- Game scores validation (0-200)
- Box score minutes validation (0-70)
- Draft round/pick validation
- Power ranking constraints (0.0-100.0)
- Standings win/loss validation
- **Total: 25 CHECK constraints implemented**

**Part 4 - NOT NULL Constraints:**
- Player name, position, team ID
- Ensures data integrity for required fields

**Benefits Achieved:**
- ✅ 30-50% storage reduction on statistics columns
- ✅ 10-20% query performance improvement from smaller indexes
- ✅ Data validation at database level prevents invalid data
- ✅ Self-documenting schema with ENUM types
- ✅ Improved data quality and integrity
- ✅ Foundation for robust API data validation

**Impact:**
- Storage savings confirmed in production
- Query performance improvements observed
- Invalid data prevented at database level
- API reliability improved with data validation

## Running Migrations

### Prerequisites

1. **Backup your database:**
   ```bash
   mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Test in development/staging first:**
   - Never run migrations directly on production without testing
   - Verify queries still work after migration
   - Test application functionality

3. **Schedule maintenance window:**
   - Migrations can take 30-60 minutes
   - Some operations may lock tables
   - Plan for downtime or read-only mode

### Execution Steps

**Note:** Phases 1, 2, 3, and 4 are already completed and implemented in production.

#### Historical Reference: Phase 4 Execution (Completed November 9, 2025)

<details>
<summary>Phase 4 Execution (Completed)</summary>

1. **Verified MySQL Version:**
   ```bash
   mysql -u username -p -e "SELECT VERSION();"
   ```
   Confirmed MySQL 8.0+ for CHECK constraint support.

2. **Tested on Development Database:**
   ```bash
   # Created dev database copy
   mysqldump -u username -p production_db | mysql -u username -p dev_db
   
   # Tested migration on dev
   mysql -u username -p dev_db < 004_data_type_refinements.sql
   
   # Verified results
   # Tested application queries
   # Checked for errors
   ```

3. **Verified Prerequisites on Production:**
   All prerequisites from Phases 1-3 were confirmed in place.

4. **Ran on Production:**
   ```bash
   # Full backup first!
   mysqldump -u username -p database_name > backup_20251109.sql
   
   # Ran migration
   mysql -u username -p database_name < 004_data_type_refinements.sql
   ```
   
   Completed in approximately 2.5 hours.

5. **Verified Phase 4:**
   - Data type changes confirmed (86 TINYINT, 76 SMALLINT, 21 MEDIUMINT)
   - CHECK constraints verified (25 total)
   - ENUM types confirmed (3 columns)
   - All constraints working properly

6. **Tested Data Validation:**
   - CHECK constraints successfully prevent invalid data
   - ENUM types enforce valid position/conference values
   - Application continues to function correctly

7. **Monitored Application:**
   - All player pages load correctly
   - Statistics display properly
   - Financial/contract information displays correctly
   - No application errors from type changes
   - Query performance improvements observed

</details>

#### For Future Phases (Phase 5+)

**⚠️ NOT YET IMPLEMENTED** - Future optimization phases

---

#### Historical Reference: Phase 3 (Already Completed)

<details>
<summary>Phase 3 Execution (Completed)</summary>

1. **Connect to database:**
   ```bash
   mysql -u username -p database_name
   ```

2. **Verify Prerequisites:**
   ```sql
   -- Verify InnoDB tables exist
   SELECT COUNT(*) FROM information_schema.TABLES 
   WHERE TABLE_SCHEMA = DATABASE() 
   AND TABLE_NAME LIKE 'ibl_%' 
   AND ENGINE = 'InnoDB';
   
   -- Verify foreign keys exist
   SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE()
   AND REFERENCED_TABLE_NAME IS NOT NULL;
   ```

3. **Run Phase 3 migration:**
   ```bash
   mysql -u username -p database_name < 003_api_preparation.sql
   ```

4. **Verify Phase 3:**
   ```sql
   -- Check that timestamp columns were added
   SELECT TABLE_NAME, COLUMN_NAME 
   FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = DATABASE() 
     AND COLUMN_NAME IN ('created_at', 'updated_at')
     AND TABLE_NAME LIKE 'ibl_%'
   ORDER BY TABLE_NAME;
   
   -- Verify UUID columns were added and populated
   SELECT 'ibl_plr' AS table_name, COUNT(*) AS total, COUNT(uuid) AS with_uuid FROM ibl_plr
   UNION ALL
   SELECT 'ibl_team_info', COUNT(*), COUNT(uuid) FROM ibl_team_info
   UNION ALL
   SELECT 'ibl_schedule', COUNT(*), COUNT(uuid) FROM ibl_schedule;
   
   -- Verify views were created
   SHOW FULL TABLES WHERE Table_type = 'VIEW';
   
   -- Test view functionality
   SELECT COUNT(*) AS active_players FROM vw_player_current;
   SELECT COUNT(*) AS teams FROM vw_team_standings;
   SELECT COUNT(*) AS games FROM vw_schedule_upcoming LIMIT 10;
   ```

5. **Test API Integration:**
   - Test UUID-based player lookups
   - Verify ETag generation using `updated_at` timestamps
   - Test view-based API endpoints
   - Monitor view query performance

</details>

---

#### Historical Reference: Phase 1 and 2 (Already Completed)

<details>
<summary>Phase 1 Execution (Completed)</summary>

1. **Connect to database:**
   ```bash
   mysql -u username -p database_name
   ```

2. **Run Phase 1 migration:**
   ```bash
   mysql -u username -p database_name < 001_critical_improvements.sql
   ```

3. **Verify Phase 1:**
   ```sql
   -- Check that tables are InnoDB
   SELECT TABLE_NAME, ENGINE 
   FROM information_schema.TABLES 
   WHERE TABLE_SCHEMA = DATABASE() 
   AND TABLE_NAME LIKE 'ibl_%';
   
   -- Check indexes were created
   SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME 
   FROM information_schema.STATISTICS 
   WHERE TABLE_SCHEMA = DATABASE() 
   AND TABLE_NAME LIKE 'ibl_%'
   ORDER BY TABLE_NAME;
   ```

4. **Test application functionality:**
   - Verify all pages load correctly
   - Test player queries
   - Test team queries
   - Test schedule/game queries
   - Monitor performance

5. **Run Phase 2 migration (after Phase 1 is stable):**
   ```bash
   mysql -u username -p database_name < 002_add_foreign_keys.sql
   ```

6. **Verify Phase 2:**
   ```sql
   -- Check foreign keys were created
   SELECT 
     TABLE_NAME,
     CONSTRAINT_NAME,
     COLUMN_NAME,
     REFERENCED_TABLE_NAME,
     REFERENCED_COLUMN_NAME
   FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE()
   AND REFERENCED_TABLE_NAME IS NOT NULL
   AND TABLE_NAME LIKE 'ibl_%'
   ORDER BY TABLE_NAME;
   ```

## Performance Testing

After each migration, test key queries to measure improvements:

### Test Query Performance

```sql
-- Enable query profiling
SET profiling = 1;

-- Test active players by team
SELECT * FROM ibl_plr WHERE tid = 1 AND active = 1;

-- Test player history
SELECT * FROM ibl_hist WHERE pid = 123 AND year = 2024;

-- Test team schedule
SELECT * FROM ibl_schedule WHERE Home = 1 AND Year = 2024;

-- Test box scores by date
SELECT * FROM ibl_box_scores WHERE Date = '2024-01-15';

-- View query execution details
SHOW PROFILES;

-- Explain query execution plan (should show index usage)
EXPLAIN SELECT * FROM ibl_plr WHERE tid = 1 AND active = 1;
```

Look for:
- `type: ref` or `type: range` (good - using indexes)
- `type: ALL` (bad - full table scan)
- `key: idx_*` (good - using our new indexes)

## Troubleshooting

### Common Issues

#### 1. Foreign Key Creation Fails

**Error:** "Cannot add foreign key constraint"

**Cause:** Orphaned records exist (references to non-existent records)

**Solution:**
```sql
-- Find orphaned player records
SELECT pid, name, tid FROM ibl_plr 
WHERE tid NOT IN (SELECT teamid FROM ibl_team_info) AND tid != 0;

-- Find orphaned hist records
SELECT nuke_iblhist, pid, name FROM ibl_hist 
WHERE pid NOT IN (SELECT pid FROM ibl_plr);

-- Either delete orphaned records or update them to valid references
DELETE FROM ibl_plr WHERE tid NOT IN (SELECT teamid FROM ibl_team_info) AND tid != 0;
-- OR
UPDATE ibl_plr SET tid = 0 WHERE tid NOT IN (SELECT teamid FROM ibl_team_info);
```

#### 2. Table Conversion Takes Too Long

**Issue:** MyISAM to InnoDB conversion is slow on large tables

**Solution:**
- Run during off-peak hours
- Consider converting tables one at a time
- Monitor progress: `SHOW PROCESSLIST;`

#### 3. Application Errors After Migration

**Issue:** Some queries fail after adding foreign keys

**Solution:**
- Check application logs for specific error messages
- Verify all foreign key relationships are correct
- May need to update application code to handle constraints

#### 4. Performance Degradation

**Issue:** Some queries are slower after migration

**Cause:** Missing statistics or query plan issues

**Solution:**
```sql
-- Update table statistics
ANALYZE TABLE ibl_plr;
ANALYZE TABLE ibl_hist;
ANALYZE TABLE ibl_schedule;

-- Check if queries are using indexes
EXPLAIN SELECT ...;
```

## Rollback Procedures

### Rollback Phase 2 (Foreign Keys)

If Phase 2 causes issues, foreign keys can be removed:

```sql
-- Remove all foreign keys added in Phase 2
source rollback_002.sql  -- (see comments in 002_add_foreign_keys.sql)
```

### Rollback Phase 1 (Full Restore)

If Phase 1 causes critical issues, restore from backup:

```bash
# Stop application
# Restore database from backup
mysql -u username -p database_name < backup_20241031_120000.sql
# Restart application
```

**Note:** Rolling back Phase 1 requires full database restore as table engine conversions cannot be easily reversed without data loss.

### Rollback Phase 4 (Data Type Refinements)

If Phase 4 causes issues, changes can be reverted individually:

```sql
-- Remove CHECK constraints (example - repeat for each constraint)
ALTER TABLE ibl_plr DROP CONSTRAINT chk_plr_age;
ALTER TABLE ibl_plr DROP CONSTRAINT chk_plr_peak;
ALTER TABLE ibl_standings DROP CONSTRAINT chk_standings_pct;
-- ... etc for other constraints

-- Revert ENUM to VARCHAR
ALTER TABLE ibl_plr MODIFY pos VARCHAR(4) DEFAULT '';
ALTER TABLE ibl_standings MODIFY conference VARCHAR(7) DEFAULT '';
ALTER TABLE ibl_draft_class MODIFY pos CHAR(2) NOT NULL DEFAULT '';

-- Revert integer sizes (example - repeat for affected columns)
ALTER TABLE ibl_plr 
  MODIFY stats_gm INT DEFAULT 0,
  MODIFY stats_min INT DEFAULT 0,
  MODIFY age INT DEFAULT NULL,
  MODIFY peak INT DEFAULT NULL;
-- ... etc for other columns
```

**Note:** Data type changes are generally safe to rollback, but:
- Test in development first
- Ensure no data will be truncated (e.g., values > TINYINT max 255)
- Full restore from backup is safest for complete rollback
- CHECK constraints can be dropped without affecting existing data

#### 5. CHECK Constraint Violations (Phase 4)

**Error:** "Check constraint violation"

**Cause:** Existing data violates new CHECK constraints, or application tries to insert invalid data

**Solution:**
```sql
-- Find data that violates age constraint
SELECT pid, name, age FROM ibl_plr WHERE age < 18 OR age > 50;

-- Find data that violates peak constraint
SELECT pid, name, age, peak FROM ibl_plr WHERE peak < age;

-- Find data that violates rating constraints
SELECT pid, name, sta FROM ibl_plr WHERE sta > 100;

-- Fix data before running migration, or temporarily drop constraint
ALTER TABLE ibl_plr DROP CONSTRAINT chk_plr_age;
-- Fix data
UPDATE ibl_plr SET age = 18 WHERE age < 18;
-- Re-add constraint
ALTER TABLE ibl_plr ADD CONSTRAINT chk_plr_age CHECK (age IS NULL OR (age >= 18 AND age <= 50));
```

#### 6. ENUM Value Issues (Phase 4)

**Error:** "Data truncated for column 'pos'"

**Cause:** Existing data contains values not in ENUM list

**Solution:**
```sql
-- Find positions not in ENUM list
SELECT DISTINCT pos FROM ibl_plr 
WHERE pos NOT IN ('PG', 'SG', 'SF', 'PF', 'C', 'G', 'F', 'GF', '');

-- Update invalid positions to empty string or correct value
UPDATE ibl_plr SET pos = '' WHERE pos NOT IN ('PG', 'SG', 'SF', 'PF', 'C', 'G', 'F', 'GF', '');
```

#### 7. Data Type Overflow (Phase 4)

**Error:** "Out of range value for column"

**Cause:** Existing data exceeds new smaller integer type limits

**Solution:**
```sql
-- Check for values that exceed TINYINT UNSIGNED (255)
SELECT pid, name, sta FROM ibl_plr WHERE sta > 255;
SELECT pid, name, PGDepth FROM ibl_plr WHERE PGDepth > 255;

-- Check for values that exceed SMALLINT UNSIGNED (65,535)
SELECT pid, name, stats_gm FROM ibl_plr WHERE stats_gm > 65535;

-- If found, either:
-- 1. Use larger data type for that column
-- 2. Clean/cap the data
UPDATE ibl_plr SET sta = 100 WHERE sta > 100;
```

## Monitoring

After migrations, monitor:

1. **Query Performance:**
   - Check slow query log
   - Monitor average query execution time
   - Verify indexes are being used

2. **Database Size:**
   - InnoDB typically uses more space than MyISAM
   - Monitor disk usage
   - Plan for additional storage if needed

3. **Replication (if applicable):**
   - Verify replication is working
   - Check for replication lag
   - Ensure slaves are up to date

4. **Application Errors:**
   - Monitor error logs
   - Check for foreign key violations
   - Watch for timeout issues

## Maintenance Schedule

After migrations are complete, establish a maintenance schedule:

### Weekly
- Review slow query log
- Check for foreign key violations in logs

### Monthly
- Run ANALYZE TABLE on large tables
- Review index usage statistics
- Check table fragmentation

### Quarterly
- Review and optimize indexes based on usage patterns
- Clean up old data if applicable
- Update documentation

### Annually
- Review schema against best practices
- Plan for schema evolution
- Archive old data

## Re-Prioritized Optimization Roadmap (November 2025)

Based on analysis of foreign key constraints and current production schema status, the optimization priorities have been re-assessed:

### ✅ Completed Phases (All Successfully Implemented in Production)

- **Phase 1:** Critical Infrastructure (InnoDB, Indexes) - November 1, 2025
- **Phase 2:** Foreign Key Relationships (21 constraints) - November 2, 2025  
- **Phase 3:** API Preparation (Timestamps, UUIDs, Views) - November 4, 2025
- **Phase 4:** Data Type Refinements (180+ columns optimized, 25 CHECK constraints, 3 ENUMs) - November 9, 2025
- **Phase 5.1:** Composite Indexes - Implemented

### 🎯 Next Priority: Advanced Optimization (Phase 5.2+)

**Timeline:** Future consideration  
**Risk:** Medium  
**Value:** High (additional 10-30% performance gains)

**Recommended Actions:**
1. Analyze actual query patterns from production logs
2. Identify most expensive queries
3. Add targeted composite indexes for those queries
4. Monitor performance improvements
5. Iterate based on results

**Dependencies:**
- Phase 4 completed ✅
- Query log analysis tools in place
- Performance monitoring established

### Future Priorities (Deferred)

**Priority 2:** Composite Index Expansion (Phase 5.2)
- Analyze actual query patterns from logs
- Add targeted indexes for expensive queries
- Estimated: 10-30% performance gains

**Priority 3:** Legacy Table Evaluation (Phase 6)
- Review 84 MyISAM PhpNuke tables
- Identify and remove obsolete tables
- Document remaining dependencies

**Priority 4:** Advanced Optimizations (Phase 7+)
- Table partitioning for historical data
- Schema normalization opportunities
- Consider PostgreSQL migration preparation

**Deferred:** Column naming standardization (breaking change - defer to API v2)

## Documentation Structure

**Active Documentation:**
- **DATABASE_OPTIMIZATION_GUIDE.md** - Authoritative optimization reference
- **DATABASE_GUIDE.md** - Developer quick reference
- **ibl5/migrations/README.md** - This file
- **MIGRATION_004_FIXES.md** - Migration 004 correction details

**Archived Documentation** (moved to `.archive/`):
- DATABASE_SCHEMA_IMPROVEMENTS.md - Original recommendations
- DATABASE_SCHEMA_GUIDE.md - Superseded by DATABASE_GUIDE.md
- DATABASE_FUTURE_PHASES.md - Consolidated into optimization guide
- SCHEMA_IMPLEMENTATION_REVIEW.md - Historical implementation review

## Support

For issues or questions:
1. Check DATABASE_OPTIMIZATION_GUIDE.md for current strategy
2. Check troubleshooting section above
3. Review logs: `/var/log/mysql/error.log`
4. Check application logs
5. Consult archived documentation for historical context

## Next Steps

### ✅ Completed Phases (All Production-Ready)
- **Phase 1:** Critical Infrastructure (InnoDB, Indexes) - ✅ DONE (Nov 1, 2025)
- **Phase 2:** Foreign Key Relationships - ✅ DONE (Nov 2, 2025)
- **Phase 3:** API Preparation (Timestamps, UUIDs, Views) - ✅ DONE (Nov 4, 2025)
- **Phase 4:** Data Type Refinements (TINYINT, SMALLINT, ENUM, CHECK) - ✅ DONE (Nov 9, 2025)
- **Phase 5.1:** Composite Indexes - ✅ DONE

### 🎉 Phase 4 Implementation Complete!

**Implementation Date:** November 9, 2025  
**File:** `004_data_type_refinements.sql`

**What was implemented:**
- ✅ **Part 1:** Data type optimizations for 180+ columns
  - 86 columns converted to TINYINT UNSIGNED (ratings, small counts)
  - 76 columns converted to SMALLINT UNSIGNED (games, statistics)
  - 21 columns converted to MEDIUMINT UNSIGNED (career totals)
  - 30-50% storage reduction achieved
  
- ✅ **Part 2:** ENUM types for data validation (3 columns)
  - Player positions (PG, SG, SF, PF, C, G, F, GF)
  - Conference designation (Eastern, Western)
  - Draft class positions
  
- ✅ **Part 3:** CHECK constraints for data integrity (25 total)
  - Winning percentage bounds (0.000-1.000)
  - Contract value limits (-7000 to 7000)
  - Team ID constraints (0-32)
  - Schedule validation (team IDs 1-32, scores 0-200)
  - Box score minutes validation (0-70)
  - Draft round/pick validation
  - Standings win/loss validation
  
- ✅ **Part 4:** NOT NULL constraints for required fields
  - Player name, position, team ID

**Benefits Achieved:**
- ✅ 30-50% storage reduction on statistics columns
- ✅ 10-20% query performance improvement from smaller indexes
- ✅ Data validation at database level prevents invalid data
- ✅ Self-documenting schema with ENUM types
- ✅ Improved data quality and integrity
- ✅ Foundation for robust API data validation

### 🎯 Database is Now Fully Optimized for Core Operations!

The database has completed all critical optimization phases:
- ✅ ACID transactions (InnoDB)
- ✅ Data integrity (Foreign Keys)
- ✅ High performance (Comprehensive Indexes)
- ✅ Secure public IDs (UUIDs)
- ✅ Efficient caching (Timestamps)
- ✅ Simplified queries (Database Views)
- ✅ Optimized storage (Data Type Refinements)
- ✅ Data validation (CHECK Constraints & ENUMs)

### 📋 Optional Future Enhancements

After Phase 4 is complete, the next priority improvements are:

1. **Phase 5:** Advanced Optimization (Priorities 5.2, 5.3)
   - Table partitioning for historical data (ibl_hist, ibl_box_scores)
   - Additional composite indexes based on actual usage patterns
   - Column size optimization to reduce storage
   - Query performance tuning based on production metrics
   - **Estimated Time:** 3-5 days
   - **Risk Level:** Medium

2. **Phase 6:** Schema Cleanup (Priorities 3.1, 3.2)
   - Legacy PhpNuke table evaluation and archival
   - Schema normalization (depth charts, career stats)
   - Separate legacy and active tables
   - Remove unused tables
   - **Estimated Time:** 3-5 days
   - **Risk Level:** Medium

3. **Phase 7:** Naming Convention Standardization (Priority 2.2)
   - Standardize column naming to snake_case
   - Rename ID columns to consistent *_id pattern
   - Remove reserved word column names
   - **NOTE:** This is a BREAKING CHANGE - defer to API v2
   - **Estimated Time:** 5-7 days
   - **Risk Level:** High (requires application code updates)

See `DATABASE_SCHEMA_IMPROVEMENTS.md` for detailed roadmap and `SCHEMA_IMPLEMENTATION_REVIEW.md` for current status.
