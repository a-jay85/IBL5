# IBL5 - Internet Basketball League

Internet-based fantasy basketball site powered by Jump Shot Basketball simulation engine.

## 🚀 Quick Start by Role

### 👨‍💻 Application Developer
1. [**DEVELOPMENT_GUIDE.md**](DEVELOPMENT_GUIDE.md) - Priorities, testing, refactoring workflow
2. [**DATABASE_GUIDE.md**](DATABASE_GUIDE.md) - Schema, tables, query patterns
3. [**API_GUIDE.md**](API_GUIDE.md) - API design, authentication, caching

### 🗄️ Database Administrator
1. [**DATABASE_OPTIMIZATION_GUIDE.md**](DATABASE_OPTIMIZATION_GUIDE.md) - Schema optimization, migration roadmap
2. [**ibl5/migrations/README.md**](ibl5/migrations/README.md) - Migration procedures
3. [**DATABASE_GUIDE.md**](DATABASE_GUIDE.md) - Schema reference

### 🚀 DevOps/Deployment
1. [**PRODUCTION_DEPLOYMENT_GUIDE.md**](PRODUCTION_DEPLOYMENT_GUIDE.md) - Deployment procedures
2. [**DATABASE_OPTIMIZATION_GUIDE.md**](DATABASE_OPTIMIZATION_GUIDE.md) - Database changes context

## 📊 Current Status

**Code Quality:**
- 12 IBL modules refactored with 50 test files
- ~35% test coverage (target: 80%)
- Next priority: Free Agency Module (2,206 lines)
- Player module refactoring complete ✅

**Database:**
- ✅ InnoDB (52 tables) - 10-100x faster
- ✅ Foreign keys (21) - Data integrity
- ✅ CHECK constraints (24) - Validation
- ✅ API-ready - UUIDs, timestamps, views
- ✅ Phases 1-4 complete

## 📚 Documentation

**📖 [Complete Documentation Index](ibl5/docs/README.md)** - Navigate all documentation

### Core Guides (Root)
- [DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md) - Development standards & priorities
- [DATABASE_GUIDE.md](DATABASE_GUIDE.md) - Schema reference for developers
- [DATABASE_OPTIMIZATION_GUIDE.md](DATABASE_OPTIMIZATION_GUIDE.md) - Optimization roadmap
- [API_GUIDE.md](API_GUIDE.md) - RESTful API development
- [PRODUCTION_DEPLOYMENT_GUIDE.md](PRODUCTION_DEPLOYMENT_GUIDE.md) - Deployment procedures

### Project Documentation (ibl5/docs/)
- [REFACTORING_HISTORY.md](ibl5/docs/REFACTORING_HISTORY.md) - Complete refactoring timeline
- [STRATEGIC_PRIORITIES.md](ibl5/docs/STRATEGIC_PRIORITIES.md) - Strategic analysis & next priorities
- [STATISTICS_FORMATTING_GUIDE.md](ibl5/docs/STATISTICS_FORMATTING_GUIDE.md) - StatsFormatter usage

### Component Documentation (With Code)
- [Statistics/](ibl5/classes/Statistics/) - StatsFormatter and StatsSanitizer
- [Player/](ibl5/classes/Player/) - Player module architecture
- [DepthChart/](ibl5/classes/DepthChart/) - Depth chart module + security patterns
- [Draft/](ibl5/classes/Draft/) - Draft module
- [Negotiation/](ibl5/classes/Negotiation/) - Contract negotiation
- [Migrations/](ibl5/migrations/) - Database migration procedures

### Historical Documents (.archive/)
Previous completion summaries and detailed reports preserved for reference.

## 🔍 Common Tasks

**"How do I deploy to production?"** → [PRODUCTION_DEPLOYMENT_GUIDE.md](PRODUCTION_DEPLOYMENT_GUIDE.md)  
**"How do I query the database?"** → [DATABASE_GUIDE.md](DATABASE_GUIDE.md)  
**"What should I work on next?"** → [DEVELOPMENT_GUIDE.md](DEVELOPMENT_GUIDE.md) or [STRATEGIC_PRIORITIES.md](ibl5/docs/STRATEGIC_PRIORITIES.md)  
**"How do I build an API endpoint?"** → [API_GUIDE.md](API_GUIDE.md)  
**"How do I format statistics?"** → [STATISTICS_FORMATTING_GUIDE.md](ibl5/docs/STATISTICS_FORMATTING_GUIDE.md)  
**"What's been refactored?"** → [REFACTORING_HISTORY.md](ibl5/docs/REFACTORING_HISTORY.md)
