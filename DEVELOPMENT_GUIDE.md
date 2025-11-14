# Development Guide

**Status:** 13/23 IBL modules refactored • 52 test files • ~40% coverage • Goal: 80%

## Refactoring Status

### ✅ Completed IBL Modules (13)
1. ~~Player~~ ✅ Complete (9 classes, 6 tests) - Core player management & display
2. ~~Statistics~~ ✅ Complete (6 classes, 5 tests) - Stats formatting & sanitization
3. ~~Team~~ ✅ Complete (4 classes, 3 tests) - Team management
4. ~~Draft~~ ✅ Complete (5 classes, 3 tests) - Draft operations
5. ~~Waivers~~ ✅ Complete (5 classes, 3 tests) - Waiver system
6. ~~Extension~~ ✅ Complete (4 classes, 4 tests) - Contract extensions
7. ~~RookieOption~~ ✅ Complete (4 classes, 3 tests) - Rookie contract options
8. ~~Trading~~ ✅ Complete (5 classes, 5 tests) - Trade processing
9. ~~Negotiation~~ ✅ Complete (4 classes, 3 tests) - Contract negotiations
10. ~~DepthChart~~ ✅ Complete (6 classes, 2 tests) - Depth chart management
11. ~~Voting~~ ✅ Complete (3 classes, 0 tests) - Award voting
12. ~~Schedule~~ ✅ Complete (2 classes, 0 tests) - Game scheduling
13. ~~Season Leaders~~ ✅ Complete (3 classes, 2 tests) - Season-long statistical leaders

### 🎯 Top Priorities (Next 3 Modules)

1. **Free Agency** (2,206 lines) - Contract signing, FA offers, salary cap validation
   - **Complexity:** Very High - Business logic for contract offers, salary cap, FA bidding
   - **Business Value:** Critical - Core gameplay mechanic for team building
   - **Tech Debt:** High - Legacy SQL, no prepared statements, mixed concerns
   - **Estimated Effort:** 3-4 weeks

2. **One-on-One** (887 lines) - Player comparison/matchup feature
   - **Complexity:** Medium - Display logic, stats comparison
   - **Business Value:** High - Frequently used by users
   - **Tech Debt:** Medium - Legacy code patterns
   - **Estimated Effort:** 1-2 weeks

3. **Leaderboards** (264 lines) - Various statistical leaderboards
   - **Complexity:** Medium - Stats queries, display formatting
   - **Business Value:** High - Important for competitive engagement
   - **Tech Debt:** Medium - Can leverage Statistics classes (similar to Season Leaders)
   - **Estimated Effort:** 1 week

### 📋 Remaining IBL Modules (10)

**Medium Priority (Display/Stats):**
- Chunk_Stats (462 lines) - Statistical chunks/periods
- Player_Search (461 lines) - Player search functionality
- Compare_Players (403 lines) - Player comparison tool
- Searchable_Stats (370 lines) - Advanced stats search
- League_Stats (351 lines) - League-wide statistics
- Leaderboards (264 lines) - Various leaderboards

**Lower Priority (Info/Display):**
- Series_Records (179 lines) - Historical series data
- Player_Awards (159 lines) - Award history display
- Cap_Info (136 lines) - Salary cap information
- Team_Schedule (129 lines) - Team schedule display
- Franchise_History (103 lines) - Team history
- Power_Rankings (101 lines) - Power rankings display
- Next_Sim (94 lines) - Next simulation info
- League_Starters (85 lines) - Starting lineups
- Draft_Pick_Locator (79 lines) - Draft pick finder
- Injuries (57 lines) - Injury reports
- EOY_Results (40 lines) - End of year results
- ASG_Results (40 lines) - All-star game results
- ASG_Stats (221 lines) - All-star game statistics
- Player_Movement (35 lines) - Transaction history

**Not IBL-Specific (Lowest Priority - Generic PHP-Nuke):**
- Web_Links, Your_Account, News, AutoTheme, Content, Donate, FAQ, Topics, Search, Submit_News, Members_List, Top, Stories_Archive, Recommend_Us, Feedback, AvantGo (81,000+ total lines)

## Quick Workflow

**Before Starting:**
- Review refactored modules: Player, Waivers, Draft, Team, Extension, Trading
- Check `ibl5/schema.sql` for database structure
- See best practices in: `ibl5/classes/Player/README.md`, `ibl5/classes/DepthChart/SECURITY.md`
- Set up dev environment: See `DEVELOPMENT_ENVIRONMENT.md` or use dev container (`.devcontainer/`)
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
- [ibl5/classes/Statistics/](ibl5/classes/Statistics/) - Stats formatting
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
