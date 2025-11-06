# IBL5 - Internet Basketball League

This is the repository for iblhoops.net, a small internet-based fantasy basketball site. The site uses Jump Shot Basketball to simulate the games and roster management.

## 🚀 Development Status

**Current State**: 12 modules fully refactored with 350+ tests, 30% test coverage  
**Goal**: 80%+ test coverage with all critical features tested  
**Next Priority**: Free Agency Module (business critical)

## 📚 Essential Documentation

### Core Guides
- **[Development Guide](DEVELOPMENT_GUIDE.md)** - Refactoring priorities, module status, workflow
- **[Database Guide](DATABASE_GUIDE.md)** - Schema reference, migrations, best practices
- **[API Guide](API_GUIDE.md)** - API development with database views, UUIDs, caching
- **[Copilot Agent Instructions](COPILOT_AGENT.md)** - Coding standards, patterns, security

### Database Status ✅
- ✅ InnoDB conversion (52 tables) - 10-100x performance gain
- ✅ Foreign keys (24 constraints) - Data integrity
- ✅ API Ready - Timestamps, UUIDs, Database Views
- 🚀 Ready for production API deployment

### Quick Start
```bash
# Review development priorities
cat DEVELOPMENT_GUIDE.md

# Check database schema
cat DATABASE_GUIDE.md

# Start API development
cat API_GUIDE.md
```

## 🔧 Development

Follow the [Development Guide](DEVELOPMENT_GUIDE.md) for:
- Module refactoring priorities and workflow
- Testing standards and coverage goals
- Code quality requirements
- Architecture patterns

Follow the [API Guide](API_GUIDE.md) for:
- RESTful endpoint design
- Database views for efficient queries
- UUIDs for secure public identifiers
- Authentication and caching strategies

## 📖 Additional Documentation

### Specialized Guides
- [Statistics Formatting Guide](STATISTICS_FORMATTING_GUIDE.md) - StatsFormatter and StatsSanitizer usage
- [Production Deployment Guide](PRODUCTION_DEPLOYMENT_GUIDE.md) - Deployment procedures

### Class Documentation
- [Draft Module](ibl5/classes/Draft/README.md)
- [Depth Chart Module](ibl5/classes/DepthChart/README.md)
- [Player Module](ibl5/classes/Player/README.md)
- [Depth Chart Security](ibl5/classes/DepthChart/SECURITY.md)
