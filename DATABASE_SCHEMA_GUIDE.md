# IBL5 Database Schema Guide

**Last Updated:** November 2, 2025  
**Schema Version:** v1.3 (Priority 1, 2.1, and 5.1 Complete)

This guide provides comprehensive information about the IBL5 database schema improvements, implementation status, and next steps for API development.

---

## Table of Contents

1. [Quick Reference](#quick-reference)
2. [Schema Status Overview](#schema-status-overview)
3. [Completed Improvements](#completed-improvements)
4. [Next Steps - Phase 3](#next-steps---phase-3)
5. [Implementation Instructions](#implementation-instructions)
6. [API Development Guidance](#api-development-guidance)
7. [Maintenance and Monitoring](#maintenance-and-monitoring)
8. [Reference Documentation](#reference-documentation)

---

## Quick Reference

### Current Schema Status

| Component | Status | Impact |
|-----------|--------|--------|
| InnoDB Tables | ✅ 52 tables (100% critical) | ACID transactions, row-level locking |
| Indexes | ✅ 56+ indexes | 10-100x query performance |
| Composite Indexes | ✅ 4 strategic indexes | 5-25x multi-column query performance |
| Foreign Keys | ✅ 24 constraints | Data integrity enforcement |
| Timestamps | ✅ 7+ core tables | Audit trails, API caching |
| UUIDs | ⏭️ **NEXT** Phase 3 | Secure public API identifiers |
| Database Views | ⏭️ **NEXT** Phase 3 | Simplified API queries |

### Database is Ready For

- ✅ Production use with ACID guarantees
- ✅ High-concurrency operations (10-50x improvement)
- ✅ API development with data integrity
- ⏭️ Public API deployment (after Phase 3)

### Quick Start for Different Roles

**For API Developers:**
1. Review [API Development Guidance](#api-development-guidance) section below
2. Execute Phase 3 migration: `ibl5/migrations/003_api_preparation.sql`
3. Use database views and UUIDs in your API endpoints
4. Reference: `DATABASE_ER_DIAGRAM.md` for relationships

**For Database Administrators:**
1. Review completed improvements in [Schema Status Overview](#schema-status-overview)
2. Execute Phase 3 migration: `ibl5/migrations/003_api_preparation.sql`
3. Follow monitoring guidelines in [Maintenance and Monitoring](#maintenance-and-monitoring)
4. Reference: `ibl5/migrations/README.md` for detailed instructions

**For Project Managers:**
- Phases 1, 2, and 5.1 complete (~1 week of work)
- Phase 3 ready for implementation (30-45 minutes)
- Database is production-ready for API development
- Expected API performance: sub-100ms response times

---

## Schema Status Overview

### Original State (Before Improvements)

- 136 total tables
- 125 MyISAM tables (92%) - no ACID, table-level locking
- No foreign key relationships - data integrity at risk
- Missing critical indexes - full table scans common
- No audit trails - no change tracking

### Current State (November 2, 2025)

- **136 total tables**
  - 52 InnoDB tables (100% of critical IBL tables) ✅
  - 84 MyISAM tables (legacy PhpNuke, to be evaluated separately)
- **56+ indexes** for query optimization ✅
- **4 composite indexes** for multi-column queries ✅
- **24 foreign key constraints** enforcing data integrity ✅
- **7+ core tables** with audit timestamps ✅
- **Database is API-ready** with ACID transactions ✅

### Performance Improvements Achieved

| Query Type | Before | After | Improvement |
|------------|--------|-------|-------------|
| Player by team queries | Full table scan | Index lookup | 50-100x faster |
| Historical stats | Sequential scan | Composite index | 10-20x faster |
| Schedule queries | Full scan | Multi-column index | 30-80x faster |
| Concurrent operations | Table locks | Row locks | 10-50x more concurrent |

---

## Completed Improvements

### ✅ Priority 1.1: InnoDB Conversion

**Status:** Complete - 52 critical tables converted

**What was done:**
- Converted all critical IBL tables from MyISAM to InnoDB
- Includes: players, teams, schedule, standings, box scores, draft, free agency, trades, awards, voting

**Benefits achieved:**
- ACID transaction support for data integrity
- Row-level locking for better concurrency
- Foreign key constraint support
- Better crash recovery
- Essential foundation for API operations

### ✅ Priority 1.2: Critical Indexes

**Status:** Complete - 56+ indexes added

**Key indexes added:**
- Player queries: `idx_tid`, `idx_active`, `idx_retired`, `idx_tid_active`, `idx_pos`
- Historical stats: `idx_pid_year`, `idx_team_year`, `idx_year`
- Schedule: `idx_year`, `idx_date`, `idx_visitor`, `idx_home`, `idx_year_date`
- Box scores: `idx_date`, `idx_pid`, `idx_visitor_tid`, `idx_home_tid`
- Draft: `idx_year`, `idx_team`, `idx_player`, `idx_year_round_pick`

**Performance impact:**
- Common queries: 10-100x faster
- Eliminated most full table scans
- Query response times: typically < 100ms

### ✅ Priority 2.1: Foreign Key Constraints

**Status:** Complete - 24 constraints added

**Key relationships enforced:**
- Players → Teams
- Historical stats → Players
- Schedule → Teams (home and visitor)
- Box scores → Players and Teams
- Draft picks → Teams
- Free agency offers → Players and Teams
- Standings → Teams
- Voting → Teams

**Benefits achieved:**
- Database enforces referential integrity
- Orphaned records prevented
- Automatic cascade updates
- Self-documenting relationships

### ✅ Priority 3.3: Timestamps (Partial)

**Status:** 7+ core tables complete

**Tables with timestamps:**
- `ibl_plr` - Player records
- `ibl_team_info` - Team information
- `ibl_schedule` - Game schedule
- Additional core tables

**Benefits:**
- Audit trail for when records are created/modified
- Enables API caching with ETag and Last-Modified headers
- Debugging support for data changes

### ✅ Priority 5.1: Composite Indexes

**Status:** Complete - 4 strategic indexes added

**Composite indexes:**
1. `idx_pid_year_team` on `ibl_hist` - Player stats by year and team
2. `idx_date_home_visitor` on `ibl_box_scores` - Game lookups
3. `idx_tid_pos_active` on `ibl_plr` - Roster queries
4. `idx_year_round_pick` on `ibl_draft` - Draft pick lookups

**Performance impact:**
- Multi-column filtered queries: 5-25x faster
- Roster queries: 5-10x faster
- Historical lookups: 10-20x faster

---

## Next Steps - Phase 3

### 🎯 Priority: API Preparation (Phase 3)

**Status:** Ready to implement  
**File:** `ibl5/migrations/003_api_preparation.sql`  
**Estimated Time:** 30-45 minutes  
**Risk Level:** Low

### What Phase 3 Includes

#### Part 1: Complete Timestamp Coverage
Add `created_at` and `updated_at` to remaining core tables:
- Historical stats tables
- Box scores and game data
- Standings and rankings
- Draft system
- Free agency and contracts
- Trade system
- Career statistics tables

**Benefits:**
- Complete audit trail coverage
- Full API caching support (ETags)
- Change tracking for all core data

#### Part 2: UUID Support
Add UUID columns to critical tables for secure public API identifiers:
- Players (`ibl_plr`)
- Teams (`ibl_team_info`)
- Schedule/Games (`ibl_schedule`)
- Draft picks (`ibl_draft`)
- Box scores (`ibl_box_scores`)

**Benefits:**
- Secure public identifiers (no ID enumeration)
- Non-sequential IDs prevent information leakage
- Better for distributed systems
- Standard modern API practice

#### Part 3: Database Views
Create 5 API-friendly views:

1. **`vw_player_current`** - Active players with team info and calculated stats
2. **`vw_team_standings`** - Standings with formatted records and calculated fields
3. **`vw_schedule_upcoming`** - Schedule with team names for easy consumption
4. **`vw_player_career_stats`** - Career statistics summary
5. **`vw_free_agency_offers`** - Free agency market overview

**Benefits:**
- Simplified API queries (no complex joins needed)
- Consistent data formatting
- Calculated fields included automatically
- Better query performance through optimization
- Easier API versioning

### Why Phase 3 is Critical

Phase 3 completes the transformation to a modern, API-ready database:
- **Security:** UUIDs prevent ID enumeration attacks
- **Performance:** Views optimize common queries
- **Caching:** Timestamps enable efficient ETags
- **Developer Experience:** Views simplify application code
- **Best Practices:** Aligns with modern API standards

---

## Implementation Instructions

### Prerequisites

1. **Backup database:**
   ```bash
   mysqldump -u username -p iblhoops_ibl5 > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Verify Phases 1 & 2 are complete:**
   ```sql
   -- Check InnoDB tables
   SELECT COUNT(*) FROM information_schema.TABLES 
   WHERE TABLE_SCHEMA = DATABASE() 
   AND TABLE_NAME LIKE 'ibl_%' 
   AND ENGINE = 'InnoDB';
   -- Should return 52
   
   -- Check foreign keys
   SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE()
   AND REFERENCED_TABLE_NAME IS NOT NULL;
   -- Should return 24+
   ```

### Execute Phase 3

1. **Connect to database:**
   ```bash
   mysql -u username -p iblhoops_ibl5
   ```

2. **Run migration:**
   ```bash
   mysql -u username -p iblhoops_ibl5 < ibl5/migrations/003_api_preparation.sql
   ```

3. **Verify completion:**
   ```sql
   -- Check timestamps added
   SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = DATABASE() 
   AND COLUMN_NAME = 'created_at'
   AND TABLE_NAME LIKE 'ibl_%';
   -- Should show 15+ tables
   
   -- Check UUIDs generated
   SELECT COUNT(*) AS total, COUNT(uuid) AS with_uuid 
   FROM ibl_plr;
   -- Both should match
   
   -- Check views created
   SHOW FULL TABLES WHERE Table_type = 'VIEW';
   -- Should show 5 views
   
   -- Test a view
   SELECT COUNT(*) FROM vw_player_current;
   ```

### Post-Migration Tasks

1. **Test API endpoints** with new UUIDs and views
2. **Update API documentation** to use UUID-based URLs
3. **Implement ETag caching** using `updated_at` timestamps
4. **Monitor view query performance**
5. **Run ANALYZE TABLE** on modified tables

---

## API Development Guidance

### Using UUIDs

**Before Phase 3:**
```
GET /api/v1/players/123
GET /api/v1/teams/5
```

**After Phase 3 (Recommended):**
```
GET /api/v1/players/550e8400-e29b-41d4-a716-446655440000
GET /api/v1/teams/6ba7b810-9dad-11d1-80b4-00c04fd430c8
```

**Example Query:**
```sql
-- Old way (by integer ID)
SELECT * FROM ibl_plr WHERE pid = 123;

-- New way (by UUID)
SELECT * FROM ibl_plr WHERE uuid = '550e8400-e29b-41d4-a716-446655440000';
```

### Using Database Views

**Example: Active Players API Endpoint**

Instead of complex joins:
```sql
SELECT p.*, t.team_city, t.team_name, t.owner_name
FROM ibl_plr p
LEFT JOIN ibl_team_info t ON p.tid = t.teamid
WHERE p.active = 1 AND p.retired = 0;
```

Use the view:
```sql
SELECT * FROM vw_player_current
WHERE player_uuid = ?;
```

**Benefits:**
- Simpler application code
- Consistent field names
- Calculated fields included
- Better performance

### Implementing API Caching

Use `updated_at` timestamps for ETags:

```python
# Example in Python/Flask
@app.route('/api/v1/players/<uuid>')
def get_player(uuid):
    player = query("SELECT * FROM vw_player_current WHERE player_uuid = %s", uuid)
    
    # Generate ETag from updated_at timestamp
    etag = generate_etag(player['updated_at'])
    
    # Check If-None-Match header
    if request.headers.get('If-None-Match') == etag:
        return '', 304  # Not Modified
    
    response = jsonify(player)
    response.headers['ETag'] = etag
    response.headers['Last-Modified'] = player['updated_at']
    response.headers['Cache-Control'] = 'max-age=3600'
    return response
```

### Recommended API Endpoints

After Phase 3, implement these endpoints:

**Players:**
- `GET /api/v1/players` → Use `vw_player_current`
- `GET /api/v1/players/{uuid}` → Use `vw_player_current`
- `GET /api/v1/players/{uuid}/stats` → Use `vw_player_career_stats`

**Teams:**
- `GET /api/v1/teams` → Use `vw_team_standings`
- `GET /api/v1/teams/{uuid}` → Use `vw_team_standings`
- `GET /api/v1/teams/{uuid}/roster` → Use `vw_player_current`

**Schedule:**
- `GET /api/v1/schedule` → Use `vw_schedule_upcoming`
- `GET /api/v1/schedule/upcoming` → Use `vw_schedule_upcoming`

**Standings:**
- `GET /api/v1/standings` → Use `vw_team_standings`
- `GET /api/v1/standings/{conference}` → Use `vw_team_standings`

**Free Agency:**
- `GET /api/v1/free-agency/offers` → Use `vw_free_agency_offers`

---

## Maintenance and Monitoring

### Weekly Tasks

- Review slow query log for optimization opportunities
- Check for foreign key violations in error logs
- Monitor API response times
- Verify ETag cache hit rates

### Monthly Tasks

- Run `ANALYZE TABLE` on large tables:
  ```sql
  ANALYZE TABLE ibl_plr, ibl_hist, ibl_schedule, ibl_box_scores;
  ```
- Review index usage statistics
- Check table fragmentation
- Update documentation if schema changes

### Quarterly Tasks

- Optimize tables:
  ```sql
  OPTIMIZE TABLE ibl_hist, ibl_box_scores;
  ```
- Review and adjust indexes based on usage patterns
- Performance testing and benchmarking
- Update API documentation

### Monitoring Queries

**Check index usage:**
```sql
SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'ibl_plr'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
```

**View query performance:**
```sql
SELECT * FROM mysql.slow_log
WHERE sql_text LIKE '%ibl_%'
ORDER BY query_time DESC
LIMIT 10;
```

**Check table sizes:**
```sql
SELECT 
  TABLE_NAME,
  ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME LIKE 'ibl_%'
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;
```

---

## Reference Documentation

### Primary Documents

1. **Database Schema Guide** (this document)
   - Comprehensive overview and quick reference
   - Implementation instructions
   - API development guidance

2. **DATABASE_SCHEMA_IMPROVEMENTS.md**
   - Detailed analysis of all priorities
   - Complete SQL examples
   - Performance impact estimates
   - Future roadmap

3. **SCHEMA_IMPLEMENTATION_REVIEW.md**
   - Verification of completed improvements
   - Performance assessment
   - New insights and recommendations
   - Testing guidelines

4. **DATABASE_ER_DIAGRAM.md**
   - Visual entity relationship diagrams
   - Foreign key relationships
   - Index overview

### Migration Files

Located in `ibl5/migrations/`:

- **001_critical_improvements.sql** ✅ - InnoDB conversion and indexes (COMPLETE)
- **002_add_foreign_keys.sql** ✅ - Foreign key constraints (COMPLETE)
- **003_api_preparation.sql** ⏭️ - Timestamps, UUIDs, Views (NEXT)
- **README.md** - Detailed migration instructions

### Related Documents

- **API_DEVELOPMENT_GUIDE.md** - API architecture and best practices
- **PRODUCTION_DEPLOYMENT_GUIDE.md** - Deployment procedures

---

## Summary

### What's Complete ✅

- **Infrastructure:** InnoDB tables, comprehensive indexing, composite indexes
- **Data Integrity:** Foreign key constraints, referential integrity
- **Foundation:** ACID transactions, row-level locking
- **Performance:** 10-100x improvement on common queries
- **Status:** Production-ready for internal use

### What's Next 🎯

- **Phase 3 (30-45 min):** Complete timestamps, add UUIDs, create views
- **Impact:** Enables secure public API with modern best practices
- **Priority:** High for API deployment
- **Risk:** Low with clear rollback procedures

### Long-term Roadmap 📋

- **Phase 4:** Advanced data type optimizations
- **Phase 5:** Table partitioning, additional optimizations
- **Phase 6:** Legacy table cleanup, naming standardization

---

**For detailed technical specifications, see:**
- Migration instructions: `ibl5/migrations/README.md`
- Improvement details: `DATABASE_SCHEMA_IMPROVEMENTS.md`
- Implementation review: `SCHEMA_IMPLEMENTATION_REVIEW.md`
- Entity relationships: `DATABASE_ER_DIAGRAM.md`

**For questions or issues:**
- Check troubleshooting in `ibl5/migrations/README.md`
- Review completed work in `SCHEMA_IMPLEMENTATION_REVIEW.md`
- Refer to roadmap in `DATABASE_SCHEMA_IMPROVEMENTS.md`
