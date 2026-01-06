# Development Guide

**Status:** 18/23 IBL modules refactored (78% complete) • 738 tests • ~52% coverage • Goal: 80%

## Refactoring Status

### ✅ Completed IBL Modules (18)
1. ~~Player~~ ✅ Complete (9 classes, 9 interfaces, 84 tests)
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
14. ~~Free Agency~~ ✅ Complete (7 classes, 7 interfaces, 11 tests)
15. ~~Player_Search~~ ✅ Complete (4 classes, 4 interfaces, 54 tests) **SQL injection fixed!**
16. ~~Compare_Players~~ ✅ Complete (3 classes, 3 interfaces, 42 tests)
17. ~~Leaderboards~~ ✅ Complete (3 classes, 3 interfaces, 22 tests)
18. ~~Standings~~ ✅ Complete (2 classes, 2 interfaces, 17 tests)

### 🎯 Top Priorities (Next 3)

1. **League_Stats** (229 lines) - League-wide statistics display (1 week)
2. **One-on-One** (907 lines) - Player matchup game/comparison (2-3 weeks)
3. **Display Modules Batch** - Series_Records, Player_Awards, Cap_Info (2 weeks)

### 📋 Remaining IBL Modules (5)

**High Priority:**
- League_Stats (229 lines) - League-wide statistics
- One-on-One (907 lines) - Side game/matchup feature

**Lower Priority (Info/Display):**
- Series_Records (184 lines), Player_Awards (160 lines), Cap_Info (134 lines)
- Team_Schedule (130 lines), Franchise_History (103 lines), Power_Rankings (90 lines)
- Next_Sim (95 lines), League_Starters (85 lines), Draft_Pick_Locator (81 lines), Injuries (57 lines)

## Quick Workflow

**Before Starting:**
- Review refactored modules with interface pattern: PlayerSearch, FreeAgency, Player, ComparePlayers, Standings
- Check `.github/copilot-instructions.md` - **Interface-Driven Architecture Pattern** section
- Review interfaces in: `ibl5/classes/PlayerSearch/Contracts/`, `ibl5/classes/FreeAgency/Contracts/`, `ibl5/classes/Player/Contracts/`
- Check `ibl5/schema.sql` for database structure
- See best practices in: `ibl5/classes/Player/README.md`, `ibl5/classes/DepthChart/SECURITY.md`, `ibl5/classes/PlayerSearch/README.md`
- Dependencies are cached via GitHub Actions (`.github/workflows/cache-dependencies.yml`)
- Run tests: `cd ibl5 && vendor/bin/phpunit tests/`
- CI/CD: Tests run automatically via GitHub Actions (`.github/workflows/tests.yml`)

**Refactoring Steps:**
1. Analyze (1-2 days) - Identify responsibilities
2. Design (1-2 days) - Plan class structure & interfaces
3. Create Interfaces (1-2 days) - Document contracts with PHPDoc
4. Extract (1-2 weeks) - Repository → Validator → Processor → View → Controller
5. Implement Interfaces (1 day) - Add interface implementations and @see docblocks
6. Test (1 week) - Unit + integration tests
7. Audit (2-3 days) - Security review
8. Review (2-3 days) - Code review, performance

**Class Pattern with Interface Architecture:**
```
Module/
├── Contracts/
│   ├── ModuleInterface.php
│   ├── ModuleRepositoryInterface.php
│   ├── ModuleValidatorInterface.php
│   └── ...more interfaces as needed
├── Module.php                    # implements ModuleInterface
├── ModuleRepository.php          # implements ModuleRepositoryInterface
├── ModuleValidator.php           # implements ModuleValidatorInterface
├── ModuleProcessor.php           # Business logic
├── ModuleView.php                # View rendering
└── ModuleService.php             # Service layer
```

See `.github/copilot-instructions.md` **Interface-Driven Architecture Pattern** section for complete details.

## Testing Standards

**Coverage:** Current ~52% → Phase 1: 60% → Phase 2: 75% → Goal: 80%

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
- **Mock objects**: Use PHPDoc annotations for IDE support:
  ```php
  /** @var InterfaceName&\PHPUnit\Framework\MockObject\MockObject */
  private InterfaceName $mockRepository;
  ```

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
