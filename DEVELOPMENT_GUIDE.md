# Development Guide

**Status:** 21/23 IBL modules refactored (91% complete) • 781 tests • ~56% coverage • Goal: 80%

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
19. ~~League_Stats~~ ✅ Complete (3 classes, 3 interfaces, 33 tests)
20. ~~Player_Awards~~ ✅ Complete (4 classes, 4 interfaces, 55 tests)
21. ~~Series_Records~~ ✅ Complete (5 classes, 4 interfaces, 29 tests)

### 🎯 Top Priorities (Next 2)

1. **One-on-One** (907 lines) - Player matchup game/comparison (2-3 weeks)
2. **Cap_Info** (134 lines) - Salary cap information display (1 week)

### 📋 Remaining IBL Modules (2)

**High Priority:**
- One-on-One (907 lines) - Side game/matchup feature

**Lower Priority (Info/Display):**
- Cap_Info (134 lines)
- Team_Schedule (130 lines), Franchise_History (103 lines), Power_Rankings (90 lines)
- Next_Sim (95 lines), League_Starters (85 lines), Draft_Pick_Locator (81 lines), Injuries (57 lines)

## 🔍 Mandatory Code Review (Always Apply)

**CRITICAL: Every code change must pass these security and standards checks BEFORE completion:**

### Security Audit (Non-Negotiable)
1. **XSS Protection**
   - [ ] All database-sourced content wrapped in `Utilities\HtmlSanitizer::safeHtmlOutput()`
   - [ ] All user inputs sanitized before output (player names, game text, form data)
   - [ ] Play-by-play text, error messages, and dynamic content properly escaped
   - [ ] HTML generated in business logic classes sanitized before embedding in output
   - **Detection:** Search for database queries, `$_POST`, `$_GET`, or string interpolation in HTML context
   - **Action:** Fix immediately - do not defer or mark as "future work"

2. **SQL Injection Protection**
   - [ ] All database queries use prepared statements via `BaseMysqliRepository`
   - [ ] No raw SQL string concatenation with variables
   - [ ] User inputs validated before database operations

### Standards Compliance (Non-Negotiable)
3. **HTML/CSS Modernization**
   - [ ] No deprecated tags: `<b>`, `<i>`, `<u>`, `<font>`, `<center>`
   - [ ] Replace with semantic HTML: `<strong style="font-weight: bold;">`, `<em style="font-style: italic;">`
   - [ ] No `border=` attributes - use `style="border: 1px solid #000; border-collapse: collapse;"`
   - [ ] Table cells with borders need `style="border: 1px solid #000; padding: 4px;"`
   - [ ] Extract repeated inline styles (2+ uses) to `<style>` blocks with CSS classes
   - **Detection:** Grep for `<b>`, `<font`, `border=`, or inspect HTML output in view classes
   - **Action:** Fix immediately - modernization is mandatory, not optional

**When to Apply:** During refactoring, feature development, bug fixes, testing, code review, or ANY file modification. These checks are mandatory regardless of the task's primary focus.

**Why This Matters:** XSS vulnerabilities expose users to attacks. Deprecated HTML violates web standards and makes code harder to maintain. Both must be fixed when detected, not deferred.

## Quick Workflow

**Before Starting:**
- Review refactored modules with interface pattern: PlayerSearch, FreeAgency, Player, ComparePlayers, Standings
- Check `.github/copilot-instructions.md` - **Interface-Driven Architecture Pattern** section
- Review interfaces in: `ibl5/classes/PlayerSearch/Contracts/`, `ibl5/classes/FreeAgency/Contracts/`, `ibl5/classes/Player/Contracts/`
- **VERIFY DATABASE STRUCTURE: Cross-reference `ibl5/schema.sql` for ALL table names, columns, and relationships before writing queries**
- See best practices in: `ibl5/classes/Player/README.md`, `ibl5/classes/DepthChart/SECURITY.md`, `ibl5/classes/PlayerSearch/README.md`
- Dependencies are cached via GitHub Actions (`.github/workflows/cache-dependencies.yml`)
- Run tests: `cd ibl5 && vendor/bin/phpunit tests/`
- CI/CD: Tests run automatically via GitHub Actions (`.github/workflows/tests.yml`)

**Refactoring Steps:**
1. Analyze - Identify responsibilities
2. Design - Plan class structure & interfaces
3. Create Interfaces - Document contracts with PHPDoc
4. Extract - Repository → Validator → Processor → View → Controller
5. Implement Interfaces - Add interface implementations and @see docblocks
6. Test - Unit + integration tests
7. **Security & Standards Audit** (MANDATORY)
   - [ ] XSS: All output wrapped in `HtmlSanitizer::safeHtmlOutput()` (scan database results, form data, play-by-play text)
   - [ ] SQL: All queries use prepared statements
   - [ ] HTML: No deprecated tags (`<b>`, `<font>`, `border=`) - convert to semantic HTML + inline CSS
   - [ ] CSS: Extract repeated styles (2+ uses) to classes
   - **Must be 100% compliant before proceeding** - no exceptions
8. **Production Validation** - Compare localhost against iblhoops.net
   - Verify all output (text, data, ordering, formatting) matches exactly
   - If mismatches found, debug and iterate until perfect match
   - This is the final verification gate before merge
9. Review - Code review, performance

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

**No Unused Convenience Methods:**
- ❌ DO NOT create "helper" or "utility" methods that aren't immediately used
- ✅ Only implement methods that are **actively called** in the refactored code
- Each method must have:
  - At least one direct caller
  - Unit tests
  - Clear, documented purpose
- If a method seems "useful later", add it later with tests when it's actually needed
- Dead code confuses developers and increases maintenance burden

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

**Database Object Preference:**
- **Always use the global `$mysqli_db` object** (modern MySQLi with prepared statements)
- **Avoid the legacy `$db` object** whenever possible
- Example: `global $mysqli_db;` then use prepared statements with `$mysqli_db->prepare()`, `bind_param()`, and `execute()`
- Only use legacy `$db` when refactoring legacy code that hasn't yet been updated

**Statistics Formatting:**
- [ ] Use `BasketballStats\StatsFormatter` for ALL statistics (never `number_format()`)
  - `formatPercentage()` for shooting/field goal percentages
  - `formatPerGameAverage()` for per-game stats (PPG, APG, RPG, etc.)
  - `formatPer36Stat()` for per-36-minute stats
  - `formatTotal()` for counting stats with comma separators
  - `formatAverage()` for general 2-decimal averages
- [ ] Use `BasketballStats\StatsSanitizer` for input validation

**HTML & CSS Standards:**
- [ ] Convert deprecated styling tags (`<font>`, `<center>`, `<b>`, `<i>`, `<u>`) to semantic HTML + inline CSS
- [ ] Extract repeated inline styles (2+ occurrences) into `<style>` blocks with CSS classes
- [ ] Use semantic HTML (`<strong>`, `<em>`, `<div>`) instead of presentation tags
- [ ] Keep `<style>` blocks at top of file for maintainability
- [ ] Follow deprecation guidelines in `.github/copilot-instructions.md` **HTML & CSS Refactoring** section

**Security Checklist:**
- [ ] Prepared statements (SQL injection)
- [ ] HTML escaping (XSS) - Use `Utilities\HtmlSanitizer::safeHtmlOutput()` instead of `htmlspecialchars()`
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
- **Use `BasketballStats\StatsFormatter` and `BasketballStats\StatsSanitizer` for all statistics** (never `number_format()`)
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
