# Production Deployment Guide

## Character Encoding Configuration

**Issue:** Characters with accents (é, ñ, etc.) display as � in production  
**Solution:** Use UTF-8 (utf8mb4) throughout

### Deployment Steps

**1. Update Application Code (Safe - No Data Impact)**

Deploy these updated files:
- `ibl5/classes/MySQL.php`
- `ibl5/db/db.php`
- `ibl5/db/mysql4.php`

These set UTF-8 encoding after MySQL connection. Safe to deploy immediately.

**2. Convert Database Tables (Requires Planning)**

⚠️ **IMPORTANT:** Plan for maintenance window. Table conversion locks tables during operation.

**Option A: Automated Conversion (Recommended)**

```sql
-- Generate ALTER statements for all latin1 tables
SELECT CONCAT('ALTER TABLE `', table_name, 
    '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
FROM information_schema.TABLES
WHERE table_schema = 'your_database_name'
AND table_collation LIKE 'latin1%';
```

Review generated statements, then execute during maintenance window.

**Option B: Manual Conversion (Large Databases)**

```sql
-- Example for single table
ALTER TABLE ibl_hist 
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**For Large Tables:**
- Convert in batches during low-traffic periods
- Consider `pt-online-schema-change` tool
- Monitor table locks

### Verification

```sql
-- Check table charset
SHOW TABLE STATUS WHERE Name = 'ibl_hist';

-- Check column charsets
SHOW FULL COLUMNS FROM ibl_hist;
```

## General Deployment Checklist

- [ ] Code tested in staging environment
- [ ] Database migrations tested
- [ ] Backup database before changes
- [ ] Plan maintenance window for DB changes
- [ ] Monitor application after deployment
- [ ] Rollback plan ready

## Resources

- [DATABASE_GUIDE.md](DATABASE_GUIDE.md) - Schema reference
- [DATABASE_OPTIMIZATION_GUIDE.md](DATABASE_OPTIMIZATION_GUIDE.md) - Migration best practices
- Production schema: `ibl5/schema.sql`
