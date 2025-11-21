# Development Guide

**Status:** 14/23 IBL modules refactored (61% complete) • 476+ tests • ~45% coverage • Goal: 80%

## Refactoring Status

### ✅ Completed IBL Modules (14)
1. ~~Player~~ ✅ Complete (9 classes, 6 tests)
2. ~~Statistics~~ ✅ Complete (6 classes, 5 tests)
3. ~~Team~~ ✅ Complete (4 classes, 3 tests)
4. ~~Draft~~ ✅ Complete (5 classes, 3 tests)
5. ~~Waivers~~ ✅ Complete (5 classes, 3 tests)
6. ~~Extension~~ ✅ Complete (4 classes, 4 tests)
7. ~~RookieOption~~ ✅ Complete (4 classes, 3 tests)
8. ~~Trading~~ ✅ Complete (5 classes, 5 tests)
9. ~~Negotiation~~ ✅ Complete (4 classes, 3 tests)
10. ~~DepthChart~~ ✅ Complete (6 classes, 2 tests)
11. ~~Voting~~ ✅ Complete (3 classes, 0 tests)
12. ~~Schedule~~ ✅ Complete (2 classes, 0 tests)
13. ~~Season Leaders~~ ✅ Complete (3 classes, 2 tests)
14. ~~Free Agency~~ ✅ Complete (7 classes, 11 tests) **NEW**

### 🎯 Top Priorities (Next 3)

1. **One-on-One** (887 lines) - Player comparison tool (1-2 weeks)
2. **Leaderboards** (264 lines) - Statistical rankings (1 week)
3. **Stats Modules** - Batch refactoring (3-5 weeks)

### 📋 Remaining IBL Modules (9)

**High Priority (Next After Top 3):**
- Compare_Players (403 lines)
- Player_Search (461 lines)
- Searchable_Stats (370 lines)
- League_Stats (351 lines)
- Chunk_Stats (462 lines)

**Lower Priority (Info/Display):**
- Series_Records, Player_Awards, Cap_Info, Team_Schedule, Franchise_History, Power_Rankings, Next_Sim, League_Starters, Draft_Pick_Locator, Injuries, EOY_Results, ASG_Results, ASG_Stats, Player_Movement

## Quick Workflow

**Before Starting:**
- Review refactored modules: Player, Waivers, Draft, Team, Extension, Trading
- Check `ibl5/schema.sql` for database structure
- See best practices in: `ibl5/classes/Player/README.md`, `ibl5/classes/DepthChart/SECURITY.md`
- Dependencies are cached via GitHub Actions (`.github/workflows/cache-dependencies.yml`)
- Run tests: `cd ibl5 && vendor/bin/phpunit tests/`
- CI/CD: Tests run automatically via GitHub Actions (`.github/workflows/tests.yml`)

**Refactoring Steps:**
1. Analyze (1-2 days) - Identify responsibilities
2. Design (1-2 days) - Plan class structure & tests
3. Extract (1-2 weeks) - Repository → Validator → Processor → View → Controller
4. Test (1 week) - Unit + integration tests
5. Audit (2-3 days) - Security review
6. Review (2-3 days) - Code review, performance

**Class Pattern:**
```
Module/
├── Repository.php    - Database
├── Validator.php     - Validation
├── Processor.php     - Business logic
├── View.php         - UI
└── Controller.php   - Orchestration
```

## Testing Standards

**Coverage:** Current 35% → Phase 1: 60% → Phase 2: 75% → Goal: 80%

**Test Pyramid:** Few E2E tests → Some integration → Many unit tests

**CI/CD:** ✅ GitHub Actions workflow implemented
- Automated PHPUnit tests on push/PR
- Composer dependency caching
- See `.github/workflows/tests.yml`

**Required:**
- All public methods tested
- Edge cases & error conditions
- Business rule validation
- Database operations
- Security (SQL injection, XSS)

## Code Quality

**Type Hints Required:**
```php
// ✅ Good
public function getPlayer(int $playerId): ?Player
public function calculateAverage(array $values): float

// ❌ Bad
public function getPlayer($playerId)
```

**Class Autoloader:**
- Place classes in `ibl5/classes/`
- Filename = class name
- Never use `require()` for classes
- Reference: `$player = new Player($db);`

**Security Checklist:**
- [ ] Prepared statements (SQL injection)
- [ ] HTML escaping (XSS)
- [ ] Input validation
- [ ] Authorization checks
- [ ] CSRF protection

## Performance

**Database:**
- Use prepared statements
- Leverage indexes (see DATABASE_GUIDE.md)
- Use database views for complex queries
- Batch operations when possible

**Code:**
- Reuse repositories
- Use formatters: StatsFormatter, StatsSanitizer
- Avoid N+1 queries
- Cache expensive operations

## Resources

**Documentation:**
- [DATABASE_GUIDE.md](DATABASE_GUIDE.md) - Schema reference
- [API_GUIDE.md](API_GUIDE.md) - API development
- [STRATEGIC_PRIORITIES.md](ibl5/docs/STRATEGIC_PRIORITIES.md) - Strategic analysis & priorities
- [REFACTORING_HISTORY.md](ibl5/docs/REFACTORING_HISTORY.md) - Complete refactoring timeline
- [STATISTICS_FORMATTING_GUIDE.md](ibl5/docs/STATISTICS_FORMATTING_GUIDE.md) - Stats formatting
- [PRODUCTION_DEPLOYMENT_GUIDE.md](PRODUCTION_DEPLOYMENT_GUIDE.md) - Deployment

**Code Examples:**
- Best refactoring: Player (`classes/Player/`, `tests/Player/`)
- Comprehensive tests: Waivers (`tests/Waivers/`)
- Service + ViewHelper: Player display (`classes/Player/PlayerPageService.php`)
- Security patterns: DepthChart (`classes/DepthChart/SECURITY.md`)
- Stats formatting: Statistics (`classes/Statistics/README.md`)

## FAQs

**Refactor everything at once?** No - Focus on priorities 1-3 first.  
**Found bugs during refactor?** Fix them! Write tests to prevent regression.  
**How much to test?** 80%+ coverage per module, all public methods.  
**Change existing APIs?** Only if necessary - maintain backward compatibility.  
**Database changes?** Use `ibl5/migrations/` - never modify schema directly.
