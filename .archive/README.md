# Archived Documentation

This directory contains historical documentation that has been superseded by consolidated guides or is no longer actively maintained.

## Why These Files Were Archived

As part of optimizing documentation for GitHub Copilot Agent's context window, these files were archived to:
- Reduce token overhead (from ~14,000 lines to ~4,000 lines)
- Eliminate redundancy and overlap
- Improve discoverability of current documentation
- Preserve historical information for reference

## Current Documentation

Please refer to these consolidated guides instead:

- **[README.md](../README.md)** - Project overview and quick start
- **[DEVELOPMENT_GUIDE.md](../DEVELOPMENT_GUIDE.md)** - Refactoring priorities, module status, workflow
- **[DATABASE_GUIDE.md](../DATABASE_GUIDE.md)** - Schema reference, migrations, best practices
- **[API_GUIDE.md](../API_GUIDE.md)** - API development guide
- **[COPILOT_AGENT.md](../COPILOT_AGENT.md)** - Coding standards and agent instructions
- **[STATISTICS_FORMATTING_GUIDE.md](../STATISTICS_FORMATTING_GUIDE.md)** - Stats formatting utilities
- **[PRODUCTION_DEPLOYMENT_GUIDE.md](../PRODUCTION_DEPLOYMENT_GUIDE.md)** - Deployment procedures

## Archived Files Index

### Database Documentation (Previously 3,682 lines → Now 135 lines in DATABASE_GUIDE.md)
- `DATABASE_SCHEMA_IMPROVEMENTS.md` - Detailed schema improvement recommendations
- `SCHEMA_IMPLEMENTATION_REVIEW.md` - Implementation review of Phase 1-3
- `DATABASE_FUTURE_PHASES.md` - Future database enhancement plans
- `DATABASE_SCHEMA_GUIDE.md` - Original comprehensive schema guide
- `DATABASE_ER_DIAGRAM.md` - Entity relationship diagrams

### Refactoring Documentation (Previously 2,397 lines → Now 240 lines in DEVELOPMENT_GUIDE.md)
- `REFACTORING_SUMMARY.md` - Overall refactoring approach
- `REFACTORING_PRIORITIES_REPORT.md` - 20+ page analysis of priorities
- `REFACTORING_ROADMAP.md` - Visual timeline and roadmap
- `NEXT_STEPS.md` - Detailed next steps guide
- `MODULE_STATUS_MATRIX.md` - Status matrix for all 63 modules

### Module-Specific Refactoring Summaries (Previously 1,248 lines → Consolidated in DEVELOPMENT_GUIDE.md)
- `DRAFT_REFACTORING_SUMMARY.md` - Draft module refactoring
- `TEAM_REFACTORING_SUMMARY.md` - Team module MVC pattern
- `COMMON_REPOSITORY_REFACTORING_SUMMARY.md` - DRY principle examples
- `PLAYER_REFACTORING_SUMMARY.md` - Player facade pattern
- `ROOKIE_OPTION_REFACTORING_SUMMARY.md` - Rookie option refactoring

### API Documentation (Previously 1,210 lines → Now 250 lines in API_GUIDE.md)
- `API_DEVELOPMENT_GUIDE.md` - Original comprehensive API guide
- `API_QUICKSTART_PHASE3.md` - Phase 3 API quick start

### Completed Milestones (Previously 589 lines → No longer needed)
- `PHASE3_COMPLETION_SUMMARY.md` - Phase 3 completion documentation
- `DRAFT_IBL_PLR_UPDATE.md` - Draft player update documentation
- `DRAFT_BUG_FIX.md` - Draft bug fix summary

### Test Module Documentation (Previously 2,084 lines → No longer needed)
- `CODE_REVIEW.md` - Extension module code review
- `FINAL_SUMMARY.md` - Extension final summary
- `MONEY_COMMITTED_AT_POSITION.md` - Extension feature documentation
- `Extension_REFACTORING_SUMMARY.md` - Extension refactoring summary
- `QUICKSTART.md` - Extension quick start
- `IMPLEMENTATION_COMPLETE.md` - Extension implementation completion
- `Trading_CODE_REVIEW.md` - Trading module code review

## Accessing Historical Information

All archived files are preserved in git history. To access previous versions or archived content:

```bash
# View file from archive
cat .archive/DATABASE_SCHEMA_IMPROVEMENTS.md

# View file history
git log --follow .archive/DATABASE_SCHEMA_IMPROVEMENTS.md

# View file at specific commit
git show <commit>:DATABASE_SCHEMA_IMPROVEMENTS.md
```

## Summary Statistics

### Before Consolidation
- **Total Files:** 41 markdown files
- **Total Lines:** ~14,000 lines
- **Issues:** Significant overlap, duplicate information, outdated status tracking

### After Consolidation
- **Active Files:** 17 markdown files (8 root + 9 subdirectory)
- **Total Lines:** ~3,815 lines
- **Reduction:** ~73% reduction in documentation token overhead
- **Improvements:** Better discoverability, reduced redundancy, clearer structure

## Last Updated
November 6, 2025
