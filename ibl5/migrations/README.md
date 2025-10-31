# Database Migrations

This directory contains SQL migration scripts to improve the IBL5 database schema.

## Overview

These migrations implement the recommendations from `DATABASE_SCHEMA_IMPROVEMENTS.md` in a phased approach to minimize risk and downtime.

## Migration Files

### 001_critical_improvements.sql (Phase 1)
**Priority:** Critical  
**Estimated Time:** 30-60 minutes  
**Risk Level:** Low

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

### 002_add_foreign_keys.sql (Phase 2)
**Priority:** High  
**Estimated Time:** 10-20 minutes  
**Risk Level:** Low

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

After Phase 1 and Phase 2 are stable, consider:

1. **Phase 3:** Schema normalization and cleanup
2. **Phase 4:** API-specific enhancements (UUIDs, views)
3. **Phase 5:** Advanced optimization (partitioning, etc.)

See `DATABASE_SCHEMA_IMPROVEMENTS.md` for detailed roadmap.
