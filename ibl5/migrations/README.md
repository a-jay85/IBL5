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

**Note:** Phases 1 and 2 are already completed and implemented in production. The steps below are for Phase 3.

#### For Phase 3 (API Preparation) - NEXT STEP

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

### 📋 Future Phases

After Phase 3 is complete, the next priority improvements are:

1. **Phase 4:** Data Type Refinements (Priority 2.3 - Remaining Items)
   - Complete data type optimizations for all tables
   - Add CHECK constraints for data validation (MySQL 8.0+)
   - Implement ENUM types for fixed value lists (positions, conferences, etc.)
   - Convert monetary values to DECIMAL type
   - **Estimated Time:** 2-3 days
   - **Risk Level:** Low

2. **Phase 5:** Advanced Optimization (Priorities 5.2, 5.3)
   - Table partitioning for historical data (ibl_hist, ibl_box_scores)
   - Additional composite indexes based on actual usage patterns
   - Column size optimization to reduce storage
   - Query performance tuning based on production metrics
   - **Estimated Time:** 3-5 days
   - **Risk Level:** Medium

3. **Phase 6:** Schema Cleanup (Priorities 3.1, 3.2)
   - Legacy PhpNuke table evaluation and archival
   - Schema normalization (depth charts, career stats)
   - Separate legacy and active tables
   - Remove unused tables
   - **Estimated Time:** 3-5 days
   - **Risk Level:** Medium

4. **Phase 7:** Naming Convention Standardization (Priority 2.2) - 📋 MIGRATION READY
   - Standardize column naming to snake_case
   - Rename ID columns to consistent *_id pattern
   - Remove reserved word column names
   - **NOTE:** This is a BREAKING CHANGE - defer to API v2
   - **Estimated Time:** 5-7 days (migration) + 4-7 days (code updates)
   - **Risk Level:** High (requires extensive application code updates)
   - **Migration File:** `004_naming_convention_standardization.sql`
   - **Documentation:**
     - `004_APPLICATION_CODE_UPDATES.md` - Complete code update guide
     - `004_MANUAL_VERIFICATION.md` - Step-by-step verification procedures

See `DATABASE_SCHEMA_IMPROVEMENTS.md` for detailed roadmap and `SCHEMA_IMPLEMENTATION_REVIEW.md` for current status.

---

## Phase 7: Naming Convention Standardization (MIGRATION READY)

### Overview

**Migration File:** `004_naming_convention_standardization.sql`  
**Priority:** 2.2 (Deferred to API v2)  
**Status:** ⚠️ BREAKING CHANGE - Requires extensive application code updates  
**Prerequisites:** Phases 1, 2, and 3 must be completed

### What This Migration Does

This migration standardizes database naming conventions across 14 tables, renaming 46 columns to follow consistent patterns:

1. **snake_case Standardization**: All columns use lowercase with underscores (no PascalCase/camelCase)
2. **ID Column Pattern**: All ID columns follow `*_id` pattern (not `*ID`, `*id`, or `id*`)
3. **Reserved Words Removed**: Columns like `Date`, `Year`, `Name` renamed to avoid SQL reserved words
4. **Spaces Removed**: Columns with spaces (`` `Start Date` ``) renamed with underscores

### Tables and Columns Affected

| Table | Columns Renamed | Impact Level |
|-------|----------------|--------------|
| **ibl_schedule** | 8 columns | 🔴 CRITICAL - Most used table |
| **ibl_team_info** | 10 columns | 🔴 HIGH - Team management |
| **ibl_plr** | 7 columns | 🟡 MEDIUM - Player attributes |
| **ibl_box_scores** | 3 columns | 🔴 HIGH - Game results |
| **ibl_box_scores_teams** | 3 columns | 🟡 MEDIUM - Team stats |
| **ibl_power** | 4 columns | 🟡 MEDIUM - Power rankings |
| **ibl_sim_dates** | 3 columns | 🟢 LOW - Sim management |
| **ibl_team_awards** | 2 columns | 🟢 LOW - Awards |
| **ibl_awards** | 1 column | 🟢 LOW - Awards |
| **ibl_gm_history** | 1 column | 🟢 LOW - GM history |
| **ibl_plr_chunk** | 1 column | 🟢 LOW - Player chunks |
| **ibl_team_offense_stats** | 1 column | 🟡 MEDIUM - Stats |
| **ibl_team_defense_stats** | 1 column | 🟡 MEDIUM - Stats |
| **ibl_trade_cash** | 1 column | 🟢 LOW - Trades |

### Key Column Renames (Most Impactful)

**ibl_schedule** (used extensively in application):
- `Year` → `season_year`
- `BoxID` → `box_score_id`
- `Date` → `game_date`
- `Visitor` → `visitor_team_id`
- `VScore` → `visitor_score`
- `Home` → `home_team_id`
- `HScore` → `home_score`
- `SchedID` → `schedule_id`

**ibl_box_scores**:
- `Date` → `game_date`
- `homeTID` → `home_team_id`
- `visitorTID` → `visitor_team_id`

**ibl_team_info**:
- `Contract_Wins` → `contract_wins`
- `Contract_Losses` → `contract_losses`
- `discordID` → `discord_id`
- `HasMLE` → `has_mle`
- `HasLLE` → `has_lle`

**ibl_plr** (Player attributes):
- `Clutch` → `clutch`
- `Consistency` → `consistency`
- `PGDepth` → `pg_depth`, `SGDepth` → `sg_depth`, etc.

### Database Changes Made by Migration

1. **Foreign Keys Updated**: 5 foreign key constraints dropped and recreated
2. **Indexes Updated**: 15+ indexes dropped and recreated with new column names
3. **Views Updated**: `vw_schedule_upcoming` recreated to use new column names
4. **Column Constraints**: All NOT NULL, DEFAULT, AUTO_INCREMENT preserved

### Application Code Impact

**⚠️ CRITICAL: This is a BREAKING CHANGE**

All PHP code that references these columns must be updated before deploying this migration to production.

**Estimated Code Update Effort:**
- File identification: 4-6 hours
- Repository/Model updates: 8-12 hours
- View/Controller updates: 8-12 hours
- JavaScript/AJAX updates: 4-6 hours
- Testing: 8-16 hours
- **Total: 32-52 hours (4-6.5 days)**

**Files Requiring Updates:**
- All files querying `ibl_schedule` (schedule pages, game pages, team schedules)
- All files querying `ibl_box_scores` (box score pages, game results)
- All files querying `ibl_team_info` (team management, contracts, Discord integration)
- All files querying `ibl_plr` (player profiles, depth charts)
- Any JavaScript making AJAX calls that return these columns
- Repository/Service classes with SQL queries
- View templates displaying data from these tables

**See `004_APPLICATION_CODE_UPDATES.md` for:**
- Complete column rename mapping
- Search and replace strategies
- Code update patterns (SQL, arrays, objects)
- Testing strategies
- Rollback procedures

### Running the Migration

**⚠️ DO NOT RUN IN PRODUCTION WITHOUT:**
1. ✅ Completing all application code updates first
2. ✅ Full database backup
3. ✅ Testing in development/staging environment
4. ✅ Extended maintenance window (45-90 minutes)
5. ✅ Rollback plan ready

#### Step 1: Pre-Migration Preparation

```bash
# 1. Create full database backup
mysqldump -u username -p database_name > backup_phase7_$(date +%Y%m%d_%H%M%S).sql

# 2. Verify backup was created
ls -lh backup_phase7_*.sql

# 3. Test in development environment first
mysql -u username -p dev_database < 004_naming_convention_standardization.sql
```

#### Step 2: Application Code Updates

Before running migration in production, complete all code updates per `004_APPLICATION_CODE_UPDATES.md`:

1. Update all SQL queries to use new column names
2. Update array/object property access
3. Update WHERE/ORDER BY clauses
4. Update JavaScript/AJAX code
5. Test all affected features in development

#### Step 3: Execute Migration in Production

```bash
# After code updates are complete and tested:
mysql -u username -p database_name < 004_naming_convention_standardization.sql
```

**Estimated execution time:** 45-90 minutes depending on data size

#### Step 4: Verify Migration

Use `004_MANUAL_VERIFICATION.md` for complete verification:

```sql
-- Quick verification: Check new column names exist
SELECT TABLE_NAME, COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ibl_schedule'
  AND COLUMN_NAME IN ('season_year', 'game_date', 'home_team_id', 'visitor_team_id')
ORDER BY TABLE_NAME, ORDINAL_POSITION;
-- Should return 4 rows

-- Quick verification: Check old column names are gone
SELECT TABLE_NAME, COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ibl_schedule'
  AND COLUMN_NAME IN ('Year', 'Date', 'Home', 'Visitor')
ORDER BY TABLE_NAME;
-- Should return 0 rows

-- Verify foreign keys were recreated
SELECT COUNT(*) as fk_count
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME IS NOT NULL
  AND TABLE_NAME IN ('ibl_schedule', 'ibl_box_scores', 'ibl_power');
-- Should return at least 7

-- Test the updated view
SELECT * FROM vw_schedule_upcoming LIMIT 5;
-- Should return data without errors
```

#### Step 5: Application Testing

Complete all verification steps in `004_MANUAL_VERIFICATION.md`:

**Critical Tests:**
- [ ] Schedule pages load and display correctly
- [ ] Box score pages load and display correctly
- [ ] Team management pages work correctly
- [ ] Player profile pages work correctly
- [ ] No PHP errors in error log
- [ ] No MySQL errors in error log

### Rollback Procedure

If critical issues arise:

#### Option 1: Restore from Backup (Safest)

```bash
# Stop application
# Restore database
mysql -u username -p database_name < backup_phase7_YYYYMMDD_HHMMSS.sql
# Restart application with pre-migration code
```

#### Option 2: Reverse Column Renames (If no data changes)

See rollback section in `004_naming_convention_standardization.sql` for SQL to reverse column renames.

**⚠️ WARNING:** Rollback requires reverting application code changes as well!

### Migration Timeline

**Recommended Approach:**

1. **Week 1-2: Planning and Preparation**
   - Review all documentation
   - Identify all affected code files
   - Create detailed update plan
   - Set up comprehensive test environment

2. **Week 3-4: Code Updates (Development)**
   - Update repository/service classes
   - Update controllers/views
   - Update JavaScript/AJAX
   - Run unit tests

3. **Week 5: Testing (Staging)**
   - Deploy to staging environment
   - Run migration in staging database
   - Deploy updated code to staging
   - Execute full integration testing
   - Performance testing
   - User acceptance testing (if applicable)

4. **Week 6: Production Deployment**
   - Schedule extended maintenance window (2-4 hours)
   - Create production backup
   - Deploy updated code (but don't restart yet)
   - Run migration
   - Restart application with new code
   - Execute verification procedures
   - Monitor for 24-48 hours

**Total Timeline: 6 weeks minimum**

### Benefits After Migration

Once complete, the database will have:

✅ **Consistent Naming:** All columns follow snake_case convention  
✅ **Clear IDs:** All ID columns follow `*_id` pattern  
✅ **No Reserved Words:** Columns like `Date`, `Year` are renamed  
✅ **Better IDE Support:** Consistent naming improves autocomplete  
✅ **API Ready:** Clean column names for API v2  
✅ **Reduced Errors:** No more quoting issues with reserved words  
✅ **Maintainability:** Easier for new developers to understand schema  

### Documentation Files

1. **`004_naming_convention_standardization.sql`**
   - Complete migration SQL
   - Drops and recreates foreign keys
   - Updates indexes
   - Recreates database views
   - Includes verification queries

2. **`004_APPLICATION_CODE_UPDATES.md`**
   - Complete column rename mapping (46 columns)
   - Search and replace strategies
   - Code update patterns and examples
   - Repository update examples
   - JavaScript update examples
   - Testing strategies
   - Timeline estimates

3. **`004_MANUAL_VERIFICATION.md`**
   - Pre-migration verification steps
   - Post-migration database verification
   - Application functionality testing
   - Performance benchmarking
   - Error log checking
   - Integration test checklist
   - Rollback decision matrix

### When to Run This Migration

**Recommended:** Defer to API v2 release

**Reasons:**
1. Breaking change requires extensive code updates
2. Best done alongside major version release
3. Allows time for thorough testing
4. Can be combined with other API v2 improvements
5. Minimizes disruption to current users

**Alternatively:** Run during major refactoring initiative when significant code changes are already planned.

**Do NOT run if:**
- Active development is ongoing on affected tables
- Cannot afford extended downtime
- Code updates cannot be completed in timeframe
- Testing resources are limited

### Support

For questions or issues:
1. Review all three documentation files thoroughly
2. Test in development environment first
3. Consult `DATABASE_SCHEMA_IMPROVEMENTS.md` section 2.2
4. Keep database backups for at least 30 days post-migration
