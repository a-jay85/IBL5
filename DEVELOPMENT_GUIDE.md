# Development Guide

**Status:** 13/63 modules refactored • 380+ tests • 35% coverage • Goal: 80%

## Top Priorities

1. **Free Agency** (1,648 lines) - Contract signing, salary cap
2. **Player Display** (749 lines) - Most viewed page  
3. ~~Statistics~~ ✅ Complete

## Quick Workflow

**Before Starting:**
- Review refactored modules: Waivers, Draft, Team, Player
- Check `ibl5/schema.sql` for database structure
- Run tests: `cd ibl5 && vendor/bin/phpunit tests/`

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
- Best refactoring: Waivers (`tests/Waivers/`)
- Facade pattern: Player (`classes/Player/`)
- MVC pattern: Team (`classes/Team/`)
- Security: DepthChart (`classes/DepthChart/SECURITY.md`)

## FAQs

**Refactor everything at once?** No - Focus on priorities 1-3 first.  
**Found bugs during refactor?** Fix them! Write tests to prevent regression.  
**How much to test?** 80%+ coverage per module, all public methods.  
**Change existing APIs?** Only if necessary - maintain backward compatibility.  
**Database changes?** Use `ibl5/migrations/` - never modify schema directly.
