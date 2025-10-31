# IBL5 - Internet Basketball League

This is the repository for iblhoops.net, a small internet-based fantasy basketball site. The site uses Jump Shot Basketball to simulate the games and roster management.

## 📚 Documentation

### Database Schema Review
A comprehensive review of the database schema has been completed with production-ready improvements:

- **[Start Here: Documentation Index](DATABASE_DOCUMENTATION_INDEX.md)** - Navigation hub for all documentation
- **[Executive Summary](SCHEMA_REVIEW_SUMMARY.md)** - Quick overview for decision makers
- **[Detailed Analysis](DATABASE_SCHEMA_IMPROVEMENTS.md)** - Complete recommendations (600+ lines)
- **[ER Diagrams](DATABASE_ER_DIAGRAM.md)** - Visual entity relationships
- **[API Development Guide](API_DEVELOPMENT_GUIDE.md)** - Best practices for API development
- **[Migration Guide](ibl5/migrations/README.md)** - How to execute database improvements

### Key Improvements Available
- ✅ **Phase 1:** InnoDB conversion, critical indexes (10-100x performance gain)
- ✅ **Phase 2:** Foreign keys for data integrity
- 📊 **Total:** 3,400+ lines of documentation, 690+ lines of production-ready SQL

### Quick Start
```bash
# Review the documentation index first
cat DATABASE_DOCUMENTATION_INDEX.md

# Then execute migrations (after backup!)
mysql -u username -p database < ibl5/migrations/001_critical_improvements.sql
mysql -u username -p database < ibl5/migrations/002_add_foreign_keys.sql
```

## 🔧 Development

For API development, ensure Phase 1 & 2 migrations are complete, then follow the [API Development Guide](API_DEVELOPMENT_GUIDE.md).

## 📖 Additional Documentation

- [Copilot Agent Instructions](COPILOT_AGENT.md)
- [Draft Bug Fix Summary](DRAFT_BUG_FIX.md)
- [Player Refactoring Summary](PLAYER_REFACTORING_SUMMARY.md)
- [Production Deployment Guide](PRODUCTION_DEPLOYMENT_GUIDE.md)
- [Refactoring Summary](REFACTORING_SUMMARY.md)
