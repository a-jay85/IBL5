# IBL5 Documentation Index

**Last Updated:** November 9, 2025

This index provides a guide to all documentation in the IBL5 project, organized by topic and purpose.

## 📚 Core Documentation (Active)

### Database Documentation

1. **[DATABASE_OPTIMIZATION_GUIDE.md](DATABASE_OPTIMIZATION_GUIDE.md)** ⭐ PRIMARY REFERENCE
   - **Purpose:** Authoritative guide for database optimization efforts
   - **Audience:** Database administrators, developers implementing optimizations
   - **Content:**
     - Current production schema status
     - Completed optimization phases (1-3, 5.1)
     - Re-prioritized roadmap
     - Foreign key and constraint interaction details
     - Migration best practices and troubleshooting
   - **Status:** Active, current as of November 9, 2025

2. **[DATABASE_GUIDE.md](DATABASE_GUIDE.md)**
   - **Purpose:** Quick reference for developers working with the database
   - **Audience:** Application developers
   - **Content:**
     - Key tables and relationships
     - Common query patterns
     - Index usage guidelines
     - API-ready features (UUIDs, views, timestamps)
   - **Status:** Active, regularly updated

3. **[ibl5/migrations/README.md](ibl5/migrations/README.md)**
   - **Purpose:** Migration execution guide
   - **Audience:** Database administrators executing migrations
   - **Content:**
     - Detailed migration file descriptions
     - Step-by-step execution procedures
     - Verification queries
     - Rollback procedures
     - Troubleshooting common issues
   - **Status:** Active, updated with Phase 4 corrections needed

4. **[MIGRATION_004_FIXES.md](MIGRATION_004_FIXES.md)**
   - **Purpose:** Technical details on Migration 004 column name corrections
   - **Audience:** Developers fixing migration 004
   - **Content:**
     - Specific column name mismatches
     - Comparison with actual schema.sql
     - Preserved vs. removed optimizations
   - **Status:** Active, reference for fixing migration

### API Documentation

5. **[API_GUIDE.md](API_GUIDE.md)**
   - **Purpose:** Guide for developing REST API endpoints
   - **Audience:** API developers
   - **Content:**
     - API architecture and best practices
     - Database views usage
     - UUID-based endpoints
     - ETag and caching strategies
   - **Status:** Active

### Development Documentation

6. **[DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md)**
   - **Purpose:** General development guidelines
   - **Audience:** All developers
   - **Content:**
     - Coding standards
     - Testing practices
     - Refactoring guidelines
   - **Status:** Active

7. **[COPILOT_AGENT.md](COPILOT_AGENT.md)**
   - **Purpose:** AI-assisted development standards
   - **Audience:** Developers using GitHub Copilot
   - **Content:**
     - Coding patterns
     - Comment standards
     - Best practices for AI-assisted coding
   - **Status:** Active

### Deployment Documentation

8. **[PRODUCTION_DEPLOYMENT_GUIDE.md](PRODUCTION_DEPLOYMENT_GUIDE.md)**
   - **Purpose:** Production deployment procedures
   - **Audience:** DevOps, system administrators
   - **Status:** Active

## 📦 Archived Documentation

**Location:** `.archive/` directory

These documents contain valuable historical information but have been superseded by newer documentation:

### Database (Archived)

- **DATABASE_SCHEMA_IMPROVEMENTS.md**
  - Original optimization recommendations (November 1-4, 2025)
  - Superseded by: DATABASE_OPTIMIZATION_GUIDE.md
  - Historical value: Original analysis and priorities

- **DATABASE_SCHEMA_GUIDE.md**
  - Earlier developer database guide
  - Superseded by: DATABASE_GUIDE.md
  - Historical value: Legacy schema context

- **DATABASE_FUTURE_PHASES.md**
  - Original future optimization roadmap
  - Superseded by: DATABASE_OPTIMIZATION_GUIDE.md (roadmap section)
  - Historical value: Original planning

- **SCHEMA_IMPLEMENTATION_REVIEW.md**
  - Phase 1-3 implementation review
  - Superseded by: DATABASE_OPTIMIZATION_GUIDE.md (completed phases)
  - Historical value: Detailed implementation notes

- **DATABASE_ER_DIAGRAM.md**
  - Entity-relationship documentation
  - Status: Reference material

### Feature-Specific (Archived)

- **FORUMS_REMOVAL_SUMMARY.md** - Forums removal work
- **PHASE3_COMPLETION_SUMMARY.md** - Phase 3 API prep completion
- **API_DEVELOPMENT_GUIDE.md** - Early API guide
- **API_QUICKSTART_PHASE3.md** - Phase 3 quickstart
- Various refactoring summaries (Player, Team, Draft, etc.)

### Other Archived Docs

- **IMPLEMENTATION_COMPLETE.md** - Various completion summaries
- **TASK_COMPLETE.md** - Task completion reports
- **REFACTORING_*.md** - Various refactoring documentation
- **CODE_REVIEW.md** - Code review summaries

## 📊 Documentation Relationships

```
DATABASE_OPTIMIZATION_GUIDE.md (Strategy & Roadmap)
    ↓
    ├─→ DATABASE_GUIDE.md (Developer Reference)
    │
    ├─→ ibl5/migrations/README.md (Execution Guide)
    │       ↓
    │       └─→ MIGRATION_004_FIXES.md (Technical Details)
    │
    └─→ API_GUIDE.md (API Development)
```

## 🎯 Quick Start by Role

### Database Administrator
**Primary docs:**
1. DATABASE_OPTIMIZATION_GUIDE.md - Understand current state and priorities
2. ibl5/migrations/README.md - Execute migrations
3. MIGRATION_004_FIXES.md - Fix migration 004 (current priority)

### Application Developer
**Primary docs:**
1. DATABASE_GUIDE.md - Understand schema and query patterns
2. API_GUIDE.md - Build API endpoints
3. DEVELOPMENT_GUIDE.md - Follow development standards

### DevOps/SysAdmin
**Primary docs:**
1. PRODUCTION_DEPLOYMENT_GUIDE.md - Deploy to production
2. DATABASE_OPTIMIZATION_GUIDE.md - Understand database changes
3. ibl5/migrations/README.md - Maintenance procedures

## 🔍 Finding Information

### "I need to optimize the database"
→ **DATABASE_OPTIMIZATION_GUIDE.md**

### "I need to query player data"
→ **DATABASE_GUIDE.md** (query patterns section)

### "I need to run a migration"
→ **ibl5/migrations/README.md**

### "I need to build an API endpoint"
→ **API_GUIDE.md**

### "Migration 004 is failing"
→ **MIGRATION_004_FIXES.md**

### "How do I deploy?"
→ **PRODUCTION_DEPLOYMENT_GUIDE.md**

### "What happened in Phase 1/2/3?"
→ **DATABASE_OPTIMIZATION_GUIDE.md** (completed phases) or `.archive/SCHEMA_IMPLEMENTATION_REVIEW.md` (details)

## 📝 Documentation Maintenance

### When to Update Documentation

1. **DATABASE_OPTIMIZATION_GUIDE.md:**
   - When priorities change
   - When migrations are completed
   - When new optimization opportunities are identified
   - Major schema changes

2. **DATABASE_GUIDE.md:**
   - When new tables are added
   - When indexes change significantly
   - When common query patterns change

3. **ibl5/migrations/README.md:**
   - When new migrations are created
   - When migration procedures change
   - When new troubleshooting issues are discovered

4. **MIGRATION_004_FIXES.md:**
   - When corrections are applied
   - When new issues are discovered

### Documentation Review Schedule

- **Monthly:** Review for accuracy and completeness
- **Per Migration:** Update optimization guide and migrations README
- **Per Major Change:** Verify all cross-references are current

## 🗂️ Archival Policy

Documentation should be moved to `.archive/` when:

1. **Superseded by newer docs** (clearly document which doc replaces it)
2. **Feature/task is complete** and doc is purely historical
3. **Content is consolidated** into a newer comprehensive guide

Keep archived docs for:
- Historical reference
- Understanding decision context
- Audit trail of changes

## Version History

- **November 9, 2025:** Initial documentation index created
- **November 9, 2025:** Database optimization documentation consolidated
- **November 9, 2025:** Phase 4 priorities re-assessed

---

**Note:** This index should be updated whenever documentation structure changes significantly.
