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

**Note:** Phases 1, 2, and 3 are already completed and implemented in production.

#### For Phase 4 (Data Type Refinements) - NEXT STEP

**IMPORTANT:** Requires MySQL 8.0 or higher for CHECK constraints

1. **Verify MySQL Version:**
   ```bash
   mysql -u username -p -e "SELECT VERSION();"
   ```
   Ensure version is 8.0 or higher for CHECK constraint support.

2. **Connect to database:**
   ```bash
   mysql -u username -p database_name
   ```

3. **Verify Prerequisites:**
   ```sql
   -- Verify InnoDB tables exist
   SELECT COUNT(*) FROM information_schema.TABLES 
   WHERE TABLE_SCHEMA = DATABASE() 
   AND TABLE_NAME LIKE 'ibl_%' 
   AND ENGINE = 'InnoDB';
   
   -- Verify foreign keys exist (should be 20+)
   SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE()
   AND REFERENCED_TABLE_NAME IS NOT NULL;
   
   -- Verify timestamps exist (should be 19+ tables)
   SELECT COUNT(DISTINCT TABLE_NAME) as tables_with_timestamps
   FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = DATABASE() 
     AND COLUMN_NAME IN ('created_at', 'updated_at')
     AND TABLE_NAME LIKE 'ibl_%';
   ```

4. **Run Phase 4 migration:**
   ```bash
   mysql -u username -p database_name < 004_data_type_refinements.sql
   ```
   
   Expected time: 2-3 hours depending on table sizes

5. **Verify Phase 4:**
   ```sql
   -- Verify data type changes (should show TINYINT, SMALLINT, etc.)
   SELECT 
     COLUMN_NAME, 
     DATA_TYPE, 
     COLUMN_TYPE,
     IS_NULLABLE
   FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'ibl_plr'
     AND COLUMN_NAME IN ('age', 'peak', 'stats_gm', 'stats_min', 'sta', 'oo')
   ORDER BY COLUMN_NAME;
   
   -- Verify CHECK constraints were added (should be 30+)
   SELECT 
     COUNT(*) as check_constraints
   FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA = DATABASE()
     AND CONSTRAINT_TYPE = 'CHECK'
     AND TABLE_NAME LIKE 'ibl_%';
   
   -- List all CHECK constraints
   SELECT 
     TABLE_NAME,
     CONSTRAINT_NAME
   FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA = DATABASE()
     AND CONSTRAINT_TYPE = 'CHECK'
     AND TABLE_NAME LIKE 'ibl_%'
   ORDER BY TABLE_NAME, CONSTRAINT_NAME;
   
   -- Verify ENUM types were added
   SELECT 
     TABLE_NAME,
     COLUMN_NAME,
     COLUMN_TYPE
   FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND DATA_TYPE = 'enum'
     AND TABLE_NAME LIKE 'ibl_%'
   ORDER BY TABLE_NAME, COLUMN_NAME;
   
   -- Verify DECIMAL types for monetary values
   SELECT 
     TABLE_NAME,
     COLUMN_NAME,
     COLUMN_TYPE,
     NUMERIC_PRECISION,
     NUMERIC_SCALE
   FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND DATA_TYPE = 'decimal'
     AND TABLE_NAME LIKE 'ibl_%'
     AND COLUMN_NAME LIKE 'cy%'
   ORDER BY TABLE_NAME, COLUMN_NAME;
   ```

6. **Test Data Validation:**
   ```sql
   -- Test CHECK constraints (these should fail)
   -- Don't run these on production without reverting immediately!
   
   -- This should fail (age too low):
   -- UPDATE ibl_plr SET age = 15 WHERE pid = 1;
   
   -- This should fail (rating too high):
   -- UPDATE ibl_plr SET sta = 150 WHERE pid = 1;
   
   -- This should fail (pct out of range):
   -- UPDATE ibl_standings SET pct = 1.500 WHERE tid = 1;
   
   -- Verify constraints are working by checking constraint names
   SELECT 
     CONSTRAINT_NAME,
     CHECK_CLAUSE
   FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS
   WHERE CONSTRAINT_SCHEMA = DATABASE()
     AND CONSTRAINT_NAME LIKE 'chk_%'
   ORDER BY CONSTRAINT_NAME
   LIMIT 10;
   ```

7. **Monitor Application:**
   - Test player pages load correctly
   - Test statistics display properly
   - Test financial/contract information displays correctly
   - Verify no application errors from type changes
   - Monitor query performance (should be slightly improved)

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

-- Revert DECIMAL to INT for monetary values
ALTER TABLE ibl_plr 
  MODIFY cy INT DEFAULT 0,
  MODIFY cyt INT DEFAULT 0,
  MODIFY cy1 INT DEFAULT 0,
  MODIFY cy2 INT DEFAULT 0,
  MODIFY cy3 INT DEFAULT 0,
  MODIFY cy4 INT DEFAULT 0,
  MODIFY cy5 INT DEFAULT 0,
  MODIFY cy6 INT DEFAULT 0;

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

#### 8. DECIMAL Precision Issues (Phase 4)

**Issue:** Contract values appear truncated or have unexpected decimals

**Cause:** INT to DECIMAL conversion with existing large values

**Solution:**
```sql
-- Check for contract values that might be affected
SELECT pid, name, cy, cy1, cy2 FROM ibl_plr 
WHERE cy > 99999999 OR cy1 > 99999999 OR cy2 > 99999999;

-- If values are stored in cents rather than dollars, divide by 100
-- UPDATE ibl_plr SET cy = cy / 100, cy1 = cy1 / 100, cy2 = cy2 / 100;

-- Verify display is correct
SELECT name, FORMAT(cy, 2) as salary FROM ibl_plr WHERE cy > 0 LIMIT 10;
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

## Support

For issues or questions:
1. Check troubleshooting section above
2. Review logs: `/var/log/mysql/error.log`
3. Check application logs
4. Review `DATABASE_SCHEMA_IMPROVEMENTS.md` for context

## Next Steps

### ✅ Completed Phases
- **Phase 1:** Critical Infrastructure (InnoDB, Indexes) - ✅ DONE
- **Phase 2:** Foreign Key Relationships - ✅ DONE
- **Phase 3:** API Preparation (Timestamps, UUIDs, Views) - ✅ DONE
- **Phase 5.1:** Composite Indexes - ✅ DONE

### 🎉 Phase 3 Implementation Complete!

**Implementation Date:** Successfully completed in production schema  
**File:** `003_api_preparation.sql`

**What was implemented:**
- ✅ **Part 1:** Complete timestamp columns (`created_at`, `updated_at`) on 19 tables
  - Historical stats, box scores, standings, draft, free agency, and trade tables
  - Enables audit trails and API caching (ETags)
  
- ✅ **Part 2:** UUID support for secure public API identifiers on 5 tables
  - `ibl_plr` (Players)
  - `ibl_team_info` (Teams)
  - `ibl_schedule` (Games)
  - `ibl_draft` (Draft picks)
  - `ibl_box_scores` (Box scores)
  - All UUIDs generated and indexed
  
- ✅ **Part 3:** API-friendly database views (5 views created)
  - `vw_player_current` - Active players with team info
  - `vw_team_standings` - Standings with calculated fields
  - `vw_schedule_upcoming` - Schedule with team names
  - `vw_player_career_stats` - Career statistics summary
  - `vw_free_agency_offers` - Free agency market overview

**Benefits Achieved:**
- ✅ Secure public identifiers (UUIDs) prevent ID enumeration attacks
- ✅ Database views simplify API queries and improve performance
- ✅ Complete audit trail coverage for all core tables
- ✅ ETags and Last-Modified headers for efficient API caching
- ✅ Consistent data formatting across API endpoints
- ✅ Simplified application code with pre-joined views

### 🎯 Database is Now API-Ready!

The database is fully prepared for production API deployment with:
- ACID transactions (InnoDB)
- Data integrity (Foreign Keys)
- High performance (Comprehensive Indexes)
- Secure public IDs (UUIDs)
- Efficient caching (Timestamps)
- Simplified queries (Database Views)

### 004_data_type_refinements.sql (Phase 4) 🔄 READY TO IMPLEMENT
**Priority:** Medium (Data Quality & Validation)  
**Estimated Time:** 2-3 hours  
**Risk Level:** Low  
**Status:** Migration file prepared, ready for implementation

**Prerequisites:**
- Phase 1, 2, and 3 must be completed
- InnoDB tables with foreign keys and timestamps in place
- MySQL 8.0 or higher (for CHECK constraints)

Implements:
- **Part 1:** Complete data type optimizations for all tables
  - Integer size optimizations (TINYINT, SMALLINT, MEDIUMINT)
  - Reduces storage requirements for statistics, ratings, and counters
  - Over 200+ column optimizations across all core tables
  
- **Part 2:** Convert monetary values to DECIMAL type
  - All salary/contract columns (cy, cy1-cy6) to DECIMAL(10,2)
  - Accurate financial calculations
  - Prevents floating-point precision errors
  
- **Part 3:** Implement ENUM types for fixed value lists
  - Player positions (PG, SG, SF, PF, C, G, F, GF)
  - Conference (Eastern, Western)
  - Data validation at database level
  
- **Part 4:** Add CHECK constraints for data validation (MySQL 8.0+)
  - Age constraints (18-50 years)
  - Peak age validation (peak >= age)
  - Winning percentage bounds (0.000-1.000)
  - Rating ranges (0-100)
  - Contract value limits
  - Statistics validation
  
- **Part 5:** Add NOT NULL constraints for required fields
  - Player name, position, team ID
  - Ensures data integrity

**Benefits:**
- ✅ Reduced storage requirements (30-50% for statistics columns)
- ✅ Better query optimization from smaller data types
- ✅ Accurate financial calculations with DECIMAL
- ✅ Data validation at database level prevents invalid data
- ✅ Self-documenting schema with ENUM types
- ✅ Improved data quality and integrity
- ✅ Foundation for robust API data validation

**Impact:**
- Storage savings: Estimated 30-50% reduction in table sizes
- Query performance: 10-20% improvement from smaller indexes
- Data quality: Invalid data prevented at database level
- API reliability: Better data validation for API responses

### 📋 Future Phases

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
