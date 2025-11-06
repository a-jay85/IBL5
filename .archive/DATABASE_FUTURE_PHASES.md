# Database Schema Future Phases Roadmap

**Last Updated:** November 6, 2025  
**Current Status:** Phases 1, 2, 3, and 5.1 Complete ✅  
**Database Status:** FULLY API-READY 🚀

This document outlines the remaining database improvement phases (4-7) that can be implemented as needed to further optimize and modernize the IBL5 database schema.

---

## Overview of Completed Work

### ✅ Completed Phases (API-Ready Foundation)

**Phase 1: Critical Infrastructure** ✅
- 52 IBL tables converted from MyISAM to InnoDB
- 56+ indexes added for query optimization
- ACID transaction support enabled
- Row-level locking for better concurrency

**Phase 2: Data Integrity** ✅
- 24 foreign key constraints established
- Referential integrity enforced at database level
- Appropriate cascade strategies implemented

**Phase 3: API Preparation** ✅
- 19 tables with complete timestamp columns (created_at, updated_at)
- 5 tables with UUID support for secure public identifiers
- 5 database views for simplified API queries
- Complete audit trail and caching support

**Phase 5.1: Composite Indexes** ✅
- 4 strategic composite indexes for multi-column queries
- Optimized common query patterns

**Result:** Database is production-ready for public API deployment with all security best practices, performance optimizations, and modern features in place.

---

## Phase 4: Data Type Refinements

**Priority:** Medium  
**Estimated Time:** 2-3 days  
**Risk Level:** Low  
**Status:** Not started

### Objectives

Complete data type optimizations across all tables to:
- Reduce storage requirements
- Improve query performance
- Enforce data validation at the database level
- Use appropriate types for each field

### Part 4.1: Integer Type Optimization

**Current Issue:** Many tables use `INT(11)` where smaller types would suffice.

**Recommended Changes:**

```sql
-- Player statistics that will never exceed SMALLINT range
ALTER TABLE ibl_plr
  MODIFY stats_gm SMALLINT UNSIGNED,        -- Games: 0-65,535
  MODIFY stats_min MEDIUMINT UNSIGNED,      -- Minutes: 0-16,777,215
  MODIFY stats_fgm SMALLINT UNSIGNED,       -- Field goals made
  MODIFY stats_fga SMALLINT UNSIGNED,       -- Field goals attempted
  MODIFY stats_ftm SMALLINT UNSIGNED,       -- Free throws made
  MODIFY stats_fta SMALLINT UNSIGNED,       -- Free throws attempted
  MODIFY stats_3gm SMALLINT UNSIGNED,       -- 3-pointers made
  MODIFY stats_3ga SMALLINT UNSIGNED,       -- 3-pointers attempted
  MODIFY stats_orb SMALLINT UNSIGNED,       -- Offensive rebounds
  MODIFY stats_drb SMALLINT UNSIGNED,       -- Defensive rebounds
  MODIFY stats_ast SMALLINT UNSIGNED,       -- Assists
  MODIFY stats_stl SMALLINT UNSIGNED,       -- Steals
  MODIFY stats_to SMALLINT UNSIGNED,        -- Turnovers
  MODIFY stats_blk SMALLINT UNSIGNED,       -- Blocks
  MODIFY stats_pf SMALLINT UNSIGNED;        -- Personal fouls

-- Similar optimizations for ibl_hist, ibl_box_scores, etc.
```

**Expected Benefits:**
- 15-25% reduction in table sizes
- Improved query performance (smaller row sizes = better caching)
- Maintains full data range coverage

### Part 4.2: ENUM Type Implementation

**Current Issue:** Many VARCHAR fields store only a fixed set of values.

**Recommended Changes:**

```sql
-- Player positions
ALTER TABLE ibl_plr
  MODIFY pos ENUM('PG', 'SG', 'SF', 'PF', 'C', 'G', 'F', 'GF') NOT NULL;

-- Conference
ALTER TABLE ibl_standings
  MODIFY conference ENUM('Eastern', 'Western') NOT NULL;

-- Division names
ALTER TABLE ibl_standings
  MODIFY division ENUM('Atlantic', 'Central', 'Midwest', 'Pacific') NOT NULL;

-- Boolean flags (if not already TINYINT(1))
ALTER TABLE ibl_plr
  MODIFY active ENUM('0', '1') NOT NULL DEFAULT '1',
  MODIFY retired ENUM('0', '1') NOT NULL DEFAULT '0',
  MODIFY bird ENUM('0', '1') NOT NULL DEFAULT '0',
  MODIFY injured ENUM('0', '1') NOT NULL DEFAULT '0';
```

**Expected Benefits:**
- Enforced data validation
- Reduced storage (1-2 bytes vs VARCHAR)
- Better query optimization
- Self-documenting valid values

### Part 4.3: DECIMAL for Monetary Values

**Current Issue:** Contract values stored as INT, losing precision for fractional amounts.

**Recommended Changes:**

```sql
-- Player contracts
ALTER TABLE ibl_plr
  MODIFY cy DECIMAL(10,2),      -- Current year salary
  MODIFY cy1 DECIMAL(10,2),     -- Year 1 salary
  MODIFY cy2 DECIMAL(10,2),     -- Year 2 salary
  MODIFY cy3 DECIMAL(10,2),     -- Year 3 salary
  MODIFY cy4 DECIMAL(10,2),     -- Year 4 salary
  MODIFY cy5 DECIMAL(10,2),     -- Year 5 salary
  MODIFY cy6 DECIMAL(10,2);     -- Year 6 salary

-- Free agency offers
ALTER TABLE ibl_fa_offers
  MODIFY offer1 DECIMAL(10,2),
  MODIFY offer2 DECIMAL(10,2),
  MODIFY offer3 DECIMAL(10,2),
  MODIFY offer4 DECIMAL(10,2),
  MODIFY offer5 DECIMAL(10,2),
  MODIFY offer6 DECIMAL(10,2);

-- Demands
ALTER TABLE ibl_demands
  MODIFY dem1 DECIMAL(10,2),
  MODIFY dem2 DECIMAL(10,2),
  MODIFY dem3 DECIMAL(10,2),
  MODIFY dem4 DECIMAL(10,2),
  MODIFY dem5 DECIMAL(10,2),
  MODIFY dem6 DECIMAL(10,2);
```

**Expected Benefits:**
- Accurate financial calculations
- Support for fractional dollar amounts
- Standard accounting practice
- Better for API display

### Part 4.4: CHECK Constraints (MySQL 8.0+)

**Note:** Requires MySQL 8.0.16 or later.

**Recommended Changes:**

```sql
-- Age must be reasonable
ALTER TABLE ibl_plr
  ADD CONSTRAINT chk_age CHECK (age >= 18 AND age <= 50);

-- Peak age must be >= current age
ALTER TABLE ibl_plr
  ADD CONSTRAINT chk_peak CHECK (peak >= age);

-- Win percentage must be between 0 and 1
ALTER TABLE ibl_standings
  ADD CONSTRAINT chk_pct CHECK (pct >= 0 AND pct <= 1.000);

-- Games played cannot be negative
ALTER TABLE ibl_plr
  ADD CONSTRAINT chk_stats_gm CHECK (stats_gm >= 0);

-- Salary must be positive
ALTER TABLE ibl_plr
  ADD CONSTRAINT chk_cy CHECK (cy >= 0);
```

**Expected Benefits:**
- Database-level data validation
- Prevents invalid data insertion
- Clearer data contracts
- Reduces application-level validation code

### Part 4.5: NOT NULL Constraints

**Current Issue:** Many columns allow NULL but shouldn't.

**Recommended Changes:**

```sql
-- Core player fields should never be NULL
ALTER TABLE ibl_plr
  MODIFY pid INT NOT NULL,
  MODIFY name VARCHAR(50) NOT NULL,
  MODIFY pos ENUM(...) NOT NULL,
  MODIFY active ENUM(...) NOT NULL DEFAULT '1',
  MODIFY retired ENUM(...) NOT NULL DEFAULT '0';

-- Team fields
ALTER TABLE ibl_team_info
  MODIFY teamid INT NOT NULL,
  MODIFY team_city VARCHAR(50) NOT NULL,
  MODIFY team_name VARCHAR(50) NOT NULL;
```

**Expected Benefits:**
- Prevents NULL-related bugs
- Clearer data requirements
- Better query optimization
- Consistent data quality

### Migration File: `004_data_type_refinements.sql`

**Structure:**
1. Part 1: Integer type optimizations
2. Part 2: ENUM implementations
3. Part 3: DECIMAL for monetary values
4. Part 4: CHECK constraints (MySQL 8.0+)
5. Part 5: NOT NULL constraints
6. Verification queries
7. Rollback instructions

**Testing:**
- Verify data fits within new type ranges
- Test application functionality
- Validate performance improvements
- Measure storage reduction

---

## Phase 5: Advanced Optimization

**Priority:** Low-Medium  
**Estimated Time:** 3-5 days  
**Risk Level:** Medium  
**Status:** Not started

### Objectives

Optimize for very large datasets and high-traffic scenarios through:
- Table partitioning for historical data
- Additional composite indexes based on usage patterns
- Column size optimization
- Query performance tuning

### Part 5.2: Table Partitioning

**Target Tables:** Historical data tables that grow indefinitely.

**Candidates:**
- `ibl_hist` - Player historical statistics (partitioned by year)
- `ibl_box_scores` - Game box scores (partitioned by date/year)
- `ibl_schedule` - Game schedule (partitioned by year)

**Example Implementation:**

```sql
-- Partition ibl_hist by year
ALTER TABLE ibl_hist
PARTITION BY RANGE (year) (
  PARTITION p2015 VALUES LESS THAN (2016),
  PARTITION p2016 VALUES LESS THAN (2017),
  PARTITION p2017 VALUES LESS THAN (2018),
  PARTITION p2018 VALUES LESS THAN (2019),
  PARTITION p2019 VALUES LESS THAN (2020),
  PARTITION p2020 VALUES LESS THAN (2021),
  PARTITION p2021 VALUES LESS THAN (2022),
  PARTITION p2022 VALUES LESS THAN (2023),
  PARTITION p2023 VALUES LESS THAN (2024),
  PARTITION p2024 VALUES LESS THAN (2025),
  PARTITION p2025 VALUES LESS THAN (2026),
  PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- Partition box scores by date (year-based)
ALTER TABLE ibl_box_scores
PARTITION BY RANGE (YEAR(Date)) (
  PARTITION p2015 VALUES LESS THAN (2016),
  -- ... similar to above
  PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

**Expected Benefits:**
- Faster queries on recent data (partition pruning)
- Easier data archival (drop old partitions)
- Improved maintenance operations
- Better query optimization

**Considerations:**
- Requires careful planning
- All UNIQUE keys must include partition key
- Can't change partition key easily
- Test thoroughly before production

### Part 5.3: Additional Composite Indexes

**Based on actual usage patterns** from production logs.

**Analysis Steps:**
1. Review slow query log
2. Analyze common WHERE clause combinations
3. Identify missing composite indexes
4. Test performance impact

**Example Additional Indexes:**

```sql
-- Schedule matchup queries
ALTER TABLE ibl_schedule
  ADD INDEX idx_year_visitor_home (Year, Visitor, Home);

-- Standings ranking queries
ALTER TABLE ibl_standings
  ADD INDEX idx_conference_division_pct (conference, division, pct);

-- Free agency tracking
ALTER TABLE ibl_fa_offers
  ADD INDEX idx_year_team_pid (year, team, pid);

-- Historical player-team-year lookups
ALTER TABLE ibl_hist
  ADD INDEX idx_name_year (name, year);
```

### Part 5.4: Column Size Optimization

**Objective:** Reduce VARCHAR sizes where possible.

**Analysis:**

```sql
-- Find maximum actual length of VARCHAR columns
SELECT 
  TABLE_NAME,
  COLUMN_NAME,
  DATA_TYPE,
  CHARACTER_MAXIMUM_LENGTH AS current_size,
  MAX(LENGTH(COLUMN_NAME)) AS max_actual_length
FROM information_schema.COLUMNS
JOIN ibl_plr ON 1=1
WHERE TABLE_SCHEMA = 'iblhoops_ibl5'
  AND TABLE_NAME = 'ibl_plr'
  AND DATA_TYPE = 'varchar'
GROUP BY TABLE_NAME, COLUMN_NAME;
```

**Example Optimizations:**

```sql
-- If player names never exceed 50 characters
ALTER TABLE ibl_plr
  MODIFY name VARCHAR(50) NOT NULL;

-- If nicknames never exceed 100 characters
ALTER TABLE ibl_plr
  MODIFY nickname VARCHAR(100);
```

### Migration File: `005_advanced_optimization.sql`

**Structure:**
1. Part 1: Table partitioning (careful!)
2. Part 2: Additional composite indexes
3. Part 3: Column size optimizations
4. Part 4: Performance testing queries
5. Verification and rollback

---

## Phase 6: Schema Cleanup

**Priority:** Low  
**Estimated Time:** 3-5 days  
**Risk Level:** Medium  
**Status:** Not started

### Objectives

Clean up and modernize the schema structure:
- Evaluate and archive legacy PhpNuke tables
- Normalize denormalized structures
- Separate concerns more clearly

### Part 6.1: Legacy Table Evaluation

**Current State:** 84 PhpNuke tables (nuke_*) remain as MyISAM.

**Action Plan:**

1. **Audit Usage:**
   ```sql
   -- Check table access in slow query log
   -- Review application code references
   -- Identify completely unused tables
   ```

2. **Categorize Tables:**
   - Still in use → Convert to InnoDB
   - Archival data → Export and remove
   - Completely unused → Drop

3. **Implementation:**
   ```sql
   -- For tables still in use
   ALTER TABLE nuke_used_table ENGINE=InnoDB;
   
   -- For archival
   CREATE TABLE archive_nuke_old_data AS SELECT * FROM nuke_old_data;
   -- Export and drop original
   
   -- For unused
   DROP TABLE IF EXISTS nuke_completely_unused;
   ```

### Part 6.2: Schema Normalization

**Target: Depth Chart Data**

**Current Problem:** `ibl_plr` has columns:
- `PGDepth`, `SGDepth`, `SFDepth`, `PFDepth`, `CDepth`
- `dc_PGDepth`, `dc_SGDepth`, `dc_SFDepth`, `dc_PFDepth`, `dc_CDepth`

**Normalized Structure:**

```sql
CREATE TABLE ibl_depth_chart (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pid INT NOT NULL,
  position ENUM('PG', 'SG', 'SF', 'PF', 'C') NOT NULL,
  depth_order TINYINT NOT NULL,
  chart_type ENUM('offensive', 'defensive') NOT NULL DEFAULT 'offensive',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (pid) REFERENCES ibl_plr(pid) ON DELETE CASCADE,
  UNIQUE KEY unique_position_depth (pid, position, chart_type, depth_order)
) ENGINE=InnoDB;

-- Migrate data
INSERT INTO ibl_depth_chart (pid, position, depth_order, chart_type)
SELECT pid, 'PG', PGDepth, 'offensive' FROM ibl_plr WHERE PGDepth IS NOT NULL
UNION ALL
SELECT pid, 'SG', SGDepth, 'offensive' FROM ibl_plr WHERE SGDepth IS NOT NULL
-- ... continue for all positions

-- Drop old columns
ALTER TABLE ibl_plr
  DROP COLUMN PGDepth,
  DROP COLUMN SGDepth,
  DROP COLUMN SFDepth,
  DROP COLUMN PFDepth,
  DROP COLUMN CDepth,
  DROP COLUMN dc_PGDepth,
  DROP COLUMN dc_SGDepth,
  DROP COLUMN dc_SFDepth,
  DROP COLUMN dc_PFDepth,
  DROP COLUMN dc_CDepth;
```

**Target: Career Statistics**

**Current Problem:** Separate tables:
- `ibl_season_career_avgs`
- `ibl_playoff_career_avgs`
- `ibl_heat_career_avgs`
- `ibl_olympics_career_avgs`

**Normalized Structure:**

```sql
CREATE TABLE ibl_career_stats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pid INT NOT NULL,
  stat_type ENUM('season', 'playoff', 'heat', 'olympics') NOT NULL,
  games INT NOT NULL DEFAULT 0,
  minutes DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  -- ... other stats
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (pid) REFERENCES ibl_plr(pid) ON DELETE CASCADE,
  UNIQUE KEY unique_player_type (pid, stat_type)
) ENGINE=InnoDB;
```

### Migration File: `006_schema_cleanup.sql`

**Structure:**
1. Part 1: Archive/drop legacy tables
2. Part 2: Normalize depth charts
3. Part 3: Normalize career stats
4. Part 4: Create views for backward compatibility
5. Verification and rollback

---

## Phase 7: Naming Convention Standardization

**Priority:** Low  
**Estimated Time:** 5-7 days  
**Risk Level:** HIGH (Breaking change)  
**Status:** Not started  
**Recommendation:** Defer to API v2

### Objectives

Standardize all naming conventions for better developer experience:
- Use snake_case consistently
- Standardize ID column naming
- Remove reserved word column names
- Consistent prefixing

### WARNING ⚠️

**This is a BREAKING CHANGE that will require:**
- Application code updates
- API endpoint changes (defer to v2)
- View recreation
- Extensive testing
- Coordinated deployment

### Example Changes

**Before:**
```sql
CREATE TABLE ibl_schedule (
  SchedID int,
  Date date,
  Visitor int,
  Home int,
  VScore int,
  HScore int,
  BoxID int
)
```

**After:**
```sql
CREATE TABLE ibl_schedule (
  schedule_id INT,          -- Was: SchedID
  game_date DATE,           -- Was: Date (reserved word)
  visitor_team_id INT,      -- Was: Visitor
  home_team_id INT,         -- Was: Home
  visitor_score INT,        -- Was: VScore
  home_score INT,           -- Was: HScore
  box_score_id INT          -- Was: BoxID
)
```

### Implementation Strategy

1. **Create views with old names** for backward compatibility
2. **Update application code** to use new names
3. **Deprecate old views** after transition period
4. **Remove old views** in API v2

### Migration File: `007_naming_standardization.sql`

**This should only be implemented:**
- After full application audit
- With comprehensive test coverage
- During a major version upgrade
- With clear migration path for API consumers

---

## Implementation Guidelines

### General Best Practices

1. **Always backup before migrations**
   ```bash
   mysqldump -u username -p database > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Test in dev/staging first**
   - Never run directly on production
   - Validate queries work after migration
   - Test application functionality

3. **Monitor performance**
   - Baseline before migration
   - Compare after migration
   - Track improvements

4. **Document changes**
   - Update ER diagrams
   - Update API documentation
   - Update code comments

### Rollback Procedures

Each migration file should include:
- Clear rollback instructions
- Backup verification steps
- Recovery procedures
- Emergency contacts

### Success Criteria

For each phase:
- ✅ All migrations execute without errors
- ✅ Application functionality intact
- ✅ Performance metrics improved or maintained
- ✅ No data loss
- ✅ Tests pass
- ✅ Documentation updated

---

## Priority Recommendations

### Must Do (If Continuing Development)
- None - Database is fully API-ready!

### Should Do (Next 6 months)
- **Phase 4** - If storage or performance becomes an issue
- **Phase 5.3** - Add composite indexes based on actual usage patterns

### Could Do (Next 12 months)
- **Phase 5.2** - Table partitioning for very large tables
- **Phase 6.1** - Legacy table cleanup for clarity

### Defer (Until Major Version)
- **Phase 6.2** - Normalization (breaking changes)
- **Phase 7** - Naming standardization (breaking changes)

---

## Conclusion

The database schema is in excellent shape after completing Phases 1, 2, 3, and 5.1. The remaining phases are **optional enhancements** that can be implemented based on:
- Specific performance needs
- Storage constraints
- Developer preference
- API version requirements

**Current Status: Production-ready for public API deployment! 🚀**

The foundation is solid, secure, and performant. Future phases can be tackled as needed to further optimize and modernize the schema, but they are not required for successful API operation.
